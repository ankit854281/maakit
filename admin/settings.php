<?php
require_once __DIR__ . '/../inc/fn.php';
$u = need_role('admin');
$page_title = 'समय और छुट्टी — Maakit';

function sset(PDO $pdo, $k, $v) {
    $pdo->prepare("INSERT INTO settings (k, v) VALUES (?,?) ON DUPLICATE KEY UPDATE v=VALUES(v)")
        ->execute([$k, $v]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_ok()) {
    $do = post('do');

    if ($do === 'hours') {
        $o = preg_match('/^\d\d:\d\d$/', post('open_time'))  ? post('open_time')  : '08:00';
        $c = preg_match('/^\d\d:\d\d$/', post('close_time')) ? post('close_time') : '20:00';
        sset($pdo, 'open_time', $o);
        sset($pdo, 'close_time', $c);
        flash('समय सेव हो गया।');
    }
    elseif ($do === 'shut') {
        sset($pdo, 'closed_today', post('v') === '1' ? '1' : '0');
        sset($pdo, 'closed_note_hi', mb_substr(trim(post('note_hi')), 0, 200));
        sset($pdo, 'closed_note_en', mb_substr(trim(post('note_en')), 0, 200));
        flash(post('v') === '1' ? 'आज के लिए बंद कर दिया — वेबसाइट पर दिख रहा है।' : 'फिर से खोल दिया।');
    }
    elseif ($do === 'offday') {
        $cur = array_filter(array_map('trim', explode(',', (string)setg($pdo, 'off_days', ''))));
        $d = post('d');
        if (preg_match('/^\d{4}-\d\d-\d\d$/', $d)) {
            if (post('rm')) { $cur = array_values(array_diff($cur, [$d])); flash('छुट्टी हटा दी गई।'); }
            elseif (!in_array($d, $cur, true)) { $cur[] = $d; sort($cur); flash('छुट्टी जोड़ दी गई — उस दिन वेबसाइट पर “आज बंद है” दिखेगा।'); }
        }
        // sirf aane wale din rakhte hain
        $cur = array_values(array_filter($cur, fn($x) => $x >= date('Y-m-d')));
        sset($pdo, 'off_days', implode(',', $cur));
    }
    redirect('/admin/settings.php');
}

// cache saaf karne ke liye naya connection wala read
$S = [];
foreach ($pdo->query("SELECT k, v FROM settings") as $r) { $S[$r['k']] = $r['v']; }
$offd = array_filter(array_map('trim', explode(',', (string)($S['off_days'] ?? ''))));
$shut = ($S['closed_today'] ?? '0') === '1';
list($is_open, $short, $long) = open_now($pdo);
include __DIR__ . '/../inc/panel.php';
?>
<section>
<div class="wrap" style="max-width:720px">
  <h2>समय और छुट्टी</h2>
  <p class="lead">वेबसाइट पर सबसे ऊपर “अभी खुले हैं / आज बंद है” यहीं से तय होता है।
    ग्राहक को पहले ही पता चल जाता है, तो बेकार के फ़ोन नहीं आते।</p>

  <div class="box" style="margin-bottom:16px;display:flex;align-items:center;gap:13px;flex-wrap:wrap">
    <span class="opn <?= $is_open ? 'yes' : 'no' ?>" style="<?= $is_open ? 'background:#E5F3EA;color:#14502F' : 'background:var(--soft);color:var(--muted)' ?>">
      <i style="background:<?= $is_open ? 'var(--ok)' : 'var(--muted)' ?>"></i> <?= h($short) ?></span>
    <span class="help" style="margin:0"><?= h($long) ?></span>
    <a class="btn btn-sm btn-line" href="/" target="_blank" style="margin-left:auto;color:var(--brand);border-color:var(--line)">वेबसाइट पर देखिए</a>
  </div>

  <h3 class="ghead" style="margin-top:0">रोज़ का समय</h3>
  <form method="post" class="box" style="margin-bottom:16px">
    <input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="do" value="hours">
    <div class="grid g2">
      <div class="field"><label>कब से खुलते हैं</label>
        <input type="time" name="open_time" value="<?= h($S['open_time'] ?? '08:00') ?>"></div>
      <div class="field"><label>कब तक डिलीवरी</label>
        <input type="time" name="close_time" value="<?= h($S['close_time'] ?? '20:00') ?>"></div>
    </div>
    <button class="btn btn-brand" style="width:100%">सेव कीजिए</button>
    <p class="help" style="margin-top:8px">बंद होने के बाद भी ऑर्डर आता रहेगा — वेबसाइट लिख देती है
      “अभी ऑर्डर भेज दीजिए, सुबह सबसे पहले जाएगा”।</p>
  </form>

  <h3 class="ghead">आज बंद करना है?</h3>
  <form method="post" class="box" style="margin-bottom:16px">
    <input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="do" value="shut">
    <div class="field"><label>वजह — हिंदी में (ग्राहक को दिखेगी)</label>
      <input type="text" name="note_hi" value="<?= h($S['closed_note_hi'] ?? '') ?>" maxlength="200"
             placeholder="जैसे: होली की वजह से आज बंद है, कल सुबह 8 बजे से खुलेंगे"></div>
    <div class="field"><label>Reason — in English</label>
      <input type="text" name="note_en" value="<?= h($S['closed_note_en'] ?? '') ?>" maxlength="200"
             placeholder="Closed for Holi today, open tomorrow 8 AM"></div>
    <div style="display:flex;gap:9px;flex-wrap:wrap">
      <button class="btn <?= $shut ? 'btn-line' : 'btn-brand' ?>" name="v" value="1" style="flex:1;min-width:150px<?= $shut ? ';color:var(--brand);border-color:var(--line)' : '' ?>">
        <?= $shut ? 'बंद है' : 'आज बंद कर दीजिए' ?></button>
      <button class="btn <?= $shut ? 'btn-green' : 'btn-line' ?>" name="v" value="0" style="flex:1;min-width:150px<?= $shut ? '' : ';color:var(--brand);border-color:var(--line)' ?>">
        <?= $shut ? 'फिर से खोलिए' : 'खुले हैं' ?></button>
    </div>
  </form>

  <h3 class="ghead">आगे की छुट्टियाँ</h3>
  <div class="box" style="margin-bottom:30px">
    <p class="help" style="margin:0 0 12px">होली, दीवाली, शादी — पहले से डाल दीजिए।
      उस दिन वेबसाइट अपने आप “आज बंद है” दिखा देगी, आपको याद रखने की ज़रूरत नहीं।</p>
    <form method="post" style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap;margin-bottom:14px">
      <input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="do" value="offday">
      <div style="max-width:190px"><label>छुट्टी का दिन</label>
        <input type="date" name="d" min="<?= date('Y-m-d') ?>" required></div>
      <button class="btn btn-brand btn-sm">जोड़िए</button>
    </form>
    <?php if (!$offd): ?>
      <p class="help" style="margin:0">अभी कोई छुट्टी नहीं डाली गई।</p>
    <?php else: ?>
      <div class="chips">
        <?php foreach ($offd as $d): ?>
          <form method="post" style="display:inline">
            <input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="do" value="offday">
            <input type="hidden" name="d" value="<?= h($d) ?>"><input type="hidden" name="rm" value="1">
            <button class="chip" style="border:1.5px solid var(--line);cursor:pointer;font:inherit;font-size:15px;font-weight:600">
              <?= h(date('d M Y', strtotime($d))) ?> ✕</button>
          </form>
        <?php endforeach; ?>
      </div>
      <p class="help" style="margin-top:10px">हटाने के लिए तारीख़ पर दबाइए।</p>
    <?php endif; ?>
  </div>
</div>
</section>
<?php include __DIR__ . '/../inc/foot.php'; ?>
