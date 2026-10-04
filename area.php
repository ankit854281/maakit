<?php
require_once __DIR__ . '/inc/fn.php';
require_once __DIR__ . '/inc/icons.php';

$page_title = t('Bring Maakit to my village — Maakit', 'मेरे गाँव में भी लाइए — Maakit');
$tab = 'ghar';
$err = ''; $done = false;

$live = array_map('vname', $pdo->query("SELECT name, name_en FROM villages WHERE live=1 ORDER BY name")->fetchAll());

// kis gaon se kitni maang — customer ko bhi dikhta hai (himmat badhti hai)
try {
    $top = $pdo->query("SELECT village, COUNT(*) c FROM area_requests
                        WHERE status IN ('new','planned') GROUP BY village
                        ORDER BY c DESC, village LIMIT 8")->fetchAll();
} catch (Throwable $e) { $top = []; }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_ok()) {
    $v   = trim(post('village'));
    $blk = trim(post('block'));
    $nm  = trim(post('name'));
    $mob = preg_replace('/\D/', '', post('mobile'));
    $nt  = trim(post('note'));

    if (mb_strlen($v) < 2)          { $err = t('Please write your village name.', 'अपने गाँव का नाम लिखिए।'); }
    elseif (strlen($mob) !== 10)    { $err = t('Mobile number must be 10 digits.', 'मोबाइल नंबर 10 अंकों का लिखिए।'); }
    else {
        $c = $pdo->prepare("SELECT id FROM area_requests WHERE mobile=? AND village=?");
        $c->execute([$mob, $v]);
        if (!$c->fetch()) {
            $pdo->prepare("INSERT INTO area_requests (village, block, name, mobile, note) VALUES (?,?,?,?,?)")
                ->execute([mb_substr($v, 0, 80), mb_substr($blk, 0, 80), mb_substr($nm, 0, 80), $mob, mb_substr($nt, 0, 200)]);
        }
        $done = true;
        $cnt = $pdo->prepare("SELECT COUNT(*) c FROM area_requests WHERE village=?");
        $cnt->execute([$v]);
        $same = (int)$cnt->fetch()['c'];
    }
}
include __DIR__ . '/inc/head.php';
?>
<div class="wrap" style="max-width:660px">

<?php if ($done): ?>
  <div class="box" style="margin-top:18px;text-align:center">
    <div class="okmark"><?= svc_icon('shield', 34) ?></div>
    <h2 style="margin:10px 0 4px"><?= t('Noted. Thank you.', 'लिख लिया। धन्यवाद।') ?></h2>
    <p class="lead" style="margin-bottom:0"><?= t(
      'We go village by village. When enough people ask from one village, that village goes live next.',
      'हम एक-एक गाँव करके बढ़ रहे हैं। जिस गाँव से ज़्यादा लोग माँगते हैं, वहाँ पहले पहुँचते हैं।') ?></p>

    <div class="note" style="margin-top:16px;text-align:left">
      <div style="display:flex;justify-content:space-between;gap:10px">
        <span><?= t('Requests from', 'इस गाँव से माँग') ?> <b><?= h(post('village')) ?></b></span>
        <b><?= num($same ?? 1) ?></b>
      </div>
      <p class="help" style="margin:8px 0 0"><?= t(
        'Tell your neighbours to ask too — the more requests, the sooner we come.',
        'पड़ोसियों से भी कहिए — जितनी ज़्यादा माँग, उतनी जल्दी हम आएँगे।') ?></p>
    </div>

    <div style="display:grid;gap:9px;margin-top:14px">
      <a class="btn btn-green" href="<?= h(wa_link(MAAKIT_WA, t('Hello Maakit, please start delivery in our village: ', 'नमस्ते Maakit, हमारे गाँव में भी डिलीवरी शुरू कीजिए: ') . post('village'))) ?>" target="_blank" rel="noopener"><?= t('Tell us more on WhatsApp', 'WhatsApp पर और बताइए') ?></a>
      <a class="btn btn-brand" href="/"><?= t('Back to home', 'होम पेज') ?></a>
    </div>
    <p class="help" style="margin-top:12px"><?= t(
      'Meanwhile you can still order — we’ll tell you the charge on the phone.',
      'तब तक भी ऑर्डर कर सकते हैं — चार्ज हम फ़ोन पर बता देंगे।') ?></p>
  </div>

<?php else: ?>
  <div class="pghead">
    <span class="bigic"><?= svc_icon('box', 36) ?></span>
    <h1><?= t('Not in your village yet?', 'आपका गाँव अभी नहीं है?') ?>
      <span><?= t('Tell us — we’ll come', 'बता दीजिए — हम आएँगे') ?></span></h1>
    <p><?= t(
      'Maakit runs in ' . count($live) . ' villages today. We add the next village where people ask for it. Takes 30 seconds.',
      'Maakit अभी ' . num(count($live)) . ' गाँवों में चलता है। अगला गाँव वही होगा जहाँ से लोग माँगेंगे। 30 सेकंड लगेंगे।') ?></p>
  </div>

  <div class="promise">
    <span><?= svc_icon('rupee', 18) ?> <?= t('Free to ask', 'माँगने का कोई पैसा नहीं') ?></span>
    <span><?= svc_icon('shield', 18) ?> <?= t('Number stays private', 'नंबर किसी को नहीं देंगे') ?></span>
  </div>

  <?php if ($err): ?><div class="err"><?= h($err) ?></div><?php endif; ?>

  <form method="post" class="box">
    <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
    <div class="field"><label for="village"><?= t('Your village name', 'आपके गाँव का नाम') ?></label>
      <input type="text" id="village" name="village" value="<?= h(post('village')) ?>" required
             placeholder="<?= h(t('e.g. Rampur', 'जैसे: रामपुर')) ?>" list="vl">
      <datalist id="vl"><?php foreach ($live as $v): ?><option value="<?= h($v) ?>"><?php endforeach; ?></datalist>
    </div>
    <div class="field"><label for="block"><?= t('Block / nearest market', 'ब्लॉक / पास का बाज़ार') ?>
      <i class="opt">(<?= t('optional', 'चाहें तो') ?>)</i></label>
      <input type="text" id="block" name="block" value="<?= h(post('block')) ?>" placeholder="<?= h(t('e.g. Kapsethi', 'जैसे: कपसेठी')) ?>"></div>
    <div class="field"><label for="name"><?= t('Your name', 'आपका नाम') ?>
      <i class="opt">(<?= t('optional', 'चाहें तो') ?>)</i></label>
      <input type="text" id="name" name="name" value="<?= h(post('name')) ?>"></div>
    <div class="field"><label for="mobile"><?= t('Your mobile number', 'आपका मोबाइल नंबर') ?></label>
      <input type="tel" id="mobile" name="mobile" value="<?= h(post('mobile')) ?>" inputmode="numeric" maxlength="10" required>
      <p class="help"><?= t('We’ll call you the day we start in your village.', 'जिस दिन आपके गाँव में शुरू करेंगे, आपको फ़ोन करेंगे।') ?></p></div>
    <div class="field"><label for="note"><?= t('Anything we should know?', 'कुछ और बताना है?') ?>
      <i class="opt">(<?= t('optional', 'चाहें तो') ?>)</i></label>
      <textarea id="note" name="note" style="min-height:76px" placeholder="<?= h(t('How far is the market? How many houses? Any shop nearby?', 'बाज़ार कितनी दूर है? कितने घर हैं? पास कोई दुकान है?')) ?>"><?= h(post('note')) ?></textarea></div>
    <button type="submit" class="btn btn-brand" style="width:100%;font-size:17px"><?= t('Ask for my village', 'मेरे गाँव के लिए माँगिए') ?></button>
  </form>

  <?php if ($top): ?>
    <h3 class="ghead"><?= t('Villages asking right now', 'अभी कौन से गाँव माँग रहे हैं') ?></h3>
    <div class="box">
      <div class="bars">
        <?php $mx = max(array_column($top, 'c')); foreach ($top as $r): ?>
          <div class="bar">
            <span class="nm"><?= h($r['village']) ?></span>
            <span class="tr2"><span style="width:<?= max(8, round($r['c'] * 100 / $mx)) ?>%"></span></span>
            <span class="vv"><?= num($r['c']) ?></span>
          </div>
        <?php endforeach; ?>
      </div>
      <p class="help" style="margin-top:10px"><?= t('The village at the top is next.', 'सबसे ऊपर वाला गाँव अगला है।') ?></p>
    </div>
  <?php endif; ?>

  <h3 class="ghead"><?= t('Where we deliver today', 'अभी कहाँ-कहाँ पहुँचते हैं') ?></h3>
  <div class="box" style="margin-bottom:30px">
    <div class="chips">
      <?php foreach ($live as $v): ?><span class="chip"><?= h($v) ?></span><?php endforeach; ?>
    </div>
  </div>
<?php endif; ?>

</div>
<script>
document.getElementById('mobile') && document.getElementById('mobile')
  .addEventListener('input', function(){ this.value = this.value.replace(/\D/g,'').slice(0,10); });
(function(){ try{
  var me=JSON.parse(localStorage.getItem('mk_me')||'{}')||{};
  var n=document.getElementById('name'), m=document.getElementById('mobile');
  if(n && me.n && !n.value) n.value=me.n;
  if(m && me.m && !m.value) m.value=me.m;
}catch(e){} })();
</script>
<?php include __DIR__ . '/inc/foot.php'; ?>
