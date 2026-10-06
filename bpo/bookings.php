<?php
require_once __DIR__ . '/../inc/fn.php';
require_once __DIR__ . '/../inc/dakiya.php';
require_once __DIR__ . '/../inc/services.php';
require_once __DIR__ . '/../inc/transport.php';
$u = need_role(['bpo', 'admin']);
$page_title = 'बुकिंग — Maakit';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_ok()) {
    $id = (int)post('id');
    if (post('do') === 'save') {
        $st = post('status');
        if (!array_key_exists($st, booking_status_list())) { $st = 'Naya'; }
        $q = post('quote') !== '' ? (int)post('quote') : null;
        $cm = post('commission') !== '' ? (int)post('commission') : null;
        $pdo->prepare("UPDATE service_bookings SET status=?, quote=?, commission=?, note=CONCAT(COALESCE(note,''), ?), handled_by=? WHERE id=?")
            ->execute([$st, $q, $cm, (post('add') ? "\n" . post('add') : ''), $u['id'], $id]);
        // booking pakki hui to us gaadi ki trip ginti badhao
        if ($st === 'Done') {
            $g = $pdo->prepare("SELECT transport_id FROM service_bookings WHERE id=?"); $g->execute([$id]);
            $tid = (int)($g->fetch()['transport_id'] ?? 0);
            if ($tid) { $pdo->prepare("UPDATE transports SET trips = trips + 1 WHERE id=?")->execute([$tid]); }
        }
        flash('बुकिंग सेव हो गई।');
    }
    redirect('/bpo/bookings.php' . (get('day') ? '?day=' . urlencode(get('day')) : ''));
}

$view = get('v', 'open');   // open = jo abhi baaki hain
if ($view === 'all') {
    $rows = $pdo->query("SELECT * FROM service_bookings ORDER BY id DESC LIMIT 120")->fetchAll();
} else {
    $rows = $pdo->query("SELECT * FROM service_bookings WHERE status IN ('Naya','Dekh rahe','Confirm')
                         ORDER BY (status='Naya') DESC, id DESC LIMIT 120")->fetchAll();
}
$naye = (int)$pdo->query("SELECT COUNT(*) c FROM service_bookings WHERE status='Naya'")->fetch()['c'];
include __DIR__ . '/../inc/panel.php';
?>
<section>
<div class="wrap">
  <h2>बुकिंग</h2>
  <?php if ($naye): ?>
    <div class="ok" style="background:#FFF0D6;color:#8A5B00;font-weight:700">
      <?= $naye ?> नई बुकिंग देखना बाकी है — रेट पता करके ग्राहक को कॉल कीजिए।
    </div>
  <?php endif; ?>

  <div class="segs" style="max-width:340px;margin-bottom:16px">
    <a class="<?= $view !== 'all' ? 'on' : '' ?>" href="/bpo/bookings.php">चालू</a>
    <a class="<?= $view === 'all' ? 'on' : '' ?>" href="/bpo/bookings.php?v=all">सब</a>
  </div>

  <?php if (!$rows): ?><div class="box">अभी कोई बुकिंग नहीं है।</div><?php endif; ?>

  <?php foreach ($rows as $b):
    $svc = service_get($b['service']);
    list($pc, $pl) = booking_pill($b['status']);
    $wa = "Maakit — बुकिंग {$b['booking_no']}\nसेवा: " . (isset($svc) && $svc ? svc_name($svc) : $b['service']) . "\n"
        . trim((string)$b['summary']) . "\n"
        . ($b['quote'] ? "रेट: ₹{$b['quote']}\n" : "")
        . "बुकिंग कोड: {$b['code']}"; ?>
    <div class="box" style="margin-bottom:14px">
      <div style="display:flex;justify-content:space-between;flex-wrap:wrap;gap:8px;align-items:center">
        <div>
          <b style="font-size:19px"><?= h($b['booking_no']) ?></b>
          <span class="pill <?= h($pc) ?>"><?= h($pl) ?></span>
          <span class="tag tag-gold"><?= h(isset($svc) && $svc ? svc_name($svc) : $b['service']) ?></span>
          <span class="tag tag-off">कोड <?= h($b['code']) ?></span>
        </div>
        <div class="meta"><?= h(date('d/m h:i A', strtotime($b['created_at']))) ?></div>
      </div>

      <div style="margin-top:8px">
        <div><b><?= h($b['name']) ?></b> · <a href="tel:+91<?= h($b['mobile']) ?>"><?= h($b['mobile']) ?></a></div>
        <div class="meta"><?= h($b['village']) ?><?= $b['address'] ? ' · ' . h($b['address']) : '' ?></div>
      </div>

      <div style="margin-top:8px;background:var(--soft);border-radius:10px;padding:10px 12px;font-size:15px;line-height:1.6">
        <?= nl2br(h($b['summary'])) ?>
      </div>
      <?php if ($b['note']): ?>
        <div class="note" style="margin-top:8px"><b>नोट:</b><br><?= nl2br(h(trim($b['note']))) ?></div>
      <?php endif; ?>
      <?php if (!empty($b['transport_id'])):
        $tr = transport_get($pdo, $b['transport_id']);
        if ($tr): ?>
        <div class="note" style="margin-top:8px">
          <b>चुनी हुई गाड़ी:</b> <?= h(vtype_label($tr['vtype'])) ?><?= $tr['vnumber'] ? ' · ' . h($tr['vnumber']) : '' ?>
          — <?= h($tr['owner']) ?> · <a href="tel:+91<?= h($tr['mobile']) ?>"><?= h($tr['mobile']) ?></a>
          · <a href="<?= h(wa_link($tr['mobile'], "नमस्ते " . $tr['owner'] . " जी, Maakit से — एक बुकिंग है।\n" . trim((string)$b['summary']) . "\nग्राहक: " . $b['name'] . " (" . $b['village'] . ")\nकर पाएँगे?")) ?>" target="_blank" rel="noopener">WhatsApp भेजिए</a>
          <div class="help" style="margin-top:3px">रेट: <?= h(rate_line($tr)) ?></div>
        </div>
      <?php endif; endif; ?>
      <?php if ($b['business_id'] && empty($b['transport_id'])):
        $bz = $pdo->prepare("SELECT name,mobile,village FROM businesses WHERE id=?"); $bz->execute([$b['business_id']]);
        if ($z = $bz->fetch()): ?>
        <div class="note" style="margin-top:8px">ग्राहक ने चुना: <b><?= h($z['name']) ?></b>
          <?= $z['village'] ? ' — ' . h($z['village']) : '' ?> · <a href="tel:+91<?= h($z['mobile']) ?>"><?= h($z['mobile']) ?></a></div>
      <?php endif; endif; ?>

      <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:12px">
        <a class="btn btn-brand btn-sm" href="tel:+91<?= h($b['mobile']) ?>">कॉल</a>
        <?php // ---- डाकिया: बुकिंग के हर हाल का अपना सन्देश ----
          $q = $b['quote'] !== null && $b['quote'] !== '' ? (int)$b['quote'] : null;
          if ($b['status'] === 'Naya') {
              echo dak_btn($b['mobile'], dak_booking($b, 'mili'), 'बुकिंग मिल गई — भेजिए');
          }
          if ($q !== null) {
              echo dak_btn($b['mobile'], dak_booking($b, 'rate', $q), 'रेट भेजिए', 'btn-ghost');
              echo dak_btn($b['mobile'], dak_booking($b, 'pakki', $q), 'पक्की हो गई — भेजिए');
          }
        ?>
        <a class="btn btn-ghost btn-sm" href="<?= h(wa_link($b['mobile'], $wa)) ?>" target="_blank" rel="noopener">पूरा ब्योरा भेजिए</a>
      </div>

      <form method="post" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;margin-top:12px">
        <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
        <input type="hidden" name="do" value="save">
        <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
        <div style="min-width:160px"><label>स्टेटस</label>
          <select name="status">
            <?php foreach (booking_status_list() as $k => $v): ?>
              <option value="<?= h($k) ?>" <?= $b['status'] === $k ? 'selected' : '' ?>><?= h($v) ?></option>
            <?php endforeach; ?>
          </select></div>
        <div style="max-width:120px"><label>रेट ₹</label>
          <input type="number" name="quote" value="<?= h($b['quote']) ?>" placeholder="जो तय हुआ"></div>
        <div style="max-width:120px"><label>हमारा हिस्सा ₹</label>
          <input type="number" name="commission" value="<?= h($b['commission']) ?>" placeholder="कमीशन"></div>
        <div style="flex:1;min-width:180px"><label>नोट जोड़िए</label>
          <input type="text" name="add" placeholder="जैसे: 2 लॉन खाली हैं, ₹35000"></div>
        <button class="btn btn-brand btn-sm">सेव</button>
      </form>
    </div>
  <?php endforeach; ?>
</div>
</section>
<?php include __DIR__ . '/../inc/foot.php'; ?>
