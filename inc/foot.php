<?php if (empty($no_tabbar)): ?>
<footer class="footer">
  <div class="wrap">
    <div class="fgrid">
      <div>
        <div class="word" style="font-size:30px">Maa<span>kit</span></div>
        <div style="opacity:.88;margin-top:2px"><?= t('Anything you need. Maa hai na.', 'कुछ चाहिए? माँ है ना।') ?></div>
        <div style="opacity:.7;font-size:14px;margin-top:10px"><?= t('Kapsethi · Chauri · Kachhwa and nearby villages', 'कपसेठी · चौरी · कछवा और आसपास के गाँव') ?></div>
        <a class="btn btn-sm btn-gold" style="margin-top:12px" href="/area.php"><?= t('Request your village', 'अपने गाँव के लिए माँगिए') ?></a>
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

      <div style="text-align:right">
        <div style="opacity:.75;font-size:14px"><?= t('Call / WhatsApp', 'कॉल / WhatsApp') ?></div>
        <a href="tel:<?= MAAKIT_PHONE ?>" style="text-decoration:none;color:#FBF4E6" class="big"><?= MAAKIT_NUMBER_SHOW ?></a>
        <div style="font-size:20px;font-weight:700;opacity:.9">८४२९३ ९३९०३</div>
        <div style="opacity:.75;font-size:13.5px;margin-top:8px"><?= t('Daily 8 AM – 8 PM', 'रोज़ सुबह 8 से रात 8 बजे तक') ?></div>
      </div>
    </div>

    <div class="fbot">
      <?= t(
        'Maakit provides information and delivery only. We are not responsible for the work, prices or dealings of the shops and workers listed here.',
        'Maakit सिर्फ़ जानकारी और डिलीवरी की सुविधा देता है। दुकानों और कारीगरों के काम, दाम और लेन-देन के लिए Maakit ज़िम्मेदार नहीं है।') ?><br>
      <a href="/register-business.php"><?= t('List your shop or service — free', 'अपना काम / दुकान जोड़िए — फ़्री') ?></a> ·
      <a href="<?= h(lang_switch_url()) ?>"><?= h(lang_other_label()) ?></a> ·
      <a href="/login.php"><?= t('Shop / team login', 'दुकान / टीम लॉगिन') ?></a>
    </div>
  </div>
</footer>
<?php else: ?>
<footer class="footer" style="padding:18px 0 22px;margin-top:26px">
  <div class="wrap" style="display:flex;justify-content:space-between;align-items:center;gap:14px;flex-wrap:wrap">
    <span style="opacity:.8;font-size:14px">Maakit <?= defined('MAAKIT_VERSION') ? 'v' . MAAKIT_VERSION : '' ?>
      <?= defined('MAAKIT_VERSION_DATE') ? '· ' . MAAKIT_VERSION_DATE : '' ?> · <?= h(MAAKIT_NUMBER_SHOW) ?></span>
    <span style="display:flex;gap:14px;font-size:14px">
      <a href="/" target="_blank" rel="noopener">वेबसाइट देखिए</a>
      <?php /* Jo andar hi nahi hai (jaise login ka panna), use लॉगआउट mat dikhaiye */ ?>
      <?php if (function_exists('user') && user()): ?>
        <a href="/logout.php">लॉगआउट</a>
      <?php endif; ?>
    </span>
  </div>
</footer>
<?php endif; ?>

<?php if (empty($no_tabbar)):
  $T = [
    ['ghar',  '/',             t('Home', 'होम'),         '<path d="M3 10.5L12 3.5l9 7"/><path d="M5.5 12v8.5h13V12"/>'],
    ['order', '/order.php',    t('Order', 'ऑर्डर'),      '<path d="M4 5h2.2l2.3 10.5h9.3L20 8H7"/><circle cx="10" cy="19.5" r="1.4"/><circle cx="17.5" cy="19.5" r="1.4"/>'],
    ['book',  '/sewa.php',     t('Book', 'बुकिंग'),      '<rect x="3.6" y="4.8" width="16.8" height="15.6" rx="2.4"/><path d="M8 3v3.6M16 3v3.6M3.6 9.6h16.8"/><path d="M9 13.4h2M9 16.6h6M13.5 13.4h1.5"/>'],
    ['mere',  '/track.php',    t('My orders', 'मेरे ऑर्डर'), '<path d="M3.6 7.6 12 3.4l8.4 4.2v8.8L12 20.6l-8.4-4.2z"/><path d="M3.6 7.6 12 11.8l8.4-4.2M12 11.8v8.8"/>'],
    ['kaam',  '/directory.php',t('Services', 'काम-धंधा'), '<circle cx="11" cy="11" r="6.5"/><path d="M16 16l4.5 4.5"/>'],
  ];
?>
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
</body>
</html>
