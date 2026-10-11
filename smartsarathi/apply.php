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
    t('Deliver in your area.', 'अपने इलाके में डिलीवरी,'), t('Earn every day.', 'रोज़ की कमाई।'),
    t('Bicycle, bike or e-rickshaw — choose your own hours. Payment for every delivery, with a clear daily record.', 'साइकिल, बाइक या ई-रिक्शा — अपने समय पर काम। हर डिलीवरी का पैसा, रोज़ का साफ़ हिसाब।'));
?>
  <?php if ($done): ?>
    <div class="sr-card sp-done">
      <span class="sp-tick" aria-hidden="true">✓</span>
      <h2><?= h(t('Application received!', 'अर्ज़ी मिल गई!')) ?></h2>
      <p><?= h(t('Our office will call you within 1–2 days for a short meeting. Bring your driving licence and vehicle papers. After approval you get your login code.', 'हमारा दफ़्तर 1–2 दिन में आपको फ़ोन करेगा। ड्राइविंग लाइसेंस और गाड़ी के कागज़ साथ लाइए। मंज़ूरी के बाद आपको लॉगिन कोड मिलेगा।')) ?></p>
      <a class="sr-btn sr-btn-go" href="<?= h(u('/')) ?>"><?= h(t('Go to rider login', 'राइडर लॉगिन पर जाइए')) ?></a>
    </div>
  <?php else: ?>
    <div class="sr-brand"><div class="sr-logo"><?= h(t('Become a Sarathi rider', 'सारथी राइडर बनिए')) ?></div><p><?= h(t('Takes 1 minute. No fee.', '1 मिनट लगेगा। कोई फ़ीस नहीं।')) ?></p></div>
    <?php if ($err): ?><div class="sr-err"><?= h($err) ?></div><?php endif; ?>
    <form method="post" class="sr-card" autocomplete="on">
      <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
      <input type="text" name="website" value="" tabindex="-1" autocomplete="off" class="sp-hp" aria-hidden="true">
      <label class="sr-lab" for="n"><?= h(t('Full name', 'पूरा नाम')) ?></label>
      <input class="sr-in" id="n" name="name" value="<?= h(post('name')) ?>" autocomplete="name" required>
      <label class="sr-lab" for="m"><?= h(t('Mobile number', 'मोबाइल नंबर')) ?></label>
      <input class="sr-in" id="m" name="mobile" type="tel" inputmode="numeric" value="<?= h(post('mobile')) ?>" autocomplete="tel-national" required>
      <label class="sr-lab" for="v"><?= h(t('Village / area', 'गाँव / इलाका')) ?></label>
      <input class="sr-in" id="v" name="village" value="<?= h(post('village')) ?>" placeholder="<?= h(t('e.g. Gyanpur, Bhadohi', 'जैसे ज्ञानपुर, भदोही')) ?>">
      <span class="sr-lab"><?= h(t('Vehicle', 'गाड़ी')) ?></span>
      <div class="sp-chips">
        <?php foreach ($vehicles as $k => $lbl): ?>
          <label><input type="radio" name="vehicle" value="<?= h($k) ?>" <?= post('vehicle', 'bike') === $k ? 'checked' : '' ?>><span><?= h($lbl) ?></span></label>
        <?php endforeach; ?>
      </div>
      <span class="sr-lab"><?= h(t('When can you work?', 'कब काम कर सकते हैं?')) ?></span>
      <div class="sp-chips">
        <?php foreach ($hours as $k => $lbl): ?>
          <label><input type="radio" name="hours" value="<?= h($k) ?>" <?= post('hours', 'full') === $k ? 'checked' : '' ?>><span><?= h($lbl) ?></span></label>
        <?php endforeach; ?>
      </div>
      <div class="sp-two">
        <div><label class="sr-lab" for="dl"><?= h(t('Driving licence no. (optional)', 'ड्राइविंग लाइसेंस नं. (मर्ज़ी से)')) ?></label>
          <input class="sr-in" id="dl" name="dl" value="<?= h(post('dl')) ?>" autocapitalize="characters"></div>
        <div><label class="sr-lab" for="rc"><?= h(t('Vehicle no. (optional)', 'गाड़ी नं. (मर्ज़ी से)')) ?></label>
          <input class="sr-in" id="rc" name="rc" value="<?= h(post('rc')) ?>" autocapitalize="characters" placeholder="UP66 AB 1234"></div>
      </div>
      <label class="sr-lab" for="nt"><?= h(t('Anything else? (optional)', 'और कुछ? (मर्ज़ी से)')) ?></label>
      <input class="sr-in" id="nt" name="note" value="<?= h(post('note')) ?>" maxlength="300">
      <label class="sp-agree"><input type="checkbox" name="agree" value="1" required> <span><?= h(t('My details are correct. Sarathi may call me about this work.', 'मेरी जानकारी सही है। सारथी इस काम के लिए मुझे फ़ोन कर सकता है।')) ?></span></label>
      <button class="sr-btn sr-btn-go" type="submit"><?= h(t('Send application', 'अर्ज़ी भेजिए')) ?></button>
      <p class="sp-small"><?= h(t('We never ask for Aadhaar, bank or ATM numbers on a form or phone call.', 'हम फ़ॉर्म या फ़ोन पर कभी आधार, बैंक या ATM नंबर नहीं माँगते।')) ?></p>
    </form>
  <?php endif; ?>
<?php sr_public_foot();
