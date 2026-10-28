<?php
// ============================================================
//  सारथी — database settings (NAMUNA / SAMPLE)
//
//  Ye sirf dikhane ke liye hai.
//
//  KYA KARNA HAI:
//    1. cPanel → File Manager → public_html/smartsarathi/inc/
//    2. is file ki nakal banaiye, naam rakhiye: config.php
//    3. usme neeche wale chaar khane apne hisaab se bhar dijiye
//
//  asli config.php GitHub par kabhi nahi jaati (.gitignore me
//  hai), aur .htaccess use bahar se khulne bhi nahi deta.
//
//  Ye Maakit ka database NAHI hai. Sarathi ka apna hai.
// ============================================================

define('SR_DB_HOST', 'localhost');
define('SR_DB_NAME', 'yahan_apne_database_ka_poora_naam');   // jaise sbs81w9g9z45_sarathi
define('SR_DB_USER', 'yahan_user_ka_poora_naam');            // jaise sbs81w9g9z45_sarathi
define('SR_DB_PASS', 'yahan_wo_password_jo_aapne_banaya');

// ------------------------------------------------------------
//  Maalik ka panna kholne ki chaabi
//
//  Isse /smartsarathi/admin/ khulta hai — jahan sarathi ka rate,
//  client ki chaabi aur hisaab rehta hai.
//
//  Kam se kam 16 akshar. Kisi ko mat bataiye.
//  Banane ka aasaan tareeka: keyboard par aankh band karke
//  20 akshar type kar dijiye.
// ------------------------------------------------------------
define('SR_ADMIN_KEY', 'yahan_ek_lamba_gupt_shabd_likhiye');

// phone number jo panno par dikhega
define('SR_PHONE', '+918429393903');
define('SR_PHONE_SHOW', '84293 93903');

// Fast2SMS — Sarathi has independent config and database.
// Real values go ONLY in private inc/config.php on hosting, not this sample.
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
// define('FAST2SMS_LOGIN_ID', 'Smart OTP login ID');
// define('FAST2SMS_PICKUP_ID', 'Smart OTP pickup ID');
// define('FAST2SMS_DELIVERY_ID', 'Smart OTP delivery ID');
// define('FAST2SMS_ADMIN_MOBILE', 'owner verified 10-digit mobile');
// define('FAST2SMS_DAILY_LIMIT', '300');
// Handover templates take variables in this order: code | booking reference.

// Module 1+2: read docs/smartsarathi-module-1-2.md before enabling.
// Add these definitions to the EXISTING private config.php; do not replace its DB settings.
// define('SR_FIREBASE_ENABLED', true);
// define('SR_FIREBASE_API_KEY', 'copy web apiKey');
// define('SR_FIREBASE_AUTH_DOMAIN', 'your-project.firebaseapp.com');
// define('SR_FIREBASE_PROJECT_ID', 'your-project');
// define('SR_FIREBASE_APP_ID', 'copy web appId');
// define('SR_FIREBASE_ADMIN_MOBILES', ['YOUR_10_DIGIT_MOBILE']); // strings, no +91
// define('SR_IDENTITY_PRIVATE_DIR', '/home/CPANEL_USER/sarathi-identity-private');

// Required for Firebase login: register this score-based Enterprise site key in App Check.
// define('SR_FIREBASE_APP_CHECK_SITE_KEY', 'YOUR_RECAPTCHA_ENTERPRISE_SITE_KEY');
