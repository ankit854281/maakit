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
// --- WhatsApp OTP (Meta WhatsApp Cloud API) — DLT nahi chahiye. Pehle WhatsApp, na pahunche to SMS. ---
// define('OTP_ENABLED', '1');
// define('WA_TOKEN', 'Meta permanent access token');        // private, kabhi GitHub/chat me nahi
// define('WA_PHONE_ID', 'WhatsApp phone number ID');         // number nahi, uski ID
// define('WA_OTP_TEMPLATE', 'maakit_login');                 // Authentication template, "Copy code" button
// define('WA_PICKUP_TEMPLATE', 'maakit_pickup_code');        // Utility: {{1}} code, {{2}} booking no.
// define('WA_DELIVERY_TEMPLATE', 'maakit_delivery_code');    // Utility: {{1}} code, {{2}} booking no.
// define('WA_LANG', 'en');
// define('FAST2SMS_ROUTE', 'otp'); // BINA DLT: sirf login OTP ("Your OTP: 123456"). Tab LOGIN/PICKUP/DELIVERY ID ki zaroorat nahi.
// define('FAST2SMS_LOGIN_ID', 'Smart OTP ID: six-digit login, five-minute expiry');
// define('FAST2SMS_PICKUP_ID', 'Smart OTP ID: pickup code, booking reference');
// define('FAST2SMS_DELIVERY_ID', 'Smart OTP ID: delivery code, booking reference');
// define('FAST2SMS_DAILY_LIMIT', '300'); // per database, includes login and handover SMS
// See docs/fast2sms-setup.md before enabling. Team accounts need correct users.mobile.

// ---- Coaching Saathi (maakit.in/coaching) ----
// Login mobile + password se hota hai (OTP / SMS ki zaroorat nahi). Owner bhool jaye to
// maakit.in/coaching/index.php?reset=1 par upar wali UPDATE_KEY se naya password.
// Student app ka plan (₹54 / ₹99). Ye teeno bharne tak kisi bachche ka app LOCK nahi hota.
// define('CS_RAZORPAY_KEY_ID', 'rzp_live_...');
// define('CS_RAZORPAY_KEY_SECRET', 'private');
// define('CS_RAZORPAY_WEBHOOK_SECRET', 'private');   // Razorpay > Webhooks: /coaching/webhook.php, event payment.captured
// Home page ke Google Play / App Store button sirf tab dikhte hain jab ye link ho:
// define('APP_PLAY_URL', 'https://play.google.com/store/apps/details?id=...');
// define('APP_STORE_URL', 'https://apps.apple.com/in/app/...');
// define('CS_PRICE_6M', 54);  define('CS_PRICE_12M', 99);
