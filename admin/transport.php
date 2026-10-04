<?php
require_once __DIR__ . '/../inc/fn.php';
require_once __DIR__ . '/../inc/salon.php';
require_once __DIR__ . '/../inc/transport.php';
$u = need_role('admin');
$page_title = 'गाड़ियाँ — Maakit';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_ok()) {
    $id = (int)post('id');
    $do = post('do');

    if ($do === 'approve') {
        $tr = transport_get($pdo, $id);
        if ($tr) {
            $pdo->prepare("UPDATE transports SET status='approved' WHERE id=?")->execute([$id]);
            $pdo->prepare("UPDATE businesses SET status='approved' WHERE id=?")->execute([$tr['business_id']]);
            flash('गाड़ी मंज़ूर हो गई। अब बुकिंग पेज पर दिखेगी।');
        }
    }
    elseif ($do === 'hide') {
        $tr = transport_get($pdo, $id);
        $pdo->prepare("UPDATE transports SET status='hidden', available=0 WHERE id=?")->execute([$id]);
        if ($tr) $pdo->prepare("UPDATE businesses SET status='hidden' WHERE id=?")->execute([$tr['business_id']]);
        flash('गाड़ी छिपा दी गई।');
    }
    elseif ($do === 'verify') {
        $v = post('v') === '1' ? 1 : 0;
        $pdo->prepare("UPDATE transports SET verified=?, verified_at=IF(?=1, NOW(), NULL) WHERE id=?")
            ->execute([$v, $v, $id]);
        flash($v ? 'कागज़ देखे गए — दर्ज कर दिया।' : 'पुष्टि हटा दी गई।');
    }
    elseif ($do === 'newcode') {
        $c = make_access_code();
        $pdo->prepare("UPDATE transports SET access_code=? WHERE id=?")->execute([$c, $id]);
        $tr = transport_get($pdo, $id);
        if ($tr) $pdo->prepare("DELETE FROM shop_tokens WHERE business_id=?")->execute([$tr['business_id']]);
        flash('नया कोड: ' . $c . ' — गाड़ी वाले को भेज दीजिए।');
    }
    elseif ($do === 'trip') {
        $pdo->prepare("UPDATE transports SET trips = trips + 1 WHERE id=?")->execute([$id]);
        flash('एक ट्रिप जोड़ दी गई।');
    }
    redirect('/admin/transport.php' . (get('v') ? '?v=' . urlencode(get('v')) : ''));
}

$view = get('v', '');
$sql = "SELECT tr.*, b.name AS biz_name, b.status AS biz_status
        FROM transports tr JOIN businesses b ON b.id=tr.business_id WHERE 1";
if ($view === 'pending')  $sql .= " AND tr.status='pending'";
elseif ($view === 'live') $sql .= " AND tr.status='approved'";
elseif ($view === 'hidden') $sql .= " AND tr.status='hidden'";
$sql .= " ORDER BY FIELD(tr.status,'pending','approved','hidden'), tr.id DESC";
$rows = $pdo->query($sql)->fetchAll();

$n_pend = (int)$pdo->query("SELECT COUNT(*) c FROM transports WHERE status='pending'")->fetch()['c'];
$n_live = (int)$pdo->query("SELECT COUNT(*) c FROM transports WHERE status='approved'")->fetch()['c'];
$n_free = (int)$pdo->query("SELECT COUNT(*) c FROM transports WHERE status='approved' AND available=1")->fetch()['c'];
$exp    = papers_expiring($pdo, 45);
include __DIR__ . '/../inc/panel.php';
?>
<section>
<div class="wrap">
  <h2>गाड़ियाँ</h2>
  <p class="lead">गाड़ी वाले ख़ुद <a href="/transport.php" target="_blank">transport.php</a> से जुड़ते हैं।
    आप कागज़ देखकर मंज़ूरी देते हैं — उसके बाद उनकी गाड़ी बुकिंग पेज पर दिखने लगती है।</p>

  <div class="kpis">
    <div class="kpi <?= $n_pend ? 'warn' : '' ?>"><div class="k">मंज़ूरी बाकी</div><div class="v"><?= $n_pend ?></div>
      <div class="d"><?= $n_pend ? 'कागज़ देखकर मंज़ूर कीजिए' : 'सब निपट गए' ?></div></div>
    <div class="kpi"><div class="k">चालू गाड़ियाँ</div><div class="v"><?= $n_live ?></div>
      <div class="d">आज खाली: <?= $n_free ?></div></div>
    <div class="kpi <?= $exp ? 'warn' : 'good' ?>"><div class="k">कागज़ खत्म हो रहे</div><div class="v"><?= count($exp) ?></div>
      <div class="d">45 दिन के अंदर</div></div>
  </div>

  <?php if ($exp): ?>
    <div class="err" style="margin-bottom:16px">
      <b>इन गाड़ियों के कागज़ जल्दी खत्म हो रहे हैं</b>
      <ul style="margin:6px 0 0;padding-left:18px;line-height:1.8">
        <?php foreach ($exp as $e): ?>
          <li><b><?= h($e['biz_name']) ?></b> (<?= h($e['vnumber']) ?>) —
            <?php if ($e['ins_exp'] && strtotime($e['ins_exp']) <= time() + 45 * 86400): ?>
              बीमा <?= strtotime($e['ins_exp']) < time() ? 'खत्म हो चुका' : h(date('d M', strtotime($e['ins_exp']))) . ' तक' ?><?php endif; ?>
            <?php if ($e['fit_exp'] && strtotime($e['fit_exp']) <= time() + 45 * 86400): ?>
              · फिटनेस <?= strtotime($e['fit_exp']) < time() ? 'खत्म हो चुका' : h(date('d M', strtotime($e['fit_exp']))) . ' तक' ?><?php endif; ?>
            · <a href="tel:+91<?= h($e['mobile']) ?>"><?= h($e['mobile']) ?></a></li>
        <?php endforeach; ?>
      </ul>
      <p style="margin:8px 0 0;font-size:13.5px">खत्म कागज़ वाली गाड़ी को ट्रिप मत भेजिए — दुर्घटना में ज़िम्मेदारी आप पर आ सकती है।</p>
    </div>
  <?php endif; ?>

  <div class="segs" style="max-width:460px;margin-bottom:16px">
    <a class="<?= $view === '' ? 'on' : '' ?>" href="/admin/transport.php">सब</a>
    <a class="<?= $view === 'pending' ? 'on' : '' ?>" href="/admin/transport.php?v=pending">मंज़ूरी बाकी</a>
    <a class="<?= $view === 'live' ? 'on' : '' ?>" href="/admin/transport.php?v=live">चालू</a>
    <a class="<?= $view === 'hidden' ? 'on' : '' ?>" href="/admin/transport.php?v=hidden">छिपी</a>
  </div>

  <?php if (!$rows): ?><div class="box">अभी कोई गाड़ी दर्ज नहीं है।</div><?php endif; ?>

  <?php foreach ($rows as $r):
    $fresh = avail_fresh($r);
    $papers = (int)$r['dl_ok'] + (int)$r['rc_ok'] + (int)$r['ins_ok'] + (int)$r['permit_ok']; ?>
    <div class="box" style="margin-bottom:14px">
      <div style="display:flex;gap:14px;flex-wrap:wrap">
        <div style="width:96px;height:96px;border-radius:14px;background:var(--soft);color:var(--brand);display:grid;place-items:center;overflow:hidden;flex-shrink:0">
          <?= $r['photo'] ? '<img src="/uploads/' . h($r['photo']) . '" alt="" style="width:100%;height:100%;object-fit:cover">' : svc_icon(vtype_icon($r['vtype']), 36) ?>
        </div>
        <div style="flex:1;min-width:210px">
          <div style="display:flex;gap:7px;flex-wrap:wrap;align-items:center">
            <b style="font-size:18px"><?= h($r['biz_name']) ?></b>
            <span class="tag <?= $r['status'] === 'approved' ? 'tag-live' : 'tag-off' ?>">
              <?= ['pending'=>'मंज़ूरी बाकी','approved'=>'चालू','hidden'=>'छिपी'][$r['status']] ?></span>
            <?php if ($r['verified']): ?><span class="tag tag-live">कागज़ देखे गए</span><?php endif; ?>
            <?php if ($r['status'] === 'approved'): ?>
              <span class="tag <?= ($r['available'] && $fresh) ? 'tag-live' : 'tag-off' ?>">
                <?= ($r['available'] && $fresh) ? 'आज खाली' : (!$fresh ? 'आज बताया नहीं' : 'आज व्यस्त') ?></span>
            <?php endif; ?>
            <?php if ($r['trips']): ?><span class="tag tag-gold"><?= (int)$r['trips'] ?> ट्रिप</span><?php endif; ?>
          </div>
          <div class="meta" style="margin-top:4px">
            <?= h(vtype_label($r['vtype'])) ?><?= $r['ac'] ? ' · AC' : '' ?><?= $r['seats'] ? ' · ' . (int)$r['seats'] . ' सीट' : '' ?>
            <?= $r['vnumber'] ? ' · <b>' . h($r['vnumber']) . '</b>' : '' ?>
          </div>
          <div style="margin-top:4px"><b><?= h($r['owner']) ?></b> ·
            <a href="tel:+91<?= h($r['mobile']) ?>"><?= h($r['mobile']) ?></a>
            <?= $r['mobile2'] ? ' · ' . h($r['mobile2']) : '' ?>
            <?= $r['village'] ? ' · ' . h($r['village']) : '' ?></div>
          <div class="meta" style="margin-top:4px">रेट: <?= h(rate_line($r)) ?>
            <?= $r['outstation'] ? ' · बाहर भी' : '' ?><?= $r['night'] ? ' · रात में भी' : '' ?></div>
          <?php if ($r['note']): ?><div class="meta" style="margin-top:4px">“<?= h($r['note']) ?>”</div><?php endif; ?>
        </div>
      </div>

      <div class="note" style="margin-top:12px">
        <b>कागज़ (गाड़ी वाले ने जो बताया)</b> — <?= $papers ?>/4
        <div style="margin-top:5px;font-size:14.5px">
          <?= $r['dl_ok'] ? '✓' : '✗' ?> लाइसेंस ·
          <?= $r['rc_ok'] ? '✓' : '✗' ?> RC ·
          <?= $r['ins_ok'] ? '✓' : '✗' ?> बीमा<?= $r['ins_exp'] ? ' (' . h(date('d M Y', strtotime($r['ins_exp']))) . ')' : '' ?> ·
          <?= $r['permit_ok'] ? '✓' : '✗' ?> परमिट
          <?= $r['fit_exp'] ? ' · फिटनेस ' . h(date('d M Y', strtotime($r['fit_exp']))) : '' ?>
        </div>
        <p class="help" style="margin:6px 0 0">यह सिर्फ़ इनका कहा हुआ है। <b>असली कागज़ अपनी आँख से देखिए</b>,
          फिर नीचे “कागज़ देख लिए” दबाइए। बिना देखे मंज़ूरी मत दीजिए।</p>
      </div>

      <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:12px">
        <a class="btn btn-brand btn-sm" href="tel:+91<?= h($r['mobile']) ?>">कॉल</a>
        <a class="btn btn-green btn-sm" target="_blank" rel="noopener"
           href="<?= h(wa_link($r['mobile'], "नमस्ते " . $r['owner'] . " जी, Maakit से बोल रहे हैं। आपकी गाड़ी "
              . ($r['vnumber'] ?: vtype_label($r['vtype'])) . " के कागज़ (लाइसेंस, RC, बीमा, परमिट) की फ़ोटो भेज दीजिए। देखकर आपकी गाड़ी चालू कर देंगे।")) ?>">कागज़ माँगिए</a>
        <?php if ($r['access_code']): ?>
          <a class="btn btn-gold btn-sm" target="_blank" rel="noopener"
             href="<?= h(wa_link($r['mobile'], "Maakit — आपका लॉगिन\nनंबर: " . $r['mobile'] . "\nकोड: " . $r['access_code']
                . "\nmaakit.in/transport.php पर जाकर रोज़ बता दीजिए कि गाड़ी खाली है या नहीं।")) ?>">कोड भेजिए</a>
        <?php endif; ?>
      </div>

      <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:10px">
        <?php foreach ([
          ['approve', 'मंज़ूर कीजिए', 'btn-brand', $r['status'] !== 'approved'],
          ['hide',    'छिपाइए',      'btn-line',  $r['status'] !== 'hidden'],
          ['trip',    '+1 ट्रिप',     'btn-line',  $r['status'] === 'approved'],
          ['newcode', 'नया कोड',      'btn-line',  true],
        ] as list($act, $lbl, $cls, $show)): if (!$show) continue; ?>
          <form method="post" style="display:inline">
            <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
            <input type="hidden" name="do" value="<?= h($act) ?>"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
            <button class="btn btn-sm <?= h($cls) ?>" <?= $cls === 'btn-line' ? 'style="color:var(--brand);border-color:var(--line)"' : '' ?>><?= h($lbl) ?></button>
          </form>
        <?php endforeach; ?>
        <form method="post" style="display:inline">
          <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
          <input type="hidden" name="do" value="verify"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
          <input type="hidden" name="v" value="<?= $r['verified'] ? '0' : '1' ?>">
          <button class="btn btn-sm <?= $r['verified'] ? 'btn-line' : 'btn-gold' ?>"
            <?= $r['verified'] ? 'style="color:var(--brand);border-color:var(--line)"' : '' ?>>
            <?= $r['verified'] ? 'पुष्टि हटाइए' : 'कागज़ देख लिए' ?></button>
        </form>
      </div>
    </div>
  <?php endforeach; ?>

  <div class="box" style="margin-bottom:30px">
    <b>ध्यान रखने वाली बात</b>
    <p class="help" style="margin-top:6px;line-height:1.75">
      किराए पर सवारी ले जाने के लिए कमर्शियल परमिट, चालू बीमा, फिटनेस और सही लाइसेंस ज़रूरी है।
      Maakit गाड़ी नहीं चलाता — आप सिर्फ़ ग्राहक और गाड़ी वाले को मिलाते हैं। फिर भी अगर आपने
      बिना कागज़ वाली गाड़ी को ट्रिप भेजी और कुछ हो गया, तो बात आप तक आ सकती है।<br><br>
      <b>इसलिए:</b> कागज़ की फ़ोटो WhatsApp पर मँगाइए, अपनी आँख से देखिए, तभी “कागज़ देख लिए” दबाइए।
      जिसके कागज़ नहीं हैं उसे मंज़ूरी मत दीजिए — भले वो जान-पहचान का हो।
    </p>
  </div>
</div>
</section>
<?php include __DIR__ . '/../inc/foot.php'; ?>
