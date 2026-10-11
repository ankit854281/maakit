<?php
// ============================================================
//  सारथी — राइडर बनिए (arzi)
//
//  Pehle ye maakit.in/public/partner-apply.php?kind=rider par tha.
//  Ab delivery ka poora kaam Smart Sarathi par hai, isliye arzi
//  bhi yahin. Arzi admin (/admin/) me "नई अर्ज़ियाँ" me dikhti hai;
//  "मंज़ूर" dabate hi rider ban jaata hai aur use login code milta hai.
//
//  Yahan Aadhaar / bank number NAHI maanga jata — wo dafttar me
//  aamne-saamne dekha jata hai.
// ============================================================
require_once __DIR__ . '/inc/public.php';
sr_apply_table($pdo);

$err = ''; $done = false;
$vehicles = ['cycle' => t('Bicycle', 'साइकिल'), 'bike' => t('Motorcycle / scooter', 'बाइक / स्कूटी'), 'ev' => t('Electric scooter', 'बिजली वाली स्कूटी'), 'auto' => t('Auto / e-rickshaw', 'ऑटो / ई-रिक्शा'), 'none' => t('No vehicle yet', 'अभी गाड़ी नहीं')];
$hours = ['full' => t('Full day', 'पूरा दिन'), 'morning' => t('Morning', 'सुबह'), 'evening' => t('Evening', 'शाम'), 'weekend' => t('Holidays only', 'सिर्फ़ छुट्टी के दिन')];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim((string)post('name'));
    $mob  = preg_replace('/\D/', '', (string)post('mobile'));
    if (!csrf_ok()) {
        $err = t('Page expired — please submit again.', 'पेज पुराना हो गया — दोबारा भेजिए।');
    } elseif (post('website') !== '') {
        $done = true; // bot
    } elseif (mb_strlen($name) < 2) {
        $err = t('Please write your full name.', 'अपना पूरा नाम लिखिए।');
    } elseif (!preg_match('/^[6-9]\d{9}$/D', $mob)) {
        $err = t('Mobile number must be 10 digits.', 'मोबाइल नंबर 10 अंकों का लिखिए।');
    } elseif (post('agree') !== '1') {
        $err = t('Please tick that the details are correct.', 'जानकारी सही होने का निशान लगाइए।');
    } elseif (!sr_public_limit('apply', 5)) {
        $err = t('Too many tries. Please call us.', 'बहुत बार कोशिश हुई। हमें फ़ोन कीजिए।');
    } else {
        $st = $pdo->prepare("SELECT COUNT(*) FROM riders WHERE mobile=?"); $st->execute([$mob]);
        if ((int)$st->fetchColumn()) {
            $err = t('This number is already a Sarathi rider — just sign in.', 'यह नंबर पहले से सारथी राइडर है — बस लॉगिन कीजिए।');
        } else {
            $st = $pdo->prepare("SELECT id FROM applications WHERE mobile=? AND status='new'"); $st->execute([$mob]);
            if (!$st->fetch()) {
                $veh = array_key_exists(post('vehicle'), $vehicles) ? post('vehicle') : null;
                $hr  = array_key_exists(post('hours'), $hours) ? post('hours') : null;
                $pdo->prepare("INSERT INTO applications (name, mobile, village, vehicle, dl_number, rc_number, hours, note) VALUES (?,?,?,?,?,?,?,?)")
                    ->execute([mb_substr($name, 0, 80), $mob, mb_substr(trim((string)post('village')), 0, 80) ?: null,
                               $veh ? $vehicles[$veh] : null,
                               mb_substr(strtoupper(trim((string)post('dl'))), 0, 30) ?: null,
                               mb_substr(strtoupper(trim((string)post('rc'))), 0, 30) ?: null,
                               $hr ? $hours[$hr] : null,
                               mb_substr(trim((string)post('note')), 0, 300) ?: null]);
            }
            $done = true;
        }
    }
}

sr_public_head('apply', t('Become a rider', 'राइडर बनिए'), t('EARN NEAR HOME', 'घर के पास कमाई'),
    t('Ride with Sarathi.', 'सारथी के साथ चलिए,'), t('Earn every day.', 'रोज़ कमाइए।'),
    t('Bicycle, bike or e-rickshaw — work your own hours near home, with a clear record of every rupee.', 'साइकिल, बाइक या ई-रिक्शा — घर के पास, अपने समय पर काम, हर रुपये का साफ़ हिसाब।'),
    [['rupee', t('Payment for every delivery', 'हर डिलीवरी का पैसा')], ['clock', t('Choose your hours', 'अपना समय ख़ुद चुनिए')], ['check', t('No joining fee', 'कोई जॉइनिंग फ़ीस नहीं')]]);
?>
  <?php if ($done): ?>
    <div class="sx-card sx-done">
      <div class="sx-done-ic"><?= sr_icon('check', 30) ?></div>
      <h2><?= h(t('Application received!', 'अर्ज़ी मिल गई!')) ?></h2>
      <p><?= h(t('Our office will call you within 1–2 days. Bring your driving licence and vehicle papers to the meeting. After approval you get your login code.', 'हमारा दफ़्तर 1–2 दिन में फ़ोन करेगा। मिलने आते समय ड्राइविंग लाइसेंस और गाड़ी के काग़ज़ लाइए। मंज़ूरी के बाद लॉगिन कोड मिलेगा।')) ?></p>
      <a class="sx-btn sx-btn-navy" href="<?= h(u('/')) ?>"><?= h(t('Go to rider login', 'राइडर लॉगिन पर जाइए')) ?></a>
    </div>
  <?php else: ?>
    <?php if ($err): ?><div class="sx-alert"><?= sr_icon('alert', 18) ?> <?= h($err) ?></div><?php endif; ?>
    <form method="post" class="sx-card sx-form" autocomplete="on">
      <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
      <input type="text" name="website" value="" tabindex="-1" autocomplete="off" class="sx-hp" aria-hidden="true">
      <div class="sx-pane-h"><span class="sx-pin a"><?= sr_icon('bike', 16) ?></span><div><b><?= h(t('Join in 1 minute', '1 मिनट में अर्ज़ी')) ?></b><small><?= h(t('No fee. We call you back.', 'कोई फ़ीस नहीं। हम फ़ोन करेंगे।')) ?></small></div></div>
      <div class="sx-grid2">
        <label class="sx-field"><span><?= h(t('Full name', 'पूरा नाम')) ?></span><input name="name" value="<?= h(post('name')) ?>" autocomplete="name" required></label>
        <label class="sx-field"><span><?= h(t('Mobile', 'मोबाइल')) ?></span><input name="mobile" type="tel" inputmode="numeric" value="<?= h(post('mobile')) ?>" autocomplete="tel-national" required></label>
      </div>
      <label class="sx-field"><span><?= h(t('Village / area', 'गाँव / इलाका')) ?></span><input name="village" value="<?= h(post('village')) ?>" placeholder="<?= h(t('e.g. Gyanpur, Bhadohi', 'जैसे ज्ञानपुर, भदोही')) ?>"></label>
      <span class="sx-lab"><?= h(t('Your vehicle', 'आपकी गाड़ी')) ?></span>
      <div class="sx-tiles">
        <?php foreach (['cycle' => '🚲', 'bike' => '🏍️', 'ev' => '🛵', 'auto' => '🛺', 'none' => '🚶'] as $kk => $em): ?>
          <label><input type="radio" name="vehicle" value="<?= h($kk) ?>" <?= post('vehicle', 'bike') === $kk ? 'checked' : '' ?>><span><i><?= $em ?></i><?= h($vehicles[$kk]) ?></span></label>
        <?php endforeach; ?>
      </div>
      <span class="sx-lab"><?= h(t('When can you work?', 'कब काम कर सकते हैं?')) ?></span>
      <div class="sx-chips">
        <?php foreach ($hours as $kk => $lbl): ?><label><input type="radio" name="hours" value="<?= h($kk) ?>" <?= post('hours', 'full') === $kk ? 'checked' : '' ?>><span><?= h($lbl) ?></span></label><?php endforeach; ?>
      </div>
      <details class="sx-more">
        <summary><?= h(t('Licence & vehicle number (optional)', 'लाइसेंस और गाड़ी नंबर (मर्ज़ी से)')) ?></summary>
        <div class="sx-grid2">
          <label class="sx-field"><span><?= h(t('Driving licence no.', 'ड्राइविंग लाइसेंस नं.')) ?></span><input name="dl" value="<?= h(post('dl')) ?>" autocapitalize="characters"></label>
          <label class="sx-field"><span><?= h(t('Vehicle no.', 'गाड़ी नं.')) ?></span><input name="rc" value="<?= h(post('rc')) ?>" autocapitalize="characters" placeholder="UP66 AB 1234"></label>
        </div>
        <label class="sx-field"><span><?= h(t('Anything else?', 'और कुछ?')) ?></span><input name="note" value="<?= h(post('note')) ?>" maxlength="300"></label>
      </details>
      <label class="sx-check"><input type="checkbox" name="agree" value="1" required><span><?= h(t('My details are correct. Sarathi may call me about this work.', 'मेरी जानकारी सही है। सारथी इस काम के लिए मुझे फ़ोन कर सकता है।')) ?></span></label>
      <button class="sx-btn sx-btn-mint" type="submit"><?= sr_icon('arrow', 18) ?> <?= h(t('Send application', 'अर्ज़ी भेजिए')) ?></button>
      <p class="sx-fine"><?= h(t('We never ask for Aadhaar, bank or ATM numbers on a form or call.', 'हम फ़ॉर्म या फ़ोन पर कभी आधार, बैंक या ATM नंबर नहीं माँगते।')) ?></p>
    </form>
  <?php endif; ?>
<?php sr_public_foot();
