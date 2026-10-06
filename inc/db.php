<?php
require_once __DIR__ . '/../config.php';
try {
    $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    // Pehle ye 200 OK ke saath jata tha — yani Google, चौकीदार aur
    // browser teeno ko lagta tha ki panna theek hai. 503 bhejna
    // zaroori hai: isi se चौकीदार turant pakad leta hai aur Google
    // tooti haalat ko sambhal kar nahi rakhta.
    http_response_code(503);
    header('Retry-After: 120');
    header('Content-Type: text/html; charset=utf-8');
    exit('<!doctype html><meta charset="utf-8">'
       . '<meta name="viewport" content="width=device-width,initial-scale=1">'
       . '<div style="font-family:system-ui,sans-serif;max-width:460px;margin:50px auto;padding:0 18px;'
       . 'line-height:1.6;color:#2B1A12;background:#FBF4E6">'
       . '<h2 style="color:#7A1F1F">अभी साइट नहीं खुल पा रही</h2>'
       . '<p>थोड़ी देर में दोबारा कोशिश कीजिए। जल्दी हो तो फ़ोन कर दीजिए — '
       . '<a href="tel:+918429393903" style="color:#7A1F1F">84293 93903</a></p>'
       . '<p style="color:#6B4A2F;font-size:14px">(database se connection nahi ho paya)</p></div>');
}
