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
