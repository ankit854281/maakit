# Teacher mobile verification

Teacher App and web ERP support PIN followed by mobile OTP. The existing school-office teacher record's `phone` is the only delivery destination. Teachers cannot edit that record through legacy sync. The existing Fast2SMS/WhatsApp adapter provides hashed codes, five-minute expiry, five attempts, resend throttling and atomic single use. Challenges bind to a random browser secret, teacher identity, current PIN hash and current mobile number.

## Activation

Deployment alone does not enable this feature. Before activation, the hosting administrator must apply `sql/049-school-teacher-otp.sql`, verify all teacher phone numbers in Principal Settings and confirm the existing SMS/WhatsApp provider works. Configure the existing provider according to `docs/fast2sms-setup.md`, then set private `SCHOOL_TEACHER_OTP_ENABLED=true` in the root hosting configuration. Credentials stay outside Git. Provider charges follow the existing provider account.

The flag defaults to off so an unconfigured deployment cannot lock every teacher out. Once enabled, missing provider configuration or failed delivery blocks teacher login. There is no PIN-only fallback. Existing teacher sessions must verify again. Other roles retain their existing login. Changing a teacher's registered number invalidates their verified sessions, and deleting/resetting sessions also removes their verification records.

Test on hosting with one school-authorized teacher before rollout. Confirm delivery, incorrect/expired/reused codes, resend, first-PIN change, normal attendance entry, and login through both Teacher App and web ERP. An APK rebuild is unnecessary because both screens load the hosted website.

OTP verification confirms access to the registered number. A person sharing their OTP or unlocked phone can still delegate access. This change does not implement device approval.
