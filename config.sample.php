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
