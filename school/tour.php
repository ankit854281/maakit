<?php
// Children's Day Prayagraj Educational Tour — 14 Nov 2026 (owner, 12 Nov 2026: "jaise Diwali diya competition ka banaya
// tha waise tour ka bhi banaiye"). Public page in the cert.php style: opening story (SNMSIS in many languages → Children's
// Day → the bus to Prayagraj), then the tour. Drawings are our own inline SVG (no other websites' photos). tour.css, tour.js.
// Free-license Wikimedia Commons originals are downloaded only by an image request, optimized and cached on this hosting.
// The page never hotlinks images; a failed download leaves the original SVG visible.
$tour_photos = [
  'museum' => ['museum.jpg', 'Allahabad_Museum.jpg'],
  'khusro' => ['khusro-tombs.jpg', 'Nisar_Begum\'s_and_Khusrau\'s_tombs,_located_at_Bagh-e-Khusrau,_Allahabad.jpg'],
  'cathedral' => ['cathedral-view.jpg', 'All_Saints_Cathedral,_Prayagraj,_Uttar_Pradesh,_India_(2018)553.jpg'],
  'gallery1' => ['khusro-dome.jpg', 'Below_the_dome_of_Khusrau\'s_tomb_at_Bagh-e-Khusrau,_Allahabad.jpg'],
  'gallery2' => ['khusro-tombs.jpg', 'Nisar_Begum\'s_and_Khusrau\'s_tombs,_located_at_Bagh-e-Khusrau,_Allahabad.jpg'],
  'gallery3' => ['khusro-gateway.jpg', 'Entrance_to_Man_Bai\'s_tomb_at_Bagh-e-Khusrau,_Allahabad.jpg'],
  'gallery4' => ['cathedral-view.jpg', 'All_Saints_Cathedral,_Prayagraj,_Uttar_Pradesh,_India_(2018)553.jpg'],
];
if (isset($_GET['photo'])) {
  $id = (string) $_GET['photo'];
  if (!isset($tour_photos[$id])) { http_response_code(404); exit; }
  [$filename, $commonsName] = $tour_photos[$id];
  $folder = __DIR__ . '/img/tour';
  $file = $folder . '/' . $filename;
  if (!is_file($file)) {
    if (!is_dir($folder)) @mkdir($folder, 0755, true);
    if (is_dir($folder) && function_exists('imagecreatefromstring')) {
      // Commons sometimes fails to produce a resized thumbnail. Retry the original file instead.
      $redirect = 'https://commons.wikimedia.org/wiki/Special:Redirect/file/' . rawurlencode($commonsName);
      $bytes = false;
      if (function_exists('curl_init')) {
        foreach ([$redirect . '?width=1200', $redirect] as $url) {
          $ch = curl_init($url);
          curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 5, CURLOPT_CONNECTTIMEOUT => 4, CURLOPT_TIMEOUT => 10, CURLOPT_USERAGENT => 'SNMSIS-SchoolTour/1.0 (educational Wikimedia Commons attribution)', CURLOPT_SSL_VERIFYPEER => true]);
          $data = curl_exec($ch); $status = curl_getinfo($ch, CURLINFO_HTTP_CODE); $mime = curl_getinfo($ch, CURLINFO_CONTENT_TYPE) ?: ''; curl_close($ch);
          if ($status === 200 && is_string($data) && strlen($data) < 12000000 && stripos($mime, 'image/') === 0) {
            $bytes = $data; break;
          }
        }
      }
      if ($bytes !== false) {
        $src = @imagecreatefromstring($bytes);
        if ($src) {
          $w = imagesx($src); $h = imagesy($src);
          if ($w >= 100 && $h >= 100) {
            $newWidth = 1200; $newHeight = (int)round(1200 * $h / $w);
            $dst = imagecreatetruecolor($newWidth, $newHeight);
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $newWidth, $newHeight, $w, $h);
            for ($quality = 80; $quality >= 22; $quality -= 6) {
              ob_start(); imagejpeg($dst, null, $quality); $jpg = ob_get_clean();
              if (strlen($jpg) < 250000) break;
            }
            if (isset($jpg) && strlen($jpg) < 250000) {
              $temp = @tempnam($folder, 'tour_');
              if ($temp && @file_put_contents($temp, $jpg, LOCK_EX) !== false) { @rename($temp, $file); }
              elseif ($temp) @unlink($temp);
            }
            imagedestroy($dst);
          }
          imagedestroy($src);
        }
      }
    }
  }
  if (!is_file($file)) { http_response_code(404); header('Cache-Control: no-store'); exit; }
  header('Content-Type: image/jpeg'); header('Cache-Control: public, max-age=2592000, immutable'); header('X-Content-Type-Options: nosniff');
  readfile($file); exit;
}

header('Content-Type: text/html; charset=utf-8'); header('Cache-Control: no-cache');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; font-src 'self'; connect-src 'self'; frame-ancestors 'self'; base-uri 'none'");
$v = fn($f) => (string)@filemtime(__DIR__ . '/' . $f);
$link = 'https://maakit.in/school/tour.php';
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>Children's Day Prayagraj Tour · SN Mishra Singapore International School</title><meta name="theme-color" content="#DB2777">
<meta name="description" content="Children's Day educational tour to Prayagraj on Saturday 14 November 2026 for Classes 4 to 10 — Allahabad Museum, Hathi Park, Khusro Bagh and All Saints Cathedral.">
<meta property="og:title" content="🚌 Children's Day Prayagraj Tour — 14 Nov 2026 · SNMSIS"><meta property="og:description" content="Classes 4–10 · Allahabad Museum · Hathi Park · Khusro Bagh · All Saints Cathedral. Limited seats — register with your class teacher.">
<meta property="og:type" content="website"><meta property="og:url" content="<?= $link ?>"><meta property="og:image" content="https://maakit.in/school/img/tour-og.jpg"><meta property="og:image:width" content="1200"><meta property="og:image:height" content="630"><meta name="twitter:card" content="summary_large_image">
<link rel="icon" href="img/snmsis-logo.png"><link rel="stylesheet" href="tour.css?v=<?= $v('tour.css') ?>"></head>
<body>
<div id="intro" class="ad" aria-hidden="true"><div class="ad-bars"><i></i><i></i><i></i></div><button class="ad-skip" type="button" id="adSkip">Skip</button>
  <section class="ad-s s1"><div class="sparks"></div><img class="ad-logo" src="img/snmsis-logo.png" alt=""><div class="lang" id="lang"></div><p class="ad-t">SN Mishra Singapore International School · Bhadohi</p></section>
  <section class="ad-s t2"><div class="sparks"></div><div class="kids"><span>🧒</span><span>👧</span><span>🧒🏽</span></div><p class="ad-k">14 November · बाल दिवस</p><h1 class="ad-h">Happy<br><span class="ad-gold">Children's Day</span></h1><p class="ad-t">इस बार बाल दिवस — क्लास से बाहर, इतिहास के बीच!</p></section>
  <section class="ad-s t3"><div class="sparks"></div><p class="ad-k">Educational Tour · शैक्षिक भ्रमण</p><h1 class="ad-h">Chalo<br><span class="ad-gold">Prayagraj!</span></h1>
    <div class="road"><svg class="bus" viewBox="0 0 130 60" aria-hidden="true"><rect x="2" y="8" width="122" height="38" rx="9" fill="#FBBF24"/><rect x="10" y="15" width="18" height="14" rx="2" fill="#BAE6FD"/><rect x="32" y="15" width="18" height="14" rx="2" fill="#BAE6FD"/><rect x="54" y="15" width="18" height="14" rx="2" fill="#BAE6FD"/><rect x="76" y="15" width="18" height="14" rx="2" fill="#BAE6FD"/><rect x="100" y="15" width="18" height="22" rx="2" fill="#BAE6FD"/><rect x="2" y="33" width="122" height="5" fill="#DC2626"/><text x="50" y="44" font-size="6" font-family="sans-serif" font-weight="700" fill="#7C2D12">SNMSIS</text><circle cx="28" cy="48" r="8" fill="#1F2937"/><circle cx="28" cy="48" r="3" fill="#9CA3AF"/><circle cx="98" cy="48" r="8" fill="#1F2937"/><circle cx="98" cy="48" r="3" fill="#9CA3AF"/></svg></div>
    <p class="ad-t">Saturday 14 Nov 2026 · Classes 4–10 · 4 amazing places</p><button class="ad-go" type="button" id="adGo">See the tour</button></section>
</div>
<main class="wrap">
  <section class="experience" id="experience" aria-label="Prayagraj tour interactive presentation">
    <div class="exp-top"><span class="exp-brand"><img src="img/snmsis-logo.png" width="32" height="32" alt=""> SNMSIS <small>CHILDREN'S DAY SPECIAL</small></span><a class="exp-skip" href="#all-details">View all details ↓</a></div>
    <div class="exp-track" id="expTrack" tabindex="0" aria-label="Swipe left to see tour highlights">
      <section class="exp-slide exp-first" aria-label="Slide 1 of 6">
        <img class="exp-photo" src="tour.php?photo=gallery2" alt="Real photo of Mughal tombs at Khusro Bagh" width="1200" height="900" fetchpriority="high">
        <div class="exp-shade"></div><div class="exp-content"><span class="exp-chip">🎉 14 NOV 2026 · SATURDAY</span>
          <h1>CHALO<br><em>PRAYAGRAJ!</em></h1><p class="exp-lead">This Children's Day, adventure begins beyond the classroom. 🚌</p><p class="exp-hindi">इस बाल दिवस, किताबों से आगे बढ़कर दुनिया देखें!</p>
          <div class="exp-mini"><span>🎓 CLASS 4–10</span><span>📍 4 DESTINATIONS</span></div>
        </div>
      </section>
      <section class="exp-slide exp-history" aria-label="Slide 2 of 6">
        <img class="exp-photo" src="tour.php?photo=gallery2" alt="Historic Khusro Bagh tombs" width="1200" height="900" loading="lazy">
        <div class="exp-shade"></div><div class="exp-content"><span class="exp-chip">🏛️ DISCOVER & EXPLORE</span><h2>HISTORY<br><em>COMES ALIVE!</em></h2>
          <p class="exp-lead">Museum treasures, Mughal tombs & beautiful architecture</p><p class="exp-hindi">सिक्के • मूर्तियाँ • मकबरे • इतिहास की रोचक कहानियाँ</p><div class="exp-mini"><span>🏛️ Allahabad Museum</span><span>🏰 Khusro Bagh</span><span>⛪ Cathedral</span></div>
        </div>
      </section>
      <section class="exp-slide exp-fun" aria-label="Slide 3 of 6">
        <div class="exp-pattern" aria-hidden="true"><span>🎊</span><span>🎈</span><span>🎡</span><span>🐘</span></div>
        <div class="exp-content"><span class="exp-chip">🥳 FUN • FRIENDS • SMILES</span><h2>FUN &<br><em>ENJOYMENT!</em></h2><p class="exp-lead">Hathi Park, greenery, fresh air and moments with friends</p><p class="exp-hindi">हँसी-मज़ाक, दोस्तों के साथ खेल और ढेर सारी यादें!</p><div class="exp-mini"><span>🌳 Nature</span><span>🤝 Friendship</span><span>📸 Group photos</span></div>
        </div>
      </section>
      <section class="exp-slide exp-celebrate" aria-label="Slide 4 of 6">
        <div class="exp-pattern" aria-hidden="true"><span>🎉</span><span>🎂</span><span>✨</span><span>🎁</span></div>
        <div class="exp-content"><span class="exp-chip">🎂 CHILDREN'S DAY CELEBRATION</span><h2>MAKE<br><em>MEMORIES!</em></h2><p class="exp-lead">A day to explore, laugh, observe and celebrate being a child</p><p class="exp-hindi">खुशियाँ मनाएँ, नई बातें सीखें, यादगार पल संजोएँ!</p><div class="exp-mini"><span>🔍 Discovery challenges</span><span>📸 Picture moments</span><span>💛 Togetherness</span></div><small class="exp-caution">Activities are suggested highlights; the school will confirm the final programme.</small>
        </div>
      </section>
      <section class="exp-slide exp-ready" aria-label="Slide 5 of 6">
        <div class="exp-pattern" aria-hidden="true"><span>🎒</span><span>🪪</span><span>🍱</span><span>📓</span></div>
        <div class="exp-content exp-ready-content"><span class="exp-chip">✅ IMPORTANT • MUST BRING</span>
          <h2>READY FOR<br><em>THE TOUR?</em></h2><p class="exp-hindi">यात्रा के लिए ये सभी चीज़ें अनिवार्य हैं</p>
          <div class="exp-checklist">
            <div><b>👕 School Uniform</b><small>पूरी स्कूल ड्रेस पहनकर आएँ</small></div>
            <div><b>🪪 School ID Card</b><small>स्कूल पहचान पत्र साथ रखें</small></div>
            <div><b>🍱 Lunch / Tiffin + Water</b><small>टिफिन और पानी की बोतल</small></div>
            <div><b>📓 School Diary</b><small>स्कूल डायरी अनिवार्य है</small></div>
            <div><b>📞 Parent's Contact Number</b><small>डायरी में अभिभावक का चालू मोबाइल नंबर लिखें</small></div>
          </div>
          <p class="exp-small">Follow teacher instructions • शिक्षक के निर्देशों का पालन करें</p>
        </div>
      </section>
      <section class="exp-slide exp-register" aria-label="Slide 6 of 6">
        <div class="exp-content"><span class="exp-chip">🚨 LIMITED SEATS AVAILABLE</span><h2>DON'T MISS<br><em>THE FUN!</em></h2><p class="exp-lead">📅 14 November · 🎓 Classes 4 to 10</p><p class="exp-hindi">सीट बुक कराने के लिए अपने Class Teacher से संपर्क करें।</p>
          <a href="#registration" class="exp-cta">🎒 Register with Class Teacher ↓</a><a class="exp-share" id="expShare" href="#">📲 Share with Friends</a><p class="exp-small">Bus charges & timings will be shared by the school.</p>
        </div>
      </section>
    </div>
    <div class="exp-controls"><button type="button" id="expPrev" class="exp-prev" aria-label="Previous slide">← Back</button><div class="exp-dots" id="expDots" aria-label="Slide progress"><button class="active" aria-label="Slide 1" aria-current="step" data-index="0"></button><button aria-label="Slide 2" data-index="1"></button><button aria-label="Slide 3" data-index="2"></button><button aria-label="Slide 4" data-index="3"></button><button aria-label="Slide 5" data-index="4"></button><button aria-label="Slide 6" data-index="5"></button></div><button type="button" id="expNext" class="exp-next">Next →</button></div>
    <div class="exp-bottom"><span>Swipe left to explore 👉 · आगे देखने के लिए स्वाइप करें</span><a href="#all-details">Full tour information ↓</a></div>
  </section>
  <div id="all-details" class="exp-details-title"><h2>Explore the full tour ✨</h2><p>सभी जगहों की तस्वीरें, इतिहास और ज़रूरी जानकारी</p><div class="count" id="count" aria-live="polite"></div></div>

  <h2 class="sec">One day, four amazing places ✨<small>एक ही यात्रा में इतिहास, कला, स्थापत्य, प्रकृति और खूब सारी नई सीख</small></h2>

  <article class="place"><div class="art a1"><span class="no">01 · Museum</span>
    <img class="tour-photo" src="tour.php?photo=museum" data-local-src="img/tour/museum.jpg" alt="Allahabad Museum, Prayagraj" loading="lazy" width="1200" height="1600">
    </div><small class="photo-credit">📸 Krishna Kant Sharma · CC BY-SA 4.0 · <a href="https://commons.wikimedia.org/wiki/File:Allahabad_Museum.jpg" target="_blank" rel="noopener noreferrer">Wikimedia Commons</a> · resized</small>
    <div class="bd"><h3>🏛️ Allahabad Museum<small>इलाहाबाद संग्रहालय</small></h3><div class="loc">📍 Chandrashekhar Azad Park, Civil Lines</div>
      <p>Ancient coins, sculptures, paintings, archaeological objects and exhibits of India's freedom movement.</p><p class="hi">प्राचीन सिक्के, मूर्तियाँ, चित्रकलाएँ और स्वतंत्रता संग्राम से जुड़ी ऐतिहासिक वस्तुएँ।</p>
      <ul><li>How historians study the past</li><li>Art & heritage</li><li>Freedom fighters' story</li></ul></div></article>

  <article class="place"><div class="art a2"><span class="no">02 · Park</span><div class="park-photo-pending"><strong>🐘 Hathi Park</strong><small>Real photograph not yet available<br>असली तस्वीर उपलब्ध नहीं है</small></div>
    </div>
    <div class="bd"><h3>🐘 Hathi Park<small>हाथी पार्क</small></h3><div class="loc">📍 Civil Lines</div>
      <p>The famous elephant-shaped slide, greenery and safe group fun under teacher supervision.</p><p class="hi">हाथी वाली प्रसिद्ध स्लाइड, हरियाली और दोस्तों के साथ सुरक्षित खेल।</p>
      <ul><li>Teamwork & friendship</li><li>Respect nature</li><li>Play safely</li></ul></div></article>

  <article class="place"><div class="art a3"><span class="no">03 · Mughal garden</span>
    <img class="tour-photo" src="tour.php?photo=gallery2" data-local-src="img/tour/khusro-tombs.jpg" alt="Historic Mughal tombs at Khusro Bagh" loading="lazy" width="1200" height="900">
    </div><small class="photo-credit">📸 Tahmasp3 · CC0 1.0 · <a href="https://commons.wikimedia.org/wiki/File:Nisar_Begum%27s_and_Khusrau%27s_tombs,_located_at_Bagh-e-Khusrau,_Allahabad.jpg" target="_blank" rel="noopener noreferrer">Wikimedia Commons</a> · resized</small>
    <div class="bd"><h3>🏰 Khusro Bagh<small>खुसरो बाग</small></h3><div class="loc">📍 Near Prayagraj Junction, Lukarganj</div>
      <p>A historic Mughal garden with tombs linked to Prince Khusro — domes, arches and old stonework.</p><p class="hi">मुगलकालीन मकबरे, गुंबद और बगीचा — इतिहास को करीब से देखें।</p>
      <ul><li>Domes & arches</li><li>Mughal architecture</li><li>Protect monuments</li></ul></div></article>

  <article class="place"><div class="art a4"><span class="no">04 · Cathedral</span>
    <img class="tour-photo" src="tour.php?photo=gallery4" data-local-src="img/tour/cathedral-view.jpg" alt="All Saints Cathedral, Prayagraj" loading="lazy" width="1200" height="1600">
    </div><small class="photo-credit">📸 Ar.anujgupta · CC BY-SA 4.0 · <a href="https://commons.wikimedia.org/wiki/File:All_Saints_Cathedral,_Prayagraj,_Uttar_Pradesh,_India_(2018)553.jpg" target="_blank" rel="noopener noreferrer">Wikimedia Commons</a> · resized</small>
    <div class="bd"><h3>⛪ All Saints Cathedral<small>पत्थर गिरजाघर</small></h3><div class="loc">📍 Civil Lines, Sarojini Naidu Marg</div>
      <p>Remarkable stone architecture, pointed arches and the beauty of a historic Gothic-style church.</p><p class="hi">पत्थरों की नक्काशी, ऊँचे मेहराब और अनोखी वास्तुकला।</p>
      <ul><li>Gothic architecture</li><li>Respect all cultures</li><li>Observe & sketch</li></ul></div></article>

  <h2 class="sec">📸 Photo gallery<small>इतिहास को तस्वीरों में देखें · Real photographs</small></h2>
  <div class="tour-gallery"><figure class="gallery-card"><img src="tour.php?photo=gallery1" data-local-src="img/tour/khusro-dome.jpg" alt="Interior dome, Khusro Bagh" loading="lazy" width="1200" height="900"><figcaption><strong>Interior dome, Khusro Bagh</strong><small class="photo-credit">📸 Tahmasp3 · CC0 1.0 · <a href="https://commons.wikimedia.org/wiki/File:Below_the_dome_of_Khusrau%27s_tomb_at_Bagh-e-Khusrau,_Allahabad.jpg" target="_blank" rel="noopener noreferrer">Wikimedia Commons</a> · resized</small></figcaption></figure>
<figure class="gallery-card"><img src="tour.php?photo=gallery2" data-local-src="img/tour/khusro-tombs.jpg" alt="Mughal tombs in Khusro Bagh" loading="lazy" width="1200" height="900"><figcaption><strong>Mughal tombs in Khusro Bagh</strong><small class="photo-credit">📸 Tahmasp3 · CC0 1.0 · <a href="https://commons.wikimedia.org/wiki/File:Nisar_Begum%27s_and_Khusrau%27s_tombs,_located_at_Bagh-e-Khusrau,_Allahabad.jpg" target="_blank" rel="noopener noreferrer">Wikimedia Commons</a> · resized</small></figcaption></figure>
<figure class="gallery-card"><img src="tour.php?photo=gallery3" data-local-src="img/tour/khusro-gateway.jpg" alt="Stone entrance at Khusro Bagh" loading="lazy" width="1200" height="900"><figcaption><strong>Stone entrance at Khusro Bagh</strong><small class="photo-credit">📸 Tahmasp3 · CC0 1.0 · <a href="https://commons.wikimedia.org/wiki/File:Entrance_to_Man_Bai%27s_tomb_at_Bagh-e-Khusrau,_Allahabad.jpg" target="_blank" rel="noopener noreferrer">Wikimedia Commons</a> · resized</small></figcaption></figure>
<figure class="gallery-card"><img src="tour.php?photo=gallery4" data-local-src="img/tour/cathedral-view.jpg" alt="Additional view of All Saints Cathedral" loading="lazy" width="1200" height="900"><figcaption><strong>Additional view of All Saints Cathedral</strong><small class="photo-credit">📸 Ar.anujgupta · CC BY-SA 4.0 · <a href="https://commons.wikimedia.org/wiki/File:All_Saints_Cathedral,_Prayagraj,_Uttar_Pradesh,_India_(2018)553.jpg" target="_blank" rel="noopener noreferrer">Wikimedia Commons</a> · resized</small></figcaption></figure></div>

  <h2 class="sec">🌟 What will students learn?<small>बच्चे क्या सीखेंगे?</small></h2>
  <div class="learn"><div><i>🏛️</i><b>History</b><small>इतिहास</small></div><div><i>🎨</i><b>Art & Heritage</b><small>कला व विरासत</small></div><div><i>🔍</i><b>Observation</b><small>अवलोकन</small></div><div><i>🤝</i><b>Teamwork</b><small>टीमवर्क</small></div><div><i>🌿</i><b>Nature</b><small>प्रकृति</small></div><div><i>📸</i><b>Memories</b><small>यादें</small></div></div>

  <h2 class="sec">🎒 What to bring<small>साथ क्या लाएँ</small></h2>
  <div class="bring"><span>👕 School uniform · स्कूल ड्रेस</span><span>🪪 School ID card · पहचान पत्र</span><span>🥤 Water bottle · पानी</span><span>🍱 Lunch / Tiffin · खाना</span><span>📓 School diary (compulsory) · स्कूल डायरी अनिवार्य</span><span>📞 Parent’s working contact number in diary · अभिभावक का चालू नंबर</span></div>

  <div class="note"><b>🎟️ Entry / प्रवेश:</b> Allahabad Museum is free for school students up to Class 12 in uniform or with school ID, accompanied by teachers. Hathi Park entry is about ₹5–₹10 (to be confirmed); other site fees may apply. The cathedral is usually open to the public on Sundays, so going inside on Saturday is not assured.<br>
    <b>ध्यान दें:</b> यूनिफॉर्म / ID और शिक्षक के साथ संग्रहालय में प्रवेश निःशुल्क है। पार्क व अन्य शुल्क की पुष्टि विद्यालय करेगा। चर्च के अंदर शनिवार को प्रवेश की गारंटी नहीं है।<br>
    <b>🚌 Bus charges and departure / return time</b> will be announced by the school. बस शुल्क और प्रस्थान / वापसी का समय विद्यालय बताएगा।</div>

  <section class="cta" id="registration"><h3>🚨 Limited seats · सीमित सीटें</h3><p>Classes 4–10: register with your Class Teacher. Bring uniform, ID card, lunch, school diary and parent’s contact number.<br>कक्षा 4–10 के विद्यार्थी सीट के लिए अपने कक्षा शिक्षक से संपर्क करें।</p>
    <a class="btn wa" id="wa" href="#">📲 Share on WhatsApp</a><button class="btn y" id="copy" type="button">🔗 Copy link</button><a class="btn l" href="index.php">School ERP</a></section>
  <footer>With best wishes · School Management<br>S.N. Mishra Singapore International School · Jagannathpur, Bhadohi<br>Powered by Maakit</footer>
</main>
<script src="tour.js?v=<?= $v('tour.js') ?>"></script>
</body></html>
