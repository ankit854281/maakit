# Maakit Task 2 & 3: Node API modules

Owner-requested Node.js/PostgreSQL backend modules, committed directly to main.
The current cPanel website runs PHP/MySQL. Copying these JavaScript files to that
hosting does not start a Node server or migrate MySQL. Provision a Node 22+
process, PostgreSQL and a TLS reverse proxy before enabling this endpoint.

## Included

- `middleware/auth.js`: RS256 JWT verification with server-owned key IDs,
  issuer/audience/TTL/required claims, DB-backed session revocation, roles and
  explicit permissions. Token role/permission claims never grant privileges.
- `middleware/ownership.js`: parameterized allowlisted ownership queries for
  carts, addresses, orders, bookings, RFQs, vendor stores/variants and rider jobs.
  Unowned and missing records return the same 404. Admin has no automatic bypass.
- `controllers/checkoutController.js`: session/role recheck inside one database
  transaction, customer cart/address ownership, immutable server quote, server
  price calculation, single-store and fulfillment rules, atomic reservation,
  customer-scoped idempotency and bounded retries after known rollbacks.
- `node-api/server.js`: Express wiring for `POST /api/v1/checkout`, bounded JSON
  requests, safe error responses and environment validation.
- `docs/postgresql/task1`: the required SQL functions/schema and its 51-check
  development suite. The PostgreSQL design is outside live MySQL `sql/` migrations.

## Install, schema and runtime

```sh
npm ci --ignore-scripts
npm run test:schema
npm run test:api
# Separately apply docs/postgresql/task1/maakit_schema.sql to a NEW PostgreSQL DB.
npm run start:api
```

Never apply this SQL to the existing MySQL database. Read the schema README first.
Create a dedicated login role with membership in `maakit_service`; use no schema
ownership, SUPERUSER, CREATEROLE or BYPASSRLS for the API login. Keep DB/JWT keys in
hosting secrets, not source control. Run the login/OTP/session creation service
separately; these modules verify access tokens but do not issue them.

Environment:

| Name | Required meaning |
|---|---|
| MAAKIT_DATABASE_URL | Dedicated PostgreSQL connection URL, without SSL URL options |
| MAAKIT_PG_CA | Optional provider CA PEM; TLS certificate validation stays enabled |
| MAAKIT_PG_LOCAL_PLAINTEXT | `true` permits plaintext only on loopback for development |
| MAAKIT_JWT_ISSUER | Exact trusted token issuer |
| MAAKIT_JWT_AUDIENCE | Exact API audience |
| MAAKIT_JWT_PUBLIC_KEYS_JSON | JSON map from approved kid to RSA public PEM, minimum 2048 bits |
| MAAKIT_COLLECTION_MODE | DIRECT_TO_VENDOR (default) or explicitly configured PLATFORM |
| MAAKIT_API_PORT | Loopback API port, default 3000 |

Access tokens need string UUID-v4 `sub`, `sid` (sessions.id), `jti`, integer
`session_version`, `iat` and `exp`, approved issuer/audience and RS256 header with
approved kid. Maximum TTL/age is 15 minutes. Rotate keys via server configuration;
old keys remain only as long as issued tokens can be valid. Login/recovery/RBAC
changes must revoke sessions and/or increment users.session_version transactionally.
Redis phone/IP/device OTP limits belong to the separate token issuance endpoint.

## Checkout request

Authorization is a Bearer token, not a cookie. The Idempotency-Key header is a
client-generated UUID v4 reused verbatim for network retries of the same request.

```json
{
  "cart_id": "<own-cart-uuid-v4>",
  "address_id": "<own-address-uuid-v4>",
  "delivery_quote_id": "<server-generated-quote-uuid-v4>",
  "payment_method": "COD"
}
```

Routine checkout ignores client price/total/store/discount/quantity/recipient
fields. Prices and quantities come from the persisted cart; delivery amounts and
fingerprint come from a trusted quote service. A changed price/quote returns 409
and requires customer consent. Payment recipient comes from server configuration.
Default DIRECT_TO_VENDOR allows COD or DIRECT_TO_VENDOR and rejects platform
ONLINE requests, preserving the live site's direct-to-shop collection rule.
Enabling PLATFORM requires a separate approved payment-provider integration;
this endpoint only records an awaiting-vendor order and never charges a provider.

All money is returned as strings of INR paise, avoiding unsafe bigint conversion.
Order creation does not claim confirmed supplier availability, dispatch or payment.
Use the outbox and separately implemented vendor/dispatch/payment workers for
subsequent stages. B2B wholesale offers remain RFQ-only and cannot enter checkout.

## Security and operating boundary

The backend uses a trusted service role; clients receive no DB credentials or
arbitrary SQL. Route handlers use parameterized SQL and scoped IDs. Caller-supplied
customer IDs are not accepted. Ownership is rechecked under transaction locks,
even if a middleware check already passed. API and DB checks complement each other.

Single-client BEGIN/COMMIT covers order, stock and outbox. Only SQLSTATE 40001 and
40P01 retry after successful rollback, at most twice. A network failure during
COMMIT is ambiguous and never automatically replayed with a different key. Retry
with the SAME key or query order state. Configure proxy limits, approved same-origin
routing, TLS, monitoring and a process manager. Do not expose generic read endpoints
or private DB snapshots to riders/vendors. No middleware reads JWT key URLs from
an attacker header. Error responses/logs omit tokens, SQL and customer details.

Validation: 30 Node security/controller/route tests and the 51 schema checks use an
embedded PostgreSQL engine. Real multi-session contention, production PG pool
behavior and external provider integrations still need deployment testing.
GitHub's Node API workflow runs both suites on main pushes. Existing PHP tests
continue in the original workflow. No automatic PostgreSQL migration/deployment
is added to the cPanel updater.
