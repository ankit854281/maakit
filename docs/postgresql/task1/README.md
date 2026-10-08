# Maakit: Task 1 PostgreSQL schema

A fresh-database PostgreSQL 16+ migration for local shopping, courier shopping,
time-slot home services and direct-vendor B2B enquiries. This is a separate new
PostgreSQL design, not a migration of the existing PHP/MySQL production data.

## Deliverables

- `maakit_schema.sql`: 61 tables, UUID-v4 ID domain, constraints, indexes, RBAC/RLS,
  34 functions, checkout/stock/payout workflows and immutable financial history.
- `verify_schema.mjs`: executable functional checks against a PGlite PostgreSQL engine.
- `verification.txt`: verification output from the delivered schema.
- `package.json`: pinned verification dependency.

## Apply to a fresh database

Use a migration owner with schema creation and CREATEROLE permissions. The owner
must be separate from application connections. The two application group roles
are NOLOGIN, NOSUPERUSER, NOCREATEDB, NOCREATEROLE and NOBYPASSRLS.

```sh
psql "$MAAKIT_DATABASE_URL" -v ON_ERROR_STOP=1 -f maakit_schema.sql
```

The SQL wraps the migration in a transaction. Applying it again fails rather than
silently masking schema drift. Use numbered, reviewed migrations for later edits.
Provision application login credentials separately through your secrets manager;
do not put them in this migration. Use TLS and grant schema-owner privileges only
to the migration/deployment identity. Every CREATE TABLE primary key is a domain
based on UUID, generated with `gen_random_uuid()` and validated as version 4 with
RFC variant bits. Every foreign key has an index with matching leading columns.

## Data model

| Area | Main tables |
|---|---|
| Identity and access | users, user_credentials, sessions, otp_challenges, roles, permissions, user_roles, role_permissions, user_permissions |
| Merchant and coverage | vendors, vendor_members, stores, store_service_zones, addresses, vendor_tax_profiles, commission_policies |
| Product catalogue and offers | categories, products, product_offers, product_variants, product_images |
| Inventory | inventory, inventory_reservations, inventory_movements |
| Shopping | carts, cart_items, orders, order_items, order_events, delivery_quotes, customer_risk |
| Delivery | riders, shipments, dispatch_attempts, delivery_otps |
| Service bookings | service_providers, services, service_slots, service_bookings |
| B2B | b2b_profiles, b2b_rfqs, b2b_quotes |
| Payments and taxes | payments, refunds, provider_webhooks, tax_rules, order_tax_lines, service_tax_lines, tax_exports |
| Accounting and settlement | ledger_accounts, journal_entries, ledger_lines, vendor_payables, vendor_payable_adjustments, payout_accounts, payout_batches, payout_allocations |
| Integration and audit | outbox_events, audit_events, message_deliveries, user_consents |

Money is integer INR paise. A rate is a fraction in [0,1], not a percentage number.
The verification fixture uses invented accounting amounts; it is not a statutory
rate recommendation. This release is INR-only. Dates use `timestamptz`; coverage
uses Indian six-digit PIN codes and coordinates.

## Ownership and permissions: UUIDs are not authorization

`ADMIN`, `VENDOR`, `RIDER`, `B2C_CUSTOMER`, `B2B_BUYER` are seeded business roles.
A user can hold multiple roles. ADMIN receives no automatic finance entitlement;
FINANCE_READ/FINANCE_WRITE and other elevated permissions require explicit grants.
Vendor membership is scoped to a merchant. A permission alone never authorizes
access to a different vendor's catalogue or rider's job.

The backend authenticates the session, verifies status and session version, then
sets the actor in an explicit transaction. Use transaction-local settings with
pooled connections. Both parameters and role names below are server-owned.

```sql
BEGIN;
SET LOCAL ROLE maakit_reader;
SELECT set_config('app.user_id', $1, true); -- authenticated user ID, not request body
SELECT * FROM maakit.orders WHERE id = $2; -- another customer's row is filtered
COMMIT;
```

Reader has SELECT-only access on the selected RLS tables; credentials, OTPs,
beneficiary tokens and raw private financial tables are not granted. FORCE RLS is
enabled on order/cart/address/booking/RFQ/payout read surfaces. Order-item reads
inherit the accessible parent order. Vendor access to full customer order rows
should be mediated by an API projection with only fulfillment-required fields;
provide a similar limited projection for assigned riders. Directory/public
catalogue responses also need explicit public API projections.

`maakit_service` is TRUSTED backend infrastructure with broad write privileges
and an explicit service policy; it is not an end-user role. The browser/mobile
client never gets DB credentials, arbitrary SQL, or the ability to set app.user_id.
Every mutation endpoint must validate RBAC and entity ownership. Financial,
KYC, tax-rule and role-assignment routes require additional elevated permissions.
Checkout also checks p_customer against the server-set actor. Payout assembly
requires FINANCE_WRITE; delivery OTP verification checks its actor and assignment.

## Product and cart decisions

- Product master entries are distinct from store offers and priced variants.
  A generic catalogue item does not automatically become a purchasable offer.
- Pending-KYC merchants can build catalogues. Store/offer activation is an explicit
  operation; first payout needs verified merchant KYC and a verified payout account.
- One cart has ONE store and ONE fulfillment mode. This release also enforces
  single-store COURIER checkout, a deliberate simplification; add parent checkout
  plus per-store child orders in a future migration if courier consolidation is needed.
- B2B offers never enter a B2C cart: RFQ -> quote -> direct vendor negotiation.
  Buyer contact-sharing consent is stored before WhatsApp/vendor contact disclosure.
- NULL price means enquiry-only. Customer-facing B2C variants must publish final
  tax-inclusive prices. Tax-exclusive wholesale quotations stay in the RFQ pipeline.
- TRACKED uses on_hand/reserved inventory. ON_REQUEST does not invent stock;
  order starts AWAITING_VENDOR and only confirms after real supplier acceptance.
- Cart triggers and checkout revalidate store, mode, availability, B2B flags and
  database prices. No price parameter is accepted by checkout.

## Checkout transaction

The trusted quote service calculates delivery charges and writes delivery_quotes
using `cart_quote_fingerprint(cart_id,address_id)`. This SHA-256 checksum binds
current quantities/prices/offer flags/tax snapshots/address. It is a checksum,
not a substitute for authentication or a signed external quote.

```sql
BEGIN;
SET LOCAL ROLE maakit_service;
SELECT set_config('app.user_id', $1, true);
SELECT maakit.checkout_cart(
  $1, $2, $3, $4,             -- customer/cart/address/server delivery quote UUIDs
  $5, $6,                    -- payment method and validated recipient mode
  $7, $8                     -- idempotency key and quote fingerprint
);
COMMIT;
```

The function locks cart/address/store and priced variants, verifies coverage and
Haversine radius, checks expiry/fingerprint/MOV/COD risk, recalculates totals,
snapshots vendor tax/commission policies, reserves TRACKED items in deterministic
variant order, writes the order/outbox, and consumes the quote. Any failure rolls
back the entire transaction. Idempotency keys are customer-scoped and conflicting
requests fail. Unknown price or a changed cart requires a refreshed quote and
customer consent. Current active commission policy is mandatory. MOV and COD risk
threshold are per store; COD restrictions and amount limits are per customer.

`checkout_cart` does not calculate a route, charge a payment provider or declare
ON_REQUEST availability. The API must validate payment-recipient choice against
merchant terms, phone verification, session state and delivery-provider coverage.
The API must also calculate the required item tax breakdown using configured,
approved tax rules; consumer item prices already include these taxes. When
prices/fees change, cancel/replace an awaiting order and obtain customer consent;
order money/address snapshots are intentionally immutable.

## Inventory and delivery lifecycle

`reserve_stock(order_item_id, expiry)` performs conditional atomic reservation:
`reserved += quantity WHERE on_hand - reserved >= quantity`. A unique reservation
per order item and unique movement per reservation/kind prevent double reservation.
`finish_reservation` is idempotent for a matching outcome and refuses conflicting
consume/release outcomes. Order confirmation checks live reservations and keeps
accepted inventory held until dispatch/cancellation. Dispatch consumes stock;
cancellation releases it. `cancel_expired_orders` locks whole awaiting orders
with SKIP LOCKED and cancels them transactionally, releasing all their reservations.
Do not run an independent timestamp-only release on accepted orders.

RTO does NOT automatically put goods back on sale: the inventory worker must
inspect the returned package and append a stock-return movement. Stock adjustments
and physical receipts must be recorded through trusted inventory endpoints, with
inventory locks and audit/movement entries in the same transaction.

Order transition guards reject skipped/invalid states. DELIVERED requires a
verified delivery OTP. Store only server-peppered, context-bound HMACs and key
versions. The API derives a candidate HMAC; OTP values/pepper stay outside the DB.
`verify_delivery_otp` locks the order/challenge, checks assigned rider or courier
customer acknowledgement, enforces expiry and five attempts and records success.
Wrong OTP returns false: COMMIT the attempt, otherwise a rollback erases it.
Use Redis phone/IP/device rate limits as well. Delivery OTP is distinct from login OTP.

3PL/India Post shipments are COURIER-only. A hyperlocal order cannot silently
become a multi-day shipment. If a local pool fails, require a new courier quote,
customer consent and a replacement order. Courier completion needs an agreed OTP
or customer acknowledgement integration, not just an unsigned delivery callback.

## Financial correctness

1. Approved effective-dated tax rules store tax type, basis, applicability, rate
   and source reference. No hardcoded TCS/TDS/GST deductions are seeded. The backend
   tax resolver must account for collection mode, registration, thresholds,
   place of supply, refunds and rounding; sign off configuration with the finance owner.
2. Order/service tax rows persist the actual basis, rate, amount and full rule
   snapshot. Previously published money/tax records are immutable.
3. A journal starts DRAFT; add debit/credit lines and then set POSTED with posted_at.
   Posting requires >=2 lines, positive balanced totals and deterministic account
   locks. Posted journals/lines and account identities are immutable; correct them
   with a separately posted reversal/correction journal.
4. Vendor payables reconcile to the posted vendor liability and require captured
   platform funds plus delivered shopping order/completed service booking. Direct
   shop payment never creates a platform payout entitlement. Refund/return hold
   expiry and the true distributable gross are computed by the finance worker.
5. Pre-allocation payable corrections append vendor_payable_adjustments, linked
   to a matching posted journal. Amount history is not overwritten.
6. `assemble_payout` checks FINANCE_WRITE, verified KYC and beneficiary account,
   locks the vendor and eligible payables, applies corrections and uniquely
   allocates each entitlement to one batch. Queueing checks available posted
   liability after other in-flight payouts. Net amounts cannot go negative.
7. The worker claims batches with row locks/SKIP LOCKED, changes QUEUED -> PROCESSING,
   commits, then calls the payout provider outside the DB transaction. Use the same
   batch UUID as provider idempotency key. FAILED retries retain the allocation;
   a provider timeout remains unresolved until status reconciliation. Never create
   a second payout to retry a potentially paid transfer.
8. Provider-confirmed success requires a posted, linked settlement journal debiting
   vendor liability for the batch total, provider reference and completion time.
   Record an actual success even if eligibility changed after funds left: compliance
   is checked before dispatch, not used to erase already completed money movement.
9. For post-payout refunds/chargebacks, post vendor recovery/debt journals and apply
   account locks before planning later payouts. Hold affected payouts during
   unresolved refunds or reconciliation. SQL alone cannot prove an external bank
   transfer or verify a webhook signature.

Refund rows lock the captured payment and cap pending/successful refund amounts.
The finance worker also needs explicit provider-state transitions, authoritative
captured/refunded status updates, provider reconciliation and refund/return audit
entries; the schema is not a finished payment-provider adapter.

## Other backend contracts

- OTP request limit: Redis atomic limit, max 3 sends per phone per 10 minutes,
  plus IP/device abuse controls. Store challenge HMAC only. Recovery/bank changes
  revoke sessions and increment users.session_version; MFA for privileged access.
- Vendor self-service: whitelist fields, scope store/offer/variant UUIDs to vendor
  membership and role, increment variant version; validate files and use private
  object storage keys plus signed URLs for sensitive documents.
- Dispatch: BullMQ/outbox consumer with merchant staff/local pool/approved courier
  options, acceptance deadlines and idempotent assignments. Driver locations go in
  Redis `driver_loc:{driver_id}`; cache route/geocode lookups, track only during jobs.
- Search: category_type context filter and indexed tsvector are available. Fuzzy
  ranking, synonym catalogues, transliteration and Elasticsearch/Algolia adapters
  are subsequent application work.
- Messages: WhatsApp/FCM for routine updates; consent/template rules apply. SMS for
  critical fallback/OTP when required. Costs, number masking and provider APIs are
  deployment configuration, not hardcoded per-order schema charges.
- Service slots are non-overlapping per provider and one live booking per slot.
  REQUESTED -> CONFIRMED requires an accepted price; fixed prices are server-derived.
  Confirmed quote/booking identity cannot be rewritten. Reschedule via cancel/new
  slot flow with payment reconciliation and customer consent.
- Outbox/webhook/message dedupe keys prevent duplicate business effects. Publish
  after commit, acknowledge only after durable processing and retry safely.
- Audit metadata is redacted; add every financial/RBAC/KYC mutation's audit event
  in the same transaction. Retention, backups/PITR and access logs are deployment work.

## Verification and release boundary

Run functional checks:

```sh
npm install
npm test
```

The supplied output records **51 passing checks** on PGlite 0.5.8, an embedded
PostgreSQL engine, including migration execution, UUID validation, FK index
coverage, cart restrictions, MOV/COD risk, stale quotes, atomic stock handling,
OTP delivery gate, RLS/IDOR, service-slot collisions, balanced immutable journals,
KYC payout gate, payout/entitlement adjustments and settlement reconciliation.

PGlite tests run on a single connection. Real PostgreSQL multi-session contention,
connection-pool isolation, load, provider integration and disaster recovery tests
have NOT been run. Before production deployment, run these checks against your
chosen PostgreSQL version and add two-connection races for checkout/expiry,
slot overlap, quote changes, refunds and payout assembly, plus provider sandbox
reconciliation. Retry deadlocks/serialization errors with bounded backoff and the
same idempotency key. Validate existing DB role memberships and isolate privileged
worker credentials. Do not claim that the unspecified faults 11-20 and 26-30 have
been fully implemented by this database task.

Primary references:
- https://www.postgresql.org/docs/current/functions-uuid.html
- https://www.postgresql.org/docs/current/ddl-rowsecurity.html
- https://www.postgresql.org/docs/current/explicit-locking.html
