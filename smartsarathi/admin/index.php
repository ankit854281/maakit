<?php
// ============================================================
//  सारथी — मालिक का पन्ना
//
//  Chaar cheez ek jagah:
//    1. काम का हाल       — aaj kitne aaye, kitne gaye
//    2. रास्ते की दिक्कतें — rider ne jo bataya
//    3. सारथी            — rate, khata, paisa diya
//    4. ग्राहक कंपनियाँ   — chaabi aur mahine ka bill
//
//  Khulne ki chaabi config.php me hai (SR_ADMIN_KEY). Ek baar
//  daalne ke baad session me yaad rehti hai.
//
//  Yaad rahe: Sarathi kisi ka paisa apne paas nahi rakhta. Ye
//  panna sirf GINTI rakhta hai. Paisa aap apne khate se seedhe
//  bhejte hain.
// ============================================================
require_once __DIR__ . '/../inc/fn.php';
require_once __DIR__ . '/../inc/kaam.php';
require_once __DIR__ . '/../inc/client.php';

// ---------- दरवाज़ा ----------
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (post('do') === 'login')) {
    // Chaabi par bhi hadd — warna koi baith kar aazmata rahega
    if (try_blocked($pdo, 'admin')) {
        $err = try_error();
    } elseif (defined('SR_ADMIN_KEY') && SR_ADMIN_KEY !== ''
              && hash_equals(SR_ADMIN_KEY, (string)post('key'))) {
        try_ok($pdo, 'admin');
        session_regenerate_id(true);
        $_SESSION['sr_admin'] = 1;
        redirect('/admin/');
    } else {
        $err = 'चाबी सही नहीं है।';
    }
}

if (empty($_SESSION['sr_admin'])) {
    ?><!doctype html><html lang="hi"><head><meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="robots" content="noindex,nofollow"><title>सारथी — मालिक</title>
    <link rel="stylesheet" href="<?= h(u('/assets/app.css')) ?>"></head>
    <body class="sr-login"><div class="sr-wrap">
      <div class="sr-brand"><div class="sr-logo">सारथी</div><p>मालिक का पन्ना</p></div>
      <?php if ($err): ?><div class="sr-err"><?= h($err) ?></div><?php endif; ?>
      <form method="post" class="sr-card" autocomplete="off">
        <input type="hidden" name="do" value="login">
        <label class="sr-lab">चाबी</label>
        <input class="sr-in" type="password" name="key" required autofocus>
        <button class="sr-btn sr-btn-go" type="submit">खोलिए</button>
      </form>
    </div></body></html><?php
    exit;
}

// ---------- काम ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_ok()) {
    $do = post('do');

    if ($do === 'bahar') { rider_logout(); redirect('/admin/'); }

    if ($do === 'rider_add') {
        $mob = preg_replace('/\D/', '', (string)post('mobile'));
        $nam = trim((string)post('name'));
        $cod = (string)post('code');
        if (mb_strlen($nam) < 2 || !preg_match('/^[6-9]\d{9}$/D', $mob) || mb_strlen($cod) < 6) {
            $err = 'नाम, 10 अंक का मोबाइल और कम से कम 6 अक्षर का कोड चाहिए।';
        } else {
            try {
                $pdo->prepare("INSERT INTO riders (name,mobile,pass_hash,vehicle,rate,extra_rate)
                               VALUES (?,?,?,?,?,?)")
                    ->execute([$nam, $mob, password_hash($cod, PASSWORD_DEFAULT),
                               trim((string)post('vehicle')) ?: null,
                               (int)post('rate'), (int)post('extra_rate')]);
                flash('सारथी जुड़ गया।'); redirect('/admin/');
            } catch (PDOException $e) { $err = 'यह मोबाइल नंबर पहले से है।'; }
        }
    }

    if ($do === 'rider_rate') {
        $r = (int)post('rate'); $e = (int)post('extra_rate');
        if ($r < 0 || $r > 5000 || $e < 0 || $e > 5000) { $err = 'रेट 0 से 5000 के बीच रखिए।'; }
        else {
            $pdo->prepare("UPDATE riders SET rate=?, extra_rate=? WHERE id=?")
                ->execute([$r, $e, (int)post('id')]);
            flash('रेट सेव हो गया।'); redirect('/admin/');
        }
    }

    if ($do === 'rider_active') {
        $pdo->prepare("UPDATE riders SET active=? WHERE id=?")
            ->execute([(int)post('active'), (int)post('id')]);
        flash('बदल गया।'); redirect('/admin/');
    }

    if ($do === 'paisa') {
        flash(khata_add($pdo, (int)post('id'), post('amount'), post('how'))
              ? 'खाते में दर्ज हो गया।' : 'रकम सही नहीं है।');
        redirect('/admin/');
    }

    if ($do === 'client_add') {
        $nam = trim((string)post('name'));
        if (mb_strlen($nam) < 2) { $err = 'कंपनी का नाम चाहिए।'; }
        else {
            $pdo->prepare("INSERT INTO clients (name,contact,mobile,fee,extra_fee) VALUES (?,?,?,?,?)")
                ->execute([$nam, trim((string)post('contact')) ?: null,
                           preg_replace('/\D/', '', (string)post('mobile')) ?: null,
                           (int)post('fee'), (int)post('extra_fee')]);
            flash('कंपनी जुड़ गई। अब इसकी चाबी बनाइए।'); redirect('/admin/');
        }
    }

    if ($do === 'client_fee') {
        $pdo->prepare("UPDATE clients SET fee=?, extra_fee=? WHERE id=?")
            ->execute([(int)post('fee'), (int)post('extra_fee'), (int)post('id')]);
        flash('भाव सेव हो गया।'); redirect('/admin/');
    }

    if ($do === 'key_new') {
        // Poori chaabi sirf EK BAAR dikhti hai — flash me daal kar
        $_SESSION['sr_newkey'] = key_make($pdo, (int)post('id'), trim((string)post('note')) ?: null);
        redirect('/admin/');
    }
    if ($do === 'key_off') { key_revoke($pdo, (int)post('kid')); flash('चाबी बंद कर दी गई।'); redirect('/admin/'); }

    if ($do === 'job_give') {
        [$ok, $m] = job_give($pdo, (int)post('jid'), (int)post('rid'));
        flash($m); redirect('/admin/');
    }

    if ($do === 'prob_done') {
        $pdo->prepare("UPDATE problems SET settled=1 WHERE id=?")->execute([(int)post('pid')]);
        flash('निपटा हुआ मान लिया गया।'); redirect('/admin/');
    }
}

// ---------- जानकारी ----------
$riders  = $pdo->query("SELECT * FROM riders ORDER BY active DESC, name")->fetchAll();
$clients = $pdo->query("SELECT * FROM clients ORDER BY active DESC, name")->fetchAll();
$probs   = problems_open($pdo);
$ruke    = jobs_waiting($pdo);            // jo kaam abhi kisi ko nahi diye gaye
$taiyar  = riders_ready($pdo);
$newkey  = $_SESSION['sr_newkey'] ?? null; unset($_SESSION['sr_newkey']);
$msg     = flash();

$aaj = $pdo->query("SELECT
      SUM(DATE(created_at)=CURDATE()) aaye,
      SUM(status='delivered' AND DATE(delivered_at)=CURDATE()) gaye,
      SUM(status IN ('new','assigned','picked')) chal
    FROM jobs")->fetch();

$from = date('Y-m-01'); $to = date('Y-m-d');
?><!doctype html>
<html lang="hi"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow"><title>सारथी — मालिक</title>
<link rel="stylesheet" href="<?= h(u('/assets/app.css')) ?>?v=<?= (int)@filemtime(__DIR__.'/../assets/app.css') ?>">
<style>
  .ad{max-width:860px;margin:0 auto;padding:16px 14px 60px}
  .ad h2{margin:26px 0 10px;font-size:21px}
  .ad .box{background:#fff;border:1px solid var(--line);border-radius:14px;padding:15px;margin-bottom:11px}
  .ad .row{display:flex;gap:9px;flex-wrap:wrap;align-items:center;margin-top:10px}
  .ad input[type=text],.ad input[type=number],.ad input[type=tel],.ad input[type=password],.ad select{
    padding:10px;border:1px solid var(--line);border-radius:9px;font:inherit;font-size:16px}
  .ad .sm{width:92px} .ad .md{width:150px}
  .ad button{background:var(--mar);color:#fff;border:0;border-radius:9px;padding:11px 15px;
    font:inherit;font-weight:700;min-height:44px;cursor:pointer}
  .ad button.g{background:#EFEAE0;color:var(--ink)}
  .ad button.r{background:var(--warn)}
  .ad .k{background:#1C1A17;color:#9FE8B4;padding:13px;border-radius:10px;
    font-family:ui-monospace,monospace;font-size:14px;word-break:break-all;margin:9px 0}
  .ad .t{width:100%;border-collapse:collapse;font-size:15px}
  .ad .t td{padding:8px 0;border-bottom:1px solid #F1E9DA}
  .ad .t td:last-child{text-align:right;font-weight:700}
  .ad .hd{display:flex;gap:10px;flex-wrap:wrap;align-items:baseline}
  .ad .mut{color:var(--mut);font-size:14px}
</style></head>
<body style="background:var(--cream)">

<header class="sr-top">
  <div class="sr-top-t"><b>सारथी — मालिक</b>
    <small>आज आए <?= (int)$aaj['aaye'] ?> · पहुँचे <?= (int)$aaj['gaye'] ?> · चल रहे <?= (int)$aaj['chal'] ?></small></div>
  <form method="post"><input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
    <input type="hidden" name="do" value="bahar">
    <button type="submit" style="background:rgba(255,255,255,.15);color:#fff;border:0;border-radius:9px;padding:10px 13px;font:inherit">बाहर</button></form>
</header>

<div class="ad">
<?php if ($msg): ?><div class="sr-flash"><?= h($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="sr-err"><?= h($err) ?></div><?php endif; ?>

<?php if ($newkey): ?>
  <div class="box" style="border-color:var(--gold);border-width:2px">
    <b>नई चाबी बन गई — इसे अभी कॉपी कर लीजिए</b>
    <div class="k"><?= h($newkey) ?></div>
    <p class="mut" style="margin:0">ये पूरी चाबी <b>दोबारा नहीं दिखेगी</b> — डेटाबेस में सिर्फ़ इसका
      ताला रखा जाता है, चाबी नहीं। खो जाए तो नई बनानी पड़ेगी।</p>
  </div>
<?php endif; ?>

<!-- ===== रुके हुए काम =====
     Kaam aate hi khud chale jaate hain, agar koi sarathi hazir ho.
     Koi hazir na ho to wo yahan rukte hain -- sabse upar, taaki
     dikhe bina na reh jayein. -->
<?php if ($ruke): ?>
  <h2>रुके हुए काम <span class="sr-head-tag"><?= count($ruke) ?></span></h2>
  <?php foreach ($ruke as $j): ?>
    <div class="box" style="border-left:4px solid var(--gold)">
      <div class="hd">
        <div style="flex:1;min-width:200px">
          <b><?= h($j['drop_name']) ?></b>
          <span class="mut">· <?= h($j['drop_village'] ?: $j['drop_address']) ?></span>
          <p class="mut" style="margin:4px 0 0">
            <?= h($j['client_name']) ?> · <?= h($j['job_no']) ?>
            <?php if ($j['pick_name']): ?> · उठाना: <?= h($j['pick_name']) ?><?php endif; ?>
          </p>
        </div>
      </div>
      <?php if (!$taiyar): ?>
        <p class="mut" style="margin:10px 0 0">अभी कोई सारथी जुड़ा नहीं है — नीचे जोड़िए।</p>
      <?php else: ?>
        <form method="post" class="row">
          <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
          <input type="hidden" name="do" value="job_give">
          <input type="hidden" name="jid" value="<?= (int)$j['id'] ?>">
          <select name="rid">
            <?php foreach ($taiyar as $r): ?>
              <option value="<?= (int)$r['id'] ?>">
                <?= h($r['name']) ?> — <?= $r['hazir'] ? 'हाज़िर' : 'बंद' ?><?php
                  if ((int)$r['abhi']) echo ', अभी ' . (int)$r['abhi'] . ' काम'; ?>
              </option>
            <?php endforeach; ?>
          </select>
          <button type="submit">इसको दीजिए</button>
        </form>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
<?php endif; ?>

<!-- ===== रास्ते की दिक्कतें ===== -->
<h2>रास्ते की दिक्कतें <?php if ($probs): ?><span class="sr-head-tag"><?= count($probs) ?></span><?php endif; ?></h2>
<?php if (!$probs): ?>
  <div class="box"><b>अभी कोई दिक्कत नहीं</b>
    <p class="mut" style="margin:6px 0 0">सारथी रास्ते से जो बताएगा, वो यहाँ आएगा।</p></div>
<?php else: $pk = problem_kinds(); foreach ($probs as $p): ?>
  <div class="box" style="border-left:4px solid var(--warn)">
    <div class="hd">
      <div style="flex:1;min-width:200px">
        <b><?= h($pk[$p['kind']] ?? $p['kind']) ?></b>
        <div class="mut"><?= h($p['job_no']) ?> · <?= h($p['drop_name']) ?> · <?= h($p['drop_village']) ?></div>
        <div class="mut"><?= h($p['rider_name']) ?> · <?= h(date('j/n g:i a', strtotime($p['created_at']))) ?></div>
        <?php if ($p['note']): ?><div style="margin-top:7px;background:var(--cream);border-radius:8px;padding:8px 10px"><?= h($p['note']) ?></div><?php endif; ?>
      </div>
      <form method="post"><input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
        <input type="hidden" name="do" value="prob_done"><input type="hidden" name="pid" value="<?= (int)$p['id'] ?>">
        <button class="g" type="submit">निपट गया</button></form>
    </div>
  </div>
<?php endforeach; endif; ?>

<!-- ===== सारथी ===== -->
<h2>सारथी</h2>
<?php foreach ($riders as $r): $k = rider_khata($pdo, $r['id']); ?>
  <div class="box"<?= $r['active'] ? '' : ' style="opacity:.6"' ?>>
    <div class="hd">
      <b style="font-size:18px"><?= h($r['name']) ?></b>
      <span class="mut"><?= h($r['mobile']) ?><?= $r['vehicle'] ? ' · ' . h($r['vehicle']) : '' ?></span>
      <?php if ($r['ready_until'] && strtotime($r['ready_until']) > time()): ?>
        <span class="sr-head-tag" style="background:#E8F3EA;color:#1C5130">हाज़िर</span><?php endif; ?>
    </div>
    <div class="row" style="gap:16px">
      <span>बना <b>₹<?= $k['kul'] ?></b> <span class="mut">(<?= $k['kul_n'] ?>)</span></span>
      <span>दिया <b>₹<?= $k['diya'] ?></b></span>
      <span><?= $k['baaki'] >= 0 ? 'बाकी' : 'एडवांस' ?>
        <b style="color:var(--mar)">₹<?= abs($k['baaki']) ?></b></span>
    </div>
    <?php if ((int)$r['rate'] <= 0): ?>
      <div class="sr-err" style="margin:9px 0">रेट तय नहीं है — इसीलिए इनके खाते में ₹0 दिख रहा है।</div>
    <?php endif; ?>
    <div class="row">
      <form method="post" class="row" style="margin:0">
        <input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="do" value="rider_rate">
        <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
        <span class="mut">रेट ₹</span><input class="sm" type="number" name="rate" value="<?= (int)$r['rate'] ?>" min="0" max="5000">
        <span class="mut">चक्कर में अगला ₹</span><input class="sm" type="number" name="extra_rate" value="<?= (int)$r['extra_rate'] ?>" min="0" max="5000">
        <button class="g" type="submit">सेव</button>
      </form>
      <form method="post" class="row" style="margin:0">
        <input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="do" value="paisa">
        <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
        <span class="mut">पैसा दिया ₹</span><input class="sm" type="number" name="amount" min="1" max="500000" required>
        <select name="how"><option>नगद</option><option>UPI</option><option>बैंक</option></select>
        <button type="submit">दर्ज</button>
      </form>
      <form method="post" style="margin:0"><input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
        <input type="hidden" name="do" value="rider_active"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
        <input type="hidden" name="active" value="<?= $r['active'] ? 0 : 1 ?>">
        <button class="<?= $r['active'] ? 'r' : 'g' ?>" type="submit"><?= $r['active'] ? 'बंद कीजिए' : 'चालू कीजिए' ?></button></form>
    </div>
  </div>
<?php endforeach; ?>

<div class="box">
  <b>नया सारथी जोड़िए</b>
  <form method="post" class="row">
    <input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="do" value="rider_add">
    <input class="md" type="text" name="name" placeholder="नाम" required>
    <input class="md" type="tel" name="mobile" placeholder="मोबाइल" required>
    <input class="md" type="text" name="code" placeholder="कोड (6+ अक्षर)" required>
    <input class="md" type="text" name="vehicle" placeholder="गाड़ी">
    <input class="sm" type="number" name="rate" placeholder="रेट" min="0">
    <input class="sm" type="number" name="extra_rate" placeholder="अगला" min="0">
    <button type="submit">जोड़िए</button>
  </form>
</div>

<!-- ===== ग्राहक कंपनियाँ ===== -->
<h2>ग्राहक कंपनियाँ</h2>
<p class="mut" style="margin:-4px 0 12px">Maakit भी इनमें से एक है — पहला ग्राहक, अकेला नहीं।</p>

<?php foreach ($clients as $c):
  $bill = client_bill($pdo, $c['id'], $from, $to);
  $ks = $pdo->prepare("SELECT * FROM client_keys WHERE client_id=? ORDER BY revoked, id DESC");
  $ks->execute([$c['id']]); $keys = $ks->fetchAll(); ?>
  <div class="box">
    <div class="hd"><b style="font-size:18px"><?= h($c['name']) ?></b>
      <span class="mut"><?= h($c['mobile'] ?: '') ?></span></div>

    <table class="t" style="margin-top:9px">
      <tr><td>इस महीने डिलीवरी</td><td><?= $bill['delivery'] ?></td></tr>
      <tr><td><b>इस महीने का बिल</b></td><td style="color:var(--mar);font-size:18px">₹<?= $bill['rupay'] ?></td></tr>
    </table>

    <form method="post" class="row">
      <input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="do" value="client_fee">
      <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
      <span class="mut">एक डिलीवरी ₹</span><input class="sm" type="number" name="fee" value="<?= (int)$c['fee'] ?>" min="0">
      <span class="mut">चक्कर में अगली ₹</span><input class="sm" type="number" name="extra_fee" value="<?= (int)$c['extra_fee'] ?>" min="0">
      <button class="g" type="submit">सेव</button>
    </form>

    <div style="margin-top:12px">
      <b class="mut">चाबियाँ</b>
      <?php foreach ($keys as $k2): ?>
        <div class="row" style="justify-content:space-between">
          <span><code>sk_<?= h($k2['prefix']) ?>_…</code>
            <?php if ($k2['revoked']): ?><span class="mut">· बंद</span>
            <?php elseif ($k2['last_used']): ?><span class="mut">· आख़िरी बार <?= h(date('j M', strtotime($k2['last_used']))) ?></span>
            <?php else: ?><span class="mut">· अभी इस्तेमाल नहीं हुई</span><?php endif; ?></span>
          <?php if (!$k2['revoked']): ?>
            <form method="post" style="margin:0"><input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
              <input type="hidden" name="do" value="key_off"><input type="hidden" name="kid" value="<?= (int)$k2['id'] ?>">
              <button class="r" type="submit">बंद कीजिए</button></form>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
      <form method="post" class="row">
        <input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="do" value="key_new">
        <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
        <input class="md" type="text" name="note" placeholder="किसके लिए (जैसे: Maakit सर्वर)">
        <button type="submit">नई चाबी बनाइए</button>
      </form>
    </div>
  </div>
<?php endforeach; ?>

<div class="box">
  <b>नई कंपनी जोड़िए</b>
  <form method="post" class="row">
    <input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="do" value="client_add">
    <input class="md" type="text" name="name" placeholder="कंपनी का नाम" required>
    <input class="md" type="text" name="contact" placeholder="किससे बात होती है">
    <input class="md" type="tel" name="mobile" placeholder="मोबाइल">
    <input class="sm" type="number" name="fee" placeholder="₹ फ़ीस" min="0">
    <input class="sm" type="number" name="extra_fee" placeholder="अगली" min="0">
    <button type="submit">जोड़िए</button>
  </form>
</div>

<p class="sr-note sr-note-q" style="margin-top:22px">
  <b>याद रखिए:</b> सारथी किसी का पैसा अपने पास नहीं रखता। यह पन्ना सिर्फ़ गिनती रखता है —
  पैसा आप अपने खाते से सीधे भेजते हैं।
</p>

</div></body></html>
