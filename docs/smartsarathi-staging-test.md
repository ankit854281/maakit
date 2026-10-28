# Module 1+2 — backups, isolated staging and acceptance tests

Owner instruction: branch `module-1-2` only. No main merge, live deployment or live
migration until the owner confirms staging tests pass. Module 3 is not started.

## Step 1 — back up BOTH live databases before any migration

Use a quiet time. If you need both databases to represent exactly the same moment,
arrange a short pause in order-taking and staff updates while both backups run;
two separate exports are not a single cross-database transaction.

1. Log into your hosting account → **cPanel** → **File Manager**.
2. Find the live Maakit folder using **Domains → Document Root**. View its existing
   `config.php` and note **only the value of DB_NAME**. Do not edit or share passwords.
3. View the live `smartsarathi/inc/config.php`; note **SR_DB_NAME**. On the repository's
   current alias arrangement this sits under `public_html/smartsarathi/`. Verify the
   actual hosting paths, rather than assuming the database names match the domains.
4. Open **Files → Backup Wizard → Back Up → MySQL Databases** (under Partial Backup).
   Click the exact Maakit database name. Download the entire `.sql` / `.sql.gz` file.
5. Download the exact Smart Sarathi database the same way. These are complete
   individual database backups, despite the cPanel label "Partial Backup".
6. Store them privately on your computer, preferably in a dated folder, for example
   `2026-10-28-before-module-1-2/`. Keep a second protected copy on another device or
   your private backup storage. Record original database names and backup times.
7. Check both downloads completed and are non-empty. If compressed, verify they
   decompress without errors. Confirm each contains table structure and data:
   Maakit should include its actual existing tables; Sarathi should include
   `riders`, `jobs`, `clients`, `settings`, etc. Do not paste dumps into chat.
8. Also take a **full cPanel account backup**: Backup Wizard → Back Up → Full Backup
   → Destination **Home Directory** → enter your email → **Generate Backup**.
   Wait for completion, then return and download the completed `.tar.gz` to your
   computer. This protects code, private configuration and uploaded files too.
   Check available hosting disk space first; insufficient space can fail a backup.
9. Keep all backup archives outside `public_html`. Verify private media directories
   outside public_html are included in the full backup. Full account restore usually
   needs the hosting provider/WHM; it is not the same as importing one SQL file.

If Backup Wizard is unavailable: use **cPanel → Backup → Download a MySQL Database
Backup**. If that is also absent, use **phpMyAdmin**: select ONE exact database →
Export → Custom → select ALL tables → SQL → Structure and Data → save output as
file (gzip if large) → Go/Export. Repeat for the other database. Include triggers
and routines/events if present and available. Do not export only selected tables.

For strongest verification, ask hosting support to restore each dump into a NEW
throwaway validation database and check table/row counts. Never restore over either
live database merely to test a backup. A successful download alone is not a tested
restore. If a backup cannot be obtained or opens as an error/HTML file, stop here.

## Step 2 — create the test site, without reusing the live database

A fresh test DB is sufficient for login/profile tests and avoids copying customer
records or API keys. Back up Maakit, but there is no need to clone its DB for module 1+2.

### A. Separate filesystem and HTTPS hostname

Replace `CPUSER` below with the actual cPanel account name.

1. cPanel → File Manager → home directory → create `sarathi-staging`.
2. On GitHub open branch `module-1-2`, then **Code → Download ZIP**. Upload/extract
   the COMPLETE repository ZIP into `/home/CPUSER/sarathi-staging/`. Rename the
   extracted directory to `repo`. Do not use the earlier changed-files-only ZIP
   for an otherwise empty test site.
3. The resulting files must include both:
   - `/home/CPUSER/sarathi-staging/repo/smartsarathi/index.php`
   - `/home/CPUSER/sarathi-staging/repo/inc/order-media.php`
   The second is an existing shared helper imported by Sarathi. Keeping the full
   repository preserves that dependency; the subdomain will expose only Sarathi.
4. cPanel → **Domains → Create A New Domain** → enter `test.smartsarathi.in`.
   **Uncheck Share document root**. Set Document Root to
   `/home/CPUSER/sarathi-staging/repo/smartsarathi` (some panels ask for the relative
   value `sarathi-staging/repo/smartsarathi`). Submit.
5. If this hosting plan prohibits separate roots/subdomains, ask support to create
   an isolated test subdomain/root. Do not solve that restriction by pointing the
   test hostname at the live document root. Do not edit the live alias rewrite.
6. If DNS is managed outside cPanel, add an **A record**: name `test`, value the
   hosting account's actual server IPv4 address, in the `smartsarathi.in` DNS zone.
   Do not change the existing root or www records. If DNS is managed by cPanel,
   check whether it already created this record.
7. cPanel → **SSL/TLS Status → Run AutoSSL** for the test hostname (or the host's
   SSL installation tool). Wait until `https://test.smartsarathi.in` has a valid
   certificate. In Domains enable **Force HTTPS Redirect** for the TEST hostname.
8. Use **Directory Privacy** on the test document root to give invited testers a
   separate staging username/password. This is a browser access gate, not the
   Firebase login. Do not share that password publicly.
9. Add a `robots.txt` to the TEST root containing `User-agent: *` and `Disallow: /`
   on separate lines. Robots exclusion is not access control; keep Directory Privacy.

**Never run the repository's `.cpanel.yml` deployment from this branch. It copies
files to LIVE public_html. Do not switch the existing production cPanel checkout
to this branch or open the live update.php link.** Use only the new staging folder.
Do not copy live `config.php`, cron jobs, client API keys, WA/Fast2SMS credentials,
Firebase production settings, or real uploads into this staging copy.

### B. New database and a user with access ONLY to it

1. cPanel → **Database Wizard** (older name: MySQL Database Wizard).
2. Create database suffix `sarathi_test`. Record the COMPLETE name shown by cPanel,
   e.g. `CPUSER_sarathi_test`.
3. Create a new user, e.g. suffix `srtest`, with a new generated password.
4. Add this user to `CPUSER_sarathi_test` with **ALL PRIVILEGES on that database only**.
   Do not grant it either live database. This is a stronger safeguard than different
   config names alone. Do not reuse the live DB user.
5. Create `/home/CPUSER/sarathi-staging-private`, outside every domain root.
   Set permissions to `0700`; PHP must own it and be able to write to it.
6. Confirm your two live DB backups from Step 1 are complete. Now select ONLY
   `CPUSER_sarathi_test` in phpMyAdmin and import, in order:
   - `repo/smartsarathi/sql/001-base.sql`
   - `repo/smartsarathi/sql/002-unified-identity.sql`
   Confirm success after each. Look at the database name above the Import tab each
   time. Do not import the root Maakit `sql/` directory or any file into the live DBs.
7. MultiPHP Manager: choose PHP 8.2+ for TEST only. Enable `pdo_mysql`, `mbstring`,
   `curl`, `openssl`, `gd`, `fileinfo`. If the host only has account-wide PHP settings,
   ask support before changing a version that also serves production.
8. For the test domain set `upload_max_filesize=6M`, `post_max_size=16M`,
   `memory_limit=256M`. The application limits each photo to 5 MB.

### C. Separate Firebase project for real SMS tests

1. Firebase console → **Add project** → display name `Smart Sarathi Test`. Record
   its generated Project ID. Keep test users out of the production Firebase project.
2. Link billing/Blaze for real SMS. Configure the ₹500 alert below BEFORE real tests.
3. Project settings → General → add Web app → copy web `apiKey`, `authDomain`,
   `projectId`, `appId`. Firebase Hosting and a service-account key are not required.
4. Authentication → Sign-in method → Phone → Enable.
5. Authentication → Settings → SMS region policy → allow **India only**.
6. Authentication → Settings → Authorized domains → add **test.smartsarathi.in**
   (no https, no path). You need not authorize the production domains in this project.
7. Google Cloud → same test project → APIs & Services → Credentials → Firebase web
   API key → Website/HTTP referrer restriction: `https://test.smartsarathi.in/*`.
   Keep Firebase API restrictions, including Identity Toolkit API and Token Service
   API. Do not enable Maps/Places on this key. Allow propagation before testing.
8. For REAL delivery tests, use phones that you own/control and do not add those
   numbers to Firebase's fictional "Phone numbers for testing" list. A listed test
   number uses the configured code and sends no real SMS. Fictional numbers are
   useful for repeated testing, but do not prove SMS delivery works.
9. Firebase creates/manages reCAPTCHA keys automatically. No custom reCAPTCHA key
   is needed. Use real HTTPS staging, not localhost, for this test.

### D. Complete test-only config file

Create this NEW file in the test copy only:
`/home/CPUSER/sarathi-staging/repo/smartsarathi/inc/config.php`.
Replace every placeholder. Keep this file out of GitHub. The phone below should be
your own test contact number, and the admin array should contain your controlled
10-digit mobile as a quoted string (no +91).

```php
<?php
// TEST SITE ONLY — never copy this file to production.
define('SR_DB_HOST', 'localhost');
define('SR_DB_NAME', 'CPUSER_sarathi_test');
define('SR_DB_USER', 'CPUSER_srtest');
define('SR_DB_PASS', 'NEW_TEST_DATABASE_PASSWORD');

define('SR_ADMIN_KEY', 'NEW_RANDOM_TEST_ONLY_SECRET_AT_LEAST_32_CHARACTERS');
define('SR_PHONE', '+91YOUR_10_DIGIT_MOBILE');
define('SR_PHONE_SHOW', 'YOUR_10_DIGIT_MOBILE');
// Keep legacy customer-page links on staging, never on live Maakit.
define('SR_MAAKIT_URL', 'https://test.smartsarathi.in');

define('SR_FIREBASE_ENABLED', true);
define('SR_FIREBASE_API_KEY', 'TEST_PROJECT_WEB_API_KEY');
define('SR_FIREBASE_AUTH_DOMAIN', 'TEST_PROJECT_ID.firebaseapp.com');
define('SR_FIREBASE_PROJECT_ID', 'TEST_PROJECT_ID');
define('SR_FIREBASE_APP_ID', 'TEST_PROJECT_WEB_APP_ID');
define('SR_FIREBASE_ADMIN_MOBILES', ['YOUR_10_DIGIT_ADMIN_MOBILE']);
define('SR_IDENTITY_PRIVATE_DIR', '/home/CPUSER/sarathi-staging-private');

// No legacy SMS/WhatsApp sends or inherited provider credentials in this test copy.
define('FAST2SMS_ENABLED', '0');
define('OTP_ENABLED', '0');
define('FAST2SMS_API_KEY', '');
define('WA_TOKEN', '');
define('WA_PHONE_ID', '');
```

The `SR_ADMIN_KEY` is not a login bypass in Firebase mode. Admin signs in with OTP
at the same login page; only allowlisted numbers can open `/admin/profiles.php`.
Do not run old setup.php or create shared bridge API keys for these identity tests.
Allow server outbound HTTPS to Google's public token-signing certificate endpoint;
see the module setup guide if PHP login fails after a correct SMS code.

Confirm before using the test app: different folder, different DB name AND user,
different Firebase Project ID, different private-media directory, no live API keys.
If any test screen redirects to a production hostname, stop and correct the staging
configuration. In-app customer ride/parcel booking is not part of module 1+2;
Customer mode currently provides the account home.

## Step 3 — short acceptance checklist

Use two phone numbers you control: **A** is a normal customer/rider; **B** is the
allowlisted admin. Use a separate browser profile for B so sessions do not mix.

| Test | Expected result |
|---|---|
| New customer login | A receives a REAL SMS; correct OTP opens Customer mode. |
| Wrong OTP | Incorrect six-digit code gives an error; no signed-in account appears. Then correct code works. |
| Switch to Rider mode | Same account/number; no second signup; profile form appears. |
| Upload rider documents | Name, vehicle type/number, portrait and licence photo save as Pending. Jobs cannot be accessed yet. |
| Reject | B opens `/admin/profiles.php`, reviews both photos and rejects with a reason. A sees the reason and can resubmit. |
| Approve | B reviews the new revision and approves. A enters Rider mode; Jobs opens; rides and deliveries are enabled. |
| Switch back | Customer mode works on the same account; rider goes offline for new automatic jobs. |
| Logout/login | Protected pages require login after logout. New OTP returns the SAME account and approved profile. |
| Hindi/English | Login, customer account, rider submission and approval screens switch correctly; language survives login. |
| Permissions | A cannot open admin or another account's documents. Missing/oversized/non-image uploads are rejected. |

Do not upload someone else's identity documents for testing. Use your own or clearly
marked test images for workflow checks; inspect a real readable licence before any
real operational approval. There is no need to create live jobs or payments here.

Report each test as Pass/Fail with the screen/error text. Never send OTP codes,
private config, DB passwords, complete dumps or licence photos in chat.
No main merge or live deployment until the owner confirms all tests pass.

## Step 4 — monthly ₹500 budget alerts (not a hard spending limit)

1. Google Cloud Console → **Billing** → choose the billing account linked to the
   Firebase TEST project → **Budgets & alerts → Create budget**.
2. Name: `Smart Sarathi — ₹500 monthly`. Choose an **alerts-only** budget if the UI
   asks for a mode. Set Time range to **Monthly / Calendar month**.
3. Scope: select the **Smart Sarathi Test** project; Services **All services**.
   This avoids missing an Auth/SMS billing SKU. Later, if ₹500 is your combined
   test + production allowance, edit this budget to include BOTH project IDs
   under the same billing account. Two separate ₹500 budgets would allow separate
   warnings, not a combined ₹500 warning.
4. Budget type **Specified amount** → Target amount **500**. Verify the displayed
   currency is **INR (₹)**. Budget amounts use billing-account currency; if it says
   USD, entering 500 means $500. Do not assume changing the display converts it.
5. For early visibility, exclude promotional credits/savings from the budget's
   calculation if you want alerts on gross usage before credits. This is more
   conservative; the final payable invoice can differ.
6. Add these threshold rules:

| Type | Threshold | Amount |
|---|---:|---:|
| Actual | 20% | ₹100 |
| Actual | 50% | ₹250 |
| Actual | 80% | ₹400 |
| Actual | 100% | ₹500 |
| Forecasted | 100% | Projected ₹500 by month end |

7. Enable email alerts to billing admins/users; ensure your email has the appropriate
   billing role. For a single-project budget, enable Project Owner emails if shown.
   Alternatively link a verified Cloud Monitoring email notification channel.
8. Click **Finish/Save**. Reopen the budget and verify project scope, monthly amount,
   thresholds and recipients. Check spam/junk folders. An email need not arrive
   immediately when a new budget is created.
9. During tests review **Billing → Reports** daily, filtered to the test project.
   At the ₹400 warning, pause real SMS tests; disable the Phone provider in the TEST
   project if necessary. Use fictional test numbers for repeated form-flow checks.
   This is a manual precaution; it does not reverse charges already incurred.

**₹500 is an alert target, not a guaranteed spending ceiling.** Reporting/alerts
can be delayed, usage can continue, and final charges can differ. India-only SMS,
restricted keys and an access-protected staging site reduce exposure but cannot
guarantee zero surprise. Do not disable billing on the whole live project as a
routine response—it can interrupt other services. A separate, provider-supported
cost-control mechanism would need its own design and validation.

## Sources

- cPanel Backup Wizard: https://docs.cpanel.net/cpanel/files/backup-wizard/
- cPanel database backup: https://docs.cpanel.net/cpanel/files/backup-for-cpanel/
- cPanel domains: https://docs.cpanel.net/cpanel/domains/domains/create-a-new-domain/
- Database Wizard: https://docs.cpanel.net/cpanel/databases/mysql-database-wizard/
- Firebase phone auth: https://firebase.google.com/docs/auth/web/phone-auth
- Firebase SMS billing: https://firebase.google.com/docs/auth/faq-and-troubleshooting
- Google Cloud budgets: https://docs.cloud.google.com/billing/docs/how-to/budgets
