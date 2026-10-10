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
