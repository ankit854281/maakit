<?php
require_once __DIR__ . '/../inc/fn.php';
require_once __DIR__ . '/../inc/items.php';
require_once __DIR__ . '/../inc/services.php';
require_once __DIR__ . '/../inc/transport.php';
$u = need_role('admin');
$page_title = t('Dashboard', 'डैशबोर्ड') . ' — Maakit';

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


// ---------- har din ka hisaab (khali din bhi 0 ke saath) ----------
$days = []; $dd = strtotime($from); $de = strtotime($day);
if ($de - $dd > 120 * 86400) $dd = $de - 120 * 86400;
for ($x = $dd; $x <= $de; $x += 86400) $days[date('Y-m-d', $x)] = ['c' => 0, 's' => 0];
foreach ($series as $r) if (isset($days[$r['d']])) $days[$r['d']] = ['c' => (int)$r['c'], 's' => (int)$r['s']];
$bystatus = $pdo->prepare("SELECT status, COUNT(*) c FROM orders WHERE DATE(created_at) BETWEEN ? AND ? GROUP BY status ORDER BY c DESC");
$bystatus->execute([$from, $day]); $stat = $bystatus->fetchAll();
$recent = $pdo->query("SELECT order_no, customer_name, village, status, delivery_charge, first_order, created_at FROM orders ORDER BY id DESC LIMIT 6")->fetchAll();
$cp_tot = (int)one($pdo, "SELECT COUNT(*) FROM catalog_items");
try { $cp_done = (int)one($pdo, "SELECT COUNT(DISTINCT cat_id) FROM catalog_photos"); } catch (Throwable $e) { $cp_done = 0; }

/** SVG line + area chart */
function dash_line(array $vals, $w = 640, $h = 180, $color = '#16A34A') {
    $n = count($vals); if ($n < 2) return '';
    $max = max(1, max($vals)); $pad = 6;
    $pts = [];
    foreach (array_values($vals) as $i => $v) $pts[] = round($pad + $i * ($w - 2 * $pad) / ($n - 1), 1) . ',' . round($h - $pad - $v * ($h - 2 * $pad) / $max, 1);
    $grid = '';
    for ($g = 1; $g <= 3; $g++) { $y = round($h - $pad - $g * ($h - 2 * $pad) / 4, 1); $grid .= '<line x1="0" x2="' . $w . '" y1="' . $y . '" y2="' . $y . '" stroke="#EEF0F2"/>'; }
    $area = $pad . ',' . ($h - $pad) . ' ' . implode(' ', $pts) . ' ' . ($w - $pad) . ',' . ($h - $pad);
    return '<svg class="dz-chart" viewBox="0 0 ' . $w . ' ' . $h . '" preserveAspectRatio="none" role="img" aria-label="chart">' . $grid
        . '<polygon points="' . $area . '" fill="' . $color . '" fill-opacity=".10"/>'
        . '<polyline points="' . implode(' ', $pts) . '" fill="none" stroke="' . $color . '" stroke-width="2.2" stroke-linejoin="round" vector-effect="non-scaling-stroke"/></svg>';
}
function dash_bars(array $vals, $w = 640, $h = 180, $color = '#F2B705') {
    $n = count($vals); if (!$n) return '';
    $max = max(1, max($vals)); $bw = $w / $n; $out = '';
    foreach (array_values($vals) as $i => $v) {
        $bh = max($v ? 3 : 1, round($v * ($h - 8) / $max, 1));
        $out .= '<rect x="' . round($i * $bw + $bw * .18, 1) . '" y="' . round($h - $bh, 1) . '" width="' . round($bw * .64, 1) . '" height="' . $bh . '" rx="2" fill="' . ($v ? $color : '#EEF0F2') . '"/>';
    }
    return '<svg class="dz-chart" viewBox="0 0 ' . $w . ' ' . $h . '" preserveAspectRatio="none" role="img" aria-label="chart">' . $out . '</svg>';
}
function dash_status_label($s) { return status_hi($s); }
include __DIR__ . '/../inc/panel.php';
$avg = round($o_30 / max(1, count($days)), 1);
$rupee = fn($n) => '₹' . number_format((int)$n, 0, '.', ',');
?>
<section class="dz">
  <div class="dz-head">
    <div>
      <h1><?= t('Dashboard', 'डैशबोर्ड') ?></h1>
      <p><?= t('Business overview', 'कारोबार की झलक') ?> · <?= h(date('d M', strtotime($from))) ?> – <?= h(date('d M Y', strtotime($day))) ?></p>
    </div>
    <form method="get" class="dz-range">
      <label><span><?= t('From', 'से') ?></span><input type="date" name="from" value="<?= h($from) ?>"></label>
      <label><span><?= t('To', 'तक') ?></span><input type="date" name="day" value="<?= h($day) ?>"></label>
      <button class="mk-btn mk-btn-sm"><?= t('Apply', 'देखिए') ?></button>
    </form>
  </div>

  <div class="dz-actions">
    <a class="mk-btn mk-btn-sm" href="/update.php"><?= t('Update website', 'वेबसाइट अपडेट') ?></a>
    <a class="dz-chip" href="/admin/product-photos.php"><?= t('Product photos', 'सामान की फ़ोटो') ?> · <?= $cp_done ?>/<?= $cp_tot ?></a>
    <a class="dz-chip" href="/public/admin/approvals.php"><?= t('Partner applications', 'पार्टनर आवेदन') ?></a>
    <a class="dz-chip" href="/admin/company-delivery.php"><?= t('Company deliveries', 'कंपनी डिलीवरी') ?></a>
    <span class="dz-ver">v<?= defined('MAAKIT_VERSION') ? h(MAAKIT_VERSION) : '?' ?></span>
  </div>

  <div class="dz-kpis">
    <div class="dz-kpi"><span><?= t('Orders today', 'आज के ऑर्डर') ?></span><b><?= $o_today ?></b><small><?= $o_done ?> <?= t('delivered', 'पहुँचे') ?> · <?= $o_cancel ?> <?= t('cancelled', 'कैंसिल') ?></small></div>
    <div class="dz-kpi <?= $o_new ? 'warn' : '' ?>"><span><?= t('Waiting for a call', 'कॉल बाकी') ?></span><b><?= $o_new ?></b><small><?= $o_new ? t('Call these customers now', 'अभी कॉल कीजिए') : t('All handled', 'सब देख लिए गए') ?></small></div>
    <div class="dz-kpi good"><span><?= t('Delivery income today', 'आज डिलीवरी कमाई') ?></span><b><?= $rupee($del_today) ?></b><small><?= t('Goods value', 'सामान') ?> <?= $rupee($goods_tdy) ?></small></div>
    <div class="dz-kpi"><span><?= t('Bookings today', 'आज की बुकिंग') ?></span><b><?= $b_today ?></b><small><?= $b_new ?> <?= t('need a quote', 'का रेट बाकी') ?></small></div>
  </div>

  <div class="dz-kpis">
    <div class="dz-kpi"><span><?= t('Orders in period', 'इस दौरान ऑर्डर') ?></span><b><?= $o_30 ?></b><small><?= $avg ?> <?= t('per day', 'रोज़') ?></small></div>
    <div class="dz-kpi good"><span><?= t('Delivery income', 'डिलीवरी कमाई') ?></span><b><?= $rupee($del_30) ?></b><small><a href="/admin/summary.php"><?= t('See profit after costs', 'खर्च घटाकर देखिए') ?></a></small></div>
    <div class="dz-kpi"><span><?= t('Customers', 'ग्राहक') ?></span><b><?= $mobiles ?></b><small><?= $cust_tot ?> <?= t('accounts', 'खाते') ?> · <?= $cust_new ?> <?= t('new today', 'आज नए') ?></small></div>
    <div class="dz-kpi <?= $rep_pct >= 30 ? 'good' : '' ?>"><span><?= t('Repeat customers', 'दोबारा आए') ?></span><b><?= $rep_pct ?>%</b><small><?= $repeat ?> <?= t('ordered more than once', 'एक से ज़्यादा बार') ?></small></div>
  </div>

  <div class="dz-grid2">
    <div class="dz-card">
      <div class="dz-card-h"><b><?= t('Orders per day', 'रोज़ के ऑर्डर') ?></b><span><?= t('Peak', 'सबसे ज़्यादा') ?> <?= max(array_column($days, 'c') ?: [0]) ?></span></div>
      <?= dash_line(array_column($days, 'c')) ?: '<p class="dz-empty">' . t('No orders in this period yet', 'इस दौरान अभी कोई ऑर्डर नहीं') . '</p>' ?>
      <div class="dz-axis"><span><?= h(date('d M', strtotime(array_key_first($days)))) ?></span><span><?= h(date('d M', strtotime(array_key_last($days)))) ?></span></div>
    </div>
    <div class="dz-card">
      <div class="dz-card-h"><b><?= t('Delivery income per day', 'रोज़ की डिलीवरी कमाई') ?></b><span><?= $rupee($del_30) ?></span></div>
      <?= dash_bars(array_column($days, 's')) ?>
      <div class="dz-axis"><span><?= h(date('d M', strtotime(array_key_first($days)))) ?></span><span><?= h(date('d M', strtotime(array_key_last($days)))) ?></span></div>
    </div>
  </div>

  <div class="dz-grid3">
    <div class="dz-card">
      <div class="dz-card-h"><b><?= t('Order status', 'ऑर्डर की स्थिति') ?></b></div>
      <?php if ($stat): $ts = array_sum(array_column($stat, 'c')); ?>
        <div class="dz-stack"><?php foreach ($stat as $k => $r): ?><i class="s<?= $k % 6 ?>" style="width:<?= round($r['c'] * 100 / $ts, 2) ?>%"></i><?php endforeach; ?></div>
        <ul class="dz-legend"><?php foreach ($stat as $k => $r): ?><li><i class="s<?= $k % 6 ?>"></i><?= h(dash_status_label($r['status'])) ?><b><?= (int)$r['c'] ?></b></li><?php endforeach; ?></ul>
      <?php else: ?><p class="dz-empty"><?= t('No orders yet', 'अभी कोई ऑर्डर नहीं') ?></p><?php endif; ?>
    </div>
    <div class="dz-card">
      <div class="dz-card-h"><b><?= t('Top villages', 'कौन से गाँव से') ?></b></div>
      <?php if ($vills): $mv = max(array_column($vills, 'c')); ?>
        <div class="dz-bars"><?php foreach (array_slice($vills, 0, 7) as $v): ?>
          <div><span><?= h($v['village']) ?></span><i><em style="width:<?= max(4, round($v['c'] * 100 / $mv)) ?>%"></em></i><b><?= (int)$v['c'] ?></b></div>
        <?php endforeach; ?></div>
      <?php else: ?><p class="dz-empty"><?= t('No data yet', 'अभी आँकड़े नहीं') ?></p><?php endif; ?>
    </div>
    <div class="dz-card">
      <div class="dz-card-h"><b><?= t('Most ordered products', 'सबसे ज़्यादा माँगा सामान') ?></b></div>
      <?php if ($topitems): $mi = max($topitems); ?>
        <div class="dz-bars"><?php foreach (array_slice($topitems, 0, 7, true) as $k => $c): ?>
          <div><span title="<?= h($k) ?>"><?= h($k) ?></span><i><em style="width:<?= max(4, round($c * 100 / $mi)) ?>%"></em></i><b><?= $c ?></b></div>
        <?php endforeach; ?></div>
      <?php else: ?><p class="dz-empty"><?= t('No data yet', 'अभी आँकड़े नहीं') ?></p><?php endif; ?>
    </div>
  </div>

  <div class="dz-grid2">
    <div class="dz-card">
      <div class="dz-card-h"><b><?= t('Latest orders', 'नए ऑर्डर') ?></b><a href="/admin/"><?= t('All orders', 'सारे ऑर्डर') ?> →</a></div>
      <?php if ($recent): ?>
      <table class="dz-table">
        <tr><th><?= t('Order', 'ऑर्डर') ?></th><th><?= t('Customer', 'ग्राहक') ?></th><th><?= t('Status', 'स्थिति') ?></th><th><?= t('Charge', 'चार्ज') ?></th></tr>
        <?php foreach ($recent as $o): ?>
          <tr><td><b><?= h($o['order_no']) ?></b><small><?= h(date('d M, h:i A', strtotime($o['created_at']))) ?></small></td>
            <td><?= h($o['customer_name']) ?><small><?= h($o['village']) ?></small></td>
            <td><span class="dz-pill p-<?= h(strtolower(str_replace(' ', '', $o['status']))) ?>"><?= h(dash_status_label($o['status'])) ?></span></td>
            <td><?= $o['first_order'] ? t('Free', 'फ़्री') : $rupee($o['delivery_charge']) ?></td></tr>
        <?php endforeach; ?>
      </table>
      <?php else: ?><p class="dz-empty"><?= t('Orders will appear here as soon as they come in.', 'ऑर्डर आते ही यहाँ दिखेंगे।') ?></p><?php endif; ?>
    </div>
    <div class="dz-card">
      <div class="dz-card-h"><b><?= t('Needs your attention', 'ध्यान दीजिए') ?></b></div>
      <ul class="dz-todo">
        <?php $any = false; ?>
        <?php if ($o_new): $any = true; ?><li class="warn"><b><?= $o_new ?></b> <?= t('new orders waiting for a call', 'नए ऑर्डर कॉल का इंतज़ार कर रहे') ?> <a href="/admin/"><?= t('Open', 'देखिए') ?></a></li><?php endif; ?>
        <?php if ($b_new): $any = true; ?><li><b><?= $b_new ?></b> <?= t('bookings need a quote', 'बुकिंग का रेट बताना बाकी') ?> <a href="/bpo/bookings.php"><?= t('Open', 'देखिए') ?></a></li><?php endif; ?>
        <?php if ($tr_exp): $any = true; ?><li class="warn"><b><?= $tr_exp ?></b> <?= t('vehicle papers expire within 45 days', 'गाड़ी के कागज़ 45 दिन में खत्म') ?> <a href="/admin/transport.php"><?= t('Open', 'देखिए') ?></a></li><?php endif; ?>
        <?php if ($tr_pend): $any = true; ?><li><b><?= $tr_pend ?></b> <?= t('vehicles waiting for approval', 'गाड़ियाँ मंज़ूरी के इंतज़ार में') ?> <a href="/admin/transport.php?v=pending"><?= t('Open', 'देखिए') ?></a></li><?php endif; ?>
        <?php if ($area_new): $any = true; ?><li><b><?= $area_new ?></b> <?= t('new area requests', 'नए इलाके की माँग') ?> <a href="/admin/areas.php"><?= t('Open', 'देखिए') ?></a></li><?php endif; ?>
        <?php if ($bk_flag): $any = true; ?><li class="warn"><b><?= $bk_flag ?></b> <?= t('book complaints', 'किताब की शिकायत') ?> <a href="/admin/books.php?v=flag"><?= t('Open', 'देखिए') ?></a></li><?php endif; ?>
        <?php if ($cp_done < $cp_tot): $any = true; ?><li><b><?= $cp_tot - $cp_done ?></b> <?= t('products without photos', 'सामान बिना फ़ोटो') ?> <a href="/admin/product-photos.php?f=missing"><?= t('Add photos', 'फ़ोटो लगाइए') ?></a></li><?php endif; ?>
        <?php if (!$any): ?><li class="good"><?= t('All clear — nothing pending.', 'सब ठीक है — कुछ बाकी नहीं।') ?></li><?php endif; ?>
      </ul>
    </div>
  </div>

  <?php if ($miss || $hitq): ?>
  <div class="dz-grid2">
    <?php if ($miss): ?>
    <div class="dz-card">
      <div class="dz-card-h"><b><?= t('Searched but not found', 'ढूंढा पर नहीं मिला') ?></b><a href="/admin/items.php#naya"><?= t('Add products', 'सामान जोड़िए') ?> →</a></div>
      <table class="dz-table">
        <tr><th><?= t('Search', 'खोज') ?></th><th><?= t('Times', 'बार') ?></th><th><?= t('Last', 'आख़िरी') ?></th></tr>
        <?php foreach (array_slice($miss, 0, 8) as $m): ?><tr><td><b><?= h($m['q']) ?></b></td><td><?= (int)$m['times'] ?></td><td><small><?= h(date('d M', strtotime($m['last_at']))) ?></small></td></tr><?php endforeach; ?>
      </table>
    </div>
    <?php endif; ?>
    <?php if ($hitq || $svcs): ?>
    <div class="dz-card">
      <?php if ($hitq): ?><div class="dz-card-h"><b><?= t('Top searches', 'सबसे ज़्यादा खोजे गए') ?></b></div>
        <div class="dz-tags"><?php foreach ($hitq as $q): ?><span><?= h($q['q']) ?> <b><?= (int)$q['times'] ?></b></span><?php endforeach; ?></div><?php endif; ?>
      <?php if ($svcs): $ms = max(array_column($svcs, 'c')); ?><div class="dz-card-h" style="margin-top:14px"><b><?= t('Bookings by service', 'कौन सी बुकिंग ज़्यादा') ?></b></div>
        <div class="dz-bars"><?php foreach (array_slice($svcs, 0, 6) as $sv): $sd = service_get($sv['service']); ?>
          <div><span><?= h($sd ? $sd['name'] : $sv['service']) ?></span><i><em style="width:<?= max(4, round($sv['c'] * 100 / $ms)) ?>%"></em></i><b><?= (int)$sv['c'] ?></b></div><?php endforeach; ?></div><?php endif; ?>
    </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</section>
<?php include __DIR__ . '/../inc/foot.php'; ?>
