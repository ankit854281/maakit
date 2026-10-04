<?php
require_once __DIR__ . '/inc/fn.php';
require_once __DIR__ . '/inc/salon.php';

$b = salon_get($pdo, get('id'));
if (!$b) { redirect('/directory.php?cat=nai'); }
$tab = 'kaam';
$page_title = $b['name'] . ' — सीट बुक कीजिए | Maakit';
$services = salon_services($pdo, $b['id']);
$err = '';

// ---- मेरी बुकिंग (उसी फ़ोन पर याद रहती है) ----
$mine = null;
if (!empty($_COOKIE['mk_bk'])) {
    $st = $pdo->prepare("SELECT * FROM bookings WHERE ref=? AND business_id=? AND DATE(created_at)=CURDATE()
                         AND status IN ('requested','waiting','in_chair')");
    $st->execute([$_COOKIE['mk_bk'], $b['id']]);
    $mine = $st->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_ok()) {
    $do = post('do');
    if ($do === 'coming' && $mine) {
        $pdo->prepare("UPDATE bookings SET coming=? WHERE id=?")->execute([post('coming') === 'yes' ? 'yes' : 'no', $mine['id']]);
        if (post('coming') === 'no') {
            $pdo->prepare("UPDATE bookings SET status='cancelled' WHERE id=?")->execute([$mine['id']]);
            $pdo->prepare("INSERT INTO salon_customers (mobile, name, no_shows) VALUES (?,?,0)
                           ON DUPLICATE KEY UPDATE name=VALUES(name)")->execute([$mine['mobile'], $mine['customer_name']]);
            setcookie('mk_bk', '', ['expires' => time() - 3600, 'path' => '/']);
        }
        redirect('/salon.php?id=' . (int)$b['id']);
    }
    if ($do === 'book') {
        $name = post('name'); $mobile = preg_replace('/\D/', '', post('mobile'));
        $seats = max(1, min(4, (int)post('seats', 1)));
        $chosen = array_map('intval', (array)($_POST['svc'] ?? []));
        $live = salon_live($b);

        if (!$live) { $err = 'अभी सीट बुक नहीं हो सकती।'; }
        elseif (mb_strlen($name) < 2) { $err = 'अपना नाम लिखिए।'; }
        elseif (strlen($mobile) !== 10) { $err = 'मोबाइल नंबर 10 अंकों का लिखिए।'; }
        elseif (!$chosen) { $err = 'क्या-क्या कराना है, वह चुनिए।'; }
        else {
            $names = []; $price = 0; $mins = 0;
            foreach ($services as $s) {
                if (in_array((int)$s['id'], $chosen, true)) {
                    $names[] = $s['name']; $price += (int)$s['price']; $mins += (int)$s['minutes'];
                }
            }
            $price *= $seats; $mins *= $seats;
            $cust = salon_customer($pdo, $mobile);
            $needs_ok = ($b['mode'] === 'busy') || ($cust && (int)$cust['no_shows'] >= 3);
            $status = $needs_ok ? 'requested' : 'waiting';
            $ref = salon_ref();
            $pdo->prepare("INSERT INTO bookings (business_id, ref, customer_name, mobile, seats, service_text, price, minutes, status)
                           VALUES (?,?,?,?,?,?,?,?,?)")
                ->execute([$b['id'], $ref, $name, $mobile, $seats, implode(' + ', $names), $price, $mins, $status]);
            $pdo->prepare("INSERT INTO salon_customers (mobile, name, last_service) VALUES (?,?,?)
                           ON DUPLICATE KEY UPDATE name=VALUES(name), last_service=VALUES(last_service)")
                ->execute([$mobile, $name, implode(' + ', $names)]);
            setcookie('mk_bk', $ref, ['expires' => time() + 86400, 'path' => '/', 'samesite' => 'Lax']);
            redirect('/salon.php?id=' . (int)$b['id']);
        }
    }
}

$board = salon_board($pdo, $b);
list($dot, $slabel, $scls) = salon_status_label($b);
$live = salon_live($b);

// मेरी बुकिंग का ताज़ा हाल
$my_wait = null; $my_pos = 0;
if ($mine) {
    foreach ($board['waiting'] as $i => $w) {
        if ((int)$w['id'] === (int)$mine['id']) { $my_wait = $w; $my_pos = $i; break; }
    }
}
include __DIR__ . '/inc/head.php';
?>
<style>
.salon{background:#1B1210;color:#F6EBDD;margin-top:-1px}
.salon .wrap{padding-top:26px;padding-bottom:30px}
.salon h1{font-size:clamp(28px,5.6vw,44px);margin:0;letter-spacing:-.6px}
.salon .muted{color:#CDB79A}
.gold{color:#E8B23A}
.chairrow{display:flex;gap:10px;flex-wrap:wrap;margin-top:14px}
.chair{background:#26191A;border:1.5px solid #3E2B25;border-radius:14px;padding:12px;min-width:132px;flex:1}
.chair.busy{border-color:#E8B23A}
.chair .who{font-weight:700;margin-top:6px}
.chair .t{font-size:13.5px;color:#CDB79A}
.seatline{display:flex;gap:8px;flex-wrap:wrap;margin-top:10px}
.seat{width:44px;height:44px;border-radius:10px;background:#26191A;border:1.5px solid #3E2B25;display:grid;place-items:center;font-size:20px}
.seat.full{background:#3A2320;border-color:#E8B23A}
.seat.me{background:#E8B23A;color:#3A1208;border-color:#E8B23A;font-weight:800}
.svc{display:flex;align-items:center;gap:12px;border:1.5px solid var(--line);border-radius:14px;padding:12px 14px;margin-bottom:8px;background:var(--surface);cursor:pointer}
.svc input{width:22px;height:22px;flex-shrink:0}
.svc .nm{font-weight:600}
.svc .pr{margin-left:auto;text-align:right;font-weight:700;color:var(--brand);white-space:nowrap}
.total{position:sticky;bottom:0;background:var(--brand);color:#fff;border-radius:14px;padding:14px 16px;display:flex;justify-content:space-between;align-items:center;gap:10px;margin-top:10px}
</style>

<section class="salon">
  <div class="wrap">
    <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;align-items:flex-start">
      <div>
        <h1><?= h($b['name']) ?></h1>
        <div class="muted"><?= h($b['work'] ?: 'नाई / सैलून') ?><?= $b['village'] ? ' · ' . h($b['village']) : '' ?></div>
      </div>
      <div style="text-align:right">
        <div style="font-size:19px;font-weight:700"><?= $dot ?> <?= h($slabel) ?></div>
        <div class="muted" style="font-size:14.5px"><?= h(salon_wait_text($board)) ?></div>
      </div>
    </div>

    <div class="chairrow">
      <?php for ($i = 1; $i <= $board['workers']; $i++): $c = $board['chairs'][$i]; ?>
        <div class="chair <?= $c ? 'busy' : '' ?>">
          <div class="muted" style="font-size:13px">कुर्सी <?= $i ?></div>
          <?php if ($c): ?>
            <div style="font-size:26px">💈</div>
            <div class="who"><?= h(mb_strimwidth($c['customer_name'], 0, 14, '…')) ?></div>
            <div class="t"><?= h($c['service_text']) ?></div>
            <div class="t gold"><?= (int)$c['left_min'] ?> मिनट बाकी</div>
          <?php else: ?>
            <div style="font-size:26px;opacity:.45">🪑</div>
            <div class="who gold">खाली</div>
            <div class="t">अभी बैठ सकते हैं</div>
          <?php endif; ?>
        </div>
      <?php endfor; ?>
    </div>

    <?php if ($board['waiting']): ?>
      <div style="margin-top:16px">
        <div class="muted" style="font-size:14px">इंतज़ार में</div>
        <div class="seatline">
          <?php foreach ($board['waiting'] as $w): $isme = $mine && (int)$w['id'] === (int)$mine['id']; ?>
            <div class="seat <?= $isme ? 'me' : 'full' ?>" title="<?= h($w['customer_name']) ?>"><?= $isme ? 'आप' : '🪑' ?></div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>
  </div>
</section>

<section>
<div class="wrap" style="max-width:720px">
  <?php if ($err): ?><div class="err"><?= h($err) ?></div><?php endif; ?>

  <?php if ($mine): ?>
    <div class="box">
      <?php if ($mine['status'] === 'requested'): ?>
        <h2 style="margin-top:0">आपका अनुरोध भेजा गया</h2>
        <p>दुकान अभी व्यस्त है। दुकानदार हाँ करेंगे तो आपकी सीट पक्की हो जाएगी।</p>
        <p class="help">जवाब न आए तो <a href="tel:+91<?= h($b['mobile']) ?>">फ़ोन करके पूछ लीजिए</a>।</p>
      <?php elseif ($mine['status'] === 'in_chair'): ?>
        <h2 style="margin-top:0">आपकी बारी चल रही है 💈</h2>
        <p>कुर्सी <?= (int)$mine['chair_no'] ?> · <?= h($mine['service_text']) ?></p>
      <?php else: ?>
        <h2 style="margin-top:0">सीट पक्की है</h2>
        <div class="note">
          <div style="font-size:15px;color:var(--muted)">आपसे पहले</div>
          <div class="big"><?= (int)$my_pos ?> व्यक्ति</div>
          <div>करीब <b><?= (int)($my_wait['wait_min'] ?? 0) ?> मिनट</b> · आपका समय लगभग <b><?= h(date('h:i A', $my_wait['eta'] ?? time())) ?></b></div>
        </div>
        <?php if ($my_pos <= 1): ?>
          <div class="ok" style="margin-top:12px"><b>अब निकल जाइए</b> — आपकी बारी आने वाली है।</div>
        <?php endif; ?>
      <?php endif; ?>

      <div class="meta" style="margin-top:10px"><?= h($mine['service_text']) ?> · <?= (int)$mine['seats'] ?> सीट · ₹<?= (int)$mine['price'] ?> · बुकिंग नं. <?= h($mine['ref']) ?></div>

      <?php if ($mine['status'] !== 'in_chair'): ?>
      <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:12px">
        <form method="post"><input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="do" value="coming"><input type="hidden" name="coming" value="yes"><button class="btn btn-green btn-sm">आ रहा हूँ</button></form>
        <form method="post" onsubmit="return confirm('सीट छोड़ दें?')"><input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="do" value="coming"><input type="hidden" name="coming" value="no"><button class="btn btn-sm" style="background:#EFEAE0">नहीं आ पाऊँगा</button></form>
        <a class="btn btn-brand btn-sm" href="tel:+91<?= h($b['mobile']) ?>">दुकान को फ़ोन</a>
      </div>
      <?php endif; ?>
      <p class="help" style="margin-top:10px">यह पेज अपने आप ताज़ा होता रहता है।</p>
    </div>

  <?php elseif (!$live): ?>
    <div class="box">
      <h2 style="margin-top:0"><?= h($slabel) ?></h2>
      <p>अभी ऑनलाइन सीट बुक नहीं हो रही। आप फ़ोन करके पूछ सकते हैं।</p>
      <a class="btn btn-green" href="tel:+91<?= h($b['mobile']) ?>">📞 <?= h($b['mobile']) ?></a>
    </div>

  <?php else: ?>
    <h2>क्या-क्या कराना है?</h2>
    <p class="lead">चुनते जाइए, नीचे कुल दाम और समय अपने आप जुड़ता जाएगा।</p>
    <form method="post" id="bk">
      <input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="do" value="book">
      <?php foreach ($services as $s): ?>
        <label class="svc">
          <input type="checkbox" name="svc[]" value="<?= (int)$s['id'] ?>" data-p="<?= (int)$s['price'] ?>" data-m="<?= (int)$s['minutes'] ?>">
          <span><span class="nm"><?= h($s['name']) ?></span><br><span class="meta"><?= (int)$s['minutes'] ?> मिनट</span></span>
          <span class="pr">₹<?= (int)$s['price'] ?></span>
        </label>
      <?php endforeach; ?>
      <?php if (!$services): ?><div class="note">इस दुकान ने अभी रेट नहीं भरे हैं। कृपया फ़ोन कीजिए।</div><?php endif; ?>

      <div class="field" style="margin-top:14px"><label>कितने लोगों के लिए?</label>
        <select name="seats" id="seats"><?php for ($i = 1; $i <= 4; $i++): ?><option value="<?= $i ?>"><?= $i ?> सीट</option><?php endfor; ?></select>
        <p class="help">दोस्त या घर के लोग साथ आ रहे हों तो यहाँ बढ़ा दीजिए।</p>
      </div>
      <div class="field"><label>आपका नाम</label><input type="text" name="name" required></div>
      <div class="field"><label>मोबाइल नंबर</label><input type="tel" name="mobile" required><p class="help">यही आपकी पहचान है, कोई पासवर्ड नहीं बनाना।</p></div>

      <div class="total">
        <div><div style="font-size:14px;opacity:.85">कुल</div><div style="font-size:24px;font-weight:800"><span id="tp">₹0</span> · <span id="tm">0</span> मिनट</div></div>
        <button class="btn btn-gold btn-sm" type="submit" style="padding:12px 18px">सीट बुक कीजिए</button>
      </div>
      <p class="help" style="margin-top:10px"><?= $b['mode'] === 'busy' ? 'दुकान अभी व्यस्त है — आपका अनुरोध जाएगा, दुकानदार की हाँ पर सीट पक्की होगी।' : 'बुकिंग तुरंत पक्की हो जाएगी।' ?></p>
    </form>
  <?php endif; ?>

  <div class="box" style="margin-top:18px">
    <div style="display:flex;justify-content:space-between;flex-wrap:wrap;gap:10px">
      <div>
        <div class="meta">समय</div>
        <div><b><?= h(salon_hm($b['open_time'])) ?> से <?= h(salon_hm($b['close_time'])) ?></b></div>
      </div>
      <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
        <a class="btn btn-green btn-sm" href="tel:+91<?= h($b['mobile']) ?>">📞 फ़ोन</a>
        <a class="btn btn-brand btn-sm" href="/business.php?id=<?= (int)$b['id'] ?>">दुकान की जानकारी</a>
      </div>
    </div>
  </div>
</div>
</section>

<script>
(function(){
  var f = document.getElementById('bk');
  if (f) {
    var boxes = f.querySelectorAll('input[type=checkbox]'), seats = document.getElementById('seats');
    function calc(){
      var p = 0, m = 0;
      boxes.forEach(function(b){ if (b.checked) { p += +b.dataset.p; m += +b.dataset.m; } });
      var s = +seats.value || 1;
      document.getElementById('tp').textContent = '₹' + (p * s);
      document.getElementById('tm').textContent = (m * s);
    }
    boxes.forEach(function(b){ b.addEventListener('change', calc); });
    seats.addEventListener('change', calc); calc();
  }
  <?php if ($mine): ?>setTimeout(function(){ location.reload(); }, 30000);<?php endif; ?>
})();
</script>
<?php include __DIR__ . '/inc/foot.php'; ?>
