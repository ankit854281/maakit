<?php if (empty($no_tabbar)): ?>
<footer class="footer">
  <div class="wrap">
    <div class="fgrid">
      <div>
        <div class="mk-foot-brand"><?= function_exists('maakit_logo_svg') ? maakit_logo_svg(34) : '' ?><b>Maakit</b></div>
        <div style="opacity:.88;margin-top:2px"><?= t('Anything you need. Maa hai na.', 'कुछ चाहिए? माँ है ना।') ?></div>
        <div style="opacity:.7;font-size:14px;margin-top:10px"><?= t('Connecting local shops and services across India. Coverage varies by area.', 'भारत में स्थानीय दुकानें और सेवाएँ। उपलब्धता इलाके के अनुसार है।') ?></div>
        <a class="btn btn-sm btn-gold" style="margin-top:12px" href="/area.php"><?= t('Request your area', 'अपने इलाके में सेवा माँगें') ?></a>
      </div>

      <div class="fnav">
        <b><?= t('Order', 'ऑर्डर') ?></b>
        <a href="/order.php"><?= t('Groceries & daily needs', 'राशन और रोज़ का सामान') ?></a>
        <a href="/order.php#khana"><?= t('Food & sweets', 'खाना और मिठाई') ?></a>
        <a href="/track.php"><?= t('Track my order', 'मेरा ऑर्डर देखिए') ?></a>
        <a href="/books.php"><?= t('Old books', 'पुरानी किताबें') ?></a>
      </div>

      <div class="fnav">
        <b><?= t('Book', 'बुकिंग') ?></b>
        <a href="/sewa.php?s=safar"><?= t('Vehicle / Bolero', 'गाड़ी / बोलेरो') ?></a>
        <a href="/sewa.php?s=lawn"><?= t('Lawn & marriage hall', 'लॉन / मैरिज हॉल') ?></a>
        <a href="/sewa.php?s=tent"><?= t('Tent, sound & light', 'टेंट, साउंड, लाइट') ?></a>
        <a href="/sewa.php"><?= t('All bookings', 'सारी बुकिंग') ?></a>
        <a href="/transport.php"><?= t('Register your vehicle', 'अपनी गाड़ी जोड़िए') ?></a>
      </div>

      <div class="fcall">
        <div style="opacity:.75;font-size:14px"><?= t('Call / WhatsApp', 'कॉल / WhatsApp') ?></div>
        <a href="tel:<?= MAAKIT_PHONE ?>" class="big"><?= MAAKIT_NUMBER_SHOW ?></a>
        <div style="opacity:.75;font-size:13.5px;margin-top:8px"><?= t('Daily 8 AM – 8 PM', 'रोज़ सुबह 8 से रात 8 बजे तक') ?></div>
      </div>
    </div>

    <div class="fbot">
      <?= t(
        'Maakit provides information and delivery only. We are not responsible for the work, prices or dealings of the shops and workers listed here.',
        'Maakit सिर्फ़ जानकारी और डिलीवरी की सुविधा देता है। दुकानों और कारीगरों के काम, दाम और लेन-देन के लिए Maakit ज़िम्मेदार नहीं है।') ?><br>
      <a href="/register-business.php"><?= t('List your shop or service — free', 'अपना काम / दुकान जोड़िए — फ़्री') ?></a> ·
      <a href="<?= h(lang_switch_url()) ?>"><?= h(lang_other_label()) ?></a> ·
      <a href="/location.php"><?=t('Service areas','सेवा क्षेत्र')?></a> · <a href="/support.php"><?=t('Help & complaints','सहायता और शिकायत')?></a> ·
      <a href="/login.php"><?= t('Shop / team login', 'दुकान / टीम लॉगिन') ?></a> ·
      <a href="/smartsarathi/book.php"><?= t('Send a parcel', 'पार्सल भेजिए') ?></a> ·
      <a href="/smartsarathi/apply.php"><?= t('Become a delivery rider', 'डिलीवरी राइडर बनिए') ?></a> ·
      <a href="/photo-credits.php"><?= t('Photo credits', 'फ़ोटो क्रेडिट') ?></a>
    </div>
  </div>
</footer>
<?php else: ?>
<?php if (!empty($adm_shell)): ?></div></div><?php endif; ?>
<footer class="footer" style="padding:18px 0 22px;margin-top:26px">
  <div class="wrap" style="display:flex;justify-content:space-between;align-items:center;gap:14px;flex-wrap:wrap">
    <span style="opacity:.8;font-size:14px">Maakit <?= defined('MAAKIT_VERSION') ? 'v' . MAAKIT_VERSION : '' ?>
      <?= defined('MAAKIT_VERSION_DATE') ? '· ' . MAAKIT_VERSION_DATE : '' ?> · <?= h(MAAKIT_NUMBER_SHOW) ?></span>
    <span style="display:flex;gap:14px;font-size:14px">
      <a href="/" target="_blank" rel="noopener"><?= t('View website', 'वेबसाइट देखिए') ?></a>
      <?php /* Jo andar hi nahi hai (jaise login ka panna), use लॉगआउट mat dikhaiye */ ?>
      <?php if (function_exists('user') && user()): ?>
        <a href="/logout.php"><?= t('Log out', 'लॉगआउट') ?></a>
      <?php endif; ?>
    </span>
  </div>
</footer>
<?php endif; ?>

<?php if (empty($no_tabbar)):
  $T = [
    ['ghar',  '/',             t('Home', 'होम'),         '<path d="M3 10.5L12 3.5l9 7"/><path d="M5.5 12v8.5h13V12"/>'],
    ['order', '/bazaar.php',    t('Shop', 'शॉपिंग'),      '<path d="M4 5h2.2l2.3 10.5h9.3L20 8H7"/><circle cx="10" cy="19.5" r="1.4"/><circle cx="17.5" cy="19.5" r="1.4"/>'],
    ['book',  '/sewa.php',     t('Book', 'बुकिंग'),      '<rect x="3.6" y="4.8" width="16.8" height="15.6" rx="2.4"/><path d="M8 3v3.6M16 3v3.6M3.6 9.6h16.8"/><path d="M9 13.4h2M9 16.6h6M13.5 13.4h1.5"/>'],
    ['mere',  '/track.php',    t('My orders', 'मेरे ऑर्डर'), '<path d="M3.6 7.6 12 3.4l8.4 4.2v8.8L12 20.6l-8.4-4.2z"/><path d="M3.6 7.6 12 11.8l8.4-4.2M12 11.8v8.8"/>'],
    ['kaam',  '/directory.php',t('Services', 'काम-धंधा'), '<circle cx="11" cy="11" r="6.5"/><path d="M16 16l4.5 4.5"/>'],
  ];
?>
<a class="mk-wa" href="<?= h(wa_link(MAAKIT_WA, t('Hello Maakit, I need help.', 'नमस्ते Maakit, मुझे मदद चाहिए।'))) ?>" target="_blank" rel="noopener" aria-label="<?= h(t('Chat on WhatsApp', 'WhatsApp पर बात कीजिए')) ?>">
  <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2zm0 18.2a8.2 8.2 0 0 1-4.2-1.2l-.3-.2-3 .8.8-2.9-.2-.3A8.2 8.2 0 1 1 12 20.2zm4.5-6.1c-.2-.1-1.5-.7-1.7-.8s-.4-.1-.6.1-.7.8-.8 1-.3.2-.5.1a6.7 6.7 0 0 1-3.3-2.9c-.2-.4.2-.4.7-1.3.1-.2 0-.3 0-.4l-.8-1.8c-.2-.5-.4-.4-.6-.4h-.5a1 1 0 0 0-.7.3 3 3 0 0 0-.9 2.2 5.2 5.2 0 0 0 1.1 2.7 11.8 11.8 0 0 0 4.5 4c1.7.7 2.3.8 3.2.6a2.7 2.7 0 0 0 1.8-1.3 2.2 2.2 0 0 0 .2-1.3c-.1-.1-.3-.2-.5-.3z"/></svg>
</a>
<script>
/* chhoti si thartharahat (Android) jab ADD ya button dabaye */
document.addEventListener('click', function (e) {
  var b = e.target.closest('.mk-add,.mk-btn,.mk-fs-go,.tabbar a,.mk-cc');
  if (b && navigator.vibrate) { try { navigator.vibrate(8); } catch (_) {} }
}, { passive: true });
</script>
<nav class="tabbar" aria-label="<?= h(t('Main menu', 'मुख्य मेन्यू')) ?>">
  <?php foreach ($T as list($k, $href, $lbl, $path)): ?>
    <a href="<?= h($href) ?>" class="<?= ($tab ?? '') === $k ? 'on' : '' ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><?= $path ?></svg>
      <?= h($lbl) ?>
      <?php if ($k === 'order'): ?><i class="dot" id="tabDot" style="display:none">0</i><?php endif; ?>
    </a>
  <?php endforeach; ?>
</nav>
<script>
(function(){
  var d=document.getElementById('tabDot'); if(!d) return;
  try{
    var c=JSON.parse(localStorage.getItem('mk_cart')||'{}'),n=0;
    for(var k in c){ n+=c[k].q||0; }
    if(n>0){ d.textContent=n; d.style.display='grid'; }
  }catch(e){}
})();
</script>
<?php endif; ?>

<script>
/* ---- phone me install karne ka nyota ---- */
(function(){
  var ev = null, box = document.getElementById('installBox');
  window.addEventListener('beforeinstallprompt', function(e){
    e.preventDefault(); ev = e;
    try { if (localStorage.getItem('mk_noinstall') === '1') return; } catch(_){}
    if (box) box.classList.add('show');
  });
  window.addEventListener('appinstalled', function(){
    if (box) box.classList.remove('show');
    try { localStorage.setItem('mk_noinstall','1'); } catch(_){}
  });
  document.addEventListener('click', function(e){
    if (e.target.closest('#installYes') && ev) { ev.prompt(); ev = null; if (box) box.classList.remove('show'); }
    if (e.target.closest('#installNo')) {
      if (box) box.classList.remove('show');
      try { localStorage.setItem('mk_noinstall','1'); } catch(_){}
    }
  });
})();

/* ---- dheeme net par bhi chale ---- */
if ('serviceWorker' in navigator) {
  window.addEventListener('load', function(){ navigator.serviceWorker.register('/sw.js').catch(function(){}); });
}
</script>
<?php
// Ilaka nahi chuna, par phone pehle se lokation ki ijaazat de chuka hai
// (pichhli baar "Use my location" dabaya tha) — to chupchaap GPS se
// ilaka chun lo. Bina ijaazat ke kabhi popup nahi khulta.
if (empty($no_tabbar) && empty($adm_page) && isset($pdo) && function_exists('coverage_selected') && !coverage_selected($pdo) && empty($_SESSION['auto_area_tried'])):
  $_SESSION['auto_area_tried'] = 1;
  $_aa = array_map(fn($a) => ['name' => $a['name'], 'name_en' => $a['name_en'] ?? '', 'city' => $a['city'] ?? '', 'pincode' => $a['pincode'] ?? '', 'del' => coverage_enabled($a) ? 1 : 0], coverage_areas($pdo));
?>
<script src="/assets/geo-area.js?v=<?= (int)@filemtime(__DIR__ . '/../assets/geo-area.js') ?>" defer></script>
<script>
window.addEventListener('load', function () {
  if (!window.MkGeo) return;
  MkGeo.allowed().then(function (ok) {
    if (!ok) return;
    MkGeo.detect(<?= json_encode($_aa, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>).then(function (r) {
      if (!r.match || !r.match.del) return;
      var fd = new FormData(); fd.append('csrf', <?= json_encode(csrf()) ?>); fd.append('area', r.match.name); fd.append('ajax', '1'); fd.append('next', location.pathname);
      fetch('/location.php', { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (x) { return x.json(); }).then(function (j) {
        if (!j.ok) return;
        var b = document.querySelector('.mk-loc b'); if (b) b.firstChild.nodeValue = j.label + ' ';
        // ghar ke panne par us ilake ki dukaanein dikhane ke liye ek baar taaza
        if (location.pathname === '/' || location.pathname === '/bazaar.php') location.reload();
      });
    }).catch(function () {});
  });
});
</script>
<?php endif; ?>
</body>
</html>
