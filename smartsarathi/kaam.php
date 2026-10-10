<?php
// ============================================================
//  सारथी — काम
//
//  Ek hi kaam khula rehta hai (jo abhi karna hai), baaki patli
//  patti me. Dhoop me, gaadi par, ek haath se chalta hai.
//
//  Saare POST yahin aate hain — baaki panne sirf dikhate hain.
// ============================================================
require_once __DIR__ . '/inc/fn.php';
require_once __DIR__ . '/inc/kaam.php';
require_once __DIR__ . '/inc/ui.php';

$me = rider_me($pdo);
if (!$me) { redirect('/'); }
$rid = (int)$me['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_ok()) {
    $do = post('do');

    if ($do === 'bahar')    { rider_logout(); redirect('/'); }
    if ($do === 'duty_on')  { duty_on($pdo, $rid);  flash('हाज़िरी लग गई — अगले 90 मिनट तक।'); redirect('/kaam.php'); }
    if ($do === 'duty_off') { duty_off($pdo, $rid); flash('अब आप बंद हैं।');                   redirect('/kaam.php'); }

    if ($do === 'trip_band') {
        flash(trip_close($pdo, $rid)
            ? 'चक्कर बंद हो गया।'
            : 'इस चक्कर में कोई काम बाकी है — पहले उसे निपटाइए।');
        redirect('/kaam.php');
    }
    if ($do === 'uthaya') {
        [$ok, $msg] = job_pick($pdo, $rid, (int)post('jid'), post('otp'));
        flash($msg); redirect('/kaam.php');
    }
    if ($do === 'de_diya') {
        $photo = save_photo('pod');                       // chhoti karke rakhi jaati hai
        [$ok, $msg] = job_deliver($pdo, $rid, (int)post('jid'), post('otp'), $photo);
        if (!$ok && $photo) drop_photo($photo);
        flash($msg); redirect('/kaam.php');
    }
    if ($do === 'samasya') {
        [$ok, $msg] = problem_add($pdo, $rid, (int)post('jid'), post('kism'), post('note'));
        flash($msg); redirect('/kaam.php');
    }
}

$jobs  = rider_jobs($pdo, $rid);
$ready = duty_until($pdo, $rid);
$aaj   = rider_done_today($pdo, $rid);
$trip  = trip_open($pdo, $rid);
$msg   = flash();
$kisme = problem_kinds();
$rate  = (int)$me['rate'];
$extra = (int)$me['extra_rate'];

// "Is chakkar me kitne" — matlab is baar nikalne par KUL kitne,
// nipte hue bhi. Pehle sirf baaki kaam gine jaate the, to teen
// pahuncha chuke rider ko bhi "0" dikhta tha.
$chakkar = 0;
if ($trip) {
    $st = $pdo->prepare("SELECT COUNT(*) FROM jobs WHERE trip_id=?");
    $st->execute([(int)$trip['id']]);
    $chakkar = (int)$st->fetchColumn();
}

sr_head($me, 'kaam', 'काम', $ready, ['kaam' => count($jobs)]);
?>

<?php if ($msg): ?><div class="sr-flash"><?= h($msg) ?></div><?php endif; ?>

<!-- हाज़िरी -->
<?php if ($ready): ?>
  <div class="sr-strip sr-strip-on">
    <span><i></i> आज <b><?= $aaj ?></b> पहुँचाए</span>
    <div>
      <form method="post"><input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
        <input type="hidden" name="do" value="duty_on"><button type="submit">+90 मि</button></form>
      <form method="post"><input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
        <input type="hidden" name="do" value="duty_off"><button type="submit" class="sr-x">बंद</button></form>
    </div>
  </div>
<?php else: ?>
  <form method="post" class="sr-strip sr-strip-off">
    <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
    <input type="hidden" name="do" value="duty_on">
    <span>अभी आप बंद हैं — नया काम नहीं आएगा</span>
    <button type="submit">मैं तैयार हूँ</button>
  </form>
<?php endif; ?>

<!-- चक्कर -->
<?php if ($trip): ?>
  <div class="sr-strip sr-strip-trip">
    <span>इस चक्कर में <b><?= $chakkar ?></b></span>
    <form method="post"><input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
      <input type="hidden" name="do" value="trip_band"><button type="submit">चक्कर बंद</button></form>
  </div>
<?php endif; ?>

<!-- काम -->
<?php if (!$jobs): ?>
  <div class="sr-khali">
    <?= sr_icon('box', 40) ?>
    <p>अभी कोई काम नहीं है।</p>
    <p class="sr-khali-s">हाज़िरी लगी रहने दीजिए — काम आते ही यहीं दिखेगा।</p>
  </div>
<?php else:
  $pehla = true;
  foreach ($jobs as $j):
    $utha  = $j['status'] === 'picked';
    $khula = $pehla; $pehla = false;
    // chakkar ka pehla drop poore rate par, agla kam par
    $mera  = ($j['trip_id'] && (int)$j['trip_seq'] > 1) ? $extra : $rate;
?>
  <details class="sr-ord <?= $utha ? 'sr-ord-utha' : '' ?>" <?= $khula ? 'open' : '' ?>>
    <summary class="sr-head">
      <span class="sr-dot"></span>
      <span class="sr-head-mid">
        <b><?= h($j['drop_name']) ?></b>
        <small><?= h($j['drop_village'] ?: $j['drop_address']) ?> · <?= h($j['client_name']) ?></small>
      </span>
      <span class="sr-head-tag"><?= $utha ? 'साथ में' : 'उठाना है' ?></span>
    </summary>

    <div class="sr-body">
      <?php if (!$utha && $j['pick_address']): ?>
        <p class="sr-mark"><b>उठाइए:</b> <?= h($j['pick_name'] ? $j['pick_name'] . ' · ' : '') ?><?= h($j['pick_address']) ?></p>
      <?php endif; ?>
      <p class="sr-mark"><b>पहुँचाइए:</b> <?= h($j['drop_address']) ?></p>

      <?php if ($j['items']): ?><div class="sr-items"><?= h($j['items']) ?></div><?php endif; ?>

      <div class="sr-money">
        <span><?= h($j['job_no']) ?></span>
        <?php if ($j['cod_amount'] !== null): ?>
          <span>नगद लेना <b>₹<?= (int)$j['cod_amount'] ?></b></span>
        <?php endif; ?>
      </div>

      <?php if ($rate > 0): ?>
        <div class="sr-mera">आपको मिलेगा <b>₹<?= (int)$mera ?></b><?php
          if ($extra > 0 && (int)$mera === $rate): ?><small>· इसी चक्कर में अगले का ₹<?= $extra ?></small><?php
          endif; ?></div>
      <?php endif; ?>

      <div class="sr-go">
        <?php if ($r = maps_link($j)): ?>
          <a class="sr-go-a sr-go-map" href="<?= h($r) ?>" target="_blank" rel="noopener">रास्ता दिखाइए</a>
        <?php endif; ?>
        <a class="sr-go-a" href="tel:<?= h($j['drop_mobile']) ?>"><?= sr_icon('phone',17) ?> ग्राहक</a>
        <?php if ($j['pick_mobile']): ?>
          <a class="sr-go-a" href="tel:<?= h($j['pick_mobile']) ?>"><?= sr_icon('phone',17) ?> दुकान</a>
        <?php endif; ?>
      </div>

      <?php if (!$utha): ?>
        <form method="post" class="sr-act">
          <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
          <input type="hidden" name="do" value="uthaya">
          <input type="hidden" name="jid" value="<?= (int)$j['id'] ?>">
          <label class="sr-lab">भेजने वाले से <b>4 अंक</b> पूछिए</label>
          <div class="sr-pair">
            <input class="sr-otp" name="otp" inputmode="numeric" maxlength="4" size="4"
                   pattern="[0-9]{4}" placeholder="____" required>
            <button class="sr-btn sr-btn-go" type="submit">उठा लिया</button>
          </div>
        </form>
      <?php else: ?>
        <form method="post" class="sr-act" enctype="multipart/form-data">
          <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
          <input type="hidden" name="do" value="de_diya">
          <input type="hidden" name="jid" value="<?= (int)$j['id'] ?>">
          <label class="sr-lab">ग्राहक से <b>4 अंक</b> पूछिए</label>
          <div class="sr-pair">
            <input class="sr-otp" name="otp" inputmode="numeric" maxlength="4" size="4"
                   pattern="[0-9]{4}" placeholder="____" required>
            <button class="sr-btn sr-btn-done" type="submit">पहुँचा दिया</button>
          </div>
          <label class="sr-file-lab">
            <input type="file" name="pod" accept="image/*" capture="environment">
            <span>सामान की फ़ोटो लीजिए <small>(अच्छा रहेगा)</small></span>
          </label>
        </form>
      <?php endif; ?>

      <!-- समस्या — काम रद्द नहीं होता, सिर्फ़ दफ़्तर को पता चलता है -->
      <details class="sr-prob">
        <summary>कुछ दिक्कत आ रही है?</summary>
        <form method="post" class="sr-prob-f">
          <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
          <input type="hidden" name="do" value="samasya">
          <input type="hidden" name="jid" value="<?= (int)$j['id'] ?>">
          <?php foreach ($kisme as $kk => $kl): ?>
            <label class="sr-rad">
              <input type="radio" name="kism" value="<?= h($kk) ?>" required>
              <span><?= h($kl) ?></span>
            </label>
          <?php endforeach; ?>
          <input class="sr-in" type="text" name="note" maxlength="300" placeholder="कुछ और बताना हो तो लिखिए">
          <button class="sr-btn sr-btn-warn" type="submit">दफ़्तर को बताइए</button>
        </form>
      </details>

    </div>
  </details>
<?php endforeach; endif; ?>

<?php if ($ready && !$jobs): ?>
<script>
// हाज़िर हैं पर काम नहीं — हर मिनट खुद देख लीजिए.
// Kaam khula ho to refresh NAHI karte — likha hua OTP ud jata.
setTimeout(function () { location.reload(); }, 60000);
</script>
<?php endif; ?>

<?php sr_foot('kaam', ['kaam' => count($jobs)]); ?>
