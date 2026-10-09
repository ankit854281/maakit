<?php
// ============================================================
// Maakit — sewa booking ka page
//
// Yahi wo page hai jo pehle book.php tha. Jab purani kitaab wala
// feature bana to usi naam ki nayi file ban gayi aur ye khatm ho
// gaya — saare booking ke dibbe "kitaab nahi mili" dikhane lage.
//
//   /sewa.php              -> saari sewayein
//   /sewa.php?s=safar      -> sawari ya saaman? (pehla sawal)
//   /sewa.php?s=gaadi      -> us sewa ka form
//
// Form ka dhancha inc/services.php me hai. Nayi sewa wahin judegi,
// yahan kuchh badalna nahi padega.
// ============================================================
require_once __DIR__ . '/inc/fn.php';
require_once __DIR__ . '/inc/icons.php';
require_once __DIR__ . '/inc/customer.php';
require_once __DIR__ . '/inc/services.php';

$tab  = 'kaam';
$slug = preg_replace('/[^a-z]/', '', (string)get('s'));
$err  = '';
$done = null;

$SVCS = services();

// ---------- sewa chuni hai ya nahi ----------
$svc = $slug && $slug !== 'safar' ? service_get($slug) : null;
if ($slug && $slug !== 'safar' && !$svc) { redirect('/sewa.php'); }

$villages = array_values(array_filter(coverage_areas($pdo), fn($a)=>coverage_enabled($a,'booking')));
$me = cust();
require_once __DIR__.'/inc/submit-once.php';
$submit_key = '';
if ($svc) [$submit_key,$done,$err] = submit_once_form('booking:'.$slug);

// ============================================================
// form bhara gaya
// ============================================================
if ($svc && $_SERVER['REQUEST_METHOD'] === 'POST' && !$done && !$err) {

    if (!csrf_ok()) {
        $err = t('The page got old. Please send again.', 'पेज पुराना हो गया। एक बार फिर भेजिए।');
    } else {
        $nm  = trim((string)post('name'));
        $mob = preg_replace('/\D/', '', (string)post('mobile'));
        $vil = trim((string)post('village'));
        $adr = trim((string)post('address'));
        $nt  = trim((string)post('note'));

        // har field ka jawab uthao
        $ans = [];
        foreach ($svc['fields'] as $f) {
            $k = $f['k'];
            if ($f['t'] === 'multi') {
                $v = post($k);
                $ans[$k] = is_array($v) ? array_values(array_filter(array_map('strval', $v))) : [];
            } else {
                $ans[$k] = trim((string)post($k));
            }
        }

        // jaanch
        if (mb_strlen($nm) < 2)       { $err = t('Please write your name.', 'अपना नाम लिखिए।'); }
        elseif (strlen($mob) !== 10)  { $err = t('Mobile number must be 10 digits.', 'मोबाइल नंबर 10 अंकों का लिखिए।'); }
        elseif (!coverage_enabled(coverage_area($pdo,$vil),'booking')) { $err=coverage_error('booking'); }
        else {
            foreach ($svc['fields'] as $f) {
                if (empty($f['req'])) continue;
                $v = $ans[$f['k']];
                if ($v === '' || $v === []) {
                    $err = t('Please fill: ', 'यह भरना ज़रूरी है: ') . f_label($f['l']);
                    break;
                }
            }
        }

        if (!$err) {
            $lines = booking_lines($svc, $ans, true);
            $summ  = [];
            foreach ($lines as $l) { $summ[] = $l[0] . ': ' . $l[1]; }

            $no   = new_booking_no($pdo);
            $code = (string)new_code();

            $st = $pdo->prepare(
                "INSERT INTO service_bookings
                 (booking_no, code, service, customer_id, name, mobile, village, address, answers, summary, note)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?)"
            );
            $st->execute([
                $no, $code, $slug, $me['id'] ?? null, $nm, $mob, $vil, $adr,
                json_encode($ans, JSON_UNESCAPED_UNICODE),
                implode("\n", $summ),
                $nt !== '' ? $nt : null,
            ]);

            $done = ['no' => $no, 'code' => $code, 'mobile' => $mob, 'name' => $nm];
            submit_once_complete($submit_key,$done);
        }
    }
}

// ============================================================
$page_title = $svc
    ? svc_name($svc) . ' — Maakit'
    : t('Bookings — Maakit', 'बुकिंग — Maakit');
include __DIR__ . '/inc/head.php';
?>

<?php // ---------------------------------------------- ho gaya ?>
<?php if ($done): ?>
<section><div class="wrap" style="max-width:620px">
  <div class="okmark"><?= svc_icon('shield', 34) ?></div>
  <h1 style="text-align:center;margin:14px 0 6px"><?= t('Booking received', 'बुकिंग आ गई') ?></h1>
  <p style="text-align:center;color:var(--muted);margin-bottom:18px">
    <?= t('We will call you shortly to confirm the rate.', 'हम थोड़ी देर में फ़ोन करके रेट बता देंगे।') ?>
  </p>

  <div class="codebox">
    <div class="l"><?= t('Your booking number', 'आपका बुकिंग नंबर') ?></div>
    <div style="font-size:24px;font-weight:800;letter-spacing:.5px"><?= h($done['no']) ?></div>
    <div class="l" style="margin-top:14px"><?= t('Your code', 'आपका कोड') ?></div>
    <div class="c">
      <?php foreach (str_split($done['code']) as $d): ?><span><?= h($d) ?></span><?php endforeach; ?>
    </div>
    <div class="h"><?= t('Tell this code only when the work is done.',
                         'यह कोड तभी बताइए जब काम हो जाए।') ?></div>
  </div>

  <div style="display:grid;gap:9px;margin-top:18px">
    <a class="btn btn-brand" href="/track.php?b=<?= urlencode($done['no']) ?>&amp;m=<?= urlencode($done['mobile']) ?>">
      <?= t('See this booking', 'यह बुकिंग देखिए') ?></a>
    <a class="btn btn-green" target="_blank" rel="noopener"
       href="<?= h(wa_link(MAAKIT_WA, t('Hello Maakit, I booked ', 'नमस्ते Maakit, मैंने बुकिंग की है ') . $done['no'])) ?>">
      <?= t('Message on WhatsApp', 'WhatsApp पर भेजिए') ?></a>
    <a class="btn btn-ghost" href="tel:<?= h(MAAKIT_PHONE) ?>"><?= t('Call us', 'कॉल कीजिए') ?></a>
  </div>

  <p style="text-align:center;margin-top:18px">
    <a href="/sewa.php"><?= t('Book something else', 'कुछ और बुक कीजिए') ?></a>
  </p>
</div></section>


<?php // ---------------------------------------------- sawari ya saaman ?>
<?php elseif ($slug === 'safar'): ?>
<section><div class="wrap" style="max-width:620px">
  <div class="pghead">
    <h1><?= t('Vehicle booking', 'गाड़ी बुकिंग') ?></h1>
    <p><?= t('First tell us what has to go.', 'पहले यह बताइए कि जाना क्या है।') ?></p>
  </div>

  <div class="svcgrid">
    <a class="svc" href="/sewa.php?s=gaadi">
      <span class="ic"><?= svc_icon('ride', 28) ?></span>
      <span class="tx">
        <b><?= t('People', 'सवारी — लोग') ?></b>
        <i><?= t('One way, full day, pilgrimage, hospital', 'एक तरफ़, पूरे दिन, तीरथ, अस्पताल') ?></i>
      </span>
      <span class="ar"><?= svc_icon('plus', 18) ?></span>
    </a>
    <a class="svc" href="/sewa.php?s=maal">
      <span class="ic"><?= svc_icon('truck', 28) ?></span>
      <span class="tx">
        <b><?= t('Goods', 'सामान — माल') ?></b>
        <i><?= t('Bricks, sand, grain, furniture, house shifting', 'ईंट, बालू, अनाज, फ़र्नीचर, घर शिफ़्ट') ?></i>
      </span>
      <span class="ar"><?= svc_icon('plus', 18) ?></span>
    </a>
  </div>

  <p style="margin-top:18px;text-align:center">
    <a href="/sewa.php"><?= t('All bookings', 'सारी बुकिंग') ?></a>
  </p>
</div></section>


<?php // ---------------------------------------------- sewa ki soochi ?>
<?php elseif (!$svc): ?>
<section><div class="wrap" style="max-width:720px">
  <div class="pghead">
    <h1><?= t('What do you need booked?', 'क्या बुक करवाना है?') ?></h1>
    <p><?= t('Tell us, and we will find who is free and what it costs.',
             'बता दीजिए — कौन खाली है और क्या रेट है, हम पता करके बताएँगे।') ?></p>
  </div>

  <div class="svcgrid">
    <?php foreach ($SVCS as $k => $s): if ($k === 'maal') continue; ?>
      <a class="svc" href="/sewa.php?s=<?= h($k === 'gaadi' ? 'safar' : $k) ?>">
        <span class="ic"><?= svc_icon($s['icon'], 28) ?></span>
        <span class="tx">
          <b><?= h($k === 'gaadi' ? t('Vehicle booking', 'गाड़ी बुकिंग') : svc_name($s)) ?></b>
          <i><?= h($k === 'gaadi' ? t('People and goods — both', 'सवारी और सामान — दोनों') : $s['tag']) ?></i>
        </span>
        <span class="ar"><?= svc_icon('plus', 18) ?></span>
      </a>
    <?php endforeach; ?>
  </div>
</div></section>


<?php // ---------------------------------------------- form ?>
<?php else: ?>
<section><div class="wrap" style="max-width:620px">
  <div class="pghead">
    <h1><?= h(svc_name($svc)) ?></h1>
    <p><?= h($svc['lead']) ?></p>
  </div>

  <?php if ($err): ?><div class="err"><?= h($err) ?></div><?php endif; ?>

  <form method="post" class="card" style="padding:18px">
    <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
    <input type="hidden" name="submit_key" value="<?= h($submit_key) ?>">

    <?php foreach ($svc['fields'] as $f):
      $k = $f['k']; $lb = f_label($f['l']); $req = !empty($f['req']);
      $val = (string)post($k);
      if ($_SERVER['REQUEST_METHOD']==='GET' && $slug==='mistri' && $k==='event' && is_string($_GET['work']??null) && in_array($_GET['work'],$f['o']??[],true)) $val=$_GET['work']; ?>
      <div class="step">
        <label for="f_<?= h($k) ?>"><b><?= h($lb) ?></b><?= $req ? ' <span style="color:#A33427">*</span>' : '' ?></label>

        <?php if ($f['t'] === 'select'): ?>
          <select id="f_<?= h($k) ?>" name="<?= h($k) ?>" <?= $req ? 'required' : '' ?>>
            <option value=""><?= t('Choose…', 'चुनिए…') ?></option>
            <?php foreach ($f['o'] as $o): ?>
              <option value="<?= h($o) ?>" <?= $val === $o ? 'selected' : '' ?>><?= h(opt_label($o)) ?></option>
            <?php endforeach; ?>
          </select>

        <?php elseif ($f['t'] === 'multi'): ?>
          <?php $chosen = (array)(post($k) ?: []); ?>
          <div class="pickers">
            <?php foreach ($f['o'] as $o): ?>
              <label class="pick">
                <input type="checkbox" name="<?= h($k) ?>[]" value="<?= h($o) ?>"
                       <?= in_array($o, $chosen, true) ? 'checked' : '' ?>>
                <span><?= h(opt_label($o)) ?></span>
              </label>
            <?php endforeach; ?>
          </div>

        <?php elseif ($f['t'] === 'area'): ?>
          <select id="f_<?= h($k) ?>" name="<?= h($k) ?>" <?= $req ? 'required' : '' ?>>
            <option value=""><?= t('Choose…', 'चुनिए…') ?></option>
            <?php foreach ($villages as $v): ?>
              <option value="<?= h($v['name']) ?>" <?= $val === $v['name'] ? 'selected' : '' ?>><?= h(coverage_label($v)) ?></option>
            <?php endforeach; ?>
          </select>

        <?php elseif ($f['t'] === 'date'): ?>
          <input id="f_<?= h($k) ?>" type="date" name="<?= h($k) ?>" value="<?= h($val) ?>"
                 <?= isset($f['min']) ? 'min="' . h($f['min']) . '"' : '' ?> <?= $req ? 'required' : '' ?>>

        <?php elseif ($f['t'] === 'time'): ?>
          <input id="f_<?= h($k) ?>" type="time" name="<?= h($k) ?>" value="<?= h($val) ?>" <?= $req ? 'required' : '' ?>>

        <?php elseif ($f['t'] === 'number'): ?>
          <input id="f_<?= h($k) ?>" type="number" inputmode="numeric" name="<?= h($k) ?>" value="<?= h($val) ?>"
                 <?= isset($f['ph']) ? 'placeholder="' . h($f['ph']) . '"' : '' ?>
                 <?= isset($f['min']) ? 'min="' . (int)$f['min'] . '"' : '' ?>
                 <?= isset($f['max']) ? 'max="' . (int)$f['max'] . '"' : '' ?> <?= $req ? 'required' : '' ?>>

        <?php else: ?>
          <input id="f_<?= h($k) ?>" type="text" name="<?= h($k) ?>" value="<?= h($val) ?>"
                 <?= isset($f['ph']) ? 'placeholder="' . h($f['ph']) . '"' : '' ?> <?= $req ? 'required' : '' ?>>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>

    <hr style="border:0;border-top:1px solid var(--line);margin:18px 0">

    <div class="step">
      <label for="name"><b><?= t('Your name', 'आपका नाम') ?></b> <span style="color:#A33427">*</span></label>
      <input id="name" type="text" name="name" required value="<?= h((string)(post('name') ?: ($me['name'] ?? ''))) ?>">
    </div>
    <div class="step">
      <label for="mobile"><b><?= t('Mobile number', 'मोबाइल नंबर') ?></b> <span style="color:#A33427">*</span></label>
      <input id="mobile" type="tel" inputmode="numeric" name="mobile" maxlength="10" required
             value="<?= h((string)(post('mobile') ?: ($me['mobile'] ?? ''))) ?>">
    </div>
    <div class="step">
      <label for="village"><b><?= t('Service area', 'सेवा का इलाका') ?></b> <span style="color:#A33427">*</span></label>
      <select id="village" name="village" required>
        <option value=""><?= t('Choose…', 'चुनिए…') ?></option>
        <?php $pv = (string)(post('village') ?: (coverage_selected($pdo)['name'] ?? ($me['village'] ?? ''))); ?>
        <?php foreach ($villages as $v): ?>
          <option value="<?= h($v['name']) ?>" <?= $pv === $v['name'] ? 'selected' : '' ?>><?= h(coverage_label($v)) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="step">
      <label for="address"><b><?= t('Landmark (optional)', 'पहचान (मर्ज़ी से)') ?></b></label>
      <input id="address" type="text" name="address" value="<?= h((string)post('address')) ?>"
             placeholder="<?= h(t('near the temple, Yadav tola', 'मंदिर के पास, यादव टोला')) ?>">
    </div>
    <div class="step">
      <label for="note"><b><?= t('Anything else to tell us?', 'और कुछ बताना है?') ?></b></label>
      <input id="note" type="text" name="note" value="<?= h((string)post('note')) ?>">
    </div>

    <button class="btn btn-brand" type="submit" style="width:100%">
      <?= t('Send booking', 'बुकिंग भेजिए') ?>
    </button>
    <p style="font-size:13px;color:var(--muted);margin:12px 0 0;text-align:center">
      <?= t('No money now. We call, tell you the rate, and only then it is fixed.',
            'अभी कोई पैसा नहीं। हम फ़ोन करके रेट बताएँगे, तभी बुकिंग पक्की होगी।') ?>
    </p>
  </form>

  <p style="margin-top:16px;text-align:center">
    <a href="/sewa.php"><?= t('Book something else', 'कुछ और बुक कीजिए') ?></a>
  </p>
</div></section>
<?php endif; ?>

<?php include __DIR__ . '/inc/foot.php'; ?>

