<?php
require_once __DIR__ . '/../inc/fn.php';
require_once __DIR__ . '/../inc/items.php';
require_once __DIR__ . '/../inc/services.php';
require_once __DIR__ . '/../inc/transport.php';
$u = need_role('admin');
$page_title = 'Dashboard — Maakit';

$day  = get('day', date('Y-m-d'));
$from = get('from', date('Y-m-d', strtotime('-29 days')));

function one(PDO $p, $sql, $a = []) { $s = $p->prepare($sql); $s->execute($a); $r = $s->fetch(); return $r ? array_values($r)[0] : 0; }

// ---------- aaj ----------
$o_today   = (int)one($pdo, "SELECT COUNT(*) FROM orders WHERE DATE(created_at)=?", [$day]);
$o_new     = (int)one($pdo, "SELECT COUNT(*) FROM orders WHERE DATE(created_at)=? AND status='Naya'", [$day]);
$o_done    = (int)one($pdo, "SELECT COUNT(*) FROM orders WHERE DATE(created_at)=? AND status IN ('Delivered','Paisa jama')", [$day]);
$o_cancel  = (int)one($pdo, "SELECT COUNT(*) FROM orders WHERE DATE(created_at)=? AND status='Cancel'", [$day]);
$del_today = (int)one($pdo, "SELECT COALESCE(SUM(delivery_charge),0) FROM orders WHERE DATE(created_at)=? AND status IN ('Delivered','Paisa jama')", [$day]);
$goods_tdy = (int)one($pdo, "SELECT COALESCE(SUM(goods_amount),0) FROM orders WHERE DATE(created_at)=? AND status<>'Cancel'", [$day]);
$b_today   = (int)one($pdo, "SELECT COUNT(*) FROM service_bookings WHERE DATE(created_at)=?", [$day]);
$b_new     = (int)one($pdo, "SELECT COUNT(*) FROM service_bookings WHERE status='Naya'");
$quote_tdy = (int)one($pdo, "SELECT COALESCE(SUM(quote),0) FROM service_bookings WHERE DATE(created_at)=? AND status IN ('Confirm','Done')", [$day]);

// ---------- kul ----------
$cust_tot  = (int)one($pdo, "SELECT COUNT(*) FROM customers");
$cust_new  = (int)one($pdo, "SELECT COUNT(*) FROM customers WHERE DATE(created_at)=?", [$day]);
$repeat    = (int)one($pdo, "SELECT COUNT(*) FROM (SELECT mobile FROM orders WHERE status<>'Cancel' GROUP BY mobile HAVING COUNT(*)>1) x");
$mobiles   = (int)one($pdo, "SELECT COUNT(DISTINCT mobile) FROM orders WHERE status<>'Cancel'");
$rep_pct   = $mobiles ? round($repeat * 100 / $mobiles) : 0;
$area_new  = (int)one($pdo, "SELECT COUNT(*) FROM area_requests WHERE status='new'");
$ph_done   = (int)one($pdo, "SELECT COUNT(*) FROM items WHERE photo IS NOT NULL AND photo<>''");
$ph_tot    = (int)one($pdo, "SELECT COUNT(*) FROM items");
try {
    $bk_flag = (int)one($pdo, "SELECT COUNT(*) FROM books WHERE reports > 0");
    $bk_aaj  = (int)one($pdo, "SELECT COUNT(*) FROM books WHERE created_at >= CURDATE()");
} catch (Throwable $e) { $bk_flag = 0; $bk_aaj = 0; }
$tr_pend   = (int)one($pdo, "SELECT COUNT(*) FROM transports WHERE status='pending'");
$tr_live   = (int)one($pdo, "SELECT COUNT(*) FROM transports WHERE status='approved'");
$tr_free   = (int)one($pdo, "SELECT COUNT(*) FROM transports WHERE status='approved' AND available=1 AND avail_updated > NOW() - INTERVAL 36 HOUR");
$tr_exp    = count(papers_expiring($pdo, 45));

// ---------- 30 din ----------
$byday = $pdo->prepare("SELECT DATE(created_at) d, COUNT(*) c, COALESCE(SUM(CASE WHEN status IN ('Delivered','Paisa jama') THEN delivery_charge ELSE 0 END),0) s
                        FROM orders WHERE DATE(created_at) BETWEEN ? AND ? AND status<>'Cancel'
                        GROUP BY DATE(created_at) ORDER BY d");
$byday->execute([$from, $day]);
$series = $byday->fetchAll();
$o_30   = array_sum(array_column($series, 'c'));
$del_30 = array_sum(array_column($series, 's'));

$byvill = $pdo->prepare("SELECT village, COUNT(*) c, COALESCE(SUM(CASE WHEN status IN ('Delivered','Paisa jama') THEN delivery_charge ELSE 0 END),0) s
                         FROM orders WHERE DATE(created_at) BETWEEN ? AND ? AND status<>'Cancel'
                         GROUP BY village ORDER BY c DESC LIMIT 12");
$byvill->execute([$from, $day]);
$vills = $byvill->fetchAll();

$bysvc = $pdo->prepare("SELECT service, COUNT(*) c FROM service_bookings
                        WHERE DATE(created_at) BETWEEN ? AND ? GROUP BY service ORDER BY c DESC");
$bysvc->execute([$from, $day]);
$svcs = $bysvc->fetchAll();

// ---------- log kya dhoondh rahe hain par mil nahi raha ----------
try {
    $miss = $pdo->query("SELECT q, times, last_at FROM search_log WHERE hits=0 ORDER BY times DESC, last_at DESC LIMIT 20")->fetchAll();
    $hitq = $pdo->query("SELECT q, times FROM search_log WHERE hits>0 ORDER BY times DESC LIMIT 10")->fetchAll();
} catch (Throwable $e) { $miss = $hitq = []; }

// ---------- kaun se saaman sabse zyada ----------
$topitems = [];
try {
    $rows = $pdo->prepare("SELECT items_json FROM orders WHERE items_json IS NOT NULL AND DATE(created_at) BETWEEN ? AND ? LIMIT 800");
    $rows->execute([$from, $day]);
    $cnt = [];
    foreach ($rows as $r) {
        foreach ((json_decode($r['items_json'], true) ?: []) as $l) {
            $k = ($l['name'] ?? '') . ' · ' . ($l['unit'] ?? '');
            if (trim($k) === ' · ') continue;
            $cnt[$k] = ($cnt[$k] ?? 0) + (int)($l['qty'] ?? $l['q'] ?? 1);
        }
    }
    arsort($cnt);
    $topitems = array_slice($cnt, 0, 12, true);
} catch (Throwable $e) {}

include __DIR__ . '/../inc/panel.php';
$mxd = max(1, max(array_column($series, 'c') ?: [1]));
?>
<section>
<div class="wrap">
  <div style="display:flex;align-items:baseline;gap:10px;flex-wrap:wrap">
    <h2 style="margin:0">Dashboard</h2>
    <a class="btn btn-line btn-sm" href="/public/admin/approvals.php"><?= t('Partner applications','पार्टनर आवेदन') ?></a>
    <span class="tag tag-gold">Version <?= defined('MAAKIT_VERSION') ? h(MAAKIT_VERSION) : '?' ?><?= defined('MAAKIT_VERSION_DATE') ? ' · ' . h(MAAKIT_VERSION_DATE) : '' ?></span>
  </div>
  <form method="get" style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end;margin-bottom:16px">
    <div><label>दिन</label><input type="date" name="day" value="<?= h($day) ?>" style="max-width:180px"></div>
    <div><label>से</label><input type="date" name="from" value="<?= h($from) ?>" style="max-width:180px"></div>
    <button class="btn btn-brand btn-sm">देखिए</button>
  </form>

  <h3 class="ghead" style="margin-top:0">आज — <?= h(date('d M Y', strtotime($day))) ?></h3>
  <div class="kpis">
    <div class="kpi"><div class="k"><?= svc_icon('box', 15) ?> ऑर्डर</div><div class="v"><?= $o_today ?></div>
      <div class="d"><?= $o_done ?> पहुँचे · <?= $o_cancel ?> कैंसिल</div></div>
    <div class="kpi <?= $o_new ? 'warn' : '' ?>"><div class="k"><?= svc_icon('clock', 15) ?> देखना बाकी</div><div class="v"><?= $o_new ?></div>
      <div class="d"><?= $o_new ? 'अभी कॉल कीजिए' : 'सब देख लिए गए' ?></div></div>
    <div class="kpi good"><div class="k"><?= svc_icon('rupee', 15) ?> डिलीवरी से</div><div class="v">₹<?= $del_today ?></div>
      <div class="d">सामान ₹<?= $goods_tdy ?> का गया</div></div>
    <div class="kpi"><div class="k"><?= svc_icon('all', 15) ?> बुकिंग</div><div class="v"><?= $b_today ?></div>
      <div class="d"><?= $b_new ?> नई देखनी है<?= $quote_tdy ? ' · ₹' . $quote_tdy . ' तय' : '' ?></div></div>
  </div>

  <h3 class="ghead"><?= h(date('d M', strtotime($from))) ?> से आज तक</h3>
  <div class="kpis">
    <div class="kpi"><div class="k"><?= svc_icon('box', 15) ?> कुल ऑर्डर</div><div class="v"><?= $o_30 ?></div>
      <div class="d">रोज़ औसत <?= round($o_30 / max(1, (strtotime($day) - strtotime($from)) / 86400 + 1), 1) ?></div></div>
    <div class="kpi good"><div class="k"><?= svc_icon('rupee', 15) ?> डिलीवरी कमाई</div><div class="v">₹<?= $del_30 ?></div>
      <div class="d"><a href="/admin/summary.php">खर्च घटाकर हिसाब देखिए</a></div></div>
    <div class="kpi"><div class="k"><?= svc_icon('user', 15) ?> ग्राहक</div><div class="v"><?= $mobiles ?></div>
      <div class="d"><?= $cust_tot ?> का खाता · आज <?= $cust_new ?> नए</div></div>
    <div class="kpi <?= $rep_pct >= 30 ? 'good' : '' ?>"><div class="k"><?= svc_icon('shield', 15) ?> दोबारा आए</div><div class="v"><?= $rep_pct ?>%</div>
      <div class="d"><?= $repeat ?> लोग एक से ज़्यादा बार</div></div>
  </div>

  <?php if ($tr_live || $tr_pend): ?>
    <h3 class="ghead">गाड़ियाँ</h3>
    <div class="kpis">
      <div class="kpi"><div class="k">जुड़ी गाड़ियाँ</div><div class="v"><?= $tr_live ?></div>
        <div class="d"><?= $tr_pend ?> मंज़ूरी बाकी</div></div>
      <div class="kpi <?= $tr_free ? 'good' : 'warn' ?>"><div class="k">आज खाली</div><div class="v"><?= $tr_free ?></div>
        <div class="d"><?= $tr_free ? 'बुकिंग पेज पर दिख रही हैं' : 'किसी ने आज बताया नहीं' ?></div></div>
      <div class="kpi <?= $tr_exp ? 'warn' : '' ?>"><div class="k">कागज़ खत्म हो रहे</div><div class="v"><?= $tr_exp ?></div>
        <div class="d">45 दिन के अंदर</div></div>
    </div>
  <?php endif; ?>

  <?php if ($area_new || $ph_done < $ph_tot || $tr_pend || $tr_exp || $bk_flag || $bk_aaj): ?>
    <div class="box" style="margin-bottom:18px">
      <b>ध्यान देने वाली बातें</b>
      <ul style="margin:8px 0 0;padding-left:18px;line-height:1.9;font-size:15px">
        <?php if ($tr_exp): ?><li style="color:var(--bad)"><b><?= $tr_exp ?></b> गाड़ी के कागज़ जल्दी खत्म हो रहे हैं —
          <a href="/admin/transport.php">देखिए</a></li><?php endif; ?>
        <?php if ($tr_pend): ?><li><b><?= $tr_pend ?></b> गाड़ी मंज़ूरी का इंतज़ार कर रही है —
          <a href="/admin/transport.php?v=pending">कागज़ देखिए</a></li><?php endif; ?>
        <?php if ($area_new): ?><li><b><?= $area_new ?></b> नए गाँव से माँग आई है —
          <a href="/admin/areas.php">देखिए</a></li><?php endif; ?>
        <?php if ($bk_flag): ?><li><b><?= $bk_flag ?></b> किताब की शिकायत आई है —
          <a href="/admin/books.php?v=flag">अभी देखिए</a></li><?php endif; ?>
        <?php if ($bk_aaj): ?><li><b><?= $bk_aaj ?></b> नई किताब आज डाली गई —
          <a href="/admin/books.php">देखिए</a></li><?php endif; ?>
        <?php if ($ph_done < $ph_tot): ?><li><b><?= $ph_tot - $ph_done ?></b> सामान की फ़ोटो लगनी बाकी है —
          <a href="/admin/photos.php">एक साथ लगाइए</a></li><?php endif; ?>
        <?php if ($b_new): ?><li><b><?= $b_new ?></b> बुकिंग का रेट बताना बाकी है —
          <a href="/bpo/bookings.php">देखिए</a></li><?php endif; ?>
      </ul>
    </div>
  <?php endif; ?>

  <?php if ($series): ?>
    <h3 class="ghead">रोज़ के ऑर्डर</h3>
    <div class="box" style="margin-bottom:18px">
      <div style="display:flex;align-items:flex-end;gap:3px;height:120px">
        <?php foreach ($series as $s): ?>
          <div title="<?= h(date('d M', strtotime($s['d']))) ?> — <?= (int)$s['c'] ?> ऑर्डर, ₹<?= (int)$s['s'] ?>"
               style="flex:1;min-width:4px;height:<?= max(4, round($s['c'] * 100 / $mxd)) ?>%;background:var(--brand);border-radius:4px 4px 0 0"></div>
        <?php endforeach; ?>
      </div>
      <div style="display:flex;justify-content:space-between;font-size:12.5px;color:var(--muted);margin-top:6px">
        <span><?= h(date('d M', strtotime($series[0]['d']))) ?></span>
        <span>सबसे ज़्यादा <?= $mxd ?> / दिन</span>
        <span><?= h(date('d M', strtotime(end($series)['d']))) ?></span>
      </div>
    </div>
  <?php endif; ?>

  <div class="grid g2" style="align-items:start">
    <?php if ($vills): ?>
    <div class="box">
      <b>कौन से गाँव से कितना</b>
      <div class="bars" style="margin-top:10px">
        <?php $mv = max(array_column($vills, 'c')); foreach ($vills as $v): ?>
          <div class="bar"><span class="nm"><?= h($v['village']) ?></span>
            <span class="tr2"><span style="width:<?= max(6, round($v['c'] * 100 / $mv)) ?>%"></span></span>
            <span class="vv"><?= (int)$v['c'] ?></span></div>
        <?php endforeach; ?>
      </div>
      <p class="help" style="margin-top:10px">जहाँ सबसे कम है वहाँ पोस्टर लगवाइए।</p>
    </div>
    <?php endif; ?>

    <?php if ($topitems): ?>
    <div class="box">
      <b>सबसे ज़्यादा माँगा जाने वाला सामान</b>
      <div class="bars" style="margin-top:10px">
        <?php $mi = max($topitems); foreach ($topitems as $k => $c): ?>
          <div class="bar"><span class="nm" style="width:140px"><?= h($k) ?></span>
            <span class="tr2"><span style="width:<?= max(6, round($c * 100 / $mi)) ?>%"></span></span>
            <span class="vv"><?= $c ?></span></div>
        <?php endforeach; ?>
      </div>
      <p class="help" style="margin-top:10px">Phase 2 में यही सामान अपने स्टॉक में रखिए।</p>
    </div>
    <?php endif; ?>
  </div>

  <?php if ($miss): ?>
    <h3 class="ghead">लोगों ने ढूंढा, पर मिला नहीं</h3>
    <div class="box" style="margin-bottom:18px">
      <p class="help" style="margin:0 0 10px">यह सबसे काम की लिस्ट है — जो लोग ढूंढ रहे हैं और हमारे पास नहीं है।
        इन्हें <a href="/admin/items.php#naya">सामान की लिस्ट में जोड़िए</a>।</p>
      <div class="tablewrap">
        <table>
          <tr><th>क्या ढूंढा</th><th>कितनी बार</th><th>आख़िरी बार</th><th></th></tr>
          <?php foreach ($miss as $m): ?>
            <tr><td><b><?= h($m['q']) ?></b></td><td><?= (int)$m['times'] ?></td>
              <td><?= h(date('d M, h:i A', strtotime($m['last_at']))) ?></td>
              <td><a href="/admin/items.php#naya">जोड़िए</a></td></tr>
          <?php endforeach; ?>
        </table>
      </div>
    </div>
  <?php endif; ?>

  <div class="grid g2" style="align-items:start;margin-bottom:30px">
    <?php if ($svcs): ?>
    <div class="box">
      <b>कौन सी बुकिंग ज़्यादा</b>
      <div class="bars" style="margin-top:10px">
        <?php $ms = max(array_column($svcs, 'c')); foreach ($svcs as $sv): $sd = service_get($sv['service']); ?>
          <div class="bar"><span class="nm"><?= h($sd ? $sd['name'] : $sv['service']) ?></span>
            <span class="tr2"><span style="width:<?= max(6, round($sv['c'] * 100 / $ms)) ?>%"></span></span>
            <span class="vv"><?= (int)$sv['c'] ?></span></div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($hitq): ?>
    <div class="box">
      <b>सबसे ज़्यादा ढूंढे गए शब्द</b>
      <div class="chips" style="margin-top:10px">
        <?php foreach ($hitq as $q): ?>
          <span class="chip"><?= h($q['q']) ?> · <?= (int)$q['times'] ?></span>
        <?php endforeach; ?>
      </div>
      <p class="help" style="margin-top:10px">इन्हीं शब्दों को अपने पोस्टर और WhatsApp मैसेज में लिखिए।</p>
    </div>
    <?php endif; ?>
  </div>
</div>
</section>
<?php include __DIR__ . '/../inc/foot.php'; ?>
