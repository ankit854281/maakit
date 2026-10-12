# Fast2SMS activation — Maakit + Smart Sarathi

Owner selected Fast2SMS on 12 October 2026. This explicitly authorizes the provider
integration despite the older generic “no paid service” guideline. No purchase,
SMS send, secret change or live activation is performed by this code commit.

## What is wired

- Customer `/otp.php`: existing-account login, new account after phone verification,
  and access when a password is forgotten. A recent SMS login also permits one
  password reset from My account without the old password (within five minutes). Guest ordering stays available.
- Shop `/otp.php?role=shop`: approved shop or approved vendor only. If multiple
  accounts share a number, keep using the existing account-specific code; never
  choose an arbitrary shop. SMS login does not change the old password/code.
- Rider `/smartsarathi/otp.php`: active approved riders only; also recovers access
  when the old login code is forgotten. Applying is not approval.
- Team `/login.php?as=team`: after a correct password, a second factor is required
  when enabled. It is sent only to that account's stored mobile. Check all team
  mobiles BEFORE enabling. No phone-only administrator login or public admin signup.
- Sarathi admin: correct private admin key AND SMS to configured owner mobile.
- Website order delivery-code SMS after the order is saved. Sarathi pickup SMS
  goes to the sender when a job is created; delivery SMS goes to the recipient
  after pickup. The Maakit bridge carries the existing order codes into Sarathi.
  Consequently a Maakit customer may receive its delivery code at order creation
  and again at pickup under the Sarathi job reference. Never send handover codes
  to the rider. Repeated idempotent job creation does not send another pickup SMS.
- Registration applications remain applications. SMS login grants access only
  after the existing human approval. Existing API opt-in modules/legacy transition
  rider door are not migrated to a new identity system by this change.

## WhatsApp first (owner's choice, 12 Oct 2026 evening)

No DLT is needed for WhatsApp. Order of delivery for every login OTP:
**WhatsApp → (if it fails, e.g. the number is not on WhatsApp) SMS**, same code.
Pickup/delivery codes go on WhatsApp with the booking number when the utility
templates are set. Code: `smartsarathi/inc/fast2sms.php` (`wa_ready`, `Fast2SmsOtp::whatsapp`).

Meta setup (owner):
1. business.facebook.com → create Business portfolio "Maakit"; developers.facebook.com
   → Create app → type Business → add **WhatsApp**.
2. Add a phone number that is NOT logged in to the WhatsApp app (a new SIM is best),
   verify by SMS/call, set display name "Maakit". Add a payment method.
3. WhatsApp Manager → Message templates:
   - `maakit_login`, category **Authentication**, English, "Copy code" button,
     security line on, expiry 5 minutes.
   - `maakit_pickup_code`, category **Utility**: `Maakit: your pickup code is {{1}} for booking {{2}}. Give it to the rider only when you hand over the parcel.`
   - `maakit_delivery_code`, category **Utility**: `Maakit: your delivery code is {{1}} for booking {{2}}. Tell it to the rider only after you receive your parcel.`
4. System user (Business settings → Users → System users) → generate a **permanent token**
   with `whatsapp_business_messaging` permission. Copy the **Phone number ID**.
5. Private config (both config.php files): `OTP_ENABLED '1'`, `WA_TOKEN`, `WA_PHONE_ID`,
   `WA_OTP_TEMPLATE`, optional `WA_PICKUP_TEMPLATE`/`WA_DELIVERY_TEMPLATE`, `WA_LANG 'en'`.
   Keep the Fast2SMS lines too if you want SMS fallback.
6. Test: login with your own number; the code arrives on WhatsApp with a Copy button.

## Without DLT (owner's choice, 12 Oct 2026)

DLT registration costs ₹5,900/year, so we start with Fast2SMS's **OTP route**
(`https://www.fast2sms.com/dev/bulkV2`, `route=otp`). Fast2SMS sends
"Your OTP: 123456" from its own sender. In private config set:

```php
define('FAST2SMS_ENABLED', '1');
define('FAST2SMS_ROUTE', 'otp');
define('FAST2SMS_API_KEY', '...');          // Dev API
// Sarathi inc/config.php also: FAST2SMS_ADMIN_MOBILE
```

All logins then work by OTP: customer (`/otp.php`), shop (`/otp.php?role=shop`),
vehicle owner (`/otp.php?role=vehicle`), rider (`/smartsarathi/otp.php`),
team (password then OTP) and Sarathi admin (key then OTP). Pickup/delivery
handover SMS are OFF in this mode (a bare "Your OTP" with no brand or booking
number would confuse the receiver); those codes stay on screen and WhatsApp.
When DLT is done later, remove `FAST2SMS_ROUTE` and add the three IDs below.

## One-time setup (owner, with DLT)

1. Sign in to Fast2SMS: https://www.fast2sms.com/ . Open **Smart OTP** and create
   an SMS OTP ID. Follow their current sender/template/DLT requirements for your
   account. Do not assume a generic Quick SMS balance enables Smart OTP.
2. Login template: six digits, five-minute expiry. In **Dev API**, obtain the API
   key. Keep it on the server; never paste into GitHub or chat.
3. Create separate pickup and delivery OTP templates with variables ordered
   **code | booking reference**. The integration sends `variables_values` as
   `{otp}|<reference>`. They must describe a handover, not login. Existing job
   codes are four digits. Handover is verified by job state on Maakit/Sarathi;
   the API expiry (7 days) does not change the existing job-state code lifetime.
   Do not put a five-minute-expiry claim in these handover messages.
4. Check wallet balance and account permission to send to Indian numbers.
5. cPanel File Manager: privately add the commented `FAST2SMS_*` settings from
   `config.sample.php` to the existing root **config.php**, and from
   `smartsarathi/inc/config.sample.php` to the existing Sarathi **inc/config.php**.
   Do not replace either file or its database settings. Use quoted string `'1'`
   for `FAST2SMS_ENABLED`. Environment variables of the same names also work.
6. Confirm `users.mobile` for every Maakit team account and set
   `FAST2SMS_ADMIN_MOBILE` for Sarathi. These numbers are administrator-managed;
   the public OTP form cannot change them. Leave ENABLED off until ready.
7. PHP needs cURL, PDO MySQL and mbstring. Additive `sms_otp_challenges` and
   `sms_rate_limits` tables initialize on first use in each database (CREATE
   TABLE permission needed, like Sarathi's existing applications table). No
   existing table, config or upload is overwritten by the feature.
8. Enable on both installations and test with consenting owner-controlled accounts:
   customer signup/login; approved shop; approved rider; team password then SMS;
   admin key then SMS; pickup SMS to sender; delivery SMS to recipient; wrong,
   expired, reused and different-browser OTPs; provider failure and no balance.
   A provider acceptance response is not proof of handset delivery. Check the
   handset and Fast2SMS delivery logs. No real SMS has been verified by CI.

## Security and limits

Random six-digit login codes; only password hashes saved; five-minute expiry;
five valid-format guesses per challenge; consumed atomically once; bound to
session, role and current account credential fingerprint. Resend cancels the
previous challenge in the same flow. Phone 5 sends/hour, IP 20/hour, at least
60 seconds between sends, default global 300 attempts/day **per database**.
A separate server-side cap also limits handover messages to 10/hour/recipient.
Adjust DAILY_LIMIT deliberately; it is not an account-wide monetary cap.
Provider failures consume the abuse budget and cannot log a user in.
No OTP, key, provider payload or full mobile is logged by this module.

Uses the official send endpoint with a caller-generated OTP and local,
purpose-bound verification. It deliberately does not use mobile-only remote
verify, so login, pickup and delivery cannot verify one another.
No background SMS retry: failed handover SMS leaves the established code/WhatsApp
handover flow available, and records a generic server error. Do not claim all
messages have been delivered. Turn ENABLED off to restore old login flows while
investigating provider issues; do so only through private hosting config.

References checked 12 October 2026:
- https://docs.fast2sms.com/reference/send-otp
- https://www.fast2sms.com/help/otp-sms-api/
