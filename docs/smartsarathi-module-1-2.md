# Smart Sarathi — Module 1+2: one account + Firebase phone OTP

This module changes Smart Sarathi only. Maps, Firebase live tracking, Razorpay and
shared trips are deliberately waiting for the owner's module-by-module confirmation.
A GitHub update does not configure Firebase or activate this module on hosting.

**Current deployment restriction:** work is published only on `module-1-2`.
Do not follow any production migration/activation steps below yet. Follow
[the backup-first staging guide](smartsarathi-staging-test.md) now; production
requires the owner's explicit confirmation after staging tests pass.

## Stack found in the repository

| Area | Existing implementation |
|---|---|
| Backend | Plain PHP, PDO prepared SQL, MySQL/MariaDB; no framework or Node server |
| Smart Sarathi | `smartsarathi/`, separate DB configured in `smartsarathi/inc/config.php` |
| UI | Server-rendered HTML, plain JS, `assets/sr2.css`, Hindi/English helpers |
| Login before this module | Mobile + office-issued login code; optional WhatsApp/Fast2SMS OTP |
| Jobs | Existing `riders`, `jobs`, `trips`, `khata`; rides and deliveries share the rider |
| Customer flow before this module | Maakit `ride.php` / `trip.php`, API bridge to Sarathi |
| Maps/tracking before this module | Leaflet/OpenStreetMap; GPS saved in hosting DB |
| PWA | Root Maakit manifest/service worker; no independent Sarathi manifest/service worker found |
| Deployment | cPanel/shared hosting; preserve private configs, uploads and live databases |

Historical Node/PostgreSQL code elsewhere is not the active Sarathi backend.

## What is delivered

- One mobile + SMS OTP door for customers, riders, and allowlisted admins.
- Invisible Firebase reCAPTCHA (Google may show a challenge when needed).
- Verified Firebase UID and mobile bound to one immutable local account.
- Customer/Rider mode switch; mode is never treated as permission.
- One-time name, photo, vehicle type/number and driving-licence photo submission.
- Admin review, rejection with correction/resubmission, approval and suspension.
- Approval required in rider access and dispatch/job actions, not just in the UI.
- Existing rider IDs, work history and rates preserved when admin approves the
  matching verified number. Existing riders must complete the new profile too.
- Private image storage outside the web root; photos served only to owner/admin.
- Firebase login replaces old rider codes and admin-key login when enabled.
- No new hosting polling. Old Sarathi GPS POSTs and automatic rider-page reload
  are disabled in Firebase mode until module 4. Refresh jobs manually meanwhile.

Customer mode is the authenticated account home in this module. It clearly says
in-app bookings arrive with subsequent modules and provides the real call button.
It does not redirect customers into Maakit's separate login or pretend that new
Maps/payment/tracking flows already exist. Existing Maakit booking pages remain
separate. Car is accepted for profile review; shared-trip posting comes in module 6.

## 1. Prepare cPanel safely

1. Export the **Smart Sarathi** database in phpMyAdmin. Back up the current
   `smartsarathi/` code and its private `inc/config.php`. Do not replace that config.
2. Use PHP **8.2 or newer**. In Select PHP Version → Extensions enable
   `pdo_mysql`, `mbstring`, `curl`, `openssl`, `gd`, and `fileinfo`.
3. In PHP options set `upload_max_filesize=6M`, `post_max_size=16M`, and
   `memory_limit=256M`. Each image is limited to 5 MB and 20 million pixels in code.
4. Make sure `https://smartsarathi.in` has a valid certificate and redirects HTTP
   to HTTPS. Its document root should remain the repository's `smartsarathi/`
   folder. Keep the parent repository files in place: Sarathi currently imports
   `../inc/order-media.php`. Uploading only the subfolder onto an empty host is not
   a complete installation of this repository.
5. Create `/home/YOUR_CPANEL_USER/sarathi-identity-private` using File Manager,
   **outside public_html and all domain document roots**. Permissions: `0700`;
   the PHP process must own it and be able to write. Back this folder up privately
   along with the DB. Never put licence photos in public uploads or GitHub.
6. In phpMyAdmin select the Sarathi DB named by `SR_DB_NAME`, then Import
   `smartsarathi/sql/002-unified-identity.sql`. Run it twice if desired; both runs
   are safe. It creates three additive tables without deleting data. On a brand
   new Sarathi database first import `001-base.sql` using the existing setup.
   The Maakit root updater's SQL folder is different; do not assume it imports
   this independent Sarathi migration.
7. Upload the full changed files preserving paths, or use the established repo
   update process. Keep `SR_FIREBASE_ENABLED` unset until the steps below are ready.
   Do not upload test config files, replace private configs, or delete uploads.

## 2. Create/configure Firebase

1. Open https://console.firebase.google.com/ → create a project (for example,
   display name **Smart Sarathi**). Record the actual generated **Project ID**.
   Analytics is optional and not used by this module.
2. Link a Cloud Billing account / Blaze plan to enable real SMS. Set budget alerts
   in Google Cloud Billing. Alerts are notifications, not a spending cap.
3. Project settings → General → Your apps → Web (`</>`) → register **Smart Sarathi
   Web**. Firebase Hosting is not required; keep cPanel hosting.
4. Copy the web config's `apiKey`, `authDomain`, `projectId`, and `appId`.
   Do not generate or send a service-account private key: this module needs none.
5. Authentication (under Build/Security depending on console layout) → Sign-in
   method → Phone → Enable → Save.
6. Authentication → Settings → SMS region policy → allow **India** only. New
   projects can block all regions until this is configured.
7. Authentication → Settings → Authorized domains → add `smartsarathi.in`.
   Add `www.smartsarathi.in` only if served, and your real HTTPS staging domain
   for testing. If you will use `maakit.in/smartsarathi`, also add `maakit.in`.
   Enter hostnames only, not `https://` or paths. Phone Auth does not use localhost
   as an authorized hosted domain; use a real HTTPS staging hostname.
8. Under the Phone provider add a **fictional test phone number** and six-digit
   test code to test without sending SMS. Use a separate staging Firebase project
   where possible. Never configure a real admin/rider number as a fixed-code test
   number in production. Remove production test-number entries before launch.
9. No separate reCAPTCHA key is needed: Firebase's `RecaptchaVerifier` manages it.
   The implementation requests invisible mode; challenges can still appear.

## 3. Restrict the Firebase browser key

Google Cloud Console → the same project → APIs & Services → Credentials → select
that Firebase web API key:

- Application restrictions → Websites / HTTP referrers. Allow the origins in use:
  `https://smartsarathi.in/*`, optionally `https://www.smartsarathi.in/*`, and the
  exact HTTPS staging hostname. If Maakit is an allowed entry, add
  `https://maakit.in/*` (browsers can send origin-only referrers).
- Keep its Firebase API allowlist. Phone login requires Identity Toolkit API
  (`identitytoolkit.googleapis.com`) and Token Service API
  (`securetoken.googleapis.com`). Do not add Maps/Places to this key.
- Save and test the full OTP flow from every enabled hostname. Propagation can
  take a few minutes. A referrer restriction is not a substitute for Firebase
  security rules. Firebase web config is public by design.

The backend fetches public signing certificates from
`https://www.googleapis.com/robot/v1/metadata/x509/securetoken@system.gserviceaccount.com`.
Allow outbound HTTPS/cURL to this fixed URL, with TLS certificate validation.
Certificates are cached according to Google's max-age, up to six hours. No API
secret, long-lived socket, Firebase listener or SMS delivery runs on PHP hosting.

## 4. Configure your existing private config

Open `smartsarathi/inc/config.php` in cPanel. Keep all existing DB/phone settings.
Add these definitions once, before any closing `?>`, replacing every placeholder:

```php
define('SR_FIREBASE_API_KEY', 'YOUR_WEB_API_KEY');
define('SR_FIREBASE_AUTH_DOMAIN', 'YOUR_PROJECT_ID.firebaseapp.com');
define('SR_FIREBASE_PROJECT_ID', 'YOUR_PROJECT_ID');
define('SR_FIREBASE_APP_ID', 'YOUR_FIREBASE_WEB_APP_ID');
define('SR_FIREBASE_ADMIN_MOBILES', ['YOUR_10_DIGIT_ADMIN_MOBILE']);
define('SR_IDENTITY_PRIVATE_DIR', '/home/YOUR_CPANEL_USER/sarathi-identity-private');
define('SR_FIREBASE_ENABLED', true);
```

`SR_FIREBASE_ADMIN_MOBILES` must be an array of **quoted strings**, with no `+91`,
spaces or numeric PHP values. Only these verified phone accounts can open admin.
The project ID must match exactly, including the JWT audience. Do not use the app
ID or project number in its place. The web code pins Firebase SDK 12.19.0 from
Google's CDN; there is no npm/Composer/build step on hosting.

If you already enforce a Content Security Policy, test/allow the Firebase SDK,
Google reCAPTCHA scripts/frames and Firebase Auth endpoints. Do not disable
reCAPTCHA or TLS checking to solve a configuration issue.

## 5. Verify on staging, then enable production

1. Open `/` in English and Hindi at 360px width. Give consent, request a test OTP,
   enter it and confirm Customer mode opens. Wrong/expired codes must not sign in.
2. Confirm Resend waits a minute and Change number invalidates the pending form.
   This UX cooldown does not replace Google's server-side abuse limits.
3. Switch to Rider mode, submit the required details and both photos. Refresh:
   status must remain Pending. Direct `/kaam.php` must not grant rider work.
4. Sign out. Sign in with your configured admin phone and its real SMS OTP.
   Open `/admin/profiles.php` or Admin → Rider profile approvals.
5. Open both photos, check the identity/licence/vehicle, tick the review box and
   Approve. Rejection requires a reason and permits the rider to resubmit.
6. Sign in as that rider, switch modes and open Jobs. Delivery and Rides should
   both be selected. Existing rider rates/history must still match the backup.
   Set actual payout rates in the existing admin panel; a new rider starts at 0,
   shown as not set. No payment collection has been added in this module.
7. Verify switching Customer mode takes the rider offline for new automatic
   assignments; it does not cancel an already assigned job. Return to Rider mode
   to finish that job.
8. Test a second non-admin account: it cannot open another rider's photo or approve
   itself. An old admin-key/rider-code cookie must not bypass Firebase login.
9. Suspend a rider from Approved profiles: jobs access and new assignments stop.
   Use office support to re-review a suspended account; no automatic unblocking.
10. Sign out and test Back/refresh. Private HTML/images must not be served offline.
    Inspect browser Network: no periodic PHP GPS POST or rider page reload occurs
    in Firebase mode. Real-time job updates and tracking are pending module 4.
11. Test one real SMS login on the production domain with an Indian mobile after
    billing, regions and domains are configured. Fictional-number testing does not
    verify actual SMS delivery. Do not launch before this check passes.

## Sessions and account recovery

PHP sessions expire after 12 hours (or earlier if host session cleanup removes
them). A new sign-in requires a recent phone authentication (within 5 minutes).
The server verifies RS256 signature, issuer, audience, UID, expiry, authentication
time, phone provider and +91 mobile. It never trusts a posted phone or rider flag.

Firebase user disabling/revocation is NOT polled by this module. For immediate
local account blocking, an authorized operator uses phpMyAdmin on the Sarathi DB:

```sql
UPDATE sr_accounts
SET active = 0, session_version = session_version + 1
WHERE id = THE_VERIFIED_ACCOUNT_ID;
```

This stops local sessions and dispatch. Also disable the user in Firebase when
appropriate. To invalidate sessions without disabling the account, increment
`session_version`. Recycled numbers and Firebase UID changes are deliberately not
silently merged; office-assisted identity recovery is required. Do not delete and
recreate Firebase users as routine troubleshooting. Phone possession alone does
not prove identity; admin document review remains necessary.

## Rollback / deployment boundaries

Before enabling, finish active rider jobs or arrange office coverage because all
riders need new profiles approved. Setting `SR_FIREBASE_ENABLED=false` is an
explicit emergency rollback to the OLD login system, not a secure Firebase mode.
It restores legacy functionality including old GPS behavior. Keep the new tables
and private photos; do not drop data. Never silently turn the flag off on errors.

No Firebase Realtime Database, Storage, Cloud Functions, Google Maps or Razorpay
service needs setup for this module. Module 4 will use the same Firebase project
and UIDs with scoped rules, after your confirmation of the preceding modules.
PWA background GPS reliability cannot be assumed when the browser is suspended.

## Complete files and checks

All source files in the commit are full files, not patch fragments. Download the
repository ZIP or open the files on GitHub. `smartsarathi/inc/config.sample.php`
is a complete sample; edit the existing private config as above rather than
replacing it. Migration is `smartsarathi/sql/002-unified-identity.sql`.

Run on PHP 8.2+:

```bash
php tests/sarathi-firebase.php
SR_TEST_DB_HOST=127.0.0.1 SR_TEST_DB_PASSWORD=YOUR_TEST_DB_PASSWORD php tests/sarathi-identity-flow.php
node --check smartsarathi/assets/firebase-login.js
```

The integration check only connects to a database named `sarathi_identity_test`
with root test credentials. It runs both migrations twice and checks identity
conflicts, admin authorization, profile corrections, legacy rider linking, mode
gates, suspension and session invalidation. It does not send real SMS.
`.github/workflows/sarathi-identity.yml` runs it on an isolated MariaDB 10.6 service.
The repository-wide existing suite remains `bash .github/jaanch/sab.sh` with its
own documented database/server prerequisites. Real SMS/reCAPTCHA and cPanel file
permissions require the staging/production checks above.

## Official references checked

- https://firebase.google.com/docs/auth/web/phone-auth
- https://firebase.google.com/docs/auth/admin/verify-id-tokens
- https://firebase.google.com/docs/auth/faq-and-troubleshooting
- https://firebase.google.com/docs/projects/api-keys
- https://firebase.google.com/docs/web/alt-setup

## Validation status of this handoff

- Published to `module-1-2`; main and live hosting were not changed.
- [Smart Sarathi identity CI](https://github.com/ankit854281/maakit/actions/runs/40613830264)
  passed: PHP/JavaScript syntax, signed-token tests and native MariaDB identity
  integration checks, including repeat migrations, review and access gates.
- The broader [जाँच workflow](https://github.com/ankit854281/maakit/actions/runs/40613830233)
  failed at the shop categories/prices step. The
  [PHP API workflow](https://github.com/ankit854281/maakit/actions/runs/40613830077)
  also failed. Their causes remain unverified; no repository-wide pass or
  production readiness is claimed. Investigate before merging.
- Real SMS, reCAPTCHA, mobile UI and cPanel photo-upload testing remain pending.
- Follow the staging guide, record acceptance results, and obtain the owner's
  explicit approval before any main merge or production migration.
