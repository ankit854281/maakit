<?php
// ============================================================
//  नाई / पार्लर: tab "आज की बुकिंग" (live) aur "रेट और सेटिंग" (setup)
//  (pehle shop.php ke andar tha — yahan alag kiya, kaam wahi hai)
// ============================================================
$csrf = h(csrf());
?>
<?php if ($tab === 'live'): ?>
  <div class="sp-head">
    <div><h1><?= t("Today's bookings", 'आज की बुकिंग') ?></h1>
      <p><?= t('Done today', 'आज हो चुके') ?>: <b><?= (int)$today['c'] ?></b> · ₹<?= number_format((int)$today['p']) ?> · <span class="sp-live-dot"></span><?= t('Updates by itself', 'अपने आप ताज़ा होता है') ?></p></div>
  </div>

  <div class="sp-card">
    <div class="sp-card-h"><b><?= t('Shop status', 'दुकान की हालत') ?></b></div>
    <div class="sp-seg">
      <?php foreach (salon_modes() as $k => $lbl): ?>
        <form method="post"><input type="hidden" name="csrf" value="<?= $csrf ?>"><input type="hidden" name="do" value="mode"><input type="hidden" name="mode" value="<?= h($k) ?>">
          <button class="<?= $b['mode'] === $k ? 'on' : '' ?>"><?= h($lbl) ?></button></form>
      <?php endforeach; ?>
    </div>
    <p class="sp-note"><b><?= t('Open', 'खुली है') ?>:</b> <?= t('bookings confirm by themselves.', 'बुकिंग अपने आप पक्की — फ़ोन छूने की ज़रूरत नहीं।') ?>
      <b><?= t('Busy', 'व्यस्त है') ?>:</b> <?= t('bookings come as requests; you confirm them.', 'बुकिंग अनुरोध बनकर आएगी, आप हाँ करेंगे तभी पक्की।') ?></p>
  </div>

  <?php if ($requests): ?>
    <div class="sp-card sp-card-hot">
      <div class="sp-card-h"><b><?= t('New requests', 'नए अनुरोध') ?> (<?= count($requests) ?>)</b></div>
      <?php foreach ($requests as $r): $c = salon_customer($pdo, $r['mobile']); list($bl, $bc) = salon_customer_badge($c); ?>
        <div class="sp-row">
          <div class="sp-row-t"><b><?= h($r['customer_name']) ?></b> <span class="tag <?= h($bc) ?>"><?= h($bl) ?></span>
            <small><?= h($r['service_text']) ?> · <?= (int)$r['seats'] ?> <?= t('seats', 'सीट') ?> · <?= (int)$r['minutes'] ?> <?= t('min', 'मिनट') ?> · ₹<?= (int)$r['price'] ?></small></div>
          <form method="post"><input type="hidden" name="csrf" value="<?= $csrf ?>"><input type="hidden" name="do" value="accept"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="sp-btn sp-btn-go"><?= t('Accept', 'हाँ') ?></button></form>
          <form method="post"><input type="hidden" name="csrf" value="<?= $csrf ?>"><input type="hidden" name="do" value="reject"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="sp-btn sp-btn-soft"><?= t('Not now', 'अभी नहीं') ?></button></form>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <div class="sp-grid2">
    <?php for ($i = 1; $i <= $board['workers']; $i++): $c = $board['chairs'][$i]; ?>
      <div class="sp-card sp-chair<?= $c ? ' busy' : '' ?>">
        <small class="sp-lbl"><?= t('Chair', 'कुर्सी') ?> <?= $i ?></small>
        <?php if ($c): ?>
          <div class="sp-chair-n"><?= h($c['customer_name']) ?></div>
          <small class="sp-muted"><?= h($c['service_text']) ?> · ₹<?= (int)$c['price'] ?></small>
          <div class="sp-chair-m"><?= (int)$c['left_min'] ?> <?= t('min left', 'मिनट बाकी') ?></div>
          <div class="sp-acts">
            <form method="post"><input type="hidden" name="csrf" value="<?= $csrf ?>"><input type="hidden" name="do" value="done"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><button class="sp-btn sp-btn-go"><?= t('Done — next', 'हो गया — अगले') ?></button></form>
            <form method="post"><input type="hidden" name="csrf" value="<?= $csrf ?>"><input type="hidden" name="do" value="time"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><input type="hidden" name="delta" value="5"><button class="sp-btn sp-btn-soft">+5</button></form>
            <form method="post"><input type="hidden" name="csrf" value="<?= $csrf ?>"><input type="hidden" name="do" value="time"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><input type="hidden" name="delta" value="-5"><button class="sp-btn sp-btn-soft">−5</button></form>
          </div>
        <?php else: ?>
          <div class="sp-chair-n sp-muted"><?= t('Empty', 'खाली') ?></div>
          <form method="post" class="sp-acts"><input type="hidden" name="csrf" value="<?= $csrf ?>"><input type="hidden" name="do" value="call_next"><input type="hidden" name="chair" value="<?= $i ?>">
            <button class="sp-btn sp-btn-go" <?= $board['waiting'] ? '' : 'disabled' ?>><?= t('Seat the next customer', 'अगले को बिठाइए') ?></button></form>
        <?php endif; ?>
      </div>
    <?php endfor; ?>
  </div>

  <div class="sp-card">
    <div class="sp-card-h"><b><?= t('Waiting', 'इंतज़ार में') ?> (<?= count($board['waiting']) ?>)</b></div>
    <?php if (!$board['waiting']): ?><p class="sp-empty"><?= t('Nobody is waiting right now.', 'अभी कोई नहीं है।') ?></p><?php endif; ?>
    <?php foreach ($board['waiting'] as $i => $w): $c = salon_customer($pdo, $w['mobile']); list($bl, $bc) = salon_customer_badge($c); ?>
      <div class="sp-row">
        <div class="sp-row-t">
          <b><?= $i + 1 ?>. <?= h($w['customer_name']) ?></b>
          <?php if ($w['source'] === 'online'): ?><span class="tag <?= h($bc) ?>"><?= h($bl) ?></span><?php else: ?><span class="tag tag-off"><?= t('Walk-in', 'दुकान पर आए') ?></span><?php endif; ?>
          <?php if ($w['coming'] === 'yes'): ?><span class="tag tag-live"><?= t('On the way', 'आ रहे हैं') ?></span><?php endif; ?>
          <small><?= h($w['service_text']) ?> · <?= (int)$w['minutes'] ?> <?= t('min', 'मिनट') ?> · ~<?= h(date('h:i A', $w['eta'])) ?>
            <?= $w['mobile'] ? ' · <a href="tel:+91' . h($w['mobile']) . '">' . h($w['mobile']) . '</a>' : '' ?></small>
        </div>
        <form method="post"><input type="hidden" name="csrf" value="<?= $csrf ?>"><input type="hidden" name="do" value="time"><input type="hidden" name="id" value="<?= (int)$w['id'] ?>"><input type="hidden" name="delta" value="5"><button class="sp-btn sp-btn-soft">+5</button></form>
        <form method="post"><input type="hidden" name="csrf" value="<?= $csrf ?>"><input type="hidden" name="do" value="time"><input type="hidden" name="id" value="<?= (int)$w['id'] ?>"><input type="hidden" name="delta" value="-5"><button class="sp-btn sp-btn-soft">−5</button></form>
        <form method="post"><input type="hidden" name="csrf" value="<?= $csrf ?>"><input type="hidden" name="do" value="noshow"><input type="hidden" name="id" value="<?= (int)$w['id'] ?>"><button class="sp-btn sp-btn-soft"><?= t("Didn't come", 'नहीं आए') ?></button></form>
      </div>
    <?php endforeach; ?>

    <form method="post" class="sp-inline-form">
      <input type="hidden" name="csrf" value="<?= $csrf ?>"><input type="hidden" name="do" value="walkin">
      <label><span><?= t('Walk-in customer', 'दुकान पर आया ग्राहक') ?></span><input type="text" name="wname" placeholder="<?= h(t('Name', 'नाम')) ?>"></label>
      <label><span><?= t('Service', 'काम') ?></span><input type="text" name="wsvc" placeholder="<?= h(t('e.g. hair + beard', 'जैसे बाल+दाढ़ी')) ?>"></label>
      <label class="sp-w-s"><span><?= t('Minutes', 'मिनट') ?></span><input type="number" name="wmin" value="15"></label>
      <button class="sp-btn sp-btn-go"><?= sp_icon('plus', 15) ?> <?= t('Add to queue', 'लाइन में जोड़िए') ?></button>
    </form>
  </div>
  <script>setTimeout(function () { if (!document.querySelector('.sp-main input:focus')) location.reload(); }, 45000);</script>

<?php elseif ($tab === 'setup'): ?>
  <div class="sp-head"><div><h1><?= t('Rates & settings', 'रेट और सेटिंग') ?></h1></div></div>
  <div class="sp-grid2">
    <form method="post" class="sp-card">
      <input type="hidden" name="csrf" value="<?= $csrf ?>"><input type="hidden" name="do" value="setup">
      <div class="sp-card-h"><b><?= t('Chairs & timings', 'कुर्सी और समय') ?></b></div>
      <label class="sp-field"><span><?= t('How many people cut hair?', 'बाल बनाने वाले कितने लोग हैं?') ?></span>
        <select name="workers"><?php for ($i = 1; $i <= 6; $i++): ?><option value="<?= $i ?>" <?= (int)$b['workers'] === $i ? 'selected' : '' ?>><?= $i ?></option><?php endfor; ?></select>
        <small><?= t('Customers see this many chairs; the rest wait.', 'इतनी ही कुर्सियाँ ग्राहक को दिखेंगी, बाकी इंतज़ार वाली।') ?></small></label>
      <div class="sp-grid2 tight">
        <label class="sp-field"><span><?= t('Opens', 'खुलती है') ?></span><input type="time" name="open_time" value="<?= h(substr($b['open_time'], 0, 5)) ?>"></label>
        <label class="sp-field"><span><?= t('Closes', 'बंद होती है') ?></span><input type="time" name="close_time" value="<?= h(substr($b['close_time'], 0, 5)) ?>"></label>
      </div>
      <button class="sp-btn sp-btn-go"><?= t('Save', 'सेव कीजिए') ?></button>
    </form>

    <div class="sp-card">
      <div class="sp-card-h"><b><?= t('Your rates', 'अपने रेट') ?></b></div>
      <?php foreach ($svc as $s): ?>
        <div class="sp-row">
          <div class="sp-row-t"><b><?= h($s['name']) ?></b><small><?= (int)$s['minutes'] ?> <?= t('min', 'मिनट') ?></small></div>
          <b class="sp-amt">₹<?= (int)$s['price'] ?></b>
          <form method="post"><input type="hidden" name="csrf" value="<?= $csrf ?>"><input type="hidden" name="do" value="svc_del"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><button class="sp-btn sp-btn-soft"><?= t('Remove', 'हटाइए') ?></button></form>
        </div>
      <?php endforeach; ?>
      <form method="post" class="sp-inline-form">
        <input type="hidden" name="csrf" value="<?= $csrf ?>"><input type="hidden" name="do" value="svc_add">
        <label><span><?= t('Service name', 'काम का नाम') ?></span><input type="text" name="sname" placeholder="<?= h(t('e.g. Haircut', 'जैसे बाल कटिंग')) ?>"></label>
        <label class="sp-w-s"><span>₹</span><input type="number" name="sprice" value="50"></label>
        <label class="sp-w-s"><span><?= t('Min', 'मिनट') ?></span><input type="number" name="smin" value="15"></label>
        <button class="sp-btn sp-btn-go"><?= t('Add', 'जोड़िए') ?></button>
      </form>
      <p class="sp-note"><?= t('Guess the minutes. Pressing “Done” teaches the real time automatically.', 'मिनट अंदाज़े से भर दीजिए। “हो गया” दबाते रहने पर असली समय अपने आप सीख लिया जाएगा।') ?></p>
    </div>
  </div>
<?php endif; ?>
