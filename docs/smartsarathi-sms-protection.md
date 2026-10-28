# Module 1+2 — SMS protection and CI evidence

Use only the separate **Smart Sarathi Test** Firebase project and branch
`module-1-2`. No production deployment, SQL migration or main merge is authorized.
Code is prepared; console configuration and cloud deployment are NOT performed
by a GitHub push. Complete this guide before real SMS acceptance testing.

## 1. Both broad CI failures existed before modules 1+2

Baseline main: `d735215db7955f85e36fda1892a4829507142473`.
Module implementation: `071da0ae606c68ccedc0f425a4f38015407a60fc`.

| Check | Baseline failed run | Module failed run | Exact fatal error |
|---|---|---|---|
| जाँच | [40550841435](https://github.com/ankit854281/maakit/actions/runs/40550841435) | [40613830233](https://github.com/ankit854281/maakit/actions/runs/40613830233) | `Failed opening required '/home/runner/work/maakit/maakit/middleware/../config/config.php' ... middleware/legacy.php:7` |
| PHP API security | [40550841156](https://github.com/ankit854281/maakit/actions/runs/40550841156) | [40613830077](https://github.com/ankit854281/maakit/actions/runs/40613830077) | `Uncaught JsonException: Syntax error ... tests/php-api/security.php:121` |

The second error is a JSON response parsing failure; that line calls
`json_decode($raw, true, 32, JSON_THROW_ON_ERROR)`. The logs alone do not prove the
underlying HTTP response's cause. Do not label both as the same root cause.
Both workflows and both named PHP files are unchanged between these commits.
Therefore these two observed failures are pre-existing, not regressions from
modules 1+2. They remain unresolved; a full repository-wide pass is NOT claimed.
The dedicated identity workflow passed on the module implementation.

## 2. What protection is included

| Layer | Enforcement |
|---|---|
| SMS country | Firebase allowlist: India only; function also validates +91 mobile format |
| Authentic app | Enterprise App Check initialized before Auth; configure Authentication enforcement in Console |
| Phone number | Cloud blocking trigger: at most 5 reservations per rolling hour, minimum 60 seconds apart |
| Browser profile | localStorage + Web Locks: at most 5 attempts/hour across numbers and tabs; fallback without Web Locks is not atomic |
| Network | Cloud blocking trigger: 20 reservations/hour per IP (multiple devices can share an IP) |
| Whole test project | Cloud blocking trigger: 20 reservations per rolling 24 hours |

The browser guard is NOT a hard physical-device cap. Clearing storage, incognito,
another browser or a modified client bypasses it. App Check does not supply a
stable physical-device identity for this web PWA. Phone and project limits remain
server-enforced even when the browser guard is bypassed, once the SMS trigger is
deployed and confirmed registered. IP limits are supplemental, not device identity.
Do not claim an absolute 5/hour/device guarantee.

The cloud transaction atomically reserves all three budgets before sending.
Denied reservations write nothing. Failed sends/retries can consume reservations;
the system deliberately does not refund uncertain deliveries. Database errors
block sending. No raw phone/IP/OTP is logged or stored by this code: phone/IP
document keys use secret HMAC. Google processes phone numbers for authentication.
The function never overrides a negative Google risk verdict with ALLOW.

This is a request-count ceiling, NOT a rupee spending ceiling. Functions,
Firestore, Secret Manager, deployments and reCAPTCHA may also incur charges.
Keep the ₹500 all-services budget. The 20/day ceiling is intentionally for staging;
review before any production release. Do not erase counters or rotate the HMAC
secret routinely, as that resets phone/IP counters.

## 3. India-only console steps

1. Firebase Console → select **Smart Sarathi Test** (check actual project ID).
2. Authentication → Settings → **SMS region policy** → Edit.
3. Select the **allow** policy, select **India (IN)** only, and Save.
4. Reopen and verify that all other regions are excluded. This restricts destination
   phone numbers, not the geographic location of the browser.
5. Authentication → Settings → Authorized domains: keep only required app domains;
   add `test.smartsarathi.in`, no protocol/path. Remove unused custom domains.

## 4. App Check console steps and app configuration

1. Google Cloud Console → select the same test project → **reCAPTCHA / Fraud Defense**.
2. Enable the reCAPTCHA Enterprise API if prompted. Create a **Web**, score-based
   key for `test.smartsarathi.in`. Leave checkbox challenge OFF and domain
   verification ON. Do not add localhost or unrelated domains.
3. Copy the **site key** (public key, not a secret/service-account key).
4. Firebase Console → **Security → App Check → Apps** → your registered Web app
   → Register → **reCAPTCHA Enterprise** → paste site key → Save.
   Keep default one-hour TTL and risk threshold 0.5 initially.
5. In the TEST copy's private `smartsarathi/inc/config.php`, add once:

```php
define('SR_FIREBASE_APP_CHECK_SITE_KEY', 'YOUR_TEST_ENTERPRISE_SITE_KEY');
```

6. Upload the complete updated branch files to staging when its domain is ready.
   Login now refuses to start without this configuration; App Check is initialized
   before Firebase Auth. Firebase's invisible phone reCAPTCHA stays in place.
7. Open the test app; use a fictional Firebase phone test number first. Firebase
   Console → App Check → APIs/metrics → **Authentication**: confirm valid requests.
8. Open Authentication's App Check metrics → **Enforce** → confirm. Allow up to
   15 minutes. Verify login from staging still works and requests without App Check
   fail. Registration alone does NOT enforce protection. Do not enable debug
   tokens for public staging or production.

If Authentication enforcement is unavailable, check the project's Identity Platform
upgrade (required below) and current console support. Do not claim protection is
active without seeing enforcement enabled and testing rejected unverified requests.

## 5. Hard phone limit: extra cloud setup, not a Console slider

Firebase has provider throttles, but no configurable 5/hour/phone or 5/hour/device
Console field. This implementation uses **beforeSmsSent**, not beforeSignIn
(which would run after SMS was already sent). It requires **Firebase Authentication
with Identity Platform**, Cloud Functions and Firestore. These run at Google,
not on the shared hosting server. This is module 1+2 security, not module 4 tracking.

1. Test Firebase project → Authentication → upgrade to **Firebase Authentication
   with Identity Platform**. Keep Blaze billing and the ₹500 alert configured.
   Identity Platform changes provider quotas; rely on the deployed blocking
   guard, not the old default daily SMS quota.
2. Firebase → Firestore Database → Create database → **Standard edition**, Native
   mode, database ID `(default)`, location `asia-south1` (Mumbai), production rules.
   This guide assumes a NEW, dedicated test project without existing Firestore
   data or rules. Do not deploy the included deny-all rules over another app.
3. Open Google Cloud Shell in the test project. Run the following, replacing
   `YOUR_TEST_PROJECT_ID` with its exact ID. Node 22 is the function runtime;
   use Node 22 locally as well. Nothing is installed on cPanel.

```bash
git clone --branch module-1-2 --single-branch https://github.com/ankit854281/maakit.git sarathi-security
cd sarathi-security/firebase/sms-guard
npm install --global firebase-tools
firebase login --no-localhost
firebase projects:list
cd functions
npm ci --ignore-scripts
npm test
cd ..
firebase functions:secrets:set SARATHI_SMS_HASH_KEY --project YOUR_TEST_PROJECT_ID
```

At the secret prompt enter a password-manager-generated random secret of at least
32 bytes. Do not put it in GitHub, chat, browser JavaScript or PHP config.

```bash
firebase deploy --only firestore:rules,functions:sarathiBeforeSms --project YOUR_TEST_PROJECT_ID
```

4. Review deploy prompts in the **test** project only; allow required APIs and
   secret access for the deployed function. Never grant Owner to a runtime account.
   If Firestore access is denied, grant its runtime service account **Cloud
   Datastore User** in the test project's IAM. Review function deployment success.
5. Authentication → Settings → Blocking functions: confirm **Before sending SMS**
   points to `sarathiBeforeSms`. The Firebase CLI registers the trigger; if absent,
   do not start real SMS testing. Confirm region `asia-south1` in Functions.
6. Google Cloud → Firestore → Time-to-live → Create policy: collection group
   `sarathiSmsLimits`, timestamp field `expiresAt`. TTL deletion is asynchronous;
   correctness uses timestamps, not deletion timing.
7. Keep client Firestore rules deny-all. Only the Admin SDK function touches these
   counters. Do not make Firestore public to fix a permissions error.

## 6. Acceptance checks before calling the protection active

- With real numbers you control (not fixed-code test entries), confirm a normal
  request creates phone/IP/project counter documents and delivers an OTP.
- Confirm a repeated request within 60 seconds is denied. Browser refresh must
  not reset the local cooldown.
- Test the sixth request within an hour is denied by the CLOUD hook as well as
  the page. Clear browser storage/use a second browser for this check; keep the
  same destination number. Do not assume a UI-disabled button proves server enforcement.
- Confirm concurrent attempts cannot pass the phone ceiling. The atomic Firestore
  transaction is the enforcement boundary; exercise this on staging before release.
- Confirm a number outside India is denied by Firebase; use no third-party numbers.
- Confirm changing numbers does not reset the browser's 5/hour budget; separate
  browser profiles are a documented bypass of this browser-only layer.
- Confirm missing/invalid App Check is rejected after enforcement propagation.
- Confirm function/Firestore failures deny SMS rather than falling back to send.
- Check usage totals and do not unnecessarily exhaust 20 real SMS to test the
  project ceiling; unit tests cover count boundaries. Real trigger registration,
  App Check enforcement and SMS delivery still require the cloud checks above.

Emergency stop: disable the **Phone** sign-in provider in the TEST Firebase project.
Do not delete a registered blocking function as a way to restore login; a dangling
trigger can block all sign-ins. Do not change live billing or production providers.

## Sources

- https://firebase.google.com/docs/app-check/web/recaptcha-enterprise-provider
- https://firebase.google.com/docs/app-check/enable-enforcement
- https://firebase.google.com/docs/auth/limits
- https://firebase.google.com/docs/functions/auth-blocking-events
- https://docs.cloud.google.com/identity-platform/docs/admin/sms-regions
- https://firebase.google.com/docs/firestore/ttl
