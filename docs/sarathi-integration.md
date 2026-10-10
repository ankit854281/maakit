# Sarathi Delivery integration — 10 October 2026

The customer remains on maakit.in. Sarathi Delivery is the partner app. Parcel Delivery and passenger Rides / Carpool are separate services and roles. The historical PHP API RIDER role currently means Delivery Partner; do not treat it as passenger approval.

## Implemented code

- Existing PHP delivery dispatch, role / ownership checks, availability, offer expiry, acceptance and delivery progress are reused.
- `sql/027-sarathi-live.sql`: latest partner GPS location, per-order customer emergency contacts and delivery-fee payment records. Repeatable, additive migration.
- `api/v1/sarathi.php`: dashboard (delivery role), location (consent / bounded coordinates / throttle / duty), contacts and order_tools (customer ownership), tracking (own active order only, stale GPS suppressed), fee_create and fee_verify (customer only).
- `api/v1/payment-webhook.php`: raw-body HMAC validation and idempotent captured fee confirmation.
- `public/order-tools.php`: signed-in customer API order list, contact consent, fresh tracking link and optional fee checkout.
- Sarathi partner Site: protected adapter to existing PHP login and JWT bridge; encrypted HttpOnly partner session, no password persistence. Delivery API polling, server-expiry countdown, availability heartbeat, consent-based foreground GPS, and customer emergency-contact `tel:` links.

## Deployment / configuration checklist

1. Deploy the changed PHP files through the existing backup/update process. Run `sql/027-sarathi-live.sql` after the earlier API migrations. Do not edit private config.php or overwrite uploads.
2. Confirm the existing cPanel dispatch cron is active, the shop has READY API orders and enabled dispatch, and the Delivery Partner is verified with the correct zones.
3. The partner app uses an existing Maakit approved team username/mobile and password. Customer login is not added to that app.
4. GPS runs only after partner consent and device permission while the browser app is open. Stop sharing removes the stored latest location. No background Android tracking is claimed.
5. Customer opens `/public/order-tools.php`, selects their API order and adds up to three authorised family contacts. Only the assigned partner on an active order receives them. SOS opens the phone dialer; it does not silently place a call or send an SMS.
6. Before enabling online fees, configure private runtime values `MAAKIT_RAZORPAY_KEY_ID`, `MAAKIT_RAZORPAY_KEY_SECRET`, `MAAKIT_RAZORPAY_WEBHOOK_SECRET`. Start with Razorpay test keys. Use webhook URL `https://maakit.in/api/v1/payment-webhook.php`, event `payment.captured`.
7. `MAAKIT_DELIVERY_FEE_ONLINE=1` is required in addition to keys. Keep it disabled until staff / cash collection reconcile captured fees so the customer is not charged the same fee twice. Only delivery_minor is collected here; goods subtotal and vendor funds never enter this gateway.
8. An ambiguous provider-order creation becomes RECONCILE. Do not retry blindly; match the stored receipt UUID in the gateway dashboard and reconcile the exact request. Refund handling / payout automation are not included or claimed.

## Verification / remaining work

Automated tests cover privacy, ownership, valid / invalid GPS, throttling, active-only tracking, family-contact exposure, fee tampering, order replay, signature verification, captured-payment matching and ambiguous payment creation. CI tests MySQL and MariaDB. Real rider login, device GPS, provider test transaction, webhook delivery and cash reconciliation need operator credentials / deployed environment.

Passenger ride allocation, carpool route matching, passenger SOS contacts, production multi-stop OTP / proof storage, guardian share links, background tracking and automatic emergency alerts remain separate unfinished integrations. The UI retains their clearly labelled demo flows. API dispatch cron currently runs every minute; this is not sub-second push dispatch.

## Latest owner request recorded

“Integrate live dispatch, GPS tracking and payment processing. Make SOS call the customer's emergency/family contacts. Keep improving the layout.”

## Company deliveries and completed delivery verification — 10 October 2026

The owner subsequently authorised other companies to use Sarathi and requested pending work be completed. Added `sql/028-sarathi-companies.sql` and `sql/029-order-delivery-proof.sql`, both repeatable. Deploy these after 027. No live company or rider was fabricated or approved during development.

- `/public/company-delivery.php`: existing Maakit customer login owns one company account. Application starts PENDING. `/admin/company-delivery.php` requires a verified admin and DISPATCH_WRITE; admin approves, selects an existing active service shop, configures a radius and a flat base + per-drop tariff, and can authorise an existing verified company delivery partner. This is a service-area tariff, not Google road-distance fare. Company owner may request SHARED pool or OWN approved fleet. New area/staff approval never happens automatically.
- Company parcels use separate delivery/stop tables, not dummy shopping carts. Company ownership, scoped hashed API keys, unique company reference + payload fingerprint, radius checks, active company/shop and customer contact consent are enforced. Price fields from callers are ignored. Company list/book/quote/cancel/proof endpoints are isolated; API keys cannot administer companies or control riders. Keys expire in 90 days; creating a replacement revokes older keys.
- Existing `bin/dispatch-worker.php` now queues company offers too. A common locked rider row plus checks against both work queues prevents concurrent Maakit/company jobs. An offer lasts 60 seconds. Company-owned riders are excluded from the shared pool. No idle rider means the request waits; no invented provider booking or dispatch notification.
- Every company delivery has six-digit pickup and per-drop OTPs. Five wrong attempts lock that stage, attempts persist on rejected requests, codes expire in 24 hours, stops must complete in order, each drop requires JPEG/PNG proof under 500 KB, replay is idempotent. Proof bytes stay in the database behind ownership checks; they are not public uploads. Company owner receives codes once and forwards them to sender/individual recipients. No SMS provider is claimed. Before completion the owner can rotate codes and private tracking links; previous codes/links become invalid.
- `/public/company-track.php` uses a 256-bit secret in the URL fragment, removes it from browser history, and fetches an expiry-limited state/location view. Server stores only its hash. Link expires in 48 hours, exposes no recipient/family contacts, suppresses GPS older than 60 seconds and shows none after completion. Fresh GPS coordinates are displayed inside the app; no map-provider request sends them to another service.
- Customer `/public/order-tools.php` can opt into pickup/drop OTP plus photo proof for an existing Maakit API order before pickup. Enforcement lives inside the original `rider_progress` function so calling the legacy progress route cannot bypass an enabled safety row. Older orders without safety enabled retain their workflow. User can refresh codes before pickup; they must keep the delivery code and give pickup code to the shop.
- Partner app accepts company offers, shows the assigned pickup and ordered drop addresses, captures real codes/proof, includes company-family SOS, shares permitted foreground GPS and displays a local coordinate trail after device GPS is available. Customers remain on Maakit.
- Completed company fee totals are an activity ledger, not proof of payment, settlement, partner earnings or automated payout. Goods payments stay with the company. Company fee collection/invoicing and any partner wallet/payout need an agreed operating/payment setup and are not silently enabled.

Still requires live hosting access / deploy, approved real shops and zones, actual rider login and phone testing, real payment test keys + webhook/cash reconciliation, and carrier credentials / booking tests. Passenger rides/carpool remain a separate unfinished service. Native background tracking, passenger matching/fare, automated emergency SMS/push, payout and Play Store publishing are not represented as complete by this web delivery integration.

Automatic approval review rejected sending GPS coordinates to external map services without separate destination approval. External embeds/maps links were removed; tracking stays in the app/backend. A street-map provider integration requires separate approval/configuration.

Both allocation queues prefer available, zone-authorised riders with a fresh GPS update and rank local coordinate proximity to the service shop; absence of GPS falls back to stable zone order. This uses local coordinates, not an external routing service or road distance. Accepted work remains visible to the assigned rider even if the company is subsequently suspended, so it can be finished safely.
