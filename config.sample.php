<?php
// ============================================================
// Maakit — Database settings  (NAMUNA / SAMPLE)
//
// Ye sirf dikhane ke liye hai. Asli config.php aapke server par
// hai aur wahin rahegi — GitHub par kabhi nahi aayegi.
// Updater bhi usko haath nahi lagata.
// ============================================================
define('DB_HOST', 'localhost');
define('DB_NAME', 'maakit.in');
define('DB_USER', 'admin');
define('DB_PASS', 'yahan aapka database password');

define('MAAKIT_WA', '918429393903');
define('MAAKIT_PHONE', '+918429393903');
define('MAAKIT_NUMBER_SHOW', '84293 93903');

// ============================================================
// Muneem ki chaabi — ZAROORI NAHI HAI.
//
// Bahi dekhne ke liye bas Admin me login kijiye aur menu me
// "मुनीम" dabaiye. Koi chaabi nahi chahiye.
//
// Chaabi sirf tab chahiye jab aap chahein ki har somvaar subah
// bahi apne aap aa jaye, bina aapke khole. Bot login nahi kar
// sakta, isliye use chaabi se andar aana padta hai.
//
// Aisa karna ho to: neeche wali line se // hatakar koi lambi
// anjaan line likh dijiye, aur wahi GitHub par bhi daal dijiye —
//   Settings > Secrets and variables > Actions > New secret
//   naam: MUNEEM_KEY
//
// Ye chaabi kisi ko mat dijiye — isse aapke dhandhe ka hisaab
// khul jata hai.
// ============================================================
// define('MUNEEM_KEY', 'yahan ek lambi anjaan line likhiye');

// ============================================================
// Updater ki chaabi — YE ZAROORI HAI.
//
// update.php isi chaabi se kholta hai:
//     maakit.in/update.php?key=<yahi line>
//
// Neeche wali line se // hataiye aur apni koi lambi anjaan line
// likh dijiye (20-30 akshar, angrezi akshar aur ginti milaakar).
//
// Ye chaabi kisi ko mat dijiye — isse website ka code badla
// ja sakta hai.
// ============================================================
// define('UPDATE_KEY', 'yahan ek lambi anjaan line likhiye');

// Fast2SMS: enable only AFTER API key, templates, balance and team mobiles are ready.
// Use strings. Never put real values in this SAMPLE or GitHub.
// define('FAST2SMS_ENABLED', '1');
// define('FAST2SMS_API_KEY', 'private Dev API key');
// define('FAST2SMS_ROUTE', 'otp'); // BINA DLT: sirf login OTP ("Your OTP: 123456"). Tab LOGIN/PICKUP/DELIVERY ID ki zaroorat nahi.
// define('FAST2SMS_LOGIN_ID', 'Smart OTP ID: six-digit login, five-minute expiry');
// define('FAST2SMS_PICKUP_ID', 'Smart OTP ID: pickup code, booking reference');
// define('FAST2SMS_DELIVERY_ID', 'Smart OTP ID: delivery code, booking reference');
// define('FAST2SMS_DAILY_LIMIT', '300'); // per database, includes login and handover SMS
// See docs/fast2sms-setup.md before enabling. Team accounts need correct users.mobile.
