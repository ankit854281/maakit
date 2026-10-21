<?php
// ============================================================
//  ढिंढोरची — आज का संदेश और पोस्टर
//
//  Purane zamane me ढिंढोरची gaon me dhol baja kar khabar sunata
//  tha. Ye bot wahi kaam karta hai -- par ek shart ke saath:
//
//      JO SACH NAHI HAI, WO SANDESH ME NAHI JATA.
//
//  Is panne par jo bhi line dikhti hai, uske peeche koi record
//  hai -- judi hui dukaan, chalu packet ka asli daam, chalu
//  ilaake, panchang ki tareekh. Niche "ye baatein kahan se aayi"
//  me wahi likha hai, taaki aap bhejne se pehle khud dekh lein.
//
//  Ye bot KHUD KISI KO NAHI BHEJTA -- bhejna aapke haath me hai.
//  Do wajah: ek galti sau logon tak pahunch jati hai, aur
//  WhatsApp apne aap bhejne wale number band kar deta hai.
// ============================================================
require_once __DIR__ . '/../inc/fn.php';
require_once __DIR__ . '/../inc/dhindhora.php';
$u = need_role('admin');
$page_title = 'ढिंढोरची — आज का संदेश — Maakit';

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_ok() && post('do') === 'le-liya') {
    dhindhora_le_liya($pdo);
    $msg = 'ठीक — आज का संदेश ले लिया। कल नया बनेगा।';
}

$d = dhindhora_aaj($pdo);
$bheja = dhindhora_bheja($pdo);

include __DIR__ . '/../inc/panel.php';
?>
<section>
<div class="wrap">
  <h2>ढिंढोरची — आज का संदेश</h2>
  <p class="lead">
    यह संदेश <b>आपके ही रिकॉर्ड से</b> बना है — जुड़ी हुई दुकानें, चालू पैकेट के असली दाम,
    चालू इलाक़े, पंचांग की तारीख़। जो सच नहीं है वो इसमें नहीं जाता, क्योंकि गाँव में
    झूठा दावा एक ही बार चलता है।
  </p>

  <?php if ($msg): ?><div class="ok"><?= h($msg) ?></div><?php endif; ?>

  <?php if (!$d): ?>
    <div class="box">
      <b>आज कहने लायक़ कुछ नहीं है।</b>
      <p class="note" style="margin:8px 0 0">
        कोई त्योहार पास नहीं, कोई चालू पैकेट नहीं, इस हफ़्ते कोई नई दुकान नहीं जुड़ी।
        <b>चुप रहना झूठ बोलने से बेहतर है।</b> पैकेट चालू कर दीजिए या एक दुकान जोड़ दीजिए —
        कल संदेश अपने आप बन जाएगा।
      </p>
      <p style="margin:10px 0 0"><a class="btn" href="/admin/packet.php">पैकेट देखिए</a></p>
    </div>
  <?php else: ?>

    <?php if ($bheja === date('Y-m-d')): ?>
      <div class="ok">आज का संदेश आप ले चुके हैं।</div>
    <?php endif; ?>

    <div class="box">
      <b style="font-size:17px">संदेश — WhatsApp पर भेजने के लिए</b>
      <pre id="sandesh" style="white-space:pre-wrap;font:inherit;background:#FBF4E6;
           border:1px solid var(--line);border-radius:12px;padding:13px;margin:10px 0"><?= h($d['sandesh']) ?></pre>
      <div style="display:flex;gap:8px;flex-wrap:wrap">
        <button class="btn" type="button" id="copy">संदेश कॉपी कीजिए</button>
        <a class="btn btn-green" target="_blank" rel="noopener"
           href="https://wa.me/?text=<?= rawurlencode($d['sandesh']) ?>">WhatsApp खोलिए</a>
        <form method="post" style="display:inline">
          <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
          <input type="hidden" name="do" value="le-liya">
          <button class="btn btn-ghost">ले लिया</button>
        </form>
      </div>
      <p class="note" style="margin:10px 0 0">
        “WhatsApp खोलिए” दबाने पर WhatsApp खुलेगा और आप ख़ुद चुनेंगे किसे भेजना है —
        ग्रुप, स्टेटस, या कोई एक आदमी। <b>बॉट अपने आप किसी को नहीं भेजता।</b>
      </p>
    </div>

    <h3>पोस्टर — स्टेटस पर लगाने के लिए</h3>
    <p class="note">नीचे का पोस्टर <b>स्क्रीनशॉट</b> लेकर WhatsApp स्टेटस पर लगा दीजिए।
      यही बातें, पढ़ने में आसान।</p>
    <div style="overflow:auto;padding:4px 0 14px">
      <div style="width:360px;height:360px;position:relative;flex:none;
                  background:linear-gradient(160deg,#7A1F1F,#4A0F0F);border-radius:18px;
                  color:#FBF4E6;padding:26px;box-sizing:border-box;overflow:hidden">
        <div style="position:absolute;right:-30px;top:-30px;width:150px;height:150px;
                    border-radius:50%;background:#E0A526;opacity:.18"></div>
        <div style="font-size:13px;letter-spacing:.22em;text-transform:uppercase;opacity:.85">Maakit</div>
        <?php if (!empty($d['tyohar'])): ?>
          <div style="font-size:27px;font-weight:800;line-height:1.25;margin-top:10px;color:#E0A526">
            <?= h($d['tyohar']['tyohar']['hi']) ?>
          </div>
          <div style="font-size:15px;margin-top:4px;opacity:.95">
            <?= $d['tyohar']['aaj'] ? 'आज' : h($d['tyohar']['din'] . ' दिन बाक़ी') ?>
          </div>
        <?php elseif (!empty($d['mausam'])): ?>
          <div style="font-size:27px;font-weight:800;margin-top:10px;color:#E0A526">
            <?= h($d['mausam']['kya']) ?> का सामान
          </div>
        <?php else: ?>
          <div style="font-size:25px;font-weight:800;margin-top:10px;color:#E0A526">
            किसी भी दुकान का सामान
          </div>
        <?php endif; ?>

        <?php if (!empty($d['packet'])): ?>
          <div style="margin-top:14px;font-size:15px;line-height:1.75">
            <?php foreach (array_slice($d['packet'], 0, 3) as $p): ?>
              <div><?= h($p['naam']) ?> — <b style="color:#E0A526">₹<?= (int)$p['daam'] ?></b></div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <div style="position:absolute;left:26px;right:26px;bottom:24px">
          <div style="font-size:20px;font-weight:800">84293 93903</div>
          <div style="font-size:13px;opacity:.9">फ़ोन या WhatsApp कीजिए · maakit.in</div>
        </div>
      </div>
    </div>

    <h3>ये बातें कहाँ से आईं</h3>
    <p class="note">भेजने से पहले ख़ुद देख लीजिए। कोई लाइन आपको ग़लत लगे तो वो रिकॉर्ड ठीक कीजिए,
      संदेश अपने आप सुधर जाएगा।</p>
    <ul>
      <?php foreach ($d['sach'] as $s): ?><li><?= h($s) ?></li><?php endforeach; ?>
    </ul>

  <?php endif; ?>
</div>
</section>
<script>
(function () {
  var b = document.getElementById('copy'), p = document.getElementById('sandesh');
  if (!b || !p) return;
  b.addEventListener('click', function () {
    var t = p.textContent;
    // Teen raste, kyunki purane phone ke browser me clipboard
    // nahi hota -- aur button dabane par kuch na hona sabse bura
    // anubhav hai.
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(t).then(ok, haath);
    } else { haath(); }
    function ok() { b.textContent = 'कॉपी हो गया'; setTimeout(function () { b.textContent = 'संदेश कॉपी कीजिए'; }, 2000); }
    function haath() {
      var a = document.createElement('textarea');
      a.value = t; document.body.appendChild(a); a.select();
      try { document.execCommand('copy'); ok(); }
      catch (e) { b.textContent = 'ऊपर से ख़ुद कॉपी कीजिए'; }
      document.body.removeChild(a);
    }
  });
})();
</script>
<?php include __DIR__ . '/../inc/foot.php'; ?>
