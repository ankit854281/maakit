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

// ---------- pichhle utne hi dinon se tulna ----------
$plen  = count($days);
$pfrom = date('Y-m-d', strtotime(array_key_first($days) . " -$plen days"));
$pto   = date('Y-m-d', strtotime(array_key_first($days) . ' -1 day'));
$rng   = [array_key_first($days), $day];
$o_prev   = (int)one($pdo, "SELECT COUNT(*) FROM orders WHERE DATE(created_at) BETWEEN ? AND ? AND status<>'Cancel'", [$pfrom, $pto]);
$del_prev = (int)one($pdo, "SELECT COALESCE(SUM(delivery_charge),0) FROM orders WHERE DATE(created_at) BETWEEN ? AND ? AND status IN ('Delivered','Paisa jama')", [$pfrom, $pto]);
$aov      = (float)one($pdo, "SELECT COALESCE(AVG(NULLIF(goods_amount,0)),0) FROM orders WHERE DATE(created_at) BETWEEN ? AND ? AND status<>'Cancel'", $rng);
$aov_prev = (float)one($pdo, "SELECT COALESCE(AVG(NULLIF(goods_amount,0)),0) FROM orders WHERE DATE(created_at) BETWEEN ? AND ? AND status<>'Cancel'", [$pfrom, $pto]);
$cn_now   = (int)one($pdo, "SELECT COUNT(*) FROM customers WHERE DATE(created_at) BETWEEN ? AND ?", $rng);
$cn_prev  = (int)one($pdo, "SELECT COUNT(*) FROM customers WHERE DATE(created_at) BETWEEN ? AND ?", [$pfrom, $pto]);
$all_rng  = (int)one($pdo, "SELECT COUNT(*) FROM orders WHERE DATE(created_at) BETWEEN ? AND ?", $rng);
$can_rng  = (int)one($pdo, "SELECT COUNT(*) FROM orders WHERE DATE(created_at) BETWEEN ? AND ? AND status='Cancel'", $rng);
$cancel_pct = $all_rng ? round($can_rng * 100 / $all_rng) : 0;
$b_rng    = (int)one($pdo, "SELECT COUNT(*) FROM service_bookings WHERE DATE(created_at) BETWEEN ? AND ?", $rng);

$cust_days = array_fill_keys(array_keys($days), 0);
$q = $pdo->prepare("SELECT DATE(created_at) d, COUNT(*) c FROM customers WHERE DATE(created_at) BETWEEN ? AND ? GROUP BY DATE(created_at)");
$q->execute($rng); foreach ($q as $r) if (isset($cust_days[$r['d']])) $cust_days[$r['d']] = (int)$r['c'];
$aov_days = array_fill_keys(array_keys($days), 0);
$q = $pdo->prepare("SELECT DATE(created_at) d, AVG(NULLIF(goods_amount,0)) a FROM orders WHERE DATE(created_at) BETWEEN ? AND ? AND status<>'Cancel' GROUP BY DATE(created_at)");
$q->execute($rng); foreach ($q as $r) if (isset($aov_days[$r['d']])) $aov_days[$r['d']] = (float)$r['a'];

$hours = array_fill(0, 24, 0);
$q = $pdo->prepare("SELECT HOUR(created_at) h, COUNT(*) c FROM orders WHERE DATE(created_at) BETWEEN ? AND ? AND status<>'Cancel' GROUP BY HOUR(created_at)");
$q->execute($rng); foreach ($q as $r) $hours[(int)$r['h']] = (int)$r['c'];
$wdays = array_fill(1, 7, 0);
$q = $pdo->prepare("SELECT DAYOFWEEK(created_at) w, COUNT(*) c FROM orders WHERE DATE(created_at) BETWEEN ? AND ? AND status<>'Cancel' GROUP BY DAYOFWEEK(created_at)");
$q->execute($rng); foreach ($q as $r) $wdays[(int)$r['w']] = (int)$r['c'];

// ---------- catalogue aur saathi ----------
$cat_priced = 0; $shops_ok = 0; $riders = 0;
try { $cat_priced = (int)one($pdo, "SELECT COUNT(DISTINCT cat_id) FROM shop_items WHERE active=1 AND price>0 AND cat_id IS NOT NULL"); } catch (Throwable $e) {}
try { $shops_ok = (int)one($pdo, "SELECT COUNT(*) FROM businesses WHERE status='approved'"); } catch (Throwable $e) {}
try { $riders = (int)one($pdo, "SELECT COUNT(*) FROM users WHERE role IN ('delivery','rider') AND active=1"); } catch (Throwable $e) {}

function dash_delta($now, $prev) {
    if (!$prev) return $now ? ['up', t('new', 'नया')] : ['flat', '—'];
    $p = round(($now - $prev) * 100 / $prev);
    return [$p > 0 ? 'up' : ($p < 0 ? 'down' : 'flat'), ($p > 0 ? '+' : '') . $p . '%'];
}
function dash_spark(array $vals, $color = '#16A34A') {
    $vals = array_values($vals); $n = count($vals); if ($n < 2) return '';
    $max = max(1, max($vals)); $w = 120; $h = 34; $pts = [];
    foreach ($vals as $i => $v) $pts[] = round($i * $w / ($n - 1), 1) . ',' . round($h - 2 - $v * ($h - 4) / $max, 1);
    return '<svg class="dz-spark" viewBox="0 0 ' . $w . ' ' . $h . '" preserveAspectRatio="none" aria-hidden="true"><polyline points="0,' . $h . ' ' . implode(' ', $pts) . ' ' . $w . ',' . $h . '" fill="' . $color . '" fill-opacity=".10" stroke="none"/><polyline points="' . implode(' ', $pts) . '" fill="none" stroke="' . $color . '" stroke-width="1.6" vector-effect="non-scaling-stroke"/></svg>';
}
function dash_combo(array $orders, array $income, $w = 760, $h = 220) {
    $n = count($orders); if ($n < 2) return '';
    $orders = array_values($orders); $income = array_values($income);
    $mo = max(1, max($orders)); $mi = max(1, max($income)); $bw = $w / $n; $pad = 8; $out = '';
    for ($g = 1; $g <= 4; $g++) { $y = round($h - $g * ($h - $pad) / 4, 1); $out .= '<line x1="0" x2="' . $w . '" y1="' . $y . '" y2="' . $y . '" stroke="#F0F1F3"/>'; }
    foreach ($income as $i => $v) { $bh = round($v * ($h - $pad) / $mi, 1); if ($bh > 0) $out .= '<rect x="' . round($i * $bw + $bw * .2, 1) . '" y="' . round($h - $bh, 1) . '" width="' . round($bw * .6, 1) . '" height="' . $bh . '" rx="2" fill="#FCD34D" fill-opacity=".85"/>'; }
    $pts = [];
    foreach ($orders as $i => $v) $pts[] = round($i * $bw + $bw / 2, 1) . ',' . round($h - 2 - $v * ($h - $pad - 4) / $mo, 1);
    $out .= '<polyline points="' . implode(' ', $pts) . '" fill="none" stroke="#16A34A" stroke-width="2.2" stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke"/>';
    return '<svg class="dz-combo" viewBox="0 0 ' . $w . ' ' . $h . '" preserveAspectRatio="none" role="img" aria-label="' . h(t('Orders and income per day', 'रोज़ के ऑर्डर और कमाई')) . '">' . $out . '</svg>';
}
function dash_donut(array $parts, $size = 132) {
    $tot = array_sum($parts); if (!$tot) return '';
    $r = 52; $c = 2 * M_PI * $r; $off = 0; $out = ''; $k = 0;
    foreach ($parts as $v) { $len = $v / $tot * $c; $out .= '<circle class="s' . ($k++ % 6) . 'c" cx="66" cy="66" r="' . $r . '" fill="none" stroke-width="16" stroke-dasharray="' . round($len, 2) . ' ' . round($c - $len, 2) . '" stroke-dashoffset="' . round(-$off, 2) . '" transform="rotate(-90 66 66)"/>'; $off += $len; }
    return '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 132 132" aria-hidden="true"><circle cx="66" cy="66" r="52" fill="none" stroke="#F3F4F6" stroke-width="16"/>' . $out . '<text x="66" y="62" text-anchor="middle" class="dz-dn-v">' . $tot . '</text><text x="66" y="80" text-anchor="middle" class="dz-dn-l">' . h(t('orders', 'ऑर्डर')) . '</text></svg>';
}
function dash_minibars(array $vals, array $labels, $color = '#16A34A') {
    $max = max(1, max($vals)); $out = '<div class="dz-mb">';
    foreach (array_values($vals) as $i => $v) $out .= '<div title="' . h($labels[$i]) . ': ' . $v . '"><i style="height:' . max(3, round($v * 100 / $max)) . '%;background:' . ($v ? $color : '#EEF0F2') . '"></i><span>' . h($labels[$i]) . '</span></div>';
    return $out . '</div>';
}
include __DIR__ . '/../inc/panel.php';
$rupee = fn($n) => '₹' . number_format((float)$n, 0, '.', ',');
[$d1c, $d1t] = dash_delta($o_30, $o_prev);
[$d2c, $d2t] = dash_delta($del_30, $del_prev);
[$d3c, $d3t] = dash_delta($cn_now, $cn_prev);
[$d4c, $d4t] = dash_delta(round($aov), round($aov_prev));
$hl = []; for ($x = 0; $x < 24; $x++) $hl[] = ($x % 6 === 0) ? date('ga', mktime($x, 0)) : '';
$wl = [t('Sun','रवि'), t('Mon','सोम'), t('Tue','मंगल'), t('Wed','बुध'), t('Thu','गुरु'), t('Fri','शुक्र'), t('Sat','शनि')];
$hlabels = []; for ($x = 0; $x < 24; $x++) $hlabels[] = $x % 6 === 0 ? date('ga', mktime($x, 0)) : ' ';
$peakHour = array_search(max($hours), $hours);
?>
<section class="dz">
  <div class="dz-head">
    <div>
      <h1><?= t('Dashboard', 'डैशबोर्ड') ?></h1>
      <p><?= h(date('d M', strtotime(array_key_first($days)))) ?> – <?= h(date('d M Y', strtotime($day))) ?> · <?= t('compared with the previous', 'पिछले') ?> <?= $plen ?> <?= t('days', 'दिनों से तुलना') ?></p>
    </div>
    <form method="get" class="dz-range">
      <?php foreach ([7, 30, 90] as $nd): $f = date('Y-m-d', strtotime($day . ' -' . ($nd - 1) . ' days')); ?>
        <a class="dz-pick<?= $f === array_key_first($days) ? ' on' : '' ?>" href="?from=<?= $f ?>&day=<?= h($day) ?>"><?= $nd ?>D</a>
      <?php endforeach; ?>
      <input type="date" name="from" value="<?= h($from) ?>" aria-label="<?= h(t('From', 'से')) ?>">
      <input type="date" name="day" value="<?= h($day) ?>" aria-label="<?= h(t('To', 'तक')) ?>">
      <button class="dz-btn"><?= t('Apply', 'देखिए') ?></button>
      <a class="dz-btn dz-btn-p" href="/update.php"><?= t('Update website', 'वेबसाइट अपडेट') ?></a>
    </form>
  </div>

  <div class="dz-kpis">
    <div class="dz-kpi"><div class="dz-k-top"><span class="dz-ic g"><?= adm_icon('box') ?></span><?= t('Orders', 'ऑर्डर') ?><em class="<?= $d1c ?>"><?= $d1t ?></em></div>
      <b><?= num($o_30) ?></b><small><?= round($o_30 / max(1, $plen), 1) ?> <?= t('per day', 'रोज़') ?> · <?= $cancel_pct ?>% <?= t('cancelled', 'कैंसिल') ?></small><?= dash_spark(array_column($days, 'c')) ?></div>
    <div class="dz-kpi"><div class="dz-k-top"><span class="dz-ic y"><?= adm_icon('rupee') ?></span><?= t('Delivery income', 'डिलीवरी कमाई') ?><em class="<?= $d2c ?>"><?= $d2t ?></em></div>
      <b><?= $rupee($del_30) ?></b><small><a href="/admin/summary.php"><?= t('Profit after costs', 'खर्च घटाकर मुनाफ़ा') ?> →</a></small><?= dash_spark(array_column($days, 's'), '#D97706') ?></div>
    <div class="dz-kpi"><div class="dz-k-top"><span class="dz-ic b"><?= adm_icon('user') ?></span><?= t('New customers', 'नए ग्राहक') ?><em class="<?= $d3c ?>"><?= $d3t ?></em></div>
      <b><?= num($cn_now) ?></b><small><?= num($mobiles) ?> <?= t('ordering customers', 'ऑर्डर करने वाले') ?> · <?= $rep_pct ?>% <?= t('repeat', 'दोबारा') ?></small><?= dash_spark($cust_days, '#2563EB') ?></div>
    <div class="dz-kpi"><div class="dz-k-top"><span class="dz-ic p"><?= adm_icon('bag') ?></span><?= t('Avg. order value', 'औसत ऑर्डर') ?><em class="<?= $d4c ?>"><?= $d4t ?></em></div>
      <b><?= $rupee($aov) ?></b><small><?= t('goods per order', 'प्रति ऑर्डर सामान') ?> · <?= num($b_rng) ?> <?= t('bookings', 'बुकिंग') ?></small><?= dash_spark($aov_days, '#7C3AED') ?></div>
  </div>

  <div class="dz-today">
    <span class="dz-t-l"><?= t('Today', 'आज') ?></span>
    <span><b><?= $o_today ?></b> <?= t('orders', 'ऑर्डर') ?></span>
    <span class="<?= $o_new ? 'warn' : '' ?>"><b><?= $o_new ?></b> <?= t('waiting for call', 'कॉल बाकी') ?></span>
    <span><b><?= $o_done ?></b> <?= t('delivered', 'पहुँचे') ?></span>
    <span><b><?= $rupee($del_today) ?></b> <?= t('income', 'कमाई') ?></span>
    <span><b><?= $b_today ?></b> <?= t('bookings', 'बुकिंग') ?></span>
    <span><b><?= $cust_new ?></b> <?= t('new customers', 'नए ग्राहक') ?></span>
  </div>

  <div class="dz-row dz-r-21">
    <div class="dz-card">
      <div class="dz-card-h"><b><?= t('Orders & income', 'ऑर्डर और कमाई') ?></b>
        <span class="dz-key"><i class="k-line"></i><?= t('Orders', 'ऑर्डर') ?> <i class="k-bar"></i><?= t('Income', 'कमाई') ?></span></div>
      <?= dash_combo(array_column($days, 'c'), array_column($days, 's')) ?>
      <div class="dz-axis"><span><?= h(date('d M', strtotime(array_key_first($days)))) ?></span><span><?= h(date('d M', strtotime(array_key_first($days) . ' +' . intdiv($plen, 2) . ' days'))) ?></span><span><?= h(date('d M', strtotime(array_key_last($days)))) ?></span></div>
    </div>
    <div class="dz-card">
      <div class="dz-card-h"><b><?= t('Order status', 'ऑर्डर की स्थिति') ?></b></div>
      <?php if ($stat): ?>
        <div class="dz-donut"><?= dash_donut(array_column($stat, 'c')) ?>
          <ul class="dz-legend"><?php foreach ($stat as $k => $r): ?><li><i class="s<?= $k % 6 ?>"></i><?= h(dash_status_label($r['status'])) ?><b><?= (int)$r['c'] ?></b></li><?php endforeach; ?></ul></div>
      <?php else: ?><p class="dz-empty"><?= t('No orders in this period', 'इस दौरान कोई ऑर्डर नहीं') ?></p><?php endif; ?>
    </div>
  </div>

  <div class="dz-row dz-r-3">
    <div class="dz-card">
      <div class="dz-card-h"><b><?= t('Orders by hour', 'किस समय ऑर्डर') ?></b><span><?= max($hours) ? t('Busiest', 'सबसे ज़्यादा') . ' ' . date('g A', mktime($peakHour, 0)) : '' ?></span></div>
      <?= dash_minibars($hours, $hlabels) ?>
    </div>
    <div class="dz-card">
      <div class="dz-card-h"><b><?= t('Orders by weekday', 'किस दिन ऑर्डर') ?></b></div>
      <?= dash_minibars(array_values($wdays), $wl, '#D97706') ?>
    </div>
    <div class="dz-card">
      <div class="dz-card-h"><b><?= t('Catalogue & partners', 'सामान और साथी') ?></b><a href="/admin/product-photos.php?f=missing"><?= t('Add photos', 'फ़ोटो लगाइए') ?> →</a></div>
      <?php foreach ([[t('Products with photos', 'फ़ोटो वाला सामान'), $cp_done, $cp_tot], [t('Products with a shop price', 'दुकान के दाम वाला सामान'), $cat_priced, $cp_tot]] as [$lbl, $a, $b]): $pc = $b ? round($a * 100 / $b) : 0; ?>
        <div class="dz-prog"><div><span><?= h($lbl) ?></span><b><?= num($a) ?> / <?= num($b) ?></b></div><i><em style="width:<?= max(1, $pc) ?>%"></em></i></div>
      <?php endforeach; ?>
      <div class="dz-mini3">
        <a href="/admin/businesses.php"><b><?= num($shops_ok) ?></b><?= t('shops', 'दुकानें') ?></a>
        <a href="/admin/transport.php"><b><?= num($tr_live) ?></b><?= t('vehicles', 'गाड़ियाँ') ?> <small>(<?= $tr_free ?> <?= t('free', 'खाली') ?>)</small></a>
        <a href="/admin/sarathi.php"><b><?= num($riders) ?></b><?= t('riders', 'राइडर') ?></a>
      </div>
    </div>
  </div>

  <div class="dz-row dz-r-21">
    <div class="dz-card dz-scroll">
      <div class="dz-card-h"><b><?= t('Latest orders', 'नए ऑर्डर') ?></b><a href="/admin/"><?= t('View all', 'सब देखिए') ?> →</a></div>
      <?php if ($recent): ?>
      <table class="dz-table">
        <tr><th><?= t('Order', 'ऑर्डर') ?></th><th><?= t('Customer', 'ग्राहक') ?></th><th><?= t('Village', 'गाँव') ?></th><th><?= t('Status', 'स्थिति') ?></th><th class="r"><?= t('Charge', 'चार्ज') ?></th><th class="r"><?= t('Time', 'समय') ?></th></tr>
        <?php foreach ($recent as $o): ?>
          <tr><td><b><?= h($o['order_no']) ?></b></td><td><?= h($o['customer_name']) ?></td><td><?= h($o['village']) ?></td>
            <td><span class="dz-pill p-<?= h(strtolower(str_replace(' ', '', $o['status']))) ?>"><?= h(dash_status_label($o['status'])) ?></span></td>
            <td class="r"><?= $o['first_order'] ? t('Free', 'फ़्री') : $rupee($o['delivery_charge']) ?></td><td class="r dz-mut"><?= h(date('d M, g:i A', strtotime($o['created_at']))) ?></td></tr>
        <?php endforeach; ?>
      </table>
      <?php else: ?><p class="dz-empty"><?= t('Orders will appear here as soon as they come in.', 'ऑर्डर आते ही यहाँ दिखेंगे।') ?></p><?php endif; ?>
    </div>
    <div class="dz-card">
      <div class="dz-card-h"><b><?= t('Needs attention', 'ध्यान दीजिए') ?></b></div>
      <ul class="dz-todo">
        <?php $any = false; ?>
        <?php if ($o_new): $any = true; ?><li class="warn"><b><?= $o_new ?></b> <?= t('orders waiting for a call', 'ऑर्डर कॉल के इंतज़ार में') ?> <a href="/admin/">→</a></li><?php endif; ?>
        <?php if ($b_new): $any = true; ?><li><b><?= $b_new ?></b> <?= t('bookings need a quote', 'बुकिंग का रेट बाकी') ?> <a href="/bpo/bookings.php">→</a></li><?php endif; ?>
        <?php if ($tr_exp): $any = true; ?><li class="warn"><b><?= $tr_exp ?></b> <?= t('vehicle papers expiring', 'गाड़ी के कागज़ खत्म हो रहे') ?> <a href="/admin/transport.php">→</a></li><?php endif; ?>
        <?php if ($tr_pend): $any = true; ?><li><b><?= $tr_pend ?></b> <?= t('vehicles to approve', 'गाड़ियाँ मंज़ूरी बाकी') ?> <a href="/admin/transport.php?v=pending">→</a></li><?php endif; ?>
        <?php if ($area_new): $any = true; ?><li><b><?= $area_new ?></b> <?= t('new area requests', 'नए इलाके की माँग') ?> <a href="/admin/areas.php">→</a></li><?php endif; ?>
        <?php if ($bk_flag): $any = true; ?><li class="warn"><b><?= $bk_flag ?></b> <?= t('book complaints', 'किताब शिकायत') ?> <a href="/admin/books.php?v=flag">→</a></li><?php endif; ?>
        <?php if ($cp_done < $cp_tot): $any = true; ?><li><b><?= num($cp_tot - $cp_done) ?></b> <?= t('products without photos', 'सामान बिना फ़ोटो') ?> <a href="/admin/product-photos.php?f=missing">→</a></li><?php endif; ?>
        <?php if (!$any): ?><li class="good"><?= t('All clear', 'सब ठीक है') ?></li><?php endif; ?>
      </ul>
      <div class="dz-links">
        <a href="/public/admin/approvals.php"><?= t('Partner applications', 'पार्टनर आवेदन') ?></a>
        <a href="/admin/company-delivery.php"><?= t('Company deliveries', 'कंपनी डिलीवरी') ?></a>
        <span>v<?= defined('MAAKIT_VERSION') ? h(MAAKIT_VERSION) : '?' ?></span>
      </div>
    </div>
  </div>

  <div class="dz-row dz-r-3">
    <div class="dz-card">
      <div class="dz-card-h"><b><?= t('Top villages', 'कौन से गाँव से') ?></b></div>
      <?php if ($vills): $mv = max(array_column($vills, 'c')); ?>
        <div class="dz-bars"><?php foreach (array_slice($vills, 0, 6) as $v): ?><div><span><?= h($v['village']) ?></span><i><em style="width:<?= max(4, round($v['c'] * 100 / $mv)) ?>%"></em></i><b><?= (int)$v['c'] ?></b></div><?php endforeach; ?></div>
      <?php else: ?><p class="dz-empty"><?= t('No data yet', 'अभी आँकड़े नहीं') ?></p><?php endif; ?>
    </div>
    <div class="dz-card">
      <div class="dz-card-h"><b><?= t('Most ordered products', 'सबसे ज़्यादा माँगा सामान') ?></b></div>
      <?php if ($topitems): $mi = max($topitems); ?>
        <div class="dz-bars"><?php foreach (array_slice($topitems, 0, 6, true) as $k => $c): ?><div><span title="<?= h($k) ?>"><?= h($k) ?></span><i><em style="width:<?= max(4, round($c * 100 / $mi)) ?>%"></em></i><b><?= $c ?></b></div><?php endforeach; ?></div>
      <?php else: ?><p class="dz-empty"><?= t('No data yet', 'अभी आँकड़े नहीं') ?></p><?php endif; ?>
    </div>
    <div class="dz-card">
      <div class="dz-card-h"><b><?= t('Searches', 'खोज') ?></b><a href="/admin/items.php#naya"><?= t('Add products', 'सामान जोड़िए') ?> →</a></div>
      <?php if ($miss): ?><div class="dz-sub"><?= t('Not found', 'नहीं मिला') ?></div><div class="dz-tags miss"><?php foreach (array_slice($miss, 0, 8) as $m): ?><span><?= h($m['q']) ?> <b><?= (int)$m['times'] ?></b></span><?php endforeach; ?></div><?php endif; ?>
      <?php if ($hitq): ?><div class="dz-sub"><?= t('Most searched', 'सबसे ज़्यादा खोजे') ?></div><div class="dz-tags"><?php foreach ($hitq as $q): ?><span><?= h($q['q']) ?> <b><?= (int)$q['times'] ?></b></span><?php endforeach; ?></div><?php endif; ?>
      <?php if (!$miss && !$hitq): ?><p class="dz-empty"><?= t('No searches yet', 'अभी कोई खोज नहीं') ?></p><?php endif; ?>
    </div>
  </div>
</section>
<?php include __DIR__ . '/../inc/foot.php'; ?>
