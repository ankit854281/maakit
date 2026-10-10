<?php
// ============================================================
//  smartsarathi.in — Sarathi ka apna ghar
//
//  Ye panna sirf ye batane ke liye hai ki raasta sahi bana hai.
//  Agar aap ise smartsarathi.in par dekh rahe hain, iska matlab:
//
//    - Alias jud gaya
//    - .htaccess ne is folder tak sahi pahuncha diya
//    - Maakit alag chal raha hai, ye alag
//
//  Asli Sarathi ka code iske baad yahin aayega.
// ============================================================
$host = $_SERVER['HTTP_HOST'] ?? '';
$db   = is_file(__DIR__ . '/config.php') ? 'bhar diya gaya' : 'abhi baaki';
?><!doctype html>
<html lang="hi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="theme-color" content="#1C1A17">
<meta name="robots" content="noindex,nofollow">
<title>सारथी — तैयारी</title>
<style>
  *{box-sizing:border-box}
  body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;
       background:#1C1A17;color:#F3EDE2;padding:24px;
       font-family:system-ui,-apple-system,"Noto Sans Devanagari",sans-serif;line-height:1.6}
  .b{max-width:440px;width:100%}
  h1{font-size:40px;margin:0;letter-spacing:1px}
  .s{color:#E0A526;font-size:15px;letter-spacing:3px;margin-top:4px}
  .ok{background:#1E3A28;border:1px solid #2F5C3F;border-radius:14px;padding:16px 18px;margin:26px 0 18px}
  .ok b{color:#9FE8B4}
  table{width:100%;border-collapse:collapse;font-size:15px}
  td{padding:9px 0;border-bottom:1px solid #2E2A26}
  td:last-child{text-align:right;color:#B9AE9E}
  .n{margin-top:22px;font-size:14px;color:#8C8276}
</style>
</head>
<body><div class="b">

  <h1>सारथी</h1>
  <div class="s">DELIVERY</div>

  <div class="ok"><b>✓ रास्ता सही बन गया</b><br>
    यह पन्ना Maakit से अलग, अपने फ़ोल्डर से खुल रहा है।</div>

  <table>
    <tr><td>पता</td><td><?= htmlspecialchars($host, ENT_QUOTES, 'UTF-8') ?></td></tr>
    <tr><td>फ़ोल्डर</td><td>/smartsarathi/</td></tr>
    <tr><td>डेटाबेस</td><td><?= $db ?></td></tr>
  </table>

  <p class="n">असली ऐप का कोड अभी यहाँ आना बाकी है।</p>

</div></body>
</html>
