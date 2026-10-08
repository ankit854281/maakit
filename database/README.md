# PHP/PDO + MySQL API

PHP is now the active backend. There is no npm, framework, Redis, Node server or build step required for these endpoints. Require **64-bit PHP 8.2+**, PDO MySQL, OpenSSL, and InnoDB with **MySQL 8.0.16+ or MariaDB 10.6+**. Older MySQL versions do not enforce CHECK constraints and are unsupported.

## Deploy without destroying live data

`database/schema.sql` and `sql/024-php-api.sql` contain identical, repeatable additive DDL. The existing backed-up `update.php` deployment applies the numbered migration; the standalone file is for a fresh database. cPanel deployment copies the PHP module directories. Never run DDL inside a checkout transaction: MySQL implicitly commits DDL. Never replace private root `config.php` or uploads.

All 43 new tables use `mk_`. Existing integer-ID PHP users, catalogue, carts and orders remain intact. **This commit provides the new secured API; it does not silently migrate legacy identities/catalogue or replace legacy checkout.** Before directing the live UI to this API, import real user/vendor/offer/address data with explicit old-ID → UUID mappings, connect the existing verified OTP login to server-created sessions, and complete an end-to-end staging order. Never turn a client-supplied legacy ID into a session.

Root Node package files and its CI workflow have been removed. Historical `node-api/` and PostgreSQL documentation remain inactive references. PHP is authoritative.

## Database configuration

`config/db.php` reuses existing private DB_HOST, DB_NAME, DB_USER and DB_PASS, without changing the root configuration. Optional environment overrides:

```
MAAKIT_DB_HOST=localhost
MAAKIT_DB_PORT=3306
MAAKIT_DB_NAME=<database>
MAAKIT_DB_USER=<restricted-runtime-user>
MAAKIT_DB_PASS=<private-password>
MAAKIT_DB_SSL_CA=<trusted-CA-file-for-remote-DB>
```

Use a separate migration account for DDL. Runtime grants should cover only needed API tables; no DROP/ALTER/GRANT and no customer-accessible tariff, identity, tax or permission writers. Remote DB connections require verified TLS; localhost uses the hosting database. PDO uses native prepared statements, strict SQL mode, UTC and bounded lock waits. Exceptions do not expose connection details.

## JWT and authorization

Configure `MAAKIT_JWT_KEY_FILE`, `MAAKIT_JWT_ISSUER`, `MAAKIT_JWT_AUDIENCE` via environment or private root config constants. The key file must be **outside public_html/the repository**. Its JSON maps approved key IDs to RSA public PEM strings. RSA keys must be at least 2048 bits. Private signing keys must never be in the repository or web root.

Access tokens are RS256, typ JWT, an approved kid, exact issuer/audience, RFC v4 sub/sid/jti, integer iat/exp/session_version, and at most 15 minutes validity (5-second clock tolerance). Signature results must equal 1. No none/HS256/remote-key discovery. Refresh/OTP issuance is deliberately not exposed by these modules; integrate only with verified login, rate limits, hashed refresh tokens and revocation/rotation.

Every API call checks live active users and nonrevoked, unexpired sessions. Roles and permissions come from DB, never JWT role claims. Suspending a user, revoking a session or incrementing session_version invalidates access. Role/permission writers must also lock the affected user row so changes serialize with checkout.

Roles: ADMIN, VENDOR, RIDER, CUSTOMER, B2B_BUYER. There is no implicit admin grant. `require_permission()` requires an explicit grant. `require_ownership()` binds customer cart/address/order/RFQ/booking to their user, vendor orders/stores to owner or active members, and rider shipments to the assigned active rider. Support order access additionally requires ADMIN + ORDER_READ_ALL. UUID v4 reduces enumeration; **ownership checks actually prevent IDOR**.

## API contract

Framework-free entry point: `/api/v1/index.php?action=...`. Authorization: `Bearer <access-token>`. POST JSON, at most 16 KiB. JSON responses expose integer paise as strings and INR currency. No public CORS wildcard or cookie session authentication.

1. **POST action=quote**: `{ "cart_id": "<uuid-v4>", "address_id": "<uuid-v4>" }`. Returns quote_id and server-calculated subtotal/delivery/service fee/total, valid five minutes. An authorized operator must configure a real delivery tariff and service zones first. No tariff means an explicit quote-required error; no invented courier price.
2. **POST action=checkout**, with `Idempotency-Key: <uuid-v4>`: `{ "cart_id": "<uuid-v4>", "address_id": "<uuid-v4>", "quote_id": "<uuid-v4>", "payment_method": "COD" }`. Alternatively DIRECT_TO_VENDOR. 201 new order; 200 same-key replay. A key reused for another request returns 409. Client prices, quantities, totals, fees, store and stock values never determine checkout; items/quantities come from the owned database cart.
3. **GET action=order&uuid=<uuid-v4>**: reads only the authenticated customer's order. Vendor/rider/support scopes are reusable middleware, not publicly selectable query parameters.

The transaction rechecks session and roles, locks cart/address/store/catalogue/policies, verifies a single store and one fulfillment mode, excludes B2B offers, checks configured minimum value (default ₹150), validates PIN/radius using Haversine, enforces COD risk policy, and revalidates the delivery quote against current prices/address/tax/commission snapshots. Registered tax profiles require verified GSTIN. Unknown final prices require vendor confirmation; they never become ₹0.

Tracked stock uses SELECT ... FOR UPDATE plus conditional `reserved += quantity` only when `on_hand - reserved >= quantity`. Available stock is **on_hand minus reserved**; do not decrement on_hand twice. Order, items, reservation/movement, outbox, quote consumption and cart transition commit together. Stock shortage rolls everything back. ON_REQUEST offers need no stock row and create an AWAITING_VENDOR request; they do not claim guaranteed availability. All orders start awaiting real vendor confirmation.

Known deadlocks/explicitly rolled-back lock timeouts retry at most twice. Uncertain network/commit failures are not retried internally; clients can retry the same idempotency key. No courier, WhatsApp, payment or other network side effect occurs inside the transaction.

## Boundaries that must be completed before switching live checkout

- Connect verified login, cart writes, address capture, catalogue import and vendor UI to this UUID model. Every cart mutation must lock its cart, increment version and apply the same store/mode/B2B rules. All identity/admin writers must enforce RBAC.
- Implement vendor confirmation/cancellation and a shared-hosting cron worker to consume/release reservations exactly once. Reservation expires_at is scheduling metadata; **expiry alone does not automatically free inventory**. Do not advertise automatic expiry or fulfillment until that worker is deployed. The outbox is committed but not sent by these modules.
- Delivery OTP storage is HMAC-only with attempt/expiry/verification fields. No endpoint in this commit permits DELIVERED. A future delivery controller must verify OTP and assignment transactionally before allowing that transition.
- Home-services/B2B/dispatch entities are schema foundations; no booking, RFQ, GPS or carrier API integration is claimed complete here. Courier tariffs require actual carrier agreements.
- Ledger/payout entities store integer paise, approved effective tax rules and tax snapshots. **No payout endpoint or automatic settlement is enabled.** A future ledger writer must lock journals/accounts with FOR UPDATE, balance journals, make POSTED entries immutable and verify beneficiary ownership/KYC. Never hard-code TCS/TDS percentages or apply deductions without approved applicability rules.
- Goods remain COD/direct UPI **to the shop**. This API does not collect customer money for subsequent platform payouts. Commission is a separate disclosed vendor charge, not a hidden markup.

## Validation

GitHub `PHP API security` runs PHP lint, imports the schema twice on MySQL 8.0 and MariaDB 10.11, then exercises signed/forged/expired JWTs, DB revocation and permission checks, foreign-user IDOR, server prices, quote expiry, MOV/geofence/COD controls, B2B/mixed-store/mode rejection, ON_REQUEST, rollback/idempotency and two real competing PHP/PDO processes for the last stock unit. Existing जाँच still runs legacy site and backup/restore checks.

Locally: set the test DB environment variables, import schema into an isolated empty test DB twice, then `php tests/php-api/security.php`. With no DB env set, only JWT/unit tests run. Never run fixtures against the live database.
