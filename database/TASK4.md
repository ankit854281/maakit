# Existing PHP sessions, dispatch and frontend

## What is connected

- `/public/index.php` is a Hindi-first, server-rendered, three-segment hub. Root home links all three segments; no build or JavaScript is needed to switch categories/search.
- Shopping reads the existing real catalogue and shop offers and links the existing pack/request flow. It does not replace legacy shopping with an unpopulated UUID catalogue. Home-service cards preselect their actual work on the existing booking page; preferred times remain subject to confirmation. B2B reads only active explicit wholesale offers and sends authenticated, idempotent RFQs, not carts. Supplier RFQs appear in the vendor dashboard.
- `/public/dispatch.php` is linked from the hub for staff and from the shop panel (see account navigation). It shows scoped pending orders, vendor confirmation/packing actions, rider offers and parcel measurements. A rider goes available for five minutes by an explicit action; only human-verified active riders with fresh duty and authorized store/mode zones are eligible. No GPS or guaranteed delivery-time claim.
- Existing PHP identities map lazily into UUID identities. Neither anonymous order phone numbers, body `legacy_id`, requested roles nor JWT role claims can create privileged identities.

## Deployment and private configuration

The cPanel copy task includes config, middleware, controllers, API, public and CLI worker directories. The backed-up updater applies `025-session-dispatch.sql`. A fresh install must import `database/schema.sql` **then** `database/025-session-dispatch.sql`; both have exact numbered updater counterparts and are repeatable. Original migration 024 is unchanged.

`config/config.php` holds versioned defaults, not credentials. `.env.example` explains environment variables; it is a template, not a dotenv loader. Existing private root DB constants continue working. No real DB password, shared JWT secret, signing key or carrier token is committed. Do not overwrite root config.php/uploads.

RS256 uses a secret **RSA private signing key**, not a weak shared-secret HS256 downgrade. Default key storage is a `maakit-private` folder beside the repository/public_html. On first use, a lock and atomic rename create a 2048-bit RSA key, restrictive permissions, outside web root. Subsequent deploys never replace it. If the hosting account cannot write its parent directory, create a private folder outside public_html and configure `MAAKIT_PRIVATE_DIR`. Back up this private folder separately. Losing the key invalidates existing access tokens. Do not delete it on deployment.

You can instead set `MAAKIT_JWT_KEY_FILE` (JSON approved kid → public PEM), `MAAKIT_JWT_PRIVATE_KEY_FILE` (matching private PEM), issuer, audience and kid in private hosting settings. Signing verifies that the private/public keys match. Public/key files under the web root are rejected. Rotation should retain the previous public kid until issued 15-minute tokens expire; no automatic rotation or key overwriting.

## Session exchange and revocation

`POST /api/v1/session.php` is same-origin, with `X-CSRF-Token` from the current page and form `context=customer|staff|shop`. It uses only the existing trusted server PHP session (customer/staff) or DB-validated 90-day shop cookie. Guests get 401. CSRF errors get 403. Exchanges are limited to six per minute per logged-in browser session.

Sources remain separate even when a phone is shared. Customers get CUSTOMER; approved shops get VENDOR. DB-verified staff admin gets ADMIN and an explicit DISPATCH_WRITE grant only. Delivery staff get RIDER; BPO/DESIGNER remain BPO/DESIGNER, never administrators. Existing phone collisions are not automatic account linking. A missing/conflicting phone stays NULL; contact data remains in the corresponding legacy source. Changed role mappings require review rather than automatic promotion. No guest order/history is claimed by phone.

The browser keeps its access token in memory, refreshes through the same CSRF-protected login and never writes it to localStorage. UUID session rows are bound to the source identity and credential fingerprint. API calls recheck active legacy accounts, actual role and password/code fingerprint; shop cookie deletion/expiry invalidates the linked token. The legacy shop clock uses its original global MySQL time zone, not a changed API session time zone.

Customer, shop and team login/logout hooks revoke linked UUID sessions before rotating the PHP session. Account password changes revoke tokens and permit the actively authenticated browser to renew. A stale already-bridged browser must sign in again after its credential fingerprint changes. Pre-existing sessions at rollout are trusted under the site's existing server-side authentication; there is no retroactive proof of passwords for sessions created before this bridge. The first exchange records the credential fingerprint. Disabled accounts fail immediately. Private API setup failure does not make anonymous users logged in.

## Dispatch APIs and cron

Bearer-authenticated API actions (POST JSON unless noted):

- `dispatch_start` `{order_id}`: ADMIN with DISPATCH_WRITE, or owning vendor with DISPATCH_OWN; order must be READY and store opted in. Existing jobs are returned idempotently.
- `order_decision` `{order_id,operation:"confirm"|"ready"|"cancel"}`: same authorization, explicit allowed transitions. Vendor confirmation means agreeing to quoted availability/price; ready means actually packed. No price override. Orders already assigned to a provider need a separate cancellation review.
- `rider_duty` `{}`: active human-verified RIDER only, five-minute duty lease.
- `dispatch_accept` `{attempt_id}`: actual assigned rider only, unexpired offer, active zone/duty, one shipment and one rider slot. Repeated successful acceptance is idempotent.
- `parcel` `{order_id,weight_g,length_mm,width_mm,height_mm}`: owning vendor/authorized admin, real measurements only, no changing a parcel during an uncertain/active carrier booking.
- GET `dispatch_view`: admin queue, vendor's own orders/RFQs or rider's own offers/assigned jobs. No client-selected role bypass.
- POST `rfq` `{offer_id,quantity,message}`, UUID Idempotency-Key: CUSTOMER/B2B_BUYER, active wholesale offer only. RFQs are not purchases.

Enable only genuine ready stores using `mk_dispatch_config.enabled`; verify rider KYC/vehicle documents manually, create actual rider profiles linked to UUID user, rider zones for approved store/mode and use the dashboard duty action. A legacy rider role alone does **not** certify vehicle/KYC or invent a rider fleet.

Set a cPanel **once-per-minute cron** for the installed PHP CLI and `/home/sbs81w9g9z45/public_html/bin/dispatch-worker.php`. Confirm the PHP CLI path in cPanel; for example `/usr/local/bin/php`. The worker expires unconfirmed stock holds, enqueues opted-in READY orders and handles bounded work. No Node daemon or Redis is required. It does not run automatically merely because GitHub received a commit. HTTP access to the worker is denied.

Four levels: merchant's verified available rider → approved local pool → configured 3PL → configured India Post bridge. Rider offers time out after 60 seconds; unique rider slots prevent double allocation across workers/orders. A missing rider advances immediately. Carrier outbound calls happen after a durable request commits, outside row locks.

Only a confirmed carrier reference can create a shipment. The original fulfillment mode and customer total are preserved. A local 3PL may be used only under an explicit configured local-delivery contract. India Post fallback is courier-only; without a local provider, a local order enters review for customer courier consent, never silently becomes three-day shipping. The clerk must obtain agreement and create a properly quoted courier order. Missing tariffs/credentials are not fabricated.

## Real provider integration boundary

`config/carriers.example.json` is disabled and contains deliberately nonresolving sample bridge addresses. Put real configuration outside public_html and point `MAAKIT_CARRIER_CONFIG` at it. This is an **adapter contract**, not a claimed working public India Post endpoint. Production requires the contracted provider's credentials, documented API adapter and verified test booking. India Post's official [parcel sales manual](https://www.indiapost.gov.in/VAS/Pages/Tenders/Parcel_Sales_Manual_27.09.2021.pdf) describes bulk-customer API integration; use the contractual API specification supplied to the business.

Configured bridge: HTTPS only, approved DNS host, public IPv4 pinned for the request, no redirects, verified TLS, 3-second connect/10-second total timeout, 64-KiB response cap. Idempotent booking and lookup must both be supported. BOOK and LOOKUP carry the same durable `request_id`/Idempotency-Key, order, fulfillment, actual pickup/store, destination, product snapshots, measured parcel and maximum agreed delivery charge. No guessed dimensions/weight. The bridge must enforce the charge cap **before** booking.

Successful response: `{outcome:"BOOKED",request_id:"<same UUID>",tracking_reference:"<real reference>",fulfillment_type:"<same mode>",charge_minor:"<integer paise within agreed cap>"}`. `DECLINED` must mean definitively **no shipment was created**. An ambiguous timeout/5xx/invalid or mismatched booking response becomes UNKNOWN; the next worker LOOKUPs the same request. It cannot proceed to another carrier and create duplicate labels. A crashed worker's lease also goes through lookup. Disabled/unsupported lookup cannot prove absence and stays in reconciliation. Operator cancellation reconciliation remains necessary for already assigned carriers.

Rider assignment is not pickup/delivery. This task does not expose a DELIVERED endpoint or invent OTP verification, background GPS, courier webhook events or delivery notifications. Outbox entries are durable for a separately configured notification sender. Goods remain COD/direct UPI to the shop; no platform customer-fund collection/payout is introduced.

## Verification

PHP API CI: repeat base + supplement twice on MySQL 8.0/MariaDB 10.11, full existing JWT/IDOR/server-price/stock-concurrency tests, legacy source mappings and revocation, timeout fallback, cross-rider rejection, one-rider allocation, confirmed 3PL/India Post contracts, ambiguous-provider lookup, local-mode preservation, exact-once inventory expiry and separate RFQs. Full-site जाँच additionally tests the actual PHP HTTP login → CSRF token exchange → API → logout, service prefill and real mobile browser layout/search/sliding at 360/390/1080px, plus existing legacy pages and backup/restore.
