# Sarathi access and trip communication

Registered driver profiles take precedence over customer entry links and stale session modes. Approved, active partners reach kaam.php; pending/rejected/suspended profiles reach apply.php without granting job access. Driver navigation has Jobs, Earnings, History, Profile and Settings.

Verify a number with existing Firebase OTP to create an account, then set an 8–72 byte password in Settings. Existing accounts use mobile + password. OTP remains the recovery path. A recent successful authentication (10 minutes) or the current password is required to change a password. Hashes use PHP password_hash. Device login uses a random 256-bit HttpOnly, SameSite cookie, a SHA-256 hash on the server, a fixed 30-day expiry and account session_version checks. Logout deletes the current device token; password change revokes all other sessions and tokens. No passwords or authentication tokens enter localStorage.

The new idempotent SQL file runs against the independent Sarathi database, lazily before password/device use. Existing private config and uploads are preserved. No Firebase console change is required.

Trip chat is private to the verified pickup/drop customer or assigned approved rider, only while assigned/picked. It polls every 4 seconds while open and visible, validates CSRF on sends, limits messages to 1000 characters and 120 sends/hour per session, and locks the job against completion/reassignment before sending. DOM textContent renders messages. Calls use existing trip contact numbers.

Smart Help is an on-device bilingual topic guide with optional browser voice input and text-to-speech, not a generative AI or staffed support service. Speech availability depends on the browser. Vehicle settings store model and RC, check ownership and refuse switching during an active trip/offer; switching takes the rider offline and preserves approval and earnings.

Validation: tests/sarathi-access.php joins the existing isolated Smart Sarathi workflow; legacy identity tests now assert that approved drivers ignore stale customer mode. Do not claim live deployment until the hosting checkout is updated and the login/booking flows are checked there.
