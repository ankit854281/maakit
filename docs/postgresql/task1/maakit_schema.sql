-- Maakit Task 1: PostgreSQL 16+ fresh-database migration, raw SQL.
-- Run as migration owner. NOT a MySQL migration; do not apply to existing live DB.
-- All relational IDs (including junction-table PKs) are RFC UUID v4.
BEGIN;
CREATE SCHEMA maakit;
SET search_path = maakit, pg_catalog;
CREATE DOMAIN uuid_v4 AS uuid CHECK (VALUE::text ~ '^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$');
CREATE DOMAIN amount_minor AS bigint CHECK (VALUE >= 0); -- INR paise; never floating point
CREATE DOMAIN rate_fraction AS numeric(9,8) CHECK (VALUE BETWEEN 0 AND 1);
CREATE TYPE kyc_state AS ENUM ('PENDING','IN_REVIEW','VERIFIED','REJECTED');
CREATE TYPE fulfillment_type AS ENUM ('HYPERLOCAL','COURIER');
CREATE TYPE inventory_mode AS ENUM ('TRACKED','ON_REQUEST');
CREATE TYPE order_state AS ENUM ('AWAITING_VENDOR','AWAITING_CUSTOMER','CONFIRMED','PACKING','READY','DISPATCHED','DELIVERED','CANCELLED','RTO');
CREATE TYPE reservation_state AS ENUM ('HELD','CONSUMED','RELEASED');
CREATE TYPE payout_state AS ENUM ('DRAFT','QUEUED','PROCESSING','SUCCEEDED','FAILED','CANCELLED');
CREATE TYPE payment_state AS ENUM ('CREATED','AUTHORIZED','CAPTURED','FAILED','REFUNDED','PARTIALLY_REFUNDED');
CREATE TYPE journal_state AS ENUM ('DRAFT','POSTED');

-- 1. Authentication, RBAC, risk and scoped merchant membership.
CREATE TABLE users (
 id uuid_v4 PRIMARY KEY DEFAULT gen_random_uuid(),
 phone_e164 text NOT NULL UNIQUE CHECK (phone_e164 ~ '^\+[1-9][0-9]{7,14}$'),
 email text, display_name text NOT NULL CHECK (length(display_name) BETWEEN 1 AND 150),
 kyc_status kyc_state NOT NULL DEFAULT 'PENDING',
 status text NOT NULL DEFAULT 'ACTIVE' CHECK (status IN ('ACTIVE','SUSPENDED','DELETED')),
 session_version integer NOT NULL DEFAULT 0 CHECK (session_version >= 0),
 created_at timestamptz NOT NULL DEFAULT now(), updated_at timestamptz NOT NULL DEFAULT now()
);
CREATE UNIQUE INDEX users_email_unique ON users (lower(email)) WHERE email IS NOT NULL;
CREATE TABLE user_credentials (
 id uuid_v4 PRIMARY KEY DEFAULT gen_random_uuid(), user_id uuid_v4 NOT NULL UNIQUE REFERENCES users(id),
 password_hash text, mfa_secret_ciphertext bytea, password_changed_at timestamptz
); -- No SELECT access for customer-facing reader; Argon2id at application layer.
CREATE TABLE sessions (
 id uuid_v4 PRIMARY KEY DEFAULT gen_random_uuid(), user_id uuid_v4 NOT NULL REFERENCES users(id),
 refresh_token_hash bytea NOT NULL UNIQUE, session_version integer NOT NULL,
 expires_at timestamptz NOT NULL, revoked_at timestamptz, created_at timestamptz NOT NULL DEFAULT now(),
 CHECK (expires_at > created_at)
);
CREATE TABLE otp_challenges (
 id uuid_v4 PRIMARY KEY DEFAULT gen_random_uuid(), user_id uuid_v4 REFERENCES users(id),
 phone_e164 text NOT NULL, purpose text NOT NULL CHECK (purpose IN ('LOGIN','RECOVERY','PHONE_CHANGE','KYC')),
 otp_hmac bytea NOT NULL, key_version integer NOT NULL, attempts smallint NOT NULL DEFAULT 0 CHECK (attempts BETWEEN 0 AND 5),
 expires_at timestamptz NOT NULL, consumed_at timestamptz, created_at timestamptz NOT NULL DEFAULT now()
); -- HMAC with server-held pepper; Redis phone/IP/device limits BEFORE sending.
CREATE TABLE roles (id uuid_v4 PRIMARY KEY DEFAULT gen_random_uuid(), code text NOT NULL UNIQUE,
 CHECK (code IN ('ADMIN','VENDOR','RIDER','B2C_CUSTOMER','B2B_BUYER')));
CREATE TABLE permissions (id uuid_v4 PRIMARY KEY DEFAULT gen_random_uuid(), code text NOT NULL UNIQUE);
CREATE TABLE role_permissions (
 id uuid_v4 PRIMARY KEY DEFAULT gen_random_uuid(), role_id uuid_v4 NOT NULL REFERENCES roles(id),
 permission_id uuid_v4 NOT NULL REFERENCES permissions(id), UNIQUE(role_id,permission_id)
);
CREATE TABLE user_roles (
 id uuid_v4 PRIMARY KEY DEFAULT gen_random_uuid(), user_id uuid_v4 NOT NULL REFERENCES users(id),
 role_id uuid_v4 NOT NULL REFERENCES roles(id), granted_by uuid_v4 REFERENCES users(id),
 created_at timestamptz NOT NULL DEFAULT now(), UNIQUE(user_id,role_id)
);
CREATE TABLE user_permissions (
 id uuid_v4 PRIMARY KEY DEFAULT gen_random_uuid(), user_id uuid_v4 NOT NULL REFERENCES users(id),
 permission_id uuid_v4 NOT NULL REFERENCES permissions(id), granted_by uuid_v4 NOT NULL REFERENCES users(id),
 created_at timestamptz NOT NULL DEFAULT now(), UNIQUE(user_id,permission_id)
); -- ADMIN has no automatic finance/dispatch entitlement; explicit grants required.
CREATE TABLE customer_risk (
 id uuid_v4 PRIMARY KEY DEFAULT gen_random_uuid(), user_id uuid_v4 NOT NULL UNIQUE REFERENCES users(id),
 rto_score smallint NOT NULL DEFAULT 0 CHECK (rto_score BETWEEN 0 AND 100),
 cod_blocked boolean NOT NULL DEFAULT false, cod_order_limit_minor amount_minor,
 delivered_count integer NOT NULL DEFAULT 0 CHECK (delivered_count >= 0),
 rto_count integer NOT NULL DEFAULT 0 CHECK (rto_count >= 0), model_version text NOT NULL DEFAULT 'manual-v1',
 assessed_at timestamptz NOT NULL DEFAULT now()
);
CREATE TABLE vendors (
 id uuid_v4 PRIMARY KEY DEFAULT gen_random_uuid(), owner_user_id uuid_v4 NOT NULL REFERENCES users(id),
 legal_name text NOT NULL, kyc_status kyc_state NOT NULL DEFAULT 'PENDING',
 pan_ciphertext bytea, status text NOT NULL DEFAULT 'DRAFT' CHECK (status IN ('DRAFT','ACTIVE','SUSPENDED','CLOSED')),
 created_at timestamptz NOT NULL DEFAULT now()
);
CREATE TABLE vendor_members (
 id uuid_v4 PRIMARY KEY DEFAULT gen_random_uuid(), vendor_id uuid_v4 NOT NULL REFERENCES vendors(id),
 user_id uuid_v4 NOT NULL REFERENCES users(id), member_role text NOT NULL CHECK (member_role IN ('OWNER','MANAGER','CATALOG','FULFILLMENT','FINANCE')),
 active boolean NOT NULL DEFAULT true, UNIQUE(vendor_id,user_id)
);
CREATE TABLE stores (
 id uuid_v4 PRIMARY KEY DEFAULT gen_random_uuid(), vendor_id uuid_v4 NOT NULL REFERENCES vendors(id),
 name text NOT NULL, address jsonb NOT NULL CHECK (jsonb_typeof(address)='object'),
 latitude numeric(9,6) CHECK (latitude BETWEEN -90 AND 90), longitude numeric(9,6) CHECK (longitude BETWEEN -180 AND 180),
 radius_m integer NOT NULL DEFAULT 5000 CHECK (radius_m BETWEEN 1 AND 100000),
 minimum_order_minor amount_minor NOT NULL DEFAULT 15000,
 cod_risk_threshold smallint NOT NULL DEFAULT 70 CHECK(cod_risk_threshold BETWEEN 0 AND 100),
 supports_hyperlocal boolean NOT NULL DEFAULT true, supports_courier boolean NOT NULL DEFAULT false,
 own_dispatch boolean NOT NULL DEFAULT false, active boolean NOT NULL DEFAULT false,
 created_at timestamptz NOT NULL DEFAULT now(), UNIQUE(id,vendor_id),
 CHECK ((latitude IS NULL)=(longitude IS NULL)),
 CHECK (NOT supports_hyperlocal OR latitude IS NOT NULL)
);
CREATE TABLE store_service_zones (
 id uuid_v4 PRIMARY KEY DEFAULT gen_random_uuid(), store_id uuid_v4 NOT NULL REFERENCES stores(id),
 fulfillment fulfillment_type NOT NULL, pincode text NOT NULL CHECK (pincode ~ '^[1-9][0-9]{5}$'),
 active boolean NOT NULL DEFAULT true, UNIQUE(store_id,fulfillment,pincode)
); -- Hyperlocal: PIN + radius gate; courier: explicit coverage. Route API quote remains authoritative.
CREATE TABLE vendor_tax_profiles (
 id uuid_v4 PRIMARY KEY DEFAULT gen_random_uuid(), vendor_id uuid_v4 NOT NULL REFERENCES vendors(id),
 gstin text CHECK (gstin ~ '^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$'),
 registration_type text NOT NULL CHECK (registration_type IN ('REGULAR','COMPOSITION','UNREGISTERED')),
 state_code char(2) NOT NULL CHECK (state_code ~ '^[0-9]{2}$'),
 valid_from timestamptz NOT NULL, valid_until timestamptz, verified_at timestamptz,
 CHECK (valid_until IS NULL OR valid_until > valid_from), UNIQUE(vendor_id,valid_from)
); -- Format check is not GST registration verification.
CREATE TABLE commission_policies (
 id uuid_v4 PRIMARY KEY DEFAULT gen_random_uuid(), store_id uuid_v4 NOT NULL REFERENCES stores(id),
 rate rate_fraction NOT NULL, fixed_minor amount_minor NOT NULL DEFAULT 0,
 valid_from timestamptz NOT NULL, valid_until timestamptz,
 CHECK (valid_until IS NULL OR valid_until>valid_from), UNIQUE(store_id,valid_from)
);
CREATE TABLE tax_rules (
 id uuid_v4 PRIMARY KEY DEFAULT gen_random_uuid(), code text NOT NULL,
 tax_type text NOT NULL CHECK (tax_type IN ('CGST','SGST','IGST','GST_COMMISSION','TCS','TDS')),
 rate rate_fraction NOT NULL, basis text NOT NULL CHECK (basis IN ('ITEM_TAXABLE','COMMISSION','NET_TAXABLE_SALES','GROSS_SALES','CUSTOM')),
 applicability jsonb NOT NULL DEFAULT '{}'::jsonb, -- registration/place-of-supply/threshold/collection-mode criteria
 valid_from timestamptz NOT NULL, valid_until timestamptz,
 approved_by uuid_v4 NOT NULL REFERENCES users(id), source_reference text NOT NULL,
 CHECK (valid_until IS NULL OR valid_until>valid_from), UNIQUE(code,valid_from)
); -- No statutory-rate defaults. Approved, effective-dated configuration only.
CREATE TABLE addresses (
 id uuid_v4 PRIMARY KEY DEFAULT gen_random_uuid(), user_id uuid_v4 NOT NULL REFERENCES users(id),
 recipient_name text NOT NULL, phone_e164 text NOT NULL, lines jsonb NOT NULL,
 pincode text NOT NULL CHECK (pincode ~ '^[1-9][0-9]{5}$'), state_code char(2) NOT NULL,
 latitude numeric(9,6) CHECK(latitude BETWEEN -90 AND 90), longitude numeric(9,6) CHECK(longitude BETWEEN -180 AND 180),
 UNIQUE(id,user_id), CHECK ((latitude IS NULL)=(longitude IS NULL))
);

-- 2. Product master vs seller offers; variant is the inventory and price unit.
CREATE TABLE categories (
 id uuid_v4 PRIMARY KEY DEFAULT gen_random_uuid(), parent_id uuid_v4 REFERENCES categories(id),
 name text NOT NULL, slug text NOT NULL UNIQUE,
 category_type text NOT NULL CHECK (category_type IN ('LOCAL_SHOPPING','HOME_SERVICES','B2B')),
 CHECK(parent_id IS DISTINCT FROM id)
);
CREATE TABLE products (
 id uuid_v4 PRIMARY KEY DEFAULT gen_random_uuid(), category_id uuid_v4 NOT NULL REFERENCES categories(id),
 name text NOT NULL, brand text, description text, hsn_sac text,
 search_document tsvector GENERATED ALWAYS AS (to_tsvector('simple',name || ' ' || coalesce(brand,'') || ' ' || coalesce(description,''))) STORED,
 active boolean NOT NULL DEFAULT true, created_at timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX products_search_gin ON products USING gin(search_document);
CREATE TABLE product_offers (
 id uuid_v4 PRIMARY KEY DEFAULT gen_random_uuid(), product_id uuid_v4 NOT NULL REFERENCES products(id),
 store_id uuid_v4 NOT NULL REFERENCES stores(id), is_b2b boolean NOT NULL DEFAULT false,
 supports_hyperlocal boolean NOT NULL DEFAULT true, supports_courier boolean NOT NULL DEFAULT false,
 inventory_policy inventory_mode NOT NULL DEFAULT 'ON_REQUEST', active boolean NOT NULL DEFAULT false,
 verified_at timestamptz, UNIQUE(id,store_id), UNIQUE(product_id,store_id,is_b2b),
 CHECK (supports_hyperlocal OR supports_courier OR is_b2b)
);
CREATE TABLE product_variants (
 id uuid_v4 PRIMARY KEY DEFAULT gen_random_uuid(), offer_id uuid_v4 NOT NULL REFERENCES product_offers(id),
 sku text NOT NULL, attributes jsonb NOT NULL DEFAULT '{}'::jsonb,
 pack_label text NOT NULL, unit_price_minor amount_minor, currency char(3) NOT NULL DEFAULT 'INR' CHECK(currency='INR'),
 price_includes_item_tax boolean NOT NULL DEFAULT true, tax_rule_id uuid_v4 REFERENCES tax_rules(id),
 weight_g integer CHECK(weight_g>0), length_mm integer CHECK(length_mm>0), width_mm integer CHECK(width_mm>0), height_mm integer CHECK(height_mm>0),
 active boolean NOT NULL DEFAULT true, version bigint NOT NULL DEFAULT 0,
 UNIQUE(offer_id,sku), UNIQUE(id,offer_id)
); -- NULL price means RFQ/enquiry only; checkout refuses it. Catalog reference price never becomes an order price.
CREATE TABLE product_images (
 id uuid_v4 PRIMARY KEY DEFAULT gen_random_uuid(), product_id uuid_v4 NOT NULL REFERENCES products(id),
 object_key text NOT NULL, alt_text text, position smallint NOT NULL DEFAULT 0, UNIQUE(product_id,position)
);
CREATE TABLE inventory (
 id uuid_v4 PRIMARY KEY DEFAULT gen_random_uuid(), variant_id uuid_v4 NOT NULL UNIQUE REFERENCES product_variants(id),
 on_hand integer NOT NULL DEFAULT 0 CHECK(on_hand>=0), reserved integer NOT NULL DEFAULT 0 CHECK(reserved>=0),
 version bigint NOT NULL DEFAULT 0, updated_at timestamptz NOT NULL DEFAULT now(), CHECK(reserved<=on_hand)
);
CREATE TABLE carts (
 id uuid_v4 PRIMARY KEY DEFAULT gen_random_uuid(), customer_id uuid_v4 NOT NULL REFERENCES users(id),
 store_id uuid_v4 NOT NULL REFERENCES stores(id), fulfillment fulfillment_type NOT NULL,
 state text NOT NULL DEFAULT 'ACTIVE' CHECK(state IN ('ACTIVE','CHECKED_OUT','ABANDONED')),
 created_at timestamptz NOT NULL DEFAULT now(), updated_at timestamptz NOT NULL DEFAULT now(),
 UNIQUE(id,store_id)
); -- One store for both modes (deliberately stronger than hyperlocal-only).
CREATE UNIQUE INDEX carts_one_active_per_mode ON carts(customer_id,fulfillment) WHERE state='ACTIVE';
CREATE TABLE cart_items (
 id uuid_v4 PRIMARY KEY DEFAULT gen_random_uuid(), cart_id uuid_v4 NOT NULL REFERENCES carts(id),
 variant_id uuid_v4 NOT NULL REFERENCES product_variants(id), quantity integer NOT NULL CHECK(quantity BETWEEN 1 AND 10000),
 UNIQUE(cart_id,variant_id)
);
CREATE FUNCTION validate_cart_item() RETURNS trigger LANGUAGE plpgsql SET search_path=maakit,pg_catalog AS $$
DECLARE c carts; v product_variants; o product_offers;
BEGIN
 SELECT * INTO STRICT c FROM carts WHERE id=NEW.cart_id FOR UPDATE;
 SELECT * INTO STRICT v FROM product_variants WHERE id=NEW.variant_id;
 SELECT * INTO STRICT o FROM product_offers WHERE id=v.offer_id;
 IF c.state<>'ACTIVE' OR o.store_id<>c.store_id OR o.is_b2b OR NOT o.active OR NOT v.active OR NOT v.price_includes_item_tax OR v.unit_price_minor IS NULL
 OR NOT EXISTS(SELECT 1 FROM products WHERE id=o.product_id AND active)
 OR (c.fulfillment='HYPERLOCAL' AND NOT o.supports_hyperlocal) OR (c.fulfillment='COURIER' AND NOT o.supports_courier) THEN
 RAISE EXCEPTION 'Invalid cart: store/mode/B2B/price/availability mismatch' USING ERRCODE='23514'; END IF;
 RETURN NEW;
END $$;
CREATE TRIGGER cart_item_guard BEFORE INSERT OR UPDATE ON cart_items FOR EACH ROW EXECUTE FUNCTION validate_cart_item();
CREATE FUNCTION guard_cart_identity() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
 IF (NEW.customer_id,NEW.store_id,NEW.fulfillment) IS DISTINCT FROM (OLD.customer_id,OLD.store_id,OLD.fulfillment) THEN
 RAISE EXCEPTION 'Cart owner/store/mode immutable; create a replacement cart' USING ERRCODE='23514'; END IF;
 RETURN NEW;
END $$;
CREATE TRIGGER cart_identity BEFORE UPDATE ON carts FOR EACH ROW EXECUTE FUNCTION guard_cart_identity();

-- 3. Orders, snapshots, reservations, delivery, payments and dispatch events.
CREATE TABLE orders (
 id uuid_v4 PRIMARY KEY DEFAULT gen_random_uuid(), customer_id uuid_v4 NOT NULL REFERENCES users(id),
 store_id uuid_v4 NOT NULL, vendor_id uuid_v4 NOT NULL REFERENCES vendors(id),
 cart_id uuid_v4 NOT NULL UNIQUE REFERENCES carts(id), address_id uuid_v4 NOT NULL,
 fulfillment fulfillment_type NOT NULL, status order_state NOT NULL,
 payment_method text NOT NULL CHECK(payment_method IN ('COD','ONLINE','DIRECT_TO_VENDOR')),
 payment_recipient text NOT NULL CHECK(payment_recipient IN ('PLATFORM','VENDOR')),
 currency char(3) NOT NULL DEFAULT 'INR' CHECK(currency='INR'),
 subtotal_minor amount_minor NOT NULL, delivery_minor amount_minor NOT NULL DEFAULT 0,
 service_fee_minor amount_minor NOT NULL DEFAULT 0, discount_minor amount_minor NOT NULL DEFAULT 0,
 total_minor amount_minor NOT NULL, address_snapshot jsonb NOT NULL, vendor_tax_snapshot jsonb NOT NULL DEFAULT '{}',
 commission_snapshot jsonb NOT NULL DEFAULT '{}', risk_score_snapshot smallint NOT NULL CHECK(risk_score_snapshot BETWEEN 0 AND 100),
 idempotency_key text NOT NULL, request_fingerprint text NOT NULL,
 confirmed_at timestamptz, delivered_at timestamptz, created_at timestamptz NOT NULL DEFAULT now(),
 UNIQUE(customer_id,idempotency_key), UNIQUE(id,vendor_id,currency),
 FOREIGN KEY(store_id,vendor_id) REFERENCES stores(id,vendor_id),
 FOREIGN KEY(address_id,customer_id) REFERENCES addresses(id,user_id),
 CHECK(total_minor=subtotal_minor+delivery_minor+service_fee_minor-discount_minor),
 CHECK(discount_minor<=subtotal_minor), CHECK(status<>'DELIVERED' OR delivered_at IS NOT NULL)
);
CREATE TABLE order_items (
 id uuid_v4 PRIMARY KEY DEFAULT gen_random_uuid(), order_id uuid_v4 NOT NULL REFERENCES orders(id),
 variant_id uuid_v4 NOT NULL REFERENCES product_variants(id), quantity integer NOT NULL CHECK(quantity>0),
 unit_price_minor amount_minor NOT NULL, line_total_minor amount_minor NOT NULL,
 product_snapshot jsonb NOT NULL, tax_snapshot jsonb NOT NULL DEFAULT '{}', inventory_policy inventory_mode NOT NULL,
 UNIQUE(order_id,variant_id), CHECK(line_total_minor=unit_price_minor*quantity)
);
CREATE TABLE inventory_reservations (
 id uuid_v4 PRIMARY KEY DEFAULT gen_random_uuid(), order_item_id uuid_v4 NOT NULL UNIQUE REFERENCES order_items(id),
 inventory_id uuid_v4 NOT NULL REFERENCES inventory(id), quantity integer NOT NULL CHECK(quantity>0),
 status reservation_state NOT NULL DEFAULT 'HELD', expires_at timestamptz NOT NULL, created_at timestamptz NOT NULL DEFAULT now()
);
CREATE TABLE inventory_movements (
 id uuid_v4 PRIMARY KEY DEFAULT gen_random_uuid(), inventory_id uuid_v4 NOT NULL REFERENCES inventory(id),
 reservation_id uuid_v4 REFERENCES inventory_reservations(id), actor_id uuid_v4 REFERENCES users(id),
 kind text NOT NULL CHECK(kind IN ('RECEIPT','RESERVE','RELEASE','SALE','RETURN','ADJUSTMENT')),
 on_hand_delta integer NOT NULL, reserved_delta integer NOT NULL, reason text NOT NULL, created_at timestamptz NOT NULL DEFAULT now()
);
CREATE UNIQUE INDEX inventory_movement_once ON inventory_movements(reservation_id,kind) WHERE reservation_id IS NOT NULL;
CREATE TABLE order_events (
 id uuid_v4 PRIMARY KEY DEFAULT gen_random_uuid(), order_id uuid_v4 NOT NULL REFERENCES orders(id),
 actor_id uuid_v4 REFERENCES users(id), old_status order_state, new_status order_state NOT NULL,
 reason text, created_at timestamptz NOT NULL DEFAULT now()
);
CREATE TABLE delivery_otps (
 id uuid_v4 PRIMARY KEY DEFAULT gen_random_uuid(), order_id uuid_v4 NOT NULL UNIQUE REFERENCES orders(id),
 otp_hmac bytea NOT NULL, key_version integer NOT NULL, attempts smallint NOT NULL DEFAULT 0 CHECK(attempts BETWEEN 0 AND 5),
 expires_at timestamptz NOT NULL, verified_at timestamptz, verified_by uuid_v4 REFERENCES users(id)
); -- Private table. Never expose HMAC or raw OTP to vendor/rider/customer reads.
CREATE TABLE riders (
 id uuid_v4 PRIMARY KEY DEFAULT gen_random_uuid(), user_id uuid_v4 NOT NULL UNIQUE REFERENCES users(id),
 vendor_id uuid_v4 REFERENCES vendors(id), kyc_status kyc_state NOT NULL DEFAULT 'PENDING',
 active boolean NOT NULL DEFAULT false, vehicle_type text NOT NULL, created_at timestamptz NOT NULL DEFAULT now()
);
CREATE TABLE shipments (
 id uuid_v4 PRIMARY KEY DEFAULT gen_random_uuid(), order_id uuid_v4 NOT NULL UNIQUE REFERENCES orders(id),
 rider_id uuid_v4 REFERENCES riders(id), fulfillment fulfillment_type NOT NULL,
 provider text NOT NULL CHECK(provider IN ('MERCHANT_STAFF','LOCAL_POOL','THREE_PL','INDIA_POST')),
 provider_reference text, tracking_url text, service_code text,
 promised_at timestamptz, accepted_at timestamptz, dispatched_at timestamptz, delivered_at timestamptz,
 UNIQUE(provider,provider_reference),
 CHECK(provider NOT IN ('MERCHANT_STAFF','LOCAL_POOL') OR rider_id IS NOT NULL),
 CHECK(provider NOT IN ('THREE_PL','INDIA_POST') OR fulfillment='COURIER')
); -- No silent courier fallback for a hyperlocal promise; customer consent + replacement quote required.
CREATE TABLE dispatch_attempts (
 id uuid_v4 PRIMARY KEY DEFAULT gen_random_uuid(), order_id uuid_v4 NOT NULL REFERENCES orders(id),
 rider_id uuid_v4 REFERENCES riders(id), level smallint NOT NULL CHECK(level BETWEEN 1 AND 4),
 status text NOT NULL CHECK(status IN ('OFFERED','ACCEPTED','REJECTED','EXPIRED')),
 expires_at timestamptz NOT NULL, created_at timestamptz NOT NULL DEFAULT now()
);
CREATE UNIQUE INDEX dispatch_one_accept ON dispatch_attempts(order_id) WHERE status='ACCEPTED';
CREATE TABLE delivery_quotes (
 id uuid_v4 PRIMARY KEY DEFAULT gen_random_uuid(), cart_id uuid_v4 NOT NULL REFERENCES carts(id),
 address_id uuid_v4 NOT NULL REFERENCES addresses(id), fulfillment fulfillment_type NOT NULL,
 delivery_minor amount_minor NOT NULL, service_fee_minor amount_minor NOT NULL DEFAULT 0,
 quoted_by text NOT NULL, expires_at timestamptz NOT NULL, consumed_at timestamptz,
 fingerprint text NOT NULL, created_at timestamptz NOT NULL DEFAULT now()
); -- Trusted service writes; fingerprint binds cart version/items/address/provider promise.
CREATE TABLE payments (
 id uuid_v4 PRIMARY KEY DEFAULT gen_random_uuid(), order_id uuid_v4 NOT NULL REFERENCES orders(id),
 provider text NOT NULL, provider_payment_id text, idempotency_key text NOT NULL UNIQUE,
 status payment_state NOT NULL DEFAULT 'CREATED', amount_minor amount_minor NOT NULL,
 currency char(3) NOT NULL DEFAULT 'INR' CHECK(currency='INR'), captured_at timestamptz,
 UNIQUE(provider,provider_payment_id), created_at timestamptz NOT NULL DEFAULT now()
);
CREATE TABLE refunds (
 id uuid_v4 PRIMARY KEY DEFAULT gen_random_uuid(), payment_id uuid_v4 NOT NULL REFERENCES payments(id),
 amount_minor amount_minor NOT NULL CHECK(amount_minor>0), provider_refund_id text,
 idempotency_key text NOT NULL UNIQUE, status text NOT NULL CHECK(status IN ('REQUESTED','PROCESSING','SUCCEEDED','FAILED')),
 reason text NOT NULL, created_at timestamptz NOT NULL DEFAULT now()
);
CREATE TABLE provider_webhooks (
 id uuid_v4 PRIMARY KEY DEFAULT gen_random_uuid(), provider text NOT NULL, provider_event_id text NOT NULL,
 payload_digest bytea NOT NULL, payload_ciphertext bytea, received_at timestamptz NOT NULL DEFAULT now(),
 processed_at timestamptz, last_error text, UNIQUE(provider,provider_event_id)
); -- Only verified signatures enqueue work; callbacks are idempotent.
CREATE TABLE outbox_events (
 id uuid_v4 PRIMARY KEY DEFAULT gen_random_uuid(), aggregate_type text NOT NULL, aggregate_id uuid_v4 NOT NULL,
 event_type text NOT NULL, payload jsonb NOT NULL, dedupe_key text NOT NULL UNIQUE,
 available_at timestamptz NOT NULL DEFAULT now(), published_at timestamptz,
 attempts integer NOT NULL DEFAULT 0 CHECK(attempts>=0), created_at timestamptz NOT NULL DEFAULT now()
); -- aggregate_id intentionally polymorphic, not a foreign key. No OTP/secrets in payload.
CREATE INDEX outbox_pending ON outbox_events(available_at,id) WHERE published_at IS NULL;

-- 4. Home services: provider calendars, non-overlapping half-open time ranges.
CREATE TABLE service_providers (
 id uuid_v4 PRIMARY KEY DEFAULT gen_random_uuid(), vendor_id uuid_v4 NOT NULL REFERENCES vendors(id),
 user_id uuid_v4 NOT NULL REFERENCES users(id), active boolean NOT NULL DEFAULT false, UNIQUE(vendor_id,user_id)
);
CREATE TABLE services (
 id uuid_v4 PRIMARY KEY DEFAULT gen_random_uuid(), store_id uuid_v4 NOT NULL REFERENCES stores(id),
 category_id uuid_v4 NOT NULL REFERENCES categories(id), name text NOT NULL,
 duration_minutes integer NOT NULL CHECK(duration_minutes>0), price_minor amount_minor,
 quote_required boolean NOT NULL DEFAULT true, active boolean NOT NULL DEFAULT false,
 CHECK(quote_required OR price_minor IS NOT NULL)
);
CREATE TABLE service_slots (
 id uuid_v4 PRIMARY KEY DEFAULT gen_random_uuid(), provider_id uuid_v4 NOT NULL REFERENCES service_providers(id),
 starts_at timestamptz NOT NULL, ends_at timestamptz NOT NULL, active boolean NOT NULL DEFAULT true,
 CHECK(ends_at>starts_at), UNIQUE(provider_id,starts_at,ends_at)
);
CREATE TABLE service_bookings (
 id uuid_v4 PRIMARY KEY DEFAULT gen_random_uuid(), customer_id uuid_v4 NOT NULL REFERENCES users(id),
 service_id uuid_v4 NOT NULL REFERENCES services(id), slot_id uuid_v4 NOT NULL REFERENCES service_slots(id),
 address_id uuid_v4 NOT NULL, status text NOT NULL CHECK(status IN ('REQUESTED','CONFIRMED','IN_PROGRESS','COMPLETED','CANCELLED')),
 price_snapshot_minor amount_minor, quote_snapshot jsonb NOT NULL DEFAULT '{}',
 idempotency_key text NOT NULL, created_at timestamptz NOT NULL DEFAULT now(),
 FOREIGN KEY(address_id,customer_id) REFERENCES addresses(id,user_id), UNIQUE(customer_id,idempotency_key)
);
CREATE UNIQUE INDEX booking_slot_active ON service_bookings(slot_id) WHERE status<>'CANCELLED';
CREATE FUNCTION guard_slot_overlap() RETURNS trigger LANGUAGE plpgsql SET search_path=maakit,pg_catalog AS $$
BEGIN
 PERFORM 1 FROM service_providers WHERE id=NEW.provider_id FOR UPDATE;
 IF NEW.active AND EXISTS (SELECT 1 FROM service_slots WHERE provider_id=NEW.provider_id AND active AND id<>NEW.id
 AND starts_at<NEW.ends_at AND ends_at>NEW.starts_at) THEN
 RAISE EXCEPTION 'Overlapping provider slot' USING ERRCODE='23514'; END IF;
 IF TG_OP='UPDATE' AND EXISTS(SELECT 1 FROM service_bookings WHERE slot_id=OLD.id AND status<>'CANCELLED')
 AND (NEW.provider_id,NEW.starts_at,NEW.ends_at,NEW.active) IS DISTINCT FROM (OLD.provider_id,OLD.starts_at,OLD.ends_at,OLD.active) THEN
 RAISE EXCEPTION 'Booked slot cannot change' USING ERRCODE='23514'; END IF;
 RETURN NEW;
END $$;
CREATE TRIGGER slot_overlap BEFORE INSERT OR UPDATE ON service_slots FOR EACH ROW EXECUTE FUNCTION guard_slot_overlap();
CREATE FUNCTION validate_booking() RETURNS trigger LANGUAGE plpgsql SET search_path=maakit,pg_catalog AS $$
DECLARE s service_slots; p service_providers; v services;
BEGIN
 SELECT * INTO STRICT s FROM service_slots WHERE id=NEW.slot_id FOR UPDATE;
 SELECT * INTO STRICT p FROM service_providers WHERE id=s.provider_id;
 SELECT * INTO STRICT v FROM services WHERE id=NEW.service_id;
 IF NOT s.active OR NOT p.active OR NOT v.active OR s.starts_at<=now()
 OR NOT EXISTS(SELECT 1 FROM stores WHERE id=v.store_id AND vendor_id=p.vendor_id)
 OR s.ends_at-s.starts_at < make_interval(mins=>v.duration_minutes) THEN
 RAISE EXCEPTION 'Invalid service/provider/slot' USING ERRCODE='23514'; END IF;
 IF NEW.status<>'REQUESTED' THEN RAISE EXCEPTION 'Booking starts requested'; END IF;
 IF NOT v.quote_required THEN NEW.price_snapshot_minor:=v.price_minor;
 ELSE NEW.price_snapshot_minor:=NULL; END IF;
 SELECT vendor_id INTO NEW.vendor_id FROM stores WHERE id=v.store_id;
 RETURN NEW;
END $$;
CREATE TRIGGER booking_guard BEFORE INSERT ON service_bookings FOR EACH ROW EXECUTE FUNCTION validate_booking();

-- 5. B2B intent pipeline; no shopping-cart conversion.
CREATE TABLE b2b_profiles (
 id uuid_v4 PRIMARY KEY DEFAULT gen_random_uuid(), user_id uuid_v4 NOT NULL UNIQUE REFERENCES users(id),
 business_name text NOT NULL, gstin text, verified_at timestamptz
);
CREATE TABLE b2b_rfqs (
 id uuid_v4 PRIMARY KEY DEFAULT gen_random_uuid(), buyer_id uuid_v4 NOT NULL REFERENCES users(id),
 offer_id uuid_v4 NOT NULL REFERENCES product_offers(id), quantity numeric(14,3) NOT NULL CHECK(quantity>0),
 unit text NOT NULL, requirements text, status text NOT NULL DEFAULT 'OPEN' CHECK(status IN ('OPEN','QUOTED','ACCEPTED','CLOSED')),
 contact_sharing_consent_at timestamptz, created_at timestamptz NOT NULL DEFAULT now()
);
CREATE TABLE b2b_quotes (
 id uuid_v4 PRIMARY KEY DEFAULT gen_random_uuid(), rfq_id uuid_v4 NOT NULL REFERENCES b2b_rfqs(id),
 vendor_id uuid_v4 NOT NULL REFERENCES vendors(id), quote_minor amount_minor NOT NULL,
 terms jsonb NOT NULL, valid_until timestamptz NOT NULL, created_at timestamptz NOT NULL DEFAULT now()
);
CREATE FUNCTION validate_rfq() RETURNS trigger LANGUAGE plpgsql SET search_path=maakit,pg_catalog AS $$
BEGIN
 IF NOT EXISTS(SELECT 1 FROM product_offers WHERE id=NEW.offer_id AND is_b2b AND active) THEN
 RAISE EXCEPTION 'RFQ requires active B2B offer' USING ERRCODE='23514'; END IF;
 RETURN NEW;
END $$;
CREATE TRIGGER rfq_guard BEFORE INSERT OR UPDATE ON b2b_rfqs FOR EACH ROW EXECUTE FUNCTION validate_rfq();
CREATE FUNCTION validate_b2b_quote() RETURNS trigger LANGUAGE plpgsql SET search_path=maakit,pg_catalog AS $$
BEGIN
 IF NOT EXISTS(SELECT 1 FROM b2b_rfqs r JOIN product_offers o ON o.id=r.offer_id JOIN stores s ON s.id=o.store_id
 WHERE r.id=NEW.rfq_id AND s.vendor_id=NEW.vendor_id) THEN RAISE EXCEPTION 'Quote vendor mismatch' USING ERRCODE='23514'; END IF;
 RETURN NEW;
END $$;
CREATE TRIGGER b2b_quote_guard BEFORE INSERT OR UPDATE ON b2b_quotes FOR EACH ROW EXECUTE FUNCTION validate_b2b_quote();

-- 6. Tax snapshots, balanced double-entry journals, protected vendor entitlements.
CREATE TABLE order_tax_lines (
 id uuid_v4 PRIMARY KEY DEFAULT gen_random_uuid(), order_id uuid_v4 NOT NULL REFERENCES orders(id),
 order_item_id uuid_v4 REFERENCES order_items(id), tax_rule_id uuid_v4 NOT NULL REFERENCES tax_rules(id),
 tax_type text NOT NULL, basis_minor amount_minor NOT NULL, rate_snapshot rate_fraction NOT NULL,
 amount_minor amount_minor NOT NULL, rule_snapshot jsonb NOT NULL, created_at timestamptz NOT NULL DEFAULT now(),
 CHECK(amount_minor=round(basis_minor::numeric*rate_snapshot)::bigint)
);
CREATE TABLE ledger_accounts (
 id uuid_v4 PRIMARY KEY DEFAULT gen_random_uuid(), vendor_id uuid_v4 REFERENCES vendors(id),
 code text NOT NULL UNIQUE, account_type text NOT NULL CHECK(account_type IN ('ASSET','LIABILITY','EQUITY','REVENUE','EXPENSE')),
 currency char(3) NOT NULL DEFAULT 'INR' CHECK(currency='INR'), UNIQUE(id,currency)
);
CREATE TABLE journal_entries (
 id uuid_v4 PRIMARY KEY DEFAULT gen_random_uuid(), order_id uuid_v4 REFERENCES orders(id),
 status journal_state NOT NULL DEFAULT 'DRAFT', currency char(3) NOT NULL DEFAULT 'INR' CHECK(currency='INR'),
 idempotency_key text NOT NULL UNIQUE, description text NOT NULL,
 reversal_of uuid_v4 UNIQUE REFERENCES journal_entries(id), posted_at timestamptz,
 created_at timestamptz NOT NULL DEFAULT now(), UNIQUE(id,currency),
 CHECK((status='POSTED')=(posted_at IS NOT NULL))
);
CREATE TABLE ledger_lines (
 id uuid_v4 PRIMARY KEY DEFAULT gen_random_uuid(), journal_id uuid_v4 NOT NULL,
 account_id uuid_v4 NOT NULL, currency char(3) NOT NULL DEFAULT 'INR',
 debit_minor amount_minor NOT NULL DEFAULT 0, credit_minor amount_minor NOT NULL DEFAULT 0,
 FOREIGN KEY(journal_id,currency) REFERENCES journal_entries(id,currency),
 FOREIGN KEY(account_id,currency) REFERENCES ledger_accounts(id,currency),
 CHECK((debit_minor>0 AND credit_minor=0) OR (credit_minor>0 AND debit_minor=0))
);
CREATE FUNCTION guard_ledger_lines() RETURNS trigger LANGUAGE plpgsql SET search_path=maakit,pg_catalog AS $$
DECLARE j uuid; st journal_state;
BEGIN
 j:=CASE WHEN TG_OP='DELETE' THEN OLD.journal_id ELSE NEW.journal_id END;
 IF TG_OP='UPDATE' AND NEW.journal_id<>OLD.journal_id THEN RAISE EXCEPTION 'Journal reassignment forbidden'; END IF;
 SELECT status INTO STRICT st FROM journal_entries WHERE id=j FOR UPDATE;
 IF st='POSTED' THEN RAISE EXCEPTION 'Posted ledger immutable: use reversal'; END IF;
 IF TG_OP='DELETE' THEN RETURN OLD; ELSE RETURN NEW; END IF;
END $$;
CREATE TRIGGER ledger_line_guard BEFORE INSERT OR UPDATE OR DELETE ON ledger_lines FOR EACH ROW EXECUTE FUNCTION guard_ledger_lines();
CREATE FUNCTION guard_journal() RETURNS trigger LANGUAGE plpgsql SET search_path=maakit,pg_catalog AS $$
DECLARE d numeric; c numeric; n integer;
BEGIN
 IF TG_OP='DELETE' THEN IF OLD.status='POSTED' THEN RAISE EXCEPTION 'Posted journal immutable'; END IF; RETURN OLD; END IF;
 IF OLD.status='POSTED' THEN RAISE EXCEPTION 'Posted journal immutable'; END IF;
 IF NEW.status='POSTED' THEN
 -- Lock all touched accounts in deterministic order before exposing the journal.
 PERFORM 1 FROM ledger_accounts WHERE id IN (SELECT account_id FROM ledger_lines WHERE journal_id=NEW.id) ORDER BY id FOR UPDATE;
 SELECT count(*),coalesce(sum(debit_minor),0),coalesce(sum(credit_minor),0) INTO n,d,c FROM ledger_lines WHERE journal_id=NEW.id;
 IF n<2 OR d<>c OR d=0 THEN RAISE EXCEPTION 'Unbalanced/empty journal' USING ERRCODE='23514'; END IF;
 END IF; RETURN NEW;
END $$;
CREATE TRIGGER journal_guard BEFORE UPDATE OR DELETE ON journal_entries FOR EACH ROW EXECUTE FUNCTION guard_journal();
CREATE FUNCTION require_draft_journal() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN IF NEW.status<>'DRAFT' THEN RAISE EXCEPTION 'Insert draft, add lines, then post'; END IF; RETURN NEW; END $$;
CREATE TRIGGER journal_insert_guard BEFORE INSERT ON journal_entries FOR EACH ROW EXECUTE FUNCTION require_draft_journal();
CREATE TABLE payout_accounts (
 id uuid_v4 PRIMARY KEY DEFAULT gen_random_uuid(), vendor_id uuid_v4 NOT NULL REFERENCES vendors(id),
 provider text NOT NULL, beneficiary_token_ciphertext bytea NOT NULL, account_last4 char(4),
 status text NOT NULL DEFAULT 'PENDING' CHECK(status IN ('PENDING','VERIFIED','DISABLED')),
 verified_at timestamptz, created_at timestamptz NOT NULL DEFAULT now(), UNIQUE(id,vendor_id)
);
CREATE TABLE vendor_payables (
 id uuid_v4 PRIMARY KEY DEFAULT gen_random_uuid(), vendor_id uuid_v4 NOT NULL REFERENCES vendors(id),
 order_id uuid_v4 NOT NULL, journal_id uuid_v4 NOT NULL REFERENCES journal_entries(id),
 currency char(3) NOT NULL DEFAULT 'INR' CHECK(currency='INR'),
 gross_minor amount_minor NOT NULL, commission_minor amount_minor NOT NULL DEFAULT 0,
 commission_gst_minor amount_minor NOT NULL DEFAULT 0, tcs_minor amount_minor NOT NULL DEFAULT 0,
 tds_minor amount_minor NOT NULL DEFAULT 0, other_deductions_minor amount_minor NOT NULL DEFAULT 0,
 net_minor amount_minor NOT NULL, deduction_snapshot jsonb NOT NULL,
 eligible_at timestamptz NOT NULL, blocked boolean NOT NULL DEFAULT false,
 created_at timestamptz NOT NULL DEFAULT now(), UNIQUE(order_id), UNIQUE(id,vendor_id,currency),
 FOREIGN KEY(order_id,vendor_id,currency) REFERENCES orders(id,vendor_id,currency),
 CHECK(net_minor=gross_minor-commission_minor-commission_gst_minor-tcs_minor-tds_minor-other_deductions_minor)
); -- Trusted finance worker computes amounts from immutable order/tax snapshots, after payment + return hold.
CREATE TABLE payout_batches (
 id uuid_v4 PRIMARY KEY DEFAULT gen_random_uuid(), vendor_id uuid_v4 NOT NULL REFERENCES vendors(id),
 payout_account_id uuid_v4 NOT NULL, currency char(3) NOT NULL DEFAULT 'INR' CHECK(currency='INR'),
 status payout_state NOT NULL DEFAULT 'DRAFT', total_minor amount_minor NOT NULL DEFAULT 0,
 idempotency_key text NOT NULL UNIQUE, provider_reference text UNIQUE,
 attempt_count integer NOT NULL DEFAULT 0 CHECK(attempt_count>=0), next_retry_at timestamptz,
 created_at timestamptz NOT NULL DEFAULT now(), completed_at timestamptz,
 FOREIGN KEY(payout_account_id,vendor_id) REFERENCES payout_accounts(id,vendor_id), UNIQUE(id,vendor_id,currency)
);
CREATE TABLE payout_allocations (
 id uuid_v4 PRIMARY KEY DEFAULT gen_random_uuid(), batch_id uuid_v4 NOT NULL, payable_id uuid_v4 NOT NULL UNIQUE,
 vendor_id uuid_v4 NOT NULL REFERENCES vendors(id), currency char(3) NOT NULL DEFAULT 'INR',
 amount_minor amount_minor NOT NULL CHECK(amount_minor>0),
 FOREIGN KEY(batch_id,vendor_id,currency) REFERENCES payout_batches(id,vendor_id,currency),
 FOREIGN KEY(payable_id,vendor_id,currency) REFERENCES vendor_payables(id,vendor_id,currency)
);
CREATE FUNCTION guard_payable() RETURNS trigger LANGUAGE plpgsql SET search_path=maakit,pg_catalog AS $$
DECLARE o orders; j journal_entries; sb service_bookings;
BEGIN
 IF TG_OP='DELETE' THEN RAISE EXCEPTION 'Payable deletion forbidden; adjustments need reversal workflow'; END IF;
 IF TG_OP='UPDATE' THEN
 IF (to_jsonb(NEW)-'blocked') IS DISTINCT FROM (to_jsonb(OLD)-'blocked') THEN RAISE EXCEPTION 'Payable amounts immutable'; END IF;
 RETURN NEW; END IF;
 SELECT * INTO STRICT j FROM journal_entries WHERE id=NEW.journal_id;
 IF j.status<>'POSTED' OR NEW.net_minor<>(SELECT coalesce(sum(ll.credit_minor-ll.debit_minor),0)
 FROM ledger_lines ll JOIN ledger_accounts la ON la.id=ll.account_id
 WHERE ll.journal_id=j.id AND la.vendor_id=NEW.vendor_id AND la.account_type='LIABILITY') THEN
 RAISE EXCEPTION 'Payable must reconcile to posted vendor liability'; END IF;
 IF NEW.order_id IS NOT NULL THEN
 SELECT * INTO STRICT o FROM orders WHERE id=NEW.order_id FOR UPDATE;
 IF o.payment_recipient<>'PLATFORM' OR o.status<>'DELIVERED' OR j.order_id IS DISTINCT FROM o.id
 OR NOT EXISTS(SELECT 1 FROM payments WHERE order_id=o.id AND status='CAPTURED' AND amount_minor=o.total_minor) THEN
 RAISE EXCEPTION 'Payable requires delivered, platform-collected, captured and posted order'; END IF;
 ELSE
 SELECT * INTO STRICT sb FROM service_bookings WHERE id=NEW.service_booking_id FOR UPDATE;
 IF sb.status<>'COMPLETED' OR sb.price_snapshot_minor IS NULL OR j.service_booking_id IS DISTINCT FROM sb.id
 OR NOT EXISTS(SELECT 1 FROM payments WHERE service_booking_id=sb.id AND status='CAPTURED' AND amount_minor=sb.price_snapshot_minor) THEN
 RAISE EXCEPTION 'Service payable requires completed, priced, captured and posted booking'; END IF;
 END IF;
 RETURN NEW;
END $$;
CREATE TRIGGER payable_guard BEFORE INSERT OR UPDATE OR DELETE ON vendor_payables FOR EACH ROW EXECUTE FUNCTION guard_payable();
CREATE FUNCTION guard_allocation() RETURNS trigger LANGUAGE plpgsql SET search_path=maakit,pg_catalog AS $$
DECLARE p vendor_payables; b payout_batches; adjusted numeric;
BEGIN
 IF TG_OP<>'INSERT' THEN RAISE EXCEPTION 'Payout allocations immutable; retry same batch'; END IF;
 SELECT * INTO STRICT b FROM payout_batches WHERE id=NEW.batch_id FOR UPDATE;
 SELECT * INTO STRICT p FROM vendor_payables WHERE id=NEW.payable_id FOR UPDATE;
 SELECT p.net_minor+coalesce(sum(delta_minor),0) INTO adjusted FROM vendor_payable_adjustments WHERE payable_id=p.id;
 IF b.status<>'DRAFT' OR p.blocked OR p.eligible_at>now() OR NEW.amount_minor<>adjusted THEN
 RAISE EXCEPTION 'Payout allocation not eligible or amount mismatch'; END IF; RETURN NEW;
END $$;
CREATE TRIGGER payout_allocation_guard BEFORE INSERT OR UPDATE OR DELETE ON payout_allocations FOR EACH ROW EXECUTE FUNCTION guard_allocation();
CREATE FUNCTION guard_payout() RETURNS trigger LANGUAGE plpgsql SET search_path=maakit,pg_catalog AS $$
DECLARE n numeric; available numeric; in_flight numeric; settlement journal_entries;
BEGIN
 IF TG_OP='INSERT' THEN IF NEW.status<>'DRAFT' OR NEW.total_minor<>0 THEN RAISE EXCEPTION 'Payout starts as empty draft'; END IF; RETURN NEW; END IF;
 IF TG_OP='DELETE' THEN RAISE EXCEPTION 'Payout history immutable'; END IF;
 IF (NEW.vendor_id,NEW.payout_account_id,NEW.currency,NEW.idempotency_key) IS DISTINCT FROM (OLD.vendor_id,OLD.payout_account_id,OLD.currency,OLD.idempotency_key) THEN
 RAISE EXCEPTION 'Payout identity immutable'; END IF;
 IF OLD.status IN ('SUCCEEDED','CANCELLED') THEN RAISE EXCEPTION 'Terminal payout immutable'; END IF;
 IF NEW.status<>OLD.status AND NOT ((OLD.status='DRAFT' AND NEW.status IN ('QUEUED','CANCELLED')) OR
 (OLD.status='QUEUED' AND NEW.status IN ('PROCESSING','FAILED')) OR
 (OLD.status='PROCESSING' AND NEW.status IN ('SUCCEEDED','FAILED')) OR (OLD.status='FAILED' AND NEW.status='QUEUED')) THEN
 RAISE EXCEPTION 'Invalid payout transition'; END IF;
 SELECT coalesce(sum(amount_minor),0) INTO n FROM payout_allocations WHERE batch_id=NEW.id;
 IF NEW.status<>'DRAFT' AND (n<=0 OR NEW.total_minor<>n) THEN RAISE EXCEPTION 'Payout total mismatch'; END IF;
 IF NEW.status IN ('QUEUED','PROCESSING') THEN
 PERFORM 1 FROM vendors WHERE id=NEW.vendor_id AND kyc_status='VERIFIED' AND status='ACTIVE' FOR UPDATE;
 IF NOT FOUND OR NOT EXISTS(SELECT 1 FROM payout_accounts WHERE id=NEW.payout_account_id AND status='VERIFIED')
 OR EXISTS(SELECT 1 FROM payout_allocations a JOIN vendor_payables p ON p.id=a.payable_id WHERE a.batch_id=NEW.id AND (p.blocked OR p.eligible_at>now())) THEN
 RAISE EXCEPTION 'Payout blocked: KYC/account/hold'; END IF; END IF;
 IF NEW.status='SUCCEEDED' AND (NEW.provider_reference IS NULL OR NEW.completed_at IS NULL) THEN RAISE EXCEPTION 'Success needs provider reference + time'; END IF;
 IF NEW.status='QUEUED' THEN
 PERFORM 1 FROM ledger_accounts WHERE vendor_id=NEW.vendor_id AND account_type='LIABILITY' ORDER BY id FOR UPDATE;
 SELECT coalesce(sum(ll.credit_minor-ll.debit_minor),0) INTO available FROM ledger_lines ll
 JOIN ledger_accounts a ON a.id=ll.account_id JOIN journal_entries j ON j.id=ll.journal_id
 WHERE a.vendor_id=NEW.vendor_id AND a.currency=NEW.currency AND a.account_type='LIABILITY' AND j.status='POSTED';
 SELECT coalesce(sum(total_minor),0) INTO in_flight FROM payout_batches
 WHERE vendor_id=NEW.vendor_id AND currency=NEW.currency AND id<>NEW.id AND status IN ('QUEUED','PROCESSING','FAILED');
 IF NEW.total_minor>available-in_flight THEN RAISE EXCEPTION 'Insufficient ledger liability after pending payouts'; END IF;
 END IF;
 IF NEW.status='SUCCEEDED' THEN
 SELECT * INTO settlement FROM journal_entries WHERE id=NEW.settlement_journal_id;
 IF settlement.id IS NULL OR settlement.status<>'POSTED' OR settlement.payout_batch_id IS DISTINCT FROM NEW.id
 OR NEW.total_minor<>(SELECT coalesce(sum(ll.debit_minor-ll.credit_minor),0) FROM ledger_lines ll JOIN ledger_accounts a ON a.id=ll.account_id
 WHERE ll.journal_id=settlement.id AND a.vendor_id=NEW.vendor_id AND a.account_type='LIABILITY' AND a.currency=NEW.currency) THEN
 RAISE EXCEPTION 'Payout success requires matching posted settlement debit'; END IF;
 END IF;
 RETURN NEW;
END $$;
CREATE TRIGGER payout_guard BEFORE INSERT OR UPDATE OR DELETE ON payout_batches FOR EACH ROW EXECUTE FUNCTION guard_payout();

-- Administrative/audit/export/consent data, protected from customer-facing reads.
CREATE TABLE tax_exports (
 id uuid_v4 PRIMARY KEY DEFAULT gen_random_uuid(), requested_by uuid_v4 NOT NULL REFERENCES users(id),
 period_start date NOT NULL, period_end date NOT NULL, export_type text NOT NULL,
 status text NOT NULL CHECK(status IN ('REQUESTED','READY','FAILED')), object_key text, checksum text,
 created_at timestamptz NOT NULL DEFAULT now(), CHECK(period_end>=period_start)
);
CREATE TABLE audit_events (
 id uuid_v4 PRIMARY KEY DEFAULT gen_random_uuid(), actor_id uuid_v4 REFERENCES users(id),
 action text NOT NULL, entity_type text NOT NULL, entity_id uuid_v4, request_id uuid_v4,
 metadata jsonb NOT NULL DEFAULT '{}', created_at timestamptz NOT NULL DEFAULT now()
); -- Application writes redacted metadata; no OTP/bank/PAN secrets.
CREATE TABLE message_deliveries (
 id uuid_v4 PRIMARY KEY DEFAULT gen_random_uuid(), user_id uuid_v4 NOT NULL REFERENCES users(id),
 order_id uuid_v4 REFERENCES orders(id), channel text NOT NULL CHECK(channel IN ('WHATSAPP','FCM','SMS','EMAIL')),
 template_code text NOT NULL, provider_reference text, dedupe_key text NOT NULL UNIQUE,
 status text NOT NULL CHECK(status IN ('PENDING','SENT','DELIVERED','FAILED')), created_at timestamptz NOT NULL DEFAULT now()
);
CREATE TABLE user_consents (
 id uuid_v4 PRIMARY KEY DEFAULT gen_random_uuid(), user_id uuid_v4 NOT NULL REFERENCES users(id),
 purpose text NOT NULL, version text NOT NULL, granted_at timestamptz NOT NULL DEFAULT now(), revoked_at timestamptz
);

-- Every FK gets its own leading-column index, including nullable and composite FKs.
-- Avoid redundant indexes when PK/UNIQUE already covers the leading columns.
DO $$
DECLARE r record; cols text; covered boolean;
BEGIN
 FOR r IN SELECT c.conrelid,c.conname,c.conkey,n.nspname,t.relname FROM pg_constraint c
 JOIN pg_class t ON t.oid=c.conrelid JOIN pg_namespace n ON n.oid=t.relnamespace
 WHERE c.contype='f' AND n.nspname='maakit' LOOP
 SELECT string_agg(quote_ident(a.attname),',' ORDER BY k.ord) INTO cols FROM unnest(r.conkey) WITH ORDINALITY k(attnum,ord)
 JOIN pg_attribute a ON a.attrelid=r.conrelid AND a.attnum=k.attnum;
 SELECT EXISTS(SELECT 1 FROM pg_index i WHERE i.indrelid=r.conrelid AND i.indisvalid AND i.indpred IS NULL
 AND (SELECT array_agg(a.x ORDER BY a.ord) FROM unnest(i.indkey::smallint[]) WITH ORDINALITY a(x,ord)
 WHERE a.ord<=cardinality(r.conkey))=r.conkey) INTO covered;
 IF NOT covered THEN EXECUTE format('CREATE INDEX %I ON %I.%I (%s)',r.relname||'_'||substr(md5(r.conname),1,10)||'_fk',r.nspname,r.relname,cols); END IF;
 END LOOP;
END $$;
CREATE INDEX order_customer_history ON orders(customer_id,created_at DESC);
CREATE INDEX order_dispatch_queue ON orders(status,created_at) WHERE status IN ('CONFIRMED','PACKING','READY');
CREATE INDEX held_reservations_expiry ON inventory_reservations(expires_at,id) WHERE status='HELD';
CREATE INDEX payable_eligible ON vendor_payables(vendor_id,eligible_at) WHERE NOT blocked;
CREATE INDEX otp_phone_time ON otp_challenges(phone_e164,created_at DESC);
CREATE INDEX payout_retry ON payout_batches(next_retry_at) WHERE status='FAILED';

-- Prevent mutations of immutable business snapshots and delivery without verified OTP.
CREATE FUNCTION guard_order() RETURNS trigger LANGUAGE plpgsql SET search_path=maakit,pg_catalog AS $$
BEGIN
 IF TG_OP='INSERT' THEN IF NEW.status<>'AWAITING_VENDOR' THEN RAISE EXCEPTION 'Order starts awaiting vendor'; END IF; RETURN NEW; END IF;
 IF TG_OP='DELETE' THEN RAISE EXCEPTION 'Order deletion forbidden'; END IF;
 IF (to_jsonb(NEW)-ARRAY['status','confirmed_at','delivered_at']) IS DISTINCT FROM (to_jsonb(OLD)-ARRAY['status','confirmed_at','delivered_at']) THEN
 RAISE EXCEPTION 'Order snapshots immutable; replacement quote requires a new order'; END IF;
 IF NEW.status<>OLD.status AND NOT (
 (OLD.status='AWAITING_VENDOR' AND NEW.status IN ('AWAITING_CUSTOMER','CONFIRMED','CANCELLED')) OR
 (OLD.status='AWAITING_CUSTOMER' AND NEW.status IN ('CONFIRMED','CANCELLED')) OR
 (OLD.status='CONFIRMED' AND NEW.status IN ('PACKING','CANCELLED')) OR
 (OLD.status='PACKING' AND NEW.status IN ('READY','CANCELLED')) OR
 (OLD.status='READY' AND NEW.status IN ('DISPATCHED','CANCELLED')) OR
 (OLD.status='DISPATCHED' AND NEW.status IN ('DELIVERED','RTO'))) THEN RAISE EXCEPTION 'Invalid order transition'; END IF;
 IF NEW.status='DELIVERED' AND OLD.status<>'DELIVERED' AND NOT EXISTS
 (SELECT 1 FROM delivery_otps WHERE order_id=NEW.id AND verified_at IS NOT NULL AND verified_at<=expires_at) THEN
 RAISE EXCEPTION 'Verified delivery OTP required'; END IF;
 RETURN NEW;
END $$;
CREATE TRIGGER order_guard BEFORE INSERT OR UPDATE OR DELETE ON orders FOR EACH ROW EXECUTE FUNCTION guard_order();
CREATE FUNCTION guard_order_item() RETURNS trigger LANGUAGE plpgsql SET search_path=maakit,pg_catalog AS $$
DECLARE o orders; v product_variants; p product_offers;
BEGIN
 IF TG_OP<>'INSERT' THEN RAISE EXCEPTION 'Order lines immutable'; END IF;
 SELECT * INTO STRICT o FROM orders WHERE id=NEW.order_id FOR UPDATE;
 SELECT * INTO STRICT v FROM product_variants WHERE id=NEW.variant_id;
 SELECT * INTO STRICT p FROM product_offers WHERE id=v.offer_id;
 IF o.status<>'AWAITING_VENDOR' OR p.store_id<>o.store_id OR p.is_b2b OR NOT p.active OR NOT v.active OR NOT EXISTS(SELECT 1 FROM products WHERE id=p.product_id AND active)
 OR NOT v.price_includes_item_tax OR v.unit_price_minor IS NULL OR NEW.unit_price_minor<>v.unit_price_minor OR NEW.inventory_policy<>p.inventory_policy
 OR (o.fulfillment='HYPERLOCAL' AND NOT p.supports_hyperlocal) OR (o.fulfillment='COURIER' AND NOT p.supports_courier) THEN
 RAISE EXCEPTION 'Invalid order line'; END IF; RETURN NEW;
END $$;
CREATE TRIGGER order_item_guard BEFORE INSERT OR UPDATE OR DELETE ON order_items FOR EACH ROW EXECUTE FUNCTION guard_order_item();
CREATE FUNCTION append_order_event() RETURNS trigger LANGUAGE plpgsql SET search_path=maakit,pg_catalog AS $$
BEGIN
 IF TG_OP='INSERT' OR NEW.status<>OLD.status THEN
 INSERT INTO order_events(order_id,old_status,new_status) VALUES(NEW.id,CASE WHEN TG_OP='INSERT' THEN NULL ELSE OLD.status END,NEW.status);
 INSERT INTO outbox_events(aggregate_type,aggregate_id,event_type,payload,dedupe_key)
 VALUES('ORDER',NEW.id,'ORDER_'||NEW.status,jsonb_build_object('order_id',NEW.id,'status',NEW.status),NEW.id||':'||NEW.status);
 END IF; RETURN NEW;
END $$;
CREATE TRIGGER order_events_outbox AFTER INSERT OR UPDATE ON orders FOR EACH ROW EXECUTE FUNCTION append_order_event();

-- 7. Atomic reservation lifecycle (tracked inventory only; never fake stock for ON_REQUEST).
CREATE FUNCTION reserve_stock(p_item uuid, p_expiry timestamptz) RETURNS uuid LANGUAGE plpgsql SET search_path=maakit,pg_catalog AS $$
DECLARE i order_items; inv inventory; rid uuid;
BEGIN
 SELECT * INTO STRICT i FROM order_items WHERE id=p_item FOR UPDATE;
 SELECT id INTO rid FROM inventory_reservations WHERE order_item_id=p_item;
 IF rid IS NOT NULL THEN RETURN rid; END IF;
 IF i.inventory_policy<>'TRACKED' OR p_expiry<=now() THEN RAISE EXCEPTION 'Invalid stock reservation'; END IF;
 UPDATE inventory SET reserved=reserved+i.quantity,version=version+1,updated_at=now()
 WHERE variant_id=i.variant_id AND on_hand-reserved>=i.quantity RETURNING * INTO inv;
 IF NOT FOUND THEN RAISE EXCEPTION 'Insufficient stock' USING ERRCODE='23514'; END IF;
 INSERT INTO inventory_reservations(order_item_id,inventory_id,quantity,expires_at) VALUES(i.id,inv.id,i.quantity,p_expiry) RETURNING id INTO rid;
 INSERT INTO inventory_movements(inventory_id,reservation_id,kind,on_hand_delta,reserved_delta,reason)
 VALUES(inv.id,rid,'RESERVE',0,i.quantity,'Checkout reservation'); RETURN rid;
END $$;
CREATE FUNCTION finish_reservation(p_reservation uuid, p_consume boolean) RETURNS void LANGUAGE plpgsql SET search_path=maakit,pg_catalog AS $$
DECLARE r inventory_reservations;
BEGIN
 SELECT * INTO STRICT r FROM inventory_reservations WHERE id=p_reservation FOR UPDATE;
 IF r.status<>'HELD' THEN
 IF (p_consume AND r.status='CONSUMED') OR (NOT p_consume AND r.status='RELEASED') THEN RETURN; END IF;
 RAISE EXCEPTION 'Conflicting reservation outcome'; END IF;
 IF p_consume AND r.expires_at<=now() THEN RAISE EXCEPTION 'Expired reservation'; END IF;
 UPDATE inventory SET reserved=reserved-r.quantity,on_hand=on_hand-CASE WHEN p_consume THEN r.quantity ELSE 0 END,version=version+1,updated_at=now()
 WHERE id=r.inventory_id AND reserved>=r.quantity;
 IF NOT FOUND THEN RAISE EXCEPTION 'Inventory inconsistency'; END IF;
 UPDATE inventory_reservations SET status=CASE WHEN p_consume THEN 'CONSUMED'::reservation_state ELSE 'RELEASED'::reservation_state END WHERE id=r.id;
 INSERT INTO inventory_movements(inventory_id,reservation_id,kind,on_hand_delta,reserved_delta,reason)
 VALUES(r.inventory_id,r.id,CASE WHEN p_consume THEN 'SALE' ELSE 'RELEASE' END,CASE WHEN p_consume THEN -r.quantity ELSE 0 END,-r.quantity,'Reservation completion');
END $$;

-- Quotes bind the actual current cart prices/quantity/address, not client-supplied prices.
CREATE FUNCTION cart_quote_fingerprint(p_cart uuid,p_address uuid) RETURNS text LANGUAGE sql STABLE SET search_path=maakit,pg_catalog AS $$
 SELECT encode(sha256(convert_to(jsonb_build_object('cart',c.id,'store',c.store_id,'mode',c.fulfillment,'address',to_jsonb(a),
 'items',(SELECT jsonb_agg(jsonb_build_object('variant',v.id,'qty',ci.quantity,'price',v.unit_price_minor,
 'tax_inclusive',v.price_includes_item_tax,'variant_version',v.version,'offer_active',po.active,'is_b2b',po.is_b2b,'hyperlocal',po.supports_hyperlocal,
 'courier',po.supports_courier,'inventory',po.inventory_policy,'tax',to_jsonb(tr)) ORDER BY v.id)
 FROM cart_items ci JOIN product_variants v ON v.id=ci.variant_id JOIN product_offers po ON po.id=v.offer_id
 LEFT JOIN tax_rules tr ON tr.id=v.tax_rule_id WHERE ci.cart_id=c.id))::text,'UTF8')),'hex')
 FROM carts c JOIN addresses a ON a.id=p_address AND a.user_id=c.customer_id WHERE c.id=p_cart
$$;

-- 8. Server-side checkout: accepts IDs and idempotency key, NEVER client prices.
CREATE FUNCTION checkout_cart(p_customer uuid,p_cart uuid,p_address uuid,p_quote uuid,p_payment text,p_recipient text,p_key text,p_fingerprint text)
RETURNS uuid LANGUAGE plpgsql SET search_path=maakit,pg_catalog AS $$
DECLARE c carts; s stores; a addresses; q delivery_quotes; risk customer_risk; o orders;
 line record; sub bigint; oid uuid; itemid uuid; tx jsonb; cp jsonb;
BEGIN
 IF p_customer IS DISTINCT FROM current_actor() THEN RAISE EXCEPTION 'Checkout owner mismatch' USING ERRCODE='42501'; END IF;
 -- Serialize this customer's idempotency key (hash collisions only add harmless serialization).
 PERFORM pg_advisory_xact_lock(hashtextextended(p_customer::text||':'||p_key,0));
 SELECT * INTO o FROM orders WHERE customer_id=p_customer AND idempotency_key=p_key;
 IF FOUND THEN IF o.request_fingerprint<>p_fingerprint OR o.cart_id<>p_cart OR o.address_id<>p_address OR o.payment_method<>p_payment OR o.payment_recipient<>p_recipient THEN
 RAISE EXCEPTION 'Idempotency key reused for different request'; END IF; RETURN o.id; END IF;
 SELECT * INTO STRICT c FROM carts WHERE id=p_cart AND customer_id=p_customer FOR UPDATE;
 IF c.state<>'ACTIVE' THEN RAISE EXCEPTION 'Cart is not active'; END IF;
 SELECT * INTO STRICT s FROM stores WHERE id=c.store_id FOR SHARE;
 SELECT * INTO STRICT a FROM addresses WHERE id=p_address AND user_id=p_customer FOR SHARE;
 IF NOT s.active OR (c.fulfillment='HYPERLOCAL' AND NOT s.supports_hyperlocal) OR (c.fulfillment='COURIER' AND NOT s.supports_courier) THEN RAISE EXCEPTION 'Store/mode unavailable'; END IF;
 IF NOT EXISTS(SELECT 1 FROM store_service_zones WHERE store_id=s.id AND fulfillment=c.fulfillment AND pincode=a.pincode AND active) THEN RAISE EXCEPTION 'Address not serviceable'; END IF;
 IF c.fulfillment='HYPERLOCAL' AND (a.latitude IS NULL OR
 6371000*2*asin(sqrt(least(1.0,power(sin(radians((a.latitude-s.latitude)::double precision)/2),2)
 +cos(radians(s.latitude::double precision))*cos(radians(a.latitude::double precision))*power(sin(radians((a.longitude-s.longitude)::double precision)/2),2))))>s.radius_m) THEN
 RAISE EXCEPTION 'Address outside geofence'; END IF;
 SELECT * INTO STRICT q FROM delivery_quotes WHERE id=p_quote AND cart_id=c.id AND address_id=a.id AND fulfillment=c.fulfillment FOR UPDATE;
 IF q.expires_at<=now() OR q.consumed_at IS NOT NULL OR q.fingerprint<>p_fingerprint OR q.fingerprint IS DISTINCT FROM cart_quote_fingerprint(c.id,a.id) THEN RAISE EXCEPTION 'Stale/mismatched delivery quote'; END IF;
 SELECT * INTO risk FROM customer_risk WHERE user_id=p_customer FOR SHARE;
 -- Lock offers and variants so self-service edits cannot race with snapshot creation.
 PERFORM v.id FROM cart_items ci JOIN product_variants v ON v.id=ci.variant_id JOIN product_offers po ON po.id=v.offer_id
 WHERE ci.cart_id=c.id ORDER BY v.id FOR SHARE OF v,po;
 IF NOT EXISTS(SELECT 1 FROM cart_items WHERE cart_id=c.id) OR EXISTS(SELECT 1 FROM cart_items ci JOIN product_variants v ON v.id=ci.variant_id JOIN product_offers po ON po.id=v.offer_id
 WHERE ci.cart_id=c.id AND (v.unit_price_minor IS NULL OR NOT v.price_includes_item_tax OR NOT v.active OR NOT po.active OR NOT EXISTS(SELECT 1 FROM products WHERE id=po.product_id AND active) OR po.is_b2b OR po.store_id<>c.store_id
 OR (c.fulfillment='HYPERLOCAL' AND NOT po.supports_hyperlocal) OR (c.fulfillment='COURIER' AND NOT po.supports_courier))) THEN RAISE EXCEPTION 'Invalid cart contents'; END IF;
 IF q.fingerprint IS DISTINCT FROM cart_quote_fingerprint(c.id,a.id) THEN RAISE EXCEPTION 'Cart changed: refresh quote and obtain consent'; END IF;
 SELECT sum(v.unit_price_minor::bigint*ci.quantity) INTO sub FROM cart_items ci JOIN product_variants v ON v.id=ci.variant_id WHERE ci.cart_id=c.id;
 IF sub<s.minimum_order_minor THEN RAISE EXCEPTION 'Minimum order value not met' USING ERRCODE='23514'; END IF;
 IF p_payment='COD' AND (coalesce(risk.cod_blocked,false) OR coalesce(risk.rto_score,0)>=s.cod_risk_threshold OR (risk.cod_order_limit_minor IS NOT NULL AND sub+q.delivery_minor+q.service_fee_minor>risk.cod_order_limit_minor)) THEN RAISE EXCEPTION 'COD restricted'; END IF;
 SELECT to_jsonb(t) INTO tx FROM vendor_tax_profiles t WHERE vendor_id=s.vendor_id AND valid_from<=now() AND (valid_until IS NULL OR valid_until>now()) ORDER BY valid_from DESC LIMIT 1;
 SELECT to_jsonb(p) INTO cp FROM commission_policies p WHERE store_id=s.id AND valid_from<=now() AND (valid_until IS NULL OR valid_until>now()) ORDER BY valid_from DESC LIMIT 1;
 IF cp IS NULL THEN RAISE EXCEPTION 'Commission policy missing'; END IF;
 INSERT INTO orders(customer_id,store_id,vendor_id,cart_id,address_id,fulfillment,status,payment_method,payment_recipient,subtotal_minor,delivery_minor,service_fee_minor,total_minor,address_snapshot,vendor_tax_snapshot,commission_snapshot,risk_score_snapshot,idempotency_key,request_fingerprint)
 VALUES(p_customer,s.id,s.vendor_id,c.id,a.id,c.fulfillment,'AWAITING_VENDOR',p_payment,p_recipient,sub,q.delivery_minor,q.service_fee_minor,sub+q.delivery_minor+q.service_fee_minor,to_jsonb(a),coalesce(tx,'{}'),cp,coalesce(risk.rto_score,0),p_key,p_fingerprint) RETURNING id INTO oid;
 FOR line IN SELECT ci.quantity,v.*,po.inventory_policy,p.name,p.brand FROM cart_items ci JOIN product_variants v ON v.id=ci.variant_id
 JOIN product_offers po ON po.id=v.offer_id JOIN products p ON p.id=po.product_id WHERE ci.cart_id=c.id ORDER BY v.id LOOP
 INSERT INTO order_items(order_id,variant_id,quantity,unit_price_minor,line_total_minor,product_snapshot,tax_snapshot,inventory_policy)
 VALUES(oid,line.id,line.quantity,line.unit_price_minor,line.unit_price_minor*line.quantity,
 jsonb_build_object('name',line.name,'brand',line.brand,'pack',line.pack_label,'attributes',line.attributes),
 jsonb_build_object('price_includes_tax',line.price_includes_item_tax,'rule',
 (SELECT to_jsonb(tr) FROM tax_rules tr WHERE tr.id=line.tax_rule_id)),line.inventory_policy) RETURNING id INTO itemid;
 IF line.inventory_policy='TRACKED' THEN PERFORM reserve_stock(itemid,now()+interval '15 minutes'); END IF;
 END LOOP;
 UPDATE carts SET state='CHECKED_OUT',updated_at=now() WHERE id=c.id;
 UPDATE delivery_quotes SET consumed_at=now() WHERE id=q.id;
 RETURN oid;
END $$;

-- 9. Payout assembly: locks vendor + eligible payables; retry uses same batch and provider key.
CREATE FUNCTION assemble_payout(p_vendor uuid,p_account uuid,p_key text) RETURNS uuid LANGUAGE plpgsql SET search_path=maakit,pg_catalog AS $$
DECLARE b payout_batches; p vendor_payables; bid uuid; n bigint:=0; adjusted bigint;
BEGIN
 IF NOT has_permission('FINANCE_WRITE') THEN RAISE EXCEPTION 'FINANCE_WRITE required' USING ERRCODE='42501'; END IF;
 PERFORM 1 FROM vendors WHERE id=p_vendor AND kyc_status='VERIFIED' AND status='ACTIVE' FOR UPDATE;
 IF NOT FOUND THEN RAISE EXCEPTION 'Verified active vendor required'; END IF;
 SELECT * INTO b FROM payout_batches WHERE idempotency_key=p_key;
 IF FOUND THEN IF b.vendor_id<>p_vendor OR b.payout_account_id<>p_account THEN RAISE EXCEPTION 'Payout idempotency conflict'; END IF; RETURN b.id; END IF;
 IF NOT EXISTS(SELECT 1 FROM payout_accounts WHERE id=p_account AND vendor_id=p_vendor AND status='VERIFIED') THEN RAISE EXCEPTION 'Verified payout account required'; END IF;
 INSERT INTO payout_batches(vendor_id,payout_account_id,idempotency_key) VALUES(p_vendor,p_account,p_key) RETURNING id INTO bid;
 FOR p IN SELECT vp.* FROM vendor_payables vp WHERE vp.vendor_id=p_vendor AND NOT vp.blocked AND vp.eligible_at<=now() AND vp.net_minor>0
 AND NOT EXISTS(SELECT 1 FROM payout_allocations WHERE payable_id=vp.id) ORDER BY vp.id FOR UPDATE OF vp LOOP
 SELECT p.net_minor+coalesce(sum(delta_minor),0) INTO adjusted FROM vendor_payable_adjustments WHERE payable_id=p.id;
 IF adjusted>0 THEN
 INSERT INTO payout_allocations(batch_id,payable_id,vendor_id,amount_minor) VALUES(bid,p.id,p_vendor,adjusted); n:=n+adjusted;
 END IF;
 END LOOP;
 IF n=0 THEN RAISE EXCEPTION 'No eligible payable'; END IF;
 UPDATE payout_batches SET total_minor=n,status='QUEUED' WHERE id=bid;
 INSERT INTO outbox_events(aggregate_type,aggregate_id,event_type,payload,dedupe_key) VALUES('PAYOUT',bid,'PAYOUT_QUEUED',jsonb_build_object('batch_id',bid),'payout:'||bid);
 RETURN bid;
END $$;


-- Service bookings share financial infrastructure, independently of shopping carts.
ALTER TABLE service_bookings ADD COLUMN vendor_id uuid_v4 NOT NULL REFERENCES vendors(id);
ALTER TABLE service_bookings ADD COLUMN currency char(3) NOT NULL DEFAULT 'INR' CHECK(currency='INR');
ALTER TABLE service_bookings ADD CONSTRAINT service_booking_vendor_currency UNIQUE(id,vendor_id,currency);
CREATE INDEX service_booking_vendor_fk ON service_bookings(vendor_id);
ALTER TABLE payments ALTER COLUMN order_id DROP NOT NULL;
ALTER TABLE payments ADD COLUMN service_booking_id uuid_v4 REFERENCES service_bookings(id);
ALTER TABLE payments ADD CONSTRAINT payment_one_source CHECK(num_nonnulls(order_id,service_booking_id)=1);
CREATE INDEX payment_service_booking_fk ON payments(service_booking_id);
ALTER TABLE journal_entries ADD COLUMN service_booking_id uuid_v4 REFERENCES service_bookings(id);
ALTER TABLE journal_entries ADD CONSTRAINT journal_one_source CHECK(num_nonnulls(order_id,service_booking_id)<=1);
CREATE INDEX journal_service_booking_fk ON journal_entries(service_booking_id);
ALTER TABLE vendor_payables ALTER COLUMN order_id DROP NOT NULL;
ALTER TABLE vendor_payables ADD COLUMN service_booking_id uuid_v4 UNIQUE REFERENCES service_bookings(id);
ALTER TABLE vendor_payables ADD CONSTRAINT payable_one_source CHECK(num_nonnulls(order_id,service_booking_id)=1);
ALTER TABLE vendor_payables ADD CONSTRAINT payable_service_vendor_currency FOREIGN KEY(service_booking_id,vendor_id,currency)
 REFERENCES service_bookings(id,vendor_id,currency);
CREATE INDEX payable_service_vendor_currency_fk ON vendor_payables(service_booking_id,vendor_id,currency);
CREATE TABLE service_tax_lines (
 id uuid_v4 PRIMARY KEY DEFAULT gen_random_uuid(), service_booking_id uuid_v4 NOT NULL REFERENCES service_bookings(id),
 tax_rule_id uuid_v4 NOT NULL REFERENCES tax_rules(id), tax_type text NOT NULL,
 basis_minor amount_minor NOT NULL, rate_snapshot rate_fraction NOT NULL, amount_minor amount_minor NOT NULL,
 rule_snapshot jsonb NOT NULL, CHECK(amount_minor=round(basis_minor::numeric*rate_snapshot)::bigint)
);
CREATE INDEX service_tax_booking_fk ON service_tax_lines(service_booking_id);
CREATE INDEX service_tax_rule_fk ON service_tax_lines(tax_rule_id);

-- Settlement journal links money movement to its payout, including retry/reconciliation.
ALTER TABLE journal_entries ADD COLUMN payout_batch_id uuid_v4 UNIQUE REFERENCES payout_batches(id);
ALTER TABLE payout_batches ADD COLUMN settlement_journal_id uuid_v4 UNIQUE REFERENCES journal_entries(id);
CREATE TABLE vendor_payable_adjustments (
 id uuid_v4 PRIMARY KEY DEFAULT gen_random_uuid(), payable_id uuid_v4 NOT NULL REFERENCES vendor_payables(id),
 journal_id uuid_v4 NOT NULL UNIQUE REFERENCES journal_entries(id), delta_minor bigint NOT NULL CHECK(delta_minor<>0),
 reason text NOT NULL, idempotency_key text NOT NULL UNIQUE, created_at timestamptz NOT NULL DEFAULT now()
); -- Before allocation, posted debit corrections reduce payable without overwriting history.
CREATE INDEX payable_adjustments_payable_fk ON vendor_payable_adjustments(payable_id);
CREATE FUNCTION guard_payable_adjustment() RETURNS trigger LANGUAGE plpgsql SET search_path=maakit,pg_catalog AS $$
DECLARE p vendor_payables; j journal_entries; delta numeric;
BEGIN
 IF TG_OP<>'INSERT' THEN RAISE EXCEPTION 'Adjustment immutable'; END IF;
 SELECT * INTO STRICT p FROM vendor_payables WHERE id=NEW.payable_id FOR UPDATE;
 IF EXISTS(SELECT 1 FROM payout_allocations WHERE payable_id=p.id) THEN RAISE EXCEPTION 'Allocated payable locked; use vendor debt/recovery journal for post-payout corrections'; END IF;
 SELECT * INTO STRICT j FROM journal_entries WHERE id=NEW.journal_id;
 SELECT coalesce(sum(ll.credit_minor-ll.debit_minor),0) INTO delta FROM ledger_lines ll JOIN ledger_accounts a ON a.id=ll.account_id
 WHERE ll.journal_id=j.id AND a.vendor_id=p.vendor_id AND a.account_type='LIABILITY';
 IF j.status<>'POSTED' OR delta<>NEW.delta_minor OR (j.order_id,j.service_booking_id) IS DISTINCT FROM (p.order_id,p.service_booking_id)
 OR p.net_minor+NEW.delta_minor+(SELECT coalesce(sum(delta_minor),0) FROM vendor_payable_adjustments WHERE payable_id=p.id)<0 THEN
 RAISE EXCEPTION 'Adjustment must reconcile to posted liability and leave nonnegative entitlement'; END IF;
 RETURN NEW;
END $$;
CREATE TRIGGER payable_adjustment_guard BEFORE INSERT OR UPDATE OR DELETE ON vendor_payable_adjustments FOR EACH ROW EXECUTE FUNCTION guard_payable_adjustment();
CREATE INDEX journal_payout_batch_fk ON journal_entries(payout_batch_id);
CREATE INDEX payout_settlement_journal_fk ON payout_batches(settlement_journal_id);

ALTER TABLE journal_entries ADD CONSTRAINT journal_payout_exclusive CHECK(payout_batch_id IS NULL OR num_nonnulls(order_id,service_booking_id)=0);

-- Additional cross-entity and financial integrity constraints.
ALTER TABLE orders ADD CONSTRAINT orders_id_mode_unique UNIQUE(id,fulfillment);
ALTER TABLE shipments ADD CONSTRAINT shipment_order_mode FOREIGN KEY(order_id,fulfillment) REFERENCES orders(id,fulfillment);
CREATE INDEX shipment_order_mode_fk ON shipments(order_id,fulfillment);
ALTER TABLE order_items ADD CONSTRAINT order_item_identity UNIQUE(id,order_id);
ALTER TABLE order_tax_lines ADD CONSTRAINT tax_item_same_order FOREIGN KEY(order_item_id,order_id) REFERENCES order_items(id,order_id);
CREATE INDEX tax_item_order_fk ON order_tax_lines(order_item_id,order_id);

CREATE FUNCTION check_order_subtotal() RETURNS trigger LANGUAGE plpgsql SET search_path=maakit,pg_catalog AS $$
DECLARE oid uuid; expected bigint; actual numeric; n integer;
BEGIN
 IF TG_TABLE_NAME='orders' THEN oid:=NEW.id; ELSE oid:=NEW.order_id; END IF;
 SELECT subtotal_minor INTO STRICT expected FROM orders WHERE id=oid;
 SELECT count(*),coalesce(sum(line_total_minor),0) INTO n,actual FROM order_items WHERE order_id=oid;
 IF n=0 OR expected<>actual THEN RAISE EXCEPTION 'Order subtotal/lines mismatch' USING ERRCODE='23514'; END IF;
 RETURN NULL;
END $$;
CREATE CONSTRAINT TRIGGER order_subtotal_check AFTER INSERT ON orders DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION check_order_subtotal();
CREATE CONSTRAINT TRIGGER item_subtotal_check AFTER INSERT ON order_items DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION check_order_subtotal();

CREATE FUNCTION reject_mutation() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN RAISE EXCEPTION 'Immutable history: append correction/reversal instead'; END $$;
CREATE TRIGGER service_tax_immutable BEFORE UPDATE OR DELETE ON service_tax_lines FOR EACH ROW EXECUTE FUNCTION reject_mutation();
CREATE TRIGGER order_tax_immutable BEFORE UPDATE OR DELETE ON order_tax_lines FOR EACH ROW EXECUTE FUNCTION reject_mutation();
CREATE TRIGGER ledger_account_immutable BEFORE UPDATE OR DELETE ON ledger_accounts FOR EACH ROW EXECUTE FUNCTION reject_mutation();
CREATE TRIGGER movement_immutable BEFORE UPDATE OR DELETE ON inventory_movements FOR EACH ROW EXECUTE FUNCTION reject_mutation();
CREATE TRIGGER events_immutable BEFORE UPDATE OR DELETE ON order_events FOR EACH ROW EXECUTE FUNCTION reject_mutation();
CREATE TRIGGER audit_immutable BEFORE UPDATE OR DELETE ON audit_events FOR EACH ROW EXECUTE FUNCTION reject_mutation();
CREATE TRIGGER tax_rule_immutable BEFORE UPDATE OR DELETE ON tax_rules FOR EACH ROW EXECUTE FUNCTION reject_mutation();
-- Retire policies by setting valid_until BEFORE publication; later changes create new approved versions.
CREATE FUNCTION policy_window_guard() RETURNS trigger LANGUAGE plpgsql SET search_path=maakit,pg_catalog AS $$
DECLARE overlapping boolean; lockkey text;
BEGIN
 IF TG_TABLE_NAME='tax_rules' THEN lockkey:='tax:'||NEW.code;
 ELSIF TG_TABLE_NAME='commission_policies' THEN lockkey:='commission:'||NEW.store_id;
 ELSE lockkey:='profile:'||NEW.vendor_id; END IF;
 PERFORM pg_advisory_xact_lock(hashtextextended(lockkey,0));
 IF TG_TABLE_NAME='tax_rules' THEN
 SELECT EXISTS(SELECT 1 FROM tax_rules WHERE code=NEW.code AND id<>NEW.id AND
 tstzrange(valid_from,valid_until,'[)') && tstzrange(NEW.valid_from,NEW.valid_until,'[)')) INTO overlapping;
 ELSIF TG_TABLE_NAME='commission_policies' THEN
 SELECT EXISTS(SELECT 1 FROM commission_policies WHERE store_id=NEW.store_id AND id<>NEW.id AND
 tstzrange(valid_from,valid_until,'[)') && tstzrange(NEW.valid_from,NEW.valid_until,'[)')) INTO overlapping;
 ELSE
 SELECT EXISTS(SELECT 1 FROM vendor_tax_profiles WHERE vendor_id=NEW.vendor_id AND id<>NEW.id AND
 tstzrange(valid_from,valid_until,'[)') && tstzrange(NEW.valid_from,NEW.valid_until,'[)')) INTO overlapping;
 END IF;
 IF overlapping THEN RAISE EXCEPTION 'Effective-date policy overlap' USING ERRCODE='23514'; END IF;
 RETURN NEW;
END $$;
CREATE TRIGGER tax_window BEFORE INSERT ON tax_rules FOR EACH ROW EXECUTE FUNCTION policy_window_guard();
CREATE TRIGGER commission_window BEFORE INSERT OR UPDATE ON commission_policies FOR EACH ROW EXECUTE FUNCTION policy_window_guard();
CREATE TRIGGER profile_window BEFORE INSERT OR UPDATE ON vendor_tax_profiles FOR EACH ROW EXECUTE FUNCTION policy_window_guard();
-- Retiring a tax rule may only shorten its future validity; monetary fields are immutable.
DROP TRIGGER tax_rule_immutable ON tax_rules;
CREATE FUNCTION retire_tax_rule_only() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
 IF TG_OP='DELETE' THEN RAISE EXCEPTION 'Tax rule deletion forbidden'; END IF;
 IF (to_jsonb(NEW)-'valid_until') IS DISTINCT FROM (to_jsonb(OLD)-'valid_until')
 OR NEW.valid_until IS NULL OR NEW.valid_until<now() OR (OLD.valid_until IS NOT NULL AND NEW.valid_until>OLD.valid_until) THEN
 RAISE EXCEPTION 'Only prospective tax rule retirement allowed'; END IF; RETURN NEW;
END $$;
CREATE TRIGGER tax_rule_retire BEFORE UPDATE OR DELETE ON tax_rules FOR EACH ROW EXECUTE FUNCTION retire_tax_rule_only();

CREATE FUNCTION verify_delivery_otp(p_order uuid,p_candidate_hmac bytea,p_actor uuid) RETURNS boolean LANGUAGE plpgsql SET search_path=maakit,pg_catalog AS $$
DECLARE d delivery_otps; o orders;
BEGIN
 SELECT * INTO STRICT o FROM orders WHERE id=p_order FOR UPDATE;
 IF p_actor IS DISTINCT FROM current_actor() OR o.status<>'DISPATCHED' OR NOT (EXISTS(SELECT 1 FROM shipments s JOIN riders r ON r.id=s.rider_id
 WHERE s.order_id=p_order AND r.user_id=p_actor) OR (o.fulfillment='COURIER' AND o.customer_id=p_actor
 AND EXISTS(SELECT 1 FROM shipments WHERE order_id=p_order AND provider IN ('THREE_PL','INDIA_POST')))) THEN RAISE EXCEPTION 'Not assigned rider/order not dispatched'; END IF;
 SELECT * INTO STRICT d FROM delivery_otps WHERE order_id=p_order FOR UPDATE;
 IF d.verified_at IS NOT NULL OR d.expires_at<=now() OR d.attempts>=5 THEN RETURN false; END IF;
 UPDATE delivery_otps SET attempts=attempts+1 WHERE id=d.id;
 IF d.otp_hmac IS DISTINCT FROM p_candidate_hmac THEN RETURN false; END IF;
 UPDATE delivery_otps SET verified_at=now(),verified_by=p_actor WHERE id=d.id;
 RETURN true;
END $$; -- Caller calculates HMAC(order_id || challenge context || OTP, server pepper).
-- A failed verification returns false: COMMIT the attempt; throwing/rolling back resets attempts.

CREATE FUNCTION reserve_state_for_order() RETURNS trigger LANGUAGE plpgsql SET search_path=maakit,pg_catalog AS $$
DECLARE r inventory_reservations;
BEGIN
 IF NEW.status=OLD.status THEN RETURN NEW; END IF;
 IF NEW.status='CONFIRMED' THEN
 IF EXISTS(SELECT 1 FROM order_items i LEFT JOIN inventory_reservations x ON x.order_item_id=i.id
 WHERE i.order_id=NEW.id AND i.inventory_policy='TRACKED' AND (x.id IS NULL OR x.status<>'HELD' OR x.expires_at<=now())) THEN
 RAISE EXCEPTION 'Confirmation requires live stock reservations'; END IF;
 -- Once accepted, reservations persist until dispatch/cancel; expiry worker only handles awaiting orders.
 UPDATE inventory_reservations SET expires_at='infinity' WHERE order_item_id IN(SELECT id FROM order_items WHERE order_id=NEW.id) AND status='HELD';
 END IF;
 IF NEW.status IN ('DISPATCHED','CANCELLED') THEN
 FOR r IN SELECT x.* FROM inventory_reservations x JOIN order_items i ON i.id=x.order_item_id WHERE i.order_id=NEW.id ORDER BY x.inventory_id FOR UPDATE OF x LOOP
 PERFORM finish_reservation(r.id,NEW.status='DISPATCHED');
 END LOOP;
 END IF;
 RETURN NEW;
END $$;
CREATE TRIGGER order_inventory_state AFTER UPDATE OF status ON orders FOR EACH ROW EXECUTE FUNCTION reserve_state_for_order();
-- Never release stock solely by timestamp on a confirmed order. Cancellation and expiry are whole-order transactions.
CREATE FUNCTION cancel_expired_orders(p_limit integer DEFAULT 100) RETURNS integer LANGUAGE plpgsql SET search_path=maakit,pg_catalog AS $$
DECLARE o orders; n integer:=0;
BEGIN
 IF p_limit NOT BETWEEN 1 AND 1000 THEN RAISE EXCEPTION 'Invalid batch size'; END IF;
 FOR o IN SELECT x.* FROM orders x WHERE x.status IN ('AWAITING_VENDOR','AWAITING_CUSTOMER') AND EXISTS
 (SELECT 1 FROM order_items i JOIN inventory_reservations r ON r.order_item_id=i.id WHERE i.order_id=x.id AND r.status='HELD' AND r.expires_at<=now())
 ORDER BY x.id LIMIT p_limit FOR UPDATE OF x SKIP LOCKED LOOP
 UPDATE orders SET status='CANCELLED' WHERE id=o.id; n:=n+1;
 END LOOP;
 RETURN n;
END $$;

CREATE FUNCTION guard_refund_amount() RETURNS trigger LANGUAGE plpgsql SET search_path=maakit,pg_catalog AS $$
DECLARE p payments; used numeric;
BEGIN
 SELECT * INTO STRICT p FROM payments WHERE id=NEW.payment_id FOR UPDATE;
 IF TG_OP='UPDATE' AND (NEW.payment_id,NEW.amount_minor,NEW.idempotency_key) IS DISTINCT FROM (OLD.payment_id,OLD.amount_minor,OLD.idempotency_key) THEN
 RAISE EXCEPTION 'Refund identity/amount immutable'; END IF;
 SELECT coalesce(sum(amount_minor),0) INTO used FROM refunds WHERE payment_id=p.id AND id<>NEW.id AND status<>'FAILED';
 IF NEW.status<>'FAILED' AND (p.status NOT IN ('CAPTURED','PARTIALLY_REFUNDED','REFUNDED') OR used+NEW.amount_minor>p.amount_minor) THEN
 RAISE EXCEPTION 'Refund exceeds captured amount'; END IF;
 RETURN NEW;
END $$;
CREATE TRIGGER refund_amount BEFORE INSERT OR UPDATE ON refunds FOR EACH ROW EXECUTE FUNCTION guard_refund_amount();

CREATE FUNCTION guard_booking_update() RETURNS trigger LANGUAGE plpgsql SET search_path=maakit,pg_catalog AS $$
BEGIN
 IF TG_OP='DELETE' THEN RAISE EXCEPTION 'Booking history immutable'; END IF;
 IF (NEW.customer_id,NEW.vendor_id,NEW.service_id,NEW.slot_id,NEW.address_id,NEW.currency,NEW.idempotency_key)
 IS DISTINCT FROM (OLD.customer_id,OLD.vendor_id,OLD.service_id,OLD.slot_id,OLD.address_id,OLD.currency,OLD.idempotency_key) THEN
 RAISE EXCEPTION 'Booking identity immutable'; END IF;
 IF OLD.status<>'REQUESTED' AND (NEW.price_snapshot_minor,NEW.quote_snapshot) IS DISTINCT FROM (OLD.price_snapshot_minor,OLD.quote_snapshot) THEN RAISE EXCEPTION 'Accepted service quote immutable'; END IF;
 IF NEW.status<>OLD.status AND NOT ((OLD.status='REQUESTED' AND NEW.status IN ('CONFIRMED','CANCELLED')) OR
 (OLD.status='CONFIRMED' AND NEW.status IN ('IN_PROGRESS','CANCELLED')) OR (OLD.status='IN_PROGRESS' AND NEW.status='COMPLETED')) THEN RAISE EXCEPTION 'Invalid booking transition'; END IF;
 IF NEW.status IN ('CONFIRMED','IN_PROGRESS','COMPLETED') AND NEW.price_snapshot_minor IS NULL THEN RAISE EXCEPTION 'Accepted price required'; END IF;
 RETURN NEW;
END $$;
CREATE TRIGGER booking_update_guard BEFORE UPDATE OR DELETE ON service_bookings FOR EACH ROW EXECUTE FUNCTION guard_booking_update();
CREATE TRIGGER refund_history_immutable BEFORE DELETE ON refunds FOR EACH ROW EXECUTE FUNCTION reject_mutation();
-- Prevent a cashier/vendor bank-detail edit from redirecting an already allocated payout.
CREATE FUNCTION guard_payout_account() RETURNS trigger LANGUAGE plpgsql SET search_path=maakit,pg_catalog AS $$
BEGIN
 IF TG_OP='DELETE' THEN RAISE EXCEPTION 'Payout account history immutable'; END IF;
 IF (NEW.vendor_id,NEW.provider,NEW.beneficiary_token_ciphertext,NEW.account_last4) IS DISTINCT FROM
 (OLD.vendor_id,OLD.provider,OLD.beneficiary_token_ciphertext,OLD.account_last4) THEN RAISE EXCEPTION 'Bank detail changes need new verified account'; END IF;
 RETURN NEW;
END $$;
CREATE TRIGGER payout_account_update BEFORE UPDATE OR DELETE ON payout_accounts FOR EACH ROW EXECUTE FUNCTION guard_payout_account();

-- 10. DB roles and read-side row security. Backend alone supplies app.user_id via SET LOCAL.
-- Migration requires CREATEROLE. Neither mobile nor browser receives DB credentials.
DO $$ BEGIN
 IF NOT EXISTS(SELECT 1 FROM pg_roles WHERE rolname='maakit_reader') THEN CREATE ROLE maakit_reader NOLOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE NOBYPASSRLS; END IF;
 IF NOT EXISTS(SELECT 1 FROM pg_roles WHERE rolname='maakit_service') THEN CREATE ROLE maakit_service NOLOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE NOBYPASSRLS; END IF;
 IF EXISTS(SELECT 1 FROM pg_roles WHERE rolname IN ('maakit_reader','maakit_service') AND (rolsuper OR rolbypassrls OR rolcreaterole OR rolcreatedb OR rolcanlogin)) THEN RAISE EXCEPTION 'Unsafe pre-existing Maakit database role'; END IF;
END $$;
REVOKE ALL ON SCHEMA maakit FROM PUBLIC;
GRANT USAGE ON SCHEMA maakit TO maakit_reader,maakit_service;
REVOKE ALL ON ALL FUNCTIONS IN SCHEMA maakit FROM PUBLIC;
CREATE FUNCTION current_actor() RETURNS uuid LANGUAGE sql STABLE AS $$ SELECT nullif(current_setting('app.user_id',true),'')::uuid $$;
CREATE FUNCTION has_permission(p_code text) RETURNS boolean LANGUAGE sql STABLE SECURITY DEFINER SET search_path=maakit,pg_catalog AS $$
 SELECT EXISTS(SELECT 1 FROM user_permissions up JOIN permissions p ON p.id=up.permission_id JOIN users u ON u.id=up.user_id
 WHERE up.user_id=current_actor() AND p.code=p_code AND u.status='ACTIVE') OR EXISTS
 (SELECT 1 FROM user_roles ur JOIN role_permissions rp ON rp.role_id=ur.role_id JOIN permissions p ON p.id=rp.permission_id JOIN users u ON u.id=ur.user_id
 WHERE ur.user_id=current_actor() AND p.code=p_code AND u.status='ACTIVE') $$;
CREATE FUNCTION member_of_vendor(p_vendor uuid) RETURNS boolean LANGUAGE sql STABLE SECURITY DEFINER SET search_path=maakit,pg_catalog AS $$
 SELECT EXISTS(SELECT 1 FROM vendor_members m JOIN users u ON u.id=m.user_id WHERE m.vendor_id=p_vendor AND m.user_id=current_actor() AND m.active AND u.status='ACTIVE')
 OR EXISTS(SELECT 1 FROM vendors v JOIN users u ON u.id=v.owner_user_id WHERE v.id=p_vendor AND v.owner_user_id=current_actor() AND u.status='ACTIVE') $$;
REVOKE ALL ON ALL FUNCTIONS IN SCHEMA maakit FROM PUBLIC;
GRANT EXECUTE ON FUNCTION current_actor(),has_permission(text),member_of_vendor(uuid) TO maakit_reader,maakit_service;
GRANT SELECT,INSERT,UPDATE,DELETE ON ALL TABLES IN SCHEMA maakit TO maakit_service;
GRANT EXECUTE ON ALL FUNCTIONS IN SCHEMA maakit TO maakit_service;
-- The service role is trusted backend infrastructure, not a signed-in end-user role.
-- It must enforce route permissions AND entity ownership before every mutation.
DO $$ DECLARE t text; BEGIN
 FOREACH t IN ARRAY ARRAY['orders','order_items','carts','cart_items','addresses','b2b_rfqs','b2b_quotes','service_bookings','payout_batches','payout_allocations','vendor_payables'] LOOP
 EXECUTE format('ALTER TABLE maakit.%I ENABLE ROW LEVEL SECURITY',t);
 EXECUTE format('ALTER TABLE maakit.%I FORCE ROW LEVEL SECURITY',t);
 EXECUTE format('CREATE POLICY service_access ON maakit.%I TO maakit_service USING (true) WITH CHECK (true)',t);
 EXECUTE format('GRANT SELECT ON maakit.%I TO maakit_reader',t);
 END LOOP;
END $$;
CREATE POLICY orders_read ON orders FOR SELECT TO maakit_reader USING(customer_id=current_actor() OR member_of_vendor(vendor_id) OR has_permission('ORDER_READ_ALL'));
CREATE POLICY items_read ON order_items FOR SELECT TO maakit_reader USING(EXISTS(SELECT 1 FROM orders WHERE id=order_id));
CREATE POLICY carts_read ON carts FOR SELECT TO maakit_reader USING(customer_id=current_actor());
CREATE POLICY cart_items_read ON cart_items FOR SELECT TO maakit_reader USING(EXISTS(SELECT 1 FROM carts WHERE id=cart_id));
CREATE POLICY addresses_read ON addresses FOR SELECT TO maakit_reader USING(user_id=current_actor());
CREATE POLICY rfqs_read ON b2b_rfqs FOR SELECT TO maakit_reader USING(buyer_id=current_actor() OR has_permission('B2B_READ_ALL'));
CREATE POLICY quotes_read ON b2b_quotes FOR SELECT TO maakit_reader USING(member_of_vendor(vendor_id) OR EXISTS(SELECT 1 FROM b2b_rfqs WHERE id=rfq_id));
CREATE POLICY bookings_read ON service_bookings FOR SELECT TO maakit_reader USING(customer_id=current_actor() OR member_of_vendor(vendor_id) OR has_permission('BOOKING_READ_ALL'));
CREATE POLICY payout_read ON payout_batches FOR SELECT TO maakit_reader USING(has_permission('FINANCE_READ'));
CREATE POLICY allocations_read ON payout_allocations FOR SELECT TO maakit_reader USING(has_permission('FINANCE_READ'));
CREATE POLICY payable_read ON vendor_payables FOR SELECT TO maakit_reader USING(has_permission('FINANCE_READ'));
-- No reader grants on credentials, OTPs, payout accounts, risk data, raw journals or audit secrets.
-- Rider/vendor-safe projections must be supplied by API; do not return customer order rows wholesale.
INSERT INTO roles(code) VALUES('ADMIN'),('VENDOR'),('RIDER'),('B2C_CUSTOMER'),('B2B_BUYER');
INSERT INTO permissions(code) VALUES('FINANCE_READ'),('FINANCE_WRITE'),('DISPATCH_WRITE'),('CATALOG_WRITE'),('ORDER_READ_ALL'),('BOOKING_READ_ALL'),('B2B_READ_ALL'),('RBAC_WRITE');
INSERT INTO role_permissions(role_id,permission_id) SELECT r.id,p.id FROM roles r CROSS JOIN permissions p
 WHERE (r.code='VENDOR' AND p.code='CATALOG_WRITE') OR (r.code='RIDER' AND p.code='DISPATCH_WRITE');
-- Permissions alone never confer access to another vendor's catalog or rider's shipment.
ALTER DEFAULT PRIVILEGES IN SCHEMA maakit REVOKE EXECUTE ON FUNCTIONS FROM PUBLIC;
COMMIT;
