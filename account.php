<?php
require_once __DIR__ . '/inc/fn.php';
require_once __DIR__ . '/inc/icons.php';
require_once __DIR__ . '/inc/items.php';
require_once __DIR__ . '/inc/services.php';
require_once __DIR__ . '/inc/customer.php';

if (f2s_enabled() && !cust() && (get('m') === 'register' || ($_SERVER['REQUEST_METHOD'] === 'POST' && post('do') === 'register'))) redirect('/otp.php');
$tab = 'mere';
$villages = village_list($pdo);
$me = cust();
$err = ''; $mode = get('m', 'login');

// Naya khata ya purana? — mobile likhte hi page khud batata hai.
// (Register karte waqt bhi yahi pata chal jata hai, isliye isme koi
//  nayi jaankari bahar nahi jaati. Phir bhi ek session me 30 baar tak.)
if (get('check') !== '' && !$me) {
    $n = (int)($_SESSION['acc_chk'] ?? 0); $_SESSION['acc_chk'] = $n + 1;
    $mob = preg_replace('/\D/', '', (string)get('check'));
    $has = null;
    if ($n < 30 && strlen($mob) === 10) {
        $st = $pdo->prepare("SELECT 1 FROM customers WHERE mobile=? AND active=1"); $st->execute([$mob]);
        $has = (bool)$st->fetchColumn();
    }
    header('Content-Type: application/json'); header('Cache-Control: no-store');
    echo json_encode(['exists' => $has]); exit;
}
$info = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_ok()) {
    $do = post('do');

    if ($do === 'logout') { cust_logout(); flash(t('You are signed out.', 'आप लॉग आउट हो गए।')); redirect('/account.php'); }

    elseif ($do === 'register') {
        list($ok, $m) = cust_register($pdo, post('name'), post('mobile'), post('password'), post('village'), post('landmark'));
        if ($ok) {
            // chuna hua ilaka hi website ka ilaka bhi ban jaye
            if (post('village') !== '' && ($ar = coverage_area($pdo, post('village')))) $_SESSION['service_area'] = $ar['name'];
            flash(t('Your account is ready. Everything in one place now.', 'आपका खाता बन गया। अब सब एक जगह मिलेगा।')); redirect('/account.php');
        }
        $err = $m; $mode = 'register';
        // number pehle se juda hai — seedha "Sign in" par, number bhara hua
        $st = $pdo->prepare("SELECT 1 FROM customers WHERE mobile=?"); $st->execute([preg_replace('/\D/', '', post('mobile'))]);
        if ($st->fetchColumn()) { $err = ''; $info = t('You already have an account with this number — just enter your password.', 'इस नंबर से आपका खाता पहले से है — बस पासवर्ड डालिए।'); $mode = 'login'; }
    }

    elseif ($do === 'login') {
        list($ok, $m) = cust_login($pdo, post('mobile'), post('password'));
        if ($ok) {
            $c = cust();
            if (empty($_SESSION['service_area']) && !empty($c['village']) && ($ar = coverage_area($pdo, $c['village']))) $_SESSION['service_area'] = $ar['name'];
            redirect('/account.php');
        }
        $err = $m; $mode = 'login';
    }

    elseif ($do === 'save' && $me) {
        $n = trim(post('name')); $v = post('village'); $l = post('landmark');
        if (mb_strlen($n) >= 2) {
            $pdo->prepare("UPDATE customers SET name=?, village=?, landmark=? WHERE id=?")
                ->execute([$n, $v, $l, $me['id']]);
            $_SESSION['cust']['name'] = $n;
            $_SESSION['cust']['village'] = $v;
            $_SESSION['cust']['landmark'] = $l;
            flash(t('Your details are saved.', 'आपकी जानकारी सेव हो गई।'));
        }
        redirect('/account.php');
    }

    elseif ($do === 'pass' && $me) {
        $old = post('old'); $new = post('new');
        $st = $pdo->prepare("SELECT password FROM customers WHERE id=?"); $st->execute([$me['id']]);
        $row = $st->fetch();
        if (!$row || (!cust_sms_recent($me['id']) && !password_verify($old, $row['password']))) { $err = t('Old password is wrong.', 'पुराना पासवर्ड ग़लत है।'); }
        elseif (!cust_password_ok($new)) { $err = cust_password_error(); }
        else {
            Maakit\Api\revoke_legacy_sessions($pdo,'customer');
            $pdo->prepare("UPDATE customers SET password=? WHERE id=?")
                ->execute([password_hash($new, PASSWORD_DEFAULT), $me['id']]);
            unset($_SESSION['sms_customer_recovery']);
            flash(t('Password changed.', 'पासवर्ड बदल गया।'));
            redirect('/account.php');
        }
    }
    $me = cust();
}

$page_title = $me ? t('My account — Maakit', 'मेरा खाता — Maakit') : t('Sign in — Maakit', 'लॉगिन — Maakit');
$orders = $me ? cust_orders($pdo, $me['id'], 10) : [];
$books  = $me ? cust_bookings($pdo, $me['id'], 10) : [];
include __DIR__ . '/inc/head.php';
?>
<div class="wrap" style="max-width:640px">

<?php if ($me): ?>
  <!-- ================= मेरा खाता ================= -->
  <div class="prof">
    <span class="av"><?= svc_icon('user', 30) ?></span>
    <div>
      <b><?= h($me['name']) ?></b>
      <?php $_ma = $me['village'] ? coverage_area($pdo, $me['village']) : null; ?>
      <i><?= h($me['mobile']) ?><?= $me['village'] ? ' · ' . h($_ma ? coverage_label($_ma) : $me['village']) : '' ?></i>
    </div>
    <form method="post" style="margin-left:auto">
      <input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="do" value="logout">
      <button class="btn btn-sm btn-line" style="color:var(--brand);border-color:var(--line)"><?= t('Sign out', 'लॉग आउट') ?></button>
    </form>
  </div>

  <?php if ($err): ?><div class="err"><?= h($err) ?></div><?php endif; ?>

  <div class="quick2">
    <a class="qq" href="/order.php"><?= svc_icon('grocery', 26) ?><b><?= t('Order goods', 'सामान मँगाइए') ?></b></a>
    <a class="qq" href="/sewa.php"><?= svc_icon('all', 26) ?><b><?= t('Make a booking', 'बुकिंग कीजिए') ?></b></a>
    <a class="qq" href="/location.php?next=%2Faccount.php"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M12 22s7-7.6 7-13a7 7 0 0 0-14 0c0 5.4 7 13 7 13z"/><circle cx="12" cy="9" r="2.5"/></svg><b><?= t('Delivery area', 'डिलीवरी इलाका') ?></b></a>
  </div>

  <h3 class="ghead"><?= t('My orders', 'मेरे ऑर्डर') ?></h3>
  <?php if (!$orders): ?>
    <div class="box"><p class="help" style="margin:0"><?= t('No orders yet.', 'अभी कोई ऑर्डर नहीं।') ?> <a href="/order.php"><?= t('Place your first order', 'पहला ऑर्डर कीजिए') ?></a> — <?= t('Delivery fees and offers depend on your service area.', 'डिलीवरी शुल्क और ऑफ़र आपके सेवा क्षेत्र पर निर्भर हैं।') ?></p></div>
  <?php else: foreach ($orders as $o): list($pc,$pl) = status_pill($o['status']); ?>
    <a class="ocard" href="/track.php?no=<?= h($o['order_no']) ?>&m=<?= h($o['mobile']) ?>">
      <div class="hd"><span class="no"><?= h($o['order_no']) ?></span>
        <span class="pill <?= h($pc) ?>"><?= h($pl) ?></span>
        <span class="dt"><?= date('d/m/Y', strtotime($o['created_at'])) ?></span></div>
      <div class="li"><?= h(mb_strimwidth(str_replace("\n", ' · ', $o['items']), 0, 78, '…')) ?></div>
    </a>
  <?php endforeach; endif; ?>

  <?php if ($books): ?>
    <h3 class="ghead"><?= t('My bookings', 'मेरी बुकिंग') ?></h3>
    <?php foreach ($books as $b): list($pc,$pl) = booking_pill($b['status']); $s = service_get($b['service']); ?>
      <a class="ocard" href="/track.php?b=<?= h($b['booking_no']) ?>&m=<?= h($b['mobile']) ?>">
        <div class="hd"><span class="no"><?= h($b['booking_no']) ?></span>
          <span class="pill <?= h($pc) ?>"><?= h($pl) ?></span>
          <span class="dt"><?= date('d/m/Y', strtotime($b['created_at'])) ?></span></div>
        <div class="li"><?= h($s ? svc_name($s) : $b['service']) ?></div>
      </a>
    <?php endforeach; ?>
  <?php endif; ?>

  <h3 class="ghead"><?= t('My details', 'मेरी जानकारी') ?></h3>
  <form method="post" class="box">
    <input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="do" value="save">
    <div class="field"><label for="name"><?= t('Name', 'नाम') ?></label><input type="text" id="name" name="name" value="<?= h($me['name']) ?>" required></div>
    <div class="field"><label for="village"><?= t('Service area', 'सेवा क्षेत्र') ?></label>
      <select id="village" name="village"><option value="">— <?= t('Choose', 'चुनिए') ?> —</option>
        <?php foreach ($villages as $v): ?><option value="<?= h($v['name']) ?>" <?= $me['village'] === $v['name'] ? 'selected' : '' ?>><?= h(coverage_label($v)) ?></option><?php endforeach; ?>
      </select></div>
    <div class="field"><label for="landmark"><?= t('Landmark', 'घर की पहचान') ?></label>
      <input type="text" id="landmark" name="landmark" value="<?= h($me['landmark']) ?>" placeholder="<?= h(t('opposite the temple…', 'मंदिर के सामने…')) ?>"></div>
    <p class="help" style="margin:0 0 12px"><?= t('Mobile', 'मोबाइल नंबर') ?> <b><?= h($me['mobile']) ?></b> — <?= t('call us to change it.', 'बदलवाना हो तो हमें कॉल कीजिए।') ?></p>
    <button type="submit" class="btn btn-brand" style="width:100%"><?= t('Save', 'सेव कीजिए') ?></button>
  </form>

  <details class="box" style="margin-top:12px">
    <summary style="font-weight:600;cursor:pointer"><?= t('Change password', 'पासवर्ड बदलिए') ?></summary>
    <form method="post" style="margin-top:12px">
      <input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="do" value="pass">
      <?php if (!cust_sms_recent($me['id'])): ?><div class="field"><label for="old"><?= t('Old password', 'पुराना पासवर्ड') ?></label><input type="password" id="old" name="old" required></div><?php else: ?><p><?= h(t('Mobile verified. Set your new password now.','मोबाइल की जाँच हो गई। अब नया पासवर्ड रखिए।')) ?></p><?php endif ?>
      <div class="field"><label for="new"><?= t('New password', 'नया पासवर्ड') ?></label><input type="password" id="new" name="new" autocomplete="new-password" minlength="8" required></div>
      <button type="submit" class="btn btn-brand" style="width:100%"><?= t('Change it', 'बदल दीजिए') ?></button>
    </form>
  </details>
  <div style="height:30px"></div>

<?php else: ?>
</div>
<!-- ================= लॉगिन / नया खाता — एक ही flow ================= -->
<?php
  $sel_area = coverage_selected($pdo);
  $pre_v = post('village') ?: ($sel_area['name'] ?? '');
  $areas_js = array_map(fn($a) => ['name' => $a['name'], 'name_en' => $a['name_en'] ?? '', 'city' => $a['city'] ?? '', 'state' => $a['state'] ?? '', 'pincode' => $a['pincode'] ?? '', 'label' => coverage_label($a), 'del' => 1], $villages);
?>
<section class="lg acc">
  <div class="lg-wrap">
    <aside class="lg-side" aria-hidden="true">
      <div class="lg-brand"><?= maakit_logo_svg(44) ?><span>Maakit<small><?= t('Your account', 'आपका खाता') ?></small></span></div>
      <h1><?= t('Everything you order, in one place.', 'आपका हर ऑर्डर, एक जगह।') ?></h1>
      <ul>
        <li><?= t('See and track all your orders and bookings', 'सारे ऑर्डर और बुकिंग देखिए, ट्रैक कीजिए') ?></li>
        <li><?= t('Your area and address filled in every time', 'इलाका और पता हर बार अपने आप भरा') ?></li>
        <li><?= t('Order again in one tap', 'एक बार दबाकर दोबारा मँगाइए') ?></li>
        <li><?= t('Optional — ordering works without an account too', 'ज़रूरी नहीं — बिना खाते के भी ऑर्डर होता है') ?></li>
      </ul>
    </aside>

    <div class="lg-card" data-acc data-mode="<?= $mode === 'register' ? 'register' : 'login' ?>">
      <div class="lg-tabs" role="tablist">
        <a role="tab" href="/account.php?m=login" data-tab="login" class="<?= $mode !== 'register' ? 'on' : '' ?>" aria-selected="<?= $mode !== 'register' ? 'true' : 'false' ?>"><?= t('Sign in', 'लॉगिन') ?></a>
        <a role="tab" href="/account.php?m=register" data-tab="register" class="<?= $mode === 'register' ? 'on' : '' ?>" aria-selected="<?= $mode === 'register' ? 'true' : 'false' ?>"><?= t('New account', 'नया खाता') ?></a>
      </div>

      <?php if ($err): ?><div class="lg-err" role="alert"><?= h($err) ?></div><?php endif; ?>
      <?php if ($info): ?><div class="lg-info" role="status"><?= h($info) ?></div><?php endif; ?>
      <div class="lg-info" data-hint hidden></div>

      <?php if(f2s_enabled()): ?><p><a class="btn btn-brand" href="/otp.php"><?= h(t('SMS OTP login / new account / forgot password','SMS OTP से लॉगिन / नया खाता / पासवर्ड भूल गए')) ?></a></p><?php endif ?>
      <!-- Sign in -->
      <form method="post" class="lg-form" data-form="login"<?= $mode === 'register' ? ' hidden' : '' ?>>
        <input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="do" value="login">
        <div><h2><?= t('Welcome back', 'फिर से स्वागत है') ?></h2><p class="lg-sub"><?= t('Sign in with your mobile number and password.', 'अपना मोबाइल नंबर और पासवर्ड डालिए।') ?></p></div>
        <label class="lg-field"><span><?= t('Mobile number', 'मोबाइल नंबर') ?></span>
          <span class="lg-in"><em>+91</em><input type="tel" id="lmob" name="mobile" value="<?= h(post('mobile')) ?>" inputmode="numeric" maxlength="10" autocomplete="tel-national" required data-mobile><i class="lg-ok" aria-hidden="true"></i></span></label>
        <label class="lg-field"><span><?= t('Password', 'पासवर्ड') ?></span>
          <span class="lg-in"><input type="password" id="lpass" name="password" autocomplete="current-password" required data-pass>
            <button type="button" class="lg-eye" data-eye aria-label="<?= h(t('Show password', 'पासवर्ड दिखाइए')) ?>"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg></button></span></label>
        <label class="lg-check"><input type="checkbox" data-keep checked> <?= t('Remember my number on this phone', 'इस फ़ोन पर मेरा नंबर याद रखिए') ?></label>
        <button type="submit" class="lg-btn"><span><?= t('Sign in', 'लॉगिन कीजिए') ?></span></button>
        <p class="lg-note"><?= t('Forgot your password?', 'पासवर्ड भूल गए?') ?> <a href="tel:<?= MAAKIT_PHONE ?>"><?= t('Call us', 'हमें कॉल कीजिए') ?></a> — <?= t('we’ll set a new one.', 'हम नया बना देंगे।') ?></p>
      </form>

      <!-- New account -->
      <form method="post" class="lg-form" data-form="register"<?= $mode === 'register' ? '' : ' hidden' ?>>
        <input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="do" value="register">
        <div><h2><?= t('Create your account', 'अपना खाता बनाइए') ?></h2><p class="lg-sub"><?= t('Takes 30 seconds. We never give your number to anyone else.', '30 सेकंड लगेंगे। हम आपका नंबर किसी और को नहीं देते।') ?></p></div>
        <label class="lg-field"><span><?= t('Mobile number (10 digits)', 'मोबाइल नंबर (10 अंक)') ?></span>
          <span class="lg-in"><em>+91</em><input type="tel" id="rmob" name="mobile" value="<?= h(post('mobile')) ?>" inputmode="numeric" maxlength="10" autocomplete="tel-national" required data-mobile><i class="lg-ok" aria-hidden="true"></i></span></label>
        <label class="lg-field"><span><?= t('Your name', 'आपका नाम') ?></span>
          <span class="lg-in"><input type="text" id="rname" name="name" value="<?= h(post('name')) ?>" autocomplete="name" required></span></label>
        <label class="lg-field"><span><?= t('Create a password', 'पासवर्ड बनाइए') ?></span>
          <span class="lg-in"><input type="password" id="rpass" name="password" autocomplete="new-password" minlength="8" maxlength="72" required data-pass data-new>
            <button type="button" class="lg-eye" data-eye aria-label="<?= h(t('Show password', 'पासवर्ड दिखाइए')) ?>"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg></button></span>
          <span class="lg-meter" aria-hidden="true"><i></i></span>
          <small class="lg-small" data-pass-msg><?= cust_password_error() ?></small></label>
        <div class="lg-field">
          <span><?= t('Your delivery area', 'आपका डिलीवरी इलाका') ?></span>
          <div class="acc-area">
            <span class="lg-in"><select id="rvil" name="village" data-area>
              <option value="">— <?= t('Choose your area', 'अपना इलाका चुनिए') ?> —</option>
              <?php foreach ($villages as $v): ?><option value="<?= h($v['name']) ?>" <?= $pre_v === $v['name'] ? 'selected' : '' ?>><?= h(coverage_label($v)) ?></option><?php endforeach; ?>
            </select></span>
            <button type="button" class="acc-gps" data-gps title="<?= h(t('Use my location', 'मेरी लोकेशन')) ?>">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="3.5"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3"/><circle cx="12" cy="12" r="8"/></svg>
              <span><?= t('Locate me', 'लोकेशन') ?></span></button>
          </div>
          <small class="lg-small" data-gps-msg><?= t('Tap “Locate me” to fill it from your GPS.', '“लोकेशन” दबाइए — GPS से अपने आप भर जाएगा।') ?></small>
        </div>
        <label class="lg-field"><span><?= t('Landmark (optional)', 'घर की पहचान (मर्ज़ी से)') ?></span>
          <span class="lg-in"><input type="text" id="rland" name="landmark" value="<?= h(post('landmark')) ?>" placeholder="<?= h(t('opposite the temple, near the school…', 'मंदिर के सामने, स्कूल के पास…')) ?>" data-land></span>
          <span class="acc-chips">
            <?php foreach ([t('Near the temple', 'मंदिर के पास'), t('Opposite the school', 'स्कूल के सामने'), t('Near the water tank', 'पानी की टंकी के पास'), t('Main crossing', 'मुख्य चौराहा')] as $lm): ?>
              <button type="button" data-lm="<?= h($lm) ?>"><?= h($lm) ?></button>
            <?php endforeach; ?>
          </span></label>
        <button type="submit" class="lg-btn"><span><?= t('Create account', 'खाता बनाइए') ?></span></button>
      </form>

      <div class="lg-help">
        <span><?= t('No account needed to order.', 'ऑर्डर के लिए खाता ज़रूरी नहीं।') ?></span>
        <div><a href="/order.php"><?= t('Order goods →', 'सामान मँगाइए →') ?></a><a href="/sewa.php"><?= t('Make a booking →', 'बुकिंग कीजिए →') ?></a><a href="/track.php"><?= t('Track an order →', 'ऑर्डर देखिए →') ?></a></div>
      </div>
      <p class="lg-foot"><span class="mk-lang" role="group" aria-label="Language"><a href="<?= h(lang_url('en')) ?>"<?= is_hi() ? '' : ' class="on"' ?>>EN</a><a href="<?= h(lang_url('hi')) ?>"<?= is_hi() ? ' class="on"' : '' ?>>हिं</a></span>
        <a href="/"><?= t('Back to website', 'वेबसाइट पर वापस') ?></a></p>
    </div>
  </div>
</section>
<div>
<script src="/assets/geo-area.js?v=<?= (int)filemtime(__DIR__ . '/assets/geo-area.js') ?>"></script>
<script>
/* Ek hi flow: number likhte hi pata — purana khata (Sign in) ya naya. Tab badalne par number saath jata hai. */
(function () {
  var card = document.querySelector('[data-acc]'); if (!card) return;
  var AREAS = <?= json_encode($areas_js, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
  var L = {
    has: <?= json_encode(t('This number already has an account.', 'इस नंबर से खाता पहले से है।')) ?>,
    hasBtn: <?= json_encode(t('Sign in instead →', 'लॉगिन कीजिए →')) ?>,
    newNo: <?= json_encode(t('No account with this number yet.', 'इस नंबर से अभी कोई खाता नहीं।')) ?>,
    newBtn: <?= json_encode(t('Create one in 30 seconds →', '30 सेकंड में बनाइए →')) ?>,
    finding: <?= json_encode(t('Finding your area…', 'आपका इलाका ढूँढ रहे हैं…')) ?>,
    found: <?= json_encode(t('Area filled from your location ✓', 'लोकेशन से इलाका भर गया ✓')) ?>,
    near: <?= json_encode(t('You are near {p}. That area is not on Maakit yet — choose the closest one.', 'आप {p} के पास हैं। यह इलाका अभी Maakit पर नहीं है — सबसे पास वाला चुनिए।')) ?>,
    denied: <?= json_encode(t('Location blocked — please choose from the list.', 'लोकेशन की इजाज़त नहीं मिली — सूची से चुनिए।')) ?>,
    weak: <?= json_encode(t('Weak — add more letters', 'कमज़ोर — और अक्षर जोड़िए')) ?>, good: <?= json_encode(t('Good password', 'अच्छा पासवर्ड')) ?>,
    strong: <?= json_encode(t('Strong password ✓', 'मज़बूत पासवर्ड ✓')) ?>, short: <?= json_encode(t('At least 8 characters', 'कम से कम 8 अक्षर')) ?>
  };
  var hint = card.querySelector('[data-hint]'), KEY = 'mk_login_customer';
  var forms = { login: card.querySelector('[data-form=login]'), register: card.querySelector('[data-form=register]') };

  function show(mode, focus) {
    var from = card.dataset.mode; card.dataset.mode = mode;
    card.querySelectorAll('[data-tab]').forEach(function (t) { var on = t.dataset.tab === mode; t.classList.toggle('on', on); t.setAttribute('aria-selected', on ? 'true' : 'false'); });
    var m1 = forms[from] && forms[from].querySelector('[data-mobile]'), m2 = forms[mode].querySelector('[data-mobile]');
    if (m1 && m2 && m1.value && !m2.value) m2.value = m1.value;
    forms.login.hidden = mode !== 'login'; forms.register.hidden = mode !== 'register';
    try { history.replaceState(null, '', '/account.php?m=' + mode); } catch (e) {}
    var f = focus && forms[mode].querySelector(focus); if (f) f.focus();
  }
  card.querySelectorAll('[data-tab]').forEach(function (t) { t.addEventListener('click', function (e) { e.preventDefault(); hint.hidden = true; show(t.dataset.tab, '[data-mobile]'); }); });

  // number likhte hi jaanch
  var lastChecked = '';
  function check(inp) {
    var v = inp.value; if (v.length !== 10 || v === lastChecked) return; lastChecked = v;
    fetch('/account.php?check=' + v, { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) {
      if (j.exists === null) return;
      var mode = card.dataset.mode;
      inp.closest('.lg-in').classList.add('is-ok');
      if (j.exists && mode === 'register') {
        hint.innerHTML = L.has + ' <button type="button" data-go="login">' + L.hasBtn + '</button>'; hint.hidden = false;
      } else if (!j.exists && mode === 'login') {
        hint.innerHTML = L.newNo + ' <button type="button" data-go="register">' + L.newBtn + '</button>'; hint.hidden = false;
      } else { hint.hidden = true; var nx = forms[mode].querySelector(mode === 'login' ? '[data-pass]' : '#rname'); if (nx && !nx.value) nx.focus(); }
      var go = hint.querySelector('[data-go]');
      if (go) go.addEventListener('click', function () { hint.hidden = true; show(go.dataset.go, go.dataset.go === 'login' ? '[data-pass]' : '#rname'); });
    }).catch(function () {});
  }
  card.querySelectorAll('[data-mobile]').forEach(function (m) {
    m.addEventListener('input', function () {
      var d = m.value.replace(/\D/g, ''); if (d.length > 10 && d.indexOf('91') === 0) d = d.slice(2); m.value = d.slice(0, 10);
      m.closest('.lg-in').classList.remove('is-ok'); if (m.value.length < 10) { hint.hidden = true; lastChecked = ''; }
      check(m);
    });
  });
  // yaad rakha number
  try { var saved = localStorage.getItem(KEY); var lm = forms.login.querySelector('[data-mobile]');
        if (saved && !lm.value && card.dataset.mode === 'login') { lm.value = saved; lm.closest('.lg-in').classList.add('is-ok'); forms.login.querySelector('[data-pass]').focus(); } } catch (e) {}

  // password dikhaiye + taakat
  card.querySelectorAll('[data-eye]').forEach(function (b) { b.addEventListener('click', function () { var p = b.parentNode.querySelector('input'); p.type = p.type === 'password' ? 'text' : 'password'; b.classList.toggle('on'); p.focus(); }); });
  var np = card.querySelector('[data-new]'), meter = card.querySelector('.lg-meter'), pmsg = card.querySelector('[data-pass-msg]'), pmsg0 = pmsg ? pmsg.textContent : '';
  if (np) np.addEventListener('input', function () {
    var v = np.value, s = 0;
    if (v.length >= 8) s++; if (v.length >= 11) s++; if (/[A-Z]/.test(v) && /[a-z]/.test(v)) s++; if (/\d/.test(v) && /[^A-Za-z0-9]/.test(v)) s++;
    if (/^\d+$/.test(v)) s = Math.min(s, 1);
    meter.className = 'lg-meter s' + (v ? Math.max(1, s) : 0);
    pmsg.textContent = !v ? pmsg0 : v.length < 8 ? L.short : s <= 1 ? L.weak : s === 2 ? L.good : L.strong;
  });

  // ilaka: GPS se
  var gps = card.querySelector('[data-gps]'), sel = card.querySelector('[data-area]'), gmsg = card.querySelector('[data-gps-msg]');
  if (gps && window.MkGeo) gps.addEventListener('click', function () {
    gps.classList.add('busy'); gmsg.textContent = L.finding; gmsg.className = 'lg-small';
    MkGeo.detect(AREAS).then(function (r) {
      gps.classList.remove('busy');
      var pick = r.match || (r.near && r.near[0]);
      if (r.match) { sel.value = r.match.name; gmsg.textContent = L.found; gmsg.className = 'lg-small good'; sel.closest('.lg-in').classList.add('is-ok'); }
      else { if (pick) sel.value = pick.name; gmsg.textContent = L.near.replace('{p}', r.place || '—'); gmsg.className = 'lg-small'; sel.focus(); }
    }).catch(function () { gps.classList.remove('busy'); gmsg.textContent = L.denied; sel.focus(); });
  });
  // pehchaan ke chips
  var land = card.querySelector('[data-land]');
  card.querySelectorAll('[data-lm]').forEach(function (b) { b.addEventListener('click', function () { land.value = b.dataset.lm; land.focus(); }); });

  // bhejte waqt
  Object.keys(forms).forEach(function (k) {
    forms[k].addEventListener('submit', function () {
      if (k === 'login') { try { var keep = forms.login.querySelector('[data-keep]'), m = forms.login.querySelector('[data-mobile]').value;
        if (keep && keep.checked) localStorage.setItem(KEY, m); else localStorage.removeItem(KEY); } catch (e) {} }
      var b = forms[k].querySelector('.lg-btn'); b.classList.add('busy'); b.setAttribute('aria-busy', 'true');
    });
  });
})();
</script>
<?php endif; ?>

</div>
<?php include __DIR__ . '/inc/foot.php'; ?>

