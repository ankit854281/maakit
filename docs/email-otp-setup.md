# Maakit customer email OTP

Customer login and signup are available at `/email-otp.php`, linked from My account when enabled. Existing customers first sign in by their existing method, then choose **Link / change email for OTP login** and confirm their current password and email code. An email can belong to only one customer. Email verification never proves ownership of a mobile number and never imports guest history. Shop/team/rider and school authentication remain unchanged.

## cPanel activation

1. Deploy latest main through the existing updater; migration 039 is additive and safe to repeat.
2. Create or select a real sending mailbox on your domain in cPanel. Configure its SPF/DKIM using Email Deliverability. No example address is assumed to exist.
3. The hosting operator adds these values to the private configuration (not GitHub):

```php
define('MAAKIT_EMAIL_OTP_ENABLED', '1');
define('MAAKIT_EMAIL_FROM', 'YOUR_EXISTING_MAILBOX@YOUR_DOMAIN');
define('MAAKIT_EMAIL_DAILY_LIMIT', '300');
```

This first implementation uses the hosting PHP `mail()` transport, not authenticated external SMTP. The host must allow local outbound mail. If the hosting requires an SMTP service, leave this disabled until that adapter is configured. `mail()` acceptance does not guarantee inbox delivery. Never put mailbox passwords in chat or GitHub.

4. Test delivery to an address you control, check inbox/spam, verify the code and sign out/in. Test wrong, expired and reused codes. The automated tests use a fake mail transport; they do not send email.
5. To disable, set `MAAKIT_EMAIL_OTP_ENABLED` to `'0'`. Existing login options remain available.

Codes expire after 5 minutes, permit five wrong guesses and are bound to browser, purpose and account credentials. Resend waits at least 60 seconds; destination/IP/daily limits are database-backed. Codes are hashed, never logged. A random password is created for new email accounts; use email OTP for later logins. Email OTP is not currently a password-reset channel.

Code publication alone does not deploy or activate mail. Real delivery must be checked on hosting.
