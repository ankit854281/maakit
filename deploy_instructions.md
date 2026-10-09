# Maakit: PHP + MySQL deployment on GoDaddy/cPanel

This guide deploys the existing PHP application and its additive UUID API. A GitHub push updates source code; it does not prove the hosting files, migrations, cron or courier accounts are active. During the 9 October 2026 check, `https://maakit.in/` returned 200, but `/public/index.php` returned 404 for all three segments. Complete the hosting deployment below before expecting the switcher on the live site.

## 1. Check hosting and take a backup

1. Open GoDaddy hosting → cPanel → File Manager. Confirm the document root for `maakit.in`. The repository's `.cpanel.yml` currently targets `/home/sbs81w9g9z45/public_html/`; use that path only if it matches your hosting account.
2. Confirm PHP **8.2 or newer, 64-bit**, with PDO MySQL, OpenSSL, cURL, mbstring and the existing site's required extensions. The cron CLI must use the same supported PHP version. Check database version in phpMyAdmin with `SELECT VERSION();`: MySQL **8.0.16+** or MariaDB **10.6+**, using InnoDB.
3. Make a full backup of files and database. Keep the current private root `config.php`, `uploads/`, and any `maakit-private` folder. Do not replace them with example files. Test restoring the backup in a separate database before changing production.
4. Use the existing private owner updater, `https://maakit.in/update.php?key=<YOUR_EXISTING_PRIVATE_UPDATE_KEY>`, to deploy latest `main`. Use your real private URL; do not publish the key. The updater backs up and applies numbered migrations. If using cPanel Git Version Control instead, deploy the current `main` checkout with `.cpanel.yml`, then apply the database steps below: copying files alone does not apply SQL.
5. Confirm these installed paths exist: `public/index.php`, `public/dispatch.php`, `api/v1/session.php`, `api/v1/index.php`, `config/config.php`, `controllers/dispatch.php`, `bin/dispatch-worker.php`, `assets/hub.css`, `assets/maakit-api.js`, `assets/hub.js`, `assets/dispatch-dashboard.js`, `sql/024-php-api.sql` and `sql/025-session-dispatch.sql`.

## 2. Import schema.sql through phpMyAdmin

### Existing Maakit site (recommended path)

1. In cPanel, open **phpMyAdmin**. Select the exact database used by private root `config.php` (`DB_NAME`). Do not create an unrelated database for only the new API: the hub, session bridge and legacy records need the same database.
2. Choose **Export → Custom → SQL**, including structure and data, and download the backup. Never drop existing tables or import into production without the backup.
3. If the owner updater has already completed migrations 024 and 025, do not import them again just to deploy the frontend. They are repeatable, but their successful updater result is sufficient.
4. If performing the manual import: download the current `database/schema.sql` from GitHub to your computer. Select **Import → Choose file → schema.sql**, choose SQL/UTF-8, and press **Go**. Wait for the successful import message; stop and resolve any error before continuing.
5. Repeat **Import** for `database/025-session-dispatch.sql`. It supplements the base schema with legacy identity mappings, session origins, dispatch jobs, rider duty/zones, carrier requests and RFQ idempotency. `schema.sql` alone is insufficient for Task 4.
6. In the SQL tab, run:

   ```sql
   SELECT DATABASE(), VERSION();
   SHOW TABLES LIKE 'mk_%';
   SELECT COUNT(*) AS roles FROM mk_roles;
   SELECT COUNT(*) AS pending_jobs FROM mk_dispatch_jobs;
   SELECT COUNT(*) AS legacy_links FROM mk_legacy_identities;
   ```

   Zero jobs/identity links on a new setup is normal. Identities appear after authenticated session exchange; real opted-in READY orders generate dispatch jobs.
7. Preserve old tables such as `customers`, `users`, `businesses`, the catalogue and existing orders. The new `mk_` tables are additive. Do not rename integer IDs into UUIDs or merge accounts by phone.

### Fresh installation

`database/schema.sql` creates the new UUID API tables, not the entire legacy Maakit website. For a completely empty database, provision the complete site's numbered `sql/` migrations in filename order using the existing installer/updater, which includes 024 and 025. If importing only the standalone API into a staging database, import `database/schema.sql` then `database/025-session-dispatch.sql`; the full frontend and legacy session bridge still require the original site tables. Neither procedure creates real shop products, riders or courier credentials.

## 3. Database and JWT settings

1. The live hub follows `public/index.php → inc/fn.php → inc/db.php → private root config.php` and uses PDO prepared statements. Keep the existing `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` correct. Do not edit them in this public guide or commit their values.
2. The API and CLI worker use `config/db.php`, which reads the same root constants unless `MAAKIT_DB_*` environment overrides are configured. Do not point those overrides at another database while the hub uses the legacy database. In cPanel, the database user needs the appropriate runtime grants; migrations additionally need DDL privileges.
3. `config/config.php` reads site/JWT/private-path settings from environment variables or private PHP constants. `.env.example` is documentation, not an automatically loaded dotenv file.
4. Create `/home/<CPANEL_USER>/maakit-private` **outside public_html**, with directory permission **0700** (the account owner can access it). Default storage is beside the application root; if using another path, set `MAAKIT_PRIVATE_DIR` in server environment settings or your existing private hosting configuration. Do not insert secrets into tracked `config/config.php`.
5. The first authenticated token exchange creates a persistent RS256 RSA signing key in this private folder. Back it up separately; never regenerate/delete it during deployment. No shared JWT secret needs to be pasted into GitHub. If the host cannot create the folder/key, check ownership/write permissions and OpenSSL rather than switching to a weak secret.

## 4. Configure 3PL / India Post through config/config.php

`config/config.php` now detects `<private_dir>/carriers.json` automatically when there is no explicit `MAAKIT_CARRIER_CONFIG`. This allows cPanel File Manager setup without editing tracked PHP on every deployment. An explicit environment/private-constant path overrides the default. The controller rejects credential files inside the application web root.

1. Obtain actual authorized provider credentials and the provider's API documentation. This code expects an approved **bridge/adapter** supporting the contract below; it is not a guessed native India Post API. A native provider API needs an adapter that translates its booking/status endpoints to this contract.
2. Copy `config/carriers.example.json` to `/home/<CPANEL_USER>/maakit-private/carriers.json` using File Manager. Set file permission **0600**. Keep the example's providers `enabled: false` while setting up.
3. Edit the private JSON with your real approved HTTPS bridge endpoint and token. `allowed_hosts` contains the exact hostname, without `https://` or a path. Example values below are placeholders, not working credentials:

   ```json
   {
     "THREE_PL": {
       "enabled": false,
       "endpoint": "https://your-approved-carrier-bridge.invalid/dispatch",
       "allowed_hosts": ["your-approved-carrier-bridge.invalid"],
       "token": "REPLACE_PRIVATELY_WITH_REAL_BRIDGE_TOKEN",
       "modes": ["COURIER"],
       "idempotency_supported": true,
       "lookup_supported": true
     },
     "INDIA_POST": {
       "enabled": false,
       "endpoint": "https://your-approved-postal-bridge.invalid/dispatch",
       "allowed_hosts": ["your-approved-postal-bridge.invalid"],
       "token": "REPLACE_PRIVATELY_WITH_REAL_BRIDGE_TOKEN",
       "modes": ["COURIER"],
       "idempotency_supported": true,
       "lookup_supported": true
     }
   }
   ```

4. Set capability flags to true only when the bridge actually implements them. Both idempotent booking and lookup are required. Enable `HYPERLOCAL` on 3PL only under a verified local-delivery contract; India Post remains courier-only. Never silently convert a local order into courier delivery or change the agreed charge.
5. Test in staging: BOOK and LOOKUP are POST JSON requests carrying `operation`, the same durable `request_id`, order/fulfillment details, actual parcel measurements and the agreed charge cap. Authentication is `Authorization: Bearer <token>` and `Idempotency-Key: <request_id>`.
6. A confirmed booking must return HTTP 200 with:

   ```json
   {
     "outcome": "BOOKED",
     "request_id": "<SAME_REQUEST_UUID>",
     "tracking_reference": "<REAL_PROVIDER_REFERENCE>",
     "fulfillment_type": "COURIER",
     "charge_minor": "<ACTUAL_INTEGER_PAISE_WITHIN_THE_CAP>"
   }
   ```

   `DECLINED` must guarantee no shipment exists. A timeout, malformed response or ambiguous result is `UNKNOWN`: the worker looks up the same request before proceeding, preventing duplicate bookings. The adapter must enforce the charge cap **before** it books, not after.
7. Test a real controlled booking, status lookup, duplicate request and timeout reconciliation with the contracted provider. Then set only that verified provider's `enabled` to true. No credentials/provider response means no genuine tracking or confirmed booking. Missing providers do not fabricate a delivery.

An optional private setting, if automatic discovery is unsuitable:

```php
// Existing private hosting configuration only; never commit a real token here.
define('MAAKIT_CARRIER_CONFIG', '/home/<CPANEL_USER>/maakit-private/carriers.json');
```

Ensure the same setting is available to both web PHP and the cron CLI. Automatic private-file discovery avoids web-only environment mismatches. Never send secret credentials to the browser or put the JSON inside public_html.

## 5. Add the cPanel Cron Job

The cron entry must execute **`bin/dispatch-worker.php`**. `controllers/dispatch.php` defines functions and does not start a dispatch cycle when run directly. The worker loads it, expires stock reservations and calls `dispatch_tick()`.

1. In cPanel → **Cron Jobs**, choose **Once Per Minute**. The schedule fields must be:

   ```text
   Minute: *   Hour: *   Day: *   Month: *   Weekday: *
   ```

2. Confirm your supported PHP CLI binary in cPanel/hosting support. Examples may be `/usr/local/bin/php` or a version-specific cPanel PHP binary; do not assume either works. Confirm with `<PHP_CLI_PATH> -v` in Terminal if available.
3. Create the private folder from step 3, then add this command, replacing both placeholders with actual paths (do not include the five schedule stars in the cPanel command box):

   ```sh
   <PHP_CLI_PATH> /home/<CPANEL_USER>/public_html/bin/dispatch-worker.php >> /home/<CPANEL_USER>/maakit-private/dispatch.log 2>&1
   ```

   For the repository's current deployment path, **if confirmed on your account**, the command is:

   ```sh
   /usr/local/bin/php /home/sbs81w9g9z45/public_html/bin/dispatch-worker.php >> /home/sbs81w9g9z45/maakit-private/dispatch.log 2>&1
   ```

4. Save **Add New Cron Job**. Check `dispatch.log` after one or two minutes. Successful runs emit JSON fields such as `expired_reservations`, `processed` and `deferred`. Zero processed jobs is normal when there are no ready orders. `deferred > 0` needs investigation in the private PHP error log; a generic failure must not expose database credentials.
5. Keep the log outside public_html and restrict its permission to **0600**. Rotate/clear old logs periodically using the host's supported log management. Do not add duplicate cron entries. Overlapping workers are guarded by database locks, rider uniqueness and durable provider leases.
6. A once-per-minute scheduler checks offers whose expiry is 60 seconds; it is not a precise sub-second timer. Host scheduling and backlog can add delay.

### Enable genuine dispatch data

Cron alone does not create a fleet or migrate legacy orders. The new engine operates on `mk_orders`; existing legacy orders retain their existing delivery workflow until deliberately mapped/migrated.

1. Map verified legacy identities to UUIDs by signing in and using the authenticated dashboard/session exchange. Link the correct real store/vendor ownership explicitly; identity mapping does not automatically import a legacy shop into `mk_stores`.
2. Use real active UUID vendors/stores, human-verified active rider profiles, authorized `mk_rider_zones`, and the rider dashboard's five-minute availability action. A delivery staff role alone is not approved rider KYC.
3. Enable only the actual store's `mk_dispatch_config` row. In phpMyAdmin, with the store's real UUID:

   ```sql
   INSERT INTO mk_dispatch_config (id, store_id, enabled)
   VALUES ('<GENERATED_UUID_V4>', '<REAL_STORE_UUID>', 1)
   ON DUPLICATE KEY UPDATE enabled = 1;
   ```

   Replace placeholders first. Do not use MySQL `UUID()` here: it does not generate the required UUID v4. No sample store/rider UUID represents a real partner.
4. The vendor confirms availability and quoted price, then marks the new order packed/READY. Courier orders need actual weight and dimensions through the dashboard. Verify fallback: merchant rider → authorized local pool → configured 3PL → configured India Post for courier orders. A local order without a local carrier enters manual consent review instead of silently becoming postal shipping.

## 6. Check the three-segment frontend on hosting

1. Open `https://maakit.in/` in a private browser window. The root homepage includes the three-segment navigation linking to the new hub; the old homepage and request flow are preserved.
2. Open each hub URL:

   - `https://maakit.in/public/index.php?segment=LOCAL_SHOPPING`
   - `https://maakit.in/public/index.php?segment=HOME_SERVICES`
   - `https://maakit.in/public/index.php?segment=B2B`

3. Expect Local Shopping, Home Services and B2B Wholesale (Hindi by default, English toggle), one active segment, horizontal category sliders where categories exist, and search restricted to the active section.
4. Local Shopping uses the existing database catalogue and actual shop offers/prices. Home Services uses the site's existing work definitions and booking flow; it does not invent professional availability. B2B reads active wholesale offers in `mk_products`, `mk_offers`, `mk_categories`, `mk_stores` and `mk_vendors` from the same PDO database. A genuinely empty wholesale catalogue shows an empty state, while an unavailable schema/database reports temporary unavailability (503).
5. Sign in through the real customer account page; submit a genuine wholesale RFQ if you have an active supplier offer. Confirm it appears only in that supplier's dashboard. Verify service selection pre-fills the work and shopping links still reach the existing request flow. Log out and confirm old tokens no longer work.
6. Check the layout at 360–390px: no page-wide horizontal overflow, usable tab buttons, horizontally scrolling category rail and clear product pack/price. Missing supplier products or shop photos must not be replaced with fake content.

## 7. Troubleshooting and rollback

| Symptom | Check / action |
|---|---|
| New hub returns 404 | Hosting still has old files or wrong document root. Deploy latest main and confirm `public/index.php` exists under the real site root. Do not move it into a nested `public/public` folder. |
| Root has no switcher but hub works | Deploy updated root `index.php`, `inc/segments.php`, `inc/head.php` and `assets/hub.css`; hard-refresh/clear the site's PWA cache. |
| Database 503 | Verify private DB constants, database name/user grants and PDO MySQL. Check private server logs; do not show SQL errors to customers. |
| Wholesale temporarily unavailable | Confirm 024/025 imported into the same database as the hub; inspect redacted logs and phpMyAdmin table presence. |
| Empty B2B section with HTTP 200 | No active wholesale offers, store/vendor disabled, or filters exclude them. Add real approved supplier data; schema import does not seed offers. |
| Session setup error | Check migration 025, OpenSSL, writable private key directory and matching web/CLI configuration. |
| Cron log absent | Check PHP binary, command paths, private folder permissions and cron entry. Ask hosting support whether once-per-minute cron is supported. |
| Riders never offered | Check verified profile, active UUID account/store, approved zone/mode, fresh duty lease, store opt-in, READY order and existing rider slot. |
| Carrier stays UNKNOWN | Reconcile the SAME request with the provider. Do not delete the request or retry through another provider until absence of a shipment is established. |

If deployment fails, disable the new cron and affected store dispatch opt-in, keep the existing legacy workflows available, and restore the backed-up files if needed. Never delete carrier request history during rollback; uncertain external bookings still require reconciliation. Keep private config, uploads and keys unchanged. Restore database backups only after accounting for any orders created after the backup; additive schema normally does not require destructive rollback.

Further implementation details: [database/TASK4.md](database/TASK4.md). GitHub CI checks PHP/MySQL/MariaDB security, migrations twice, actual login/logout, mobile layout and lossless backup restore. These checks do not configure your hosting cron or provider accounts.


## 8. Partner login, documents and approvals

1. Deploy the current main and run `sql/026-partner-onboarding.sql` after 024/025, in the SAME database used by legacy login and the API. The updater runs numbered `sql/` migrations; the older unnumbered `migrations/20261009_partner_applications.sql` alone is insufficient. 026 is safe to run twice and preserves earlier applications.
2. Keep HTTPS enabled. `public/.user.ini` sets 5 MB per file, 22 MB per request and four uploads. If cPanel overrides these, apply the same values in MultiPHP INI Editor. Enable PHP fileinfo, OpenSSL and PDO MySQL.
3. Ensure `MAAKIT_PRIVATE_DIR` (default sibling `maakit-private/`, outside the deployed web root) is writable by PHP. Documents use random keys in `partner-documents/` with directory permission 0700 and file permission 0600. Include this folder in PRIVATE backups with the JWT keys; it is intentionally not in Git or public uploads. Admin document downloads require a live active admin account.
4. New sellers choose a mobile + login code (letters normalized to uppercase); riders choose mobile-as-username + password. Accounts are inactive until approval. B2B requires a GST/PAN identifier and at least one corresponding document; riders need DL and RC documents. Admins must manually inspect documents and verify identity before approving; this code does not call a government verification API.
5. In `/public/admin/approvals.php`, download and inspect documents, then Approve or Reject. Approval atomically activates `users.role=vendor/rider`, creates the UUID identity and role membership plus the vendor/rider profile. Repeated decisions do not create duplicate profiles. Rejection leaves login disabled. Old applications have no credentials and must be resubmitted through the new form; never link an existing account just because the phone matches.
6. `/public/auth.php` posts credentials to the existing login pages, then exchanges the authenticated PHP session for a 15-minute JWT. The browser stores no tokens in localStorage: the server sets a Secure HttpOnly SameSite=Strict cookie, and the API helper uses an in-memory copy for Authorization headers, renewal and a single retry. API authentication requires the Bearer header; the cookie alone does not authorize requests. Logout revokes sessions and clears token cookies. Existing authenticated sessions can use the gateway without entering credentials again.
7. Approved partners reach `/public/partner-dashboard.php` immediately. Vendors and riders can open their scoped dispatch dashboard. Approval does not invent a store, offers, stock, service zones or rider availability; configure real stores/service zones separately using the existing setup. Existing legacy shop login and staff roles keep their original panels.
8. Before enabling on hosting, verify one real vendor and rider application, manual approval/rejection, documents only downloadable by admin, logout/revocation, and the new PWA cache version. A GitHub push does not deploy the hosting files or run migrations by itself.


## 9. Rider duty and delivery completion

Deploy `controllers/riderController.php` with the updated API router, dispatch controller and dashboard assets. No new schema migration is needed beyond 024–026. Existing hashed-password rider login remains at `/public/auth.php?role=rider` (mobile as username) and `/login.php?as=team`; it exchanges the approved PHP session for a scoped JWT.

In `/public/dispatch.php`, a verified rider can renew Online for five minutes or choose Offline. Offline prevents new offer acceptance and preserves any active delivery assignment. Assigned deliveries remain actionable while offline: first confirm pickup from the shop (`rider_progress`, `operation=pickup`), then confirm actual delivery to the customer (`operation=complete`). Completion requires pickup and the same assigned, active, verified rider. It sets delivery time, records the audit event, marks the job completed and frees the rider slot in one transaction. Retried/concurrent requests cannot duplicate stock consumption or completion. Tracked inventory belongs to the shop and is consumed at pickup; no customer payment is collected by Maakit. Carrier-managed shipments still require their provider workflow and cannot be completed through a rider account.
