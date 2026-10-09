<?php
require_once __DIR__ . '/inc/fn.php';
require_once __DIR__ . '/inc/icons.php';
require_once __DIR__ . '/inc/items.php';
require_once __DIR__ . '/inc/services.php';
require_once __DIR__ . '/inc/customer.php';

$tab = 'mere';
$villages = village_list($pdo);
$me = cust();
$err = ''; $mode = get('m', 'login');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_ok()) {
    $do = post('do');

    if ($do === 'logout') { cust_logout(); flash(t('You are signed out.', 'आप लॉग आउट हो गए।')); redirect('/account.php'); }

    elseif ($do === 'register') {
        list($ok, $m) = cust_register($pdo, post('name'), post('mobile'), post('password'), post('village'), post('landmark'));
        if ($ok) { flash(t('Your account is ready. Everything in one place now.', 'आपका खाता बन गया। अब सब एक जगह मिलेगा।')); redirect('/account.php'); }
        $err = $m; $mode = 'register';
    }

    elseif ($do === 'login') {
        list($ok, $m) = cust_login($pdo, post('mobile'), post('password'));
        if ($ok) { redirect('/account.php'); }
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
        if (!$row || !password_verify($old, $row['password'])) { $err = t('Old password is wrong.', 'पुराना पासवर्ड ग़लत है।'); }
        elseif (!cust_password_ok($new)) { $err = cust_password_error(); }
        else {
            Maakit\Api\revoke_legacy_sessions($pdo,'customer');
            $pdo->prepare("UPDATE customers SET password=? WHERE id=?")
                ->execute([password_hash($new, PASSWORD_DEFAULT), $me['id']]);
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
      <i><?= h($me['mobile']) ?><?= $me['village'] ? ' · ' . h($me['village']) : '' ?></i>
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
      <div class="field"><label for="old"><?= t('Old password', 'पुराना पासवर्ड') ?></label><input type="password" id="old" name="old" required></div>
      <div class="field"><label for="new"><?= t('New password', 'नया पासवर्ड') ?></label><input type="password" id="new" name="new" autocomplete="new-password" minlength="8" required></div>
      <button type="submit" class="btn btn-brand" style="width:100%"><?= t('Change it', 'बदल दीजिए') ?></button>
    </form>
  </details>
  <div style="height:30px"></div>

<?php else: ?>
  <!-- ================= लॉगिन / नया खाता ================= -->
  <div class="pghead">
    <span class="bigic"><?= svc_icon('user', 36) ?></span>
    <h1><?= t('My Account', 'मेरा खाता') ?> <span><?= t('Optional — ordering works without it', 'ज़रूरी नहीं — बिना लॉगिन भी ऑर्डर होता है') ?></span></h1>
    <p><?= t('You can order without an account. Orders and bookings placed while signed in appear here. Track earlier guest orders using their order number.', 'बिना खाते के भी ऑर्डर होता है। लॉगिन करके किए गए ऑर्डर और बुकिंग यहाँ दिखेंगे। पहले के बिना लॉगिन वाले ऑर्डर उनके नंबर से देखें।') ?></p>
  </div>

  <div class="segs">
    <a class="<?= $mode !== 'register' ? 'on' : '' ?>" href="/account.php?m=login"><?= t('Sign in', 'लॉगिन') ?></a>
    <a class="<?= $mode === 'register' ? 'on' : '' ?>" href="/account.php?m=register"><?= t('New account', 'नया खाता') ?></a>
  </div>

  <?php if ($err): ?><div class="err"><?= h($err) ?></div><?php endif; ?>

  <?php if ($mode === 'register'): ?>
    <form method="post" class="box">
      <input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="do" value="register">
      <div class="field"><label for="rname"><?= t('Your name', 'आपका नाम') ?></label><input type="text" id="rname" name="name" value="<?= h(post('name')) ?>" autocomplete="name" required></div>
      <div class="field"><label for="rmob"><?= t('Mobile number (10 digits)', 'मोबाइल नंबर (10 अंक)') ?></label><input type="tel" id="rmob" name="mobile" value="<?= h(post('mobile')) ?>" inputmode="numeric" maxlength="10" autocomplete="tel-national" required></div>
      <div class="field"><label for="rpass"><?= t('Create a password', 'पासवर्ड बनाइए') ?></label>
        <input type="password" id="rpass" name="password" autocomplete="new-password" minlength="8" required>
        <p class="help"><?= cust_password_error() ?></p></div>
      <div class="field"><label for="rvil"><?= t('Your service area', 'आपका सेवा क्षेत्र') ?></label>
        <select id="rvil" name="village"><option value="">— <?= t('Choose', 'चुनिए') ?> —</option>
          <?php foreach ($villages as $v): ?><option value="<?= h($v['name']) ?>" <?= post('village') === $v['name'] ? 'selected' : '' ?>><?= h(coverage_label($v)) ?></option><?php endforeach; ?>
        </select></div>
      <div class="field"><label for="rland"><?= t('Landmark', 'घर की पहचान') ?></label><input type="text" id="rland" name="landmark" value="<?= h(post('landmark')) ?>" placeholder="<?= h(t('opposite the temple…', 'मंदिर के सामने…')) ?>"></div>
      <button type="submit" class="btn btn-brand" style="width:100%;font-size:17px"><?= t('Create account', 'खाता बनाइए') ?></button>
      <p class="help" style="text-align:center;margin-top:10px"><?= t('We never give your number to anyone else.', 'हम आपका नंबर किसी और को नहीं देते।') ?></p>
    </form>
  <?php else: ?>
    <form method="post" class="box">
      <input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="do" value="login">
      <div class="field"><label for="lmob"><?= t('Mobile number', 'मोबाइल नंबर') ?></label><input type="tel" id="lmob" name="mobile" value="<?= h(post('mobile')) ?>" inputmode="numeric" maxlength="10" autocomplete="tel-national" required></div>
      <div class="field"><label for="lpass"><?= t('Password', 'पासवर्ड') ?></label><input type="password" id="lpass" name="password" autocomplete="current-password" required></div>
      <button type="submit" class="btn btn-brand" style="width:100%;font-size:17px"><?= t('Sign in', 'लॉगिन कीजिए') ?></button>
      <p class="help" style="text-align:center;margin-top:10px"><?= t('Forgot your password?', 'पासवर्ड भूल गए?') ?> <a href="tel:<?= MAAKIT_PHONE ?>"><?= t('Call us', 'हमें कॉल कीजिए') ?></a> — <?= t('we’ll set a new one.', 'हम नया बना देंगे।') ?></p>
    </form>
  <?php endif; ?>

  <div class="box" style="margin-top:14px">
    <b><?= t('Everything works without an account too', 'बिना खाते के भी सब चलता है') ?></b>
    <p class="help" style="margin-top:4px"><?= t('Go straight to', 'सीधे') ?> <a href="/order.php"><?= t('ordering', 'सामान मँगाइए') ?></a> <?= t('or', 'या') ?> <a href="/sewa.php"><?= t('booking', 'बुकिंग कीजिए') ?></a> — <?= t('your order number always works.', 'ऑर्डर नंबर से हमेशा देख सकते हैं।') ?></p>
  </div>
  <div style="height:30px"></div>
<?php endif; ?>

</div>
<script>
var mb = document.getElementById('rmob') || document.getElementById('lmob');
if (mb) mb.addEventListener('input', function(){ this.value = this.value.replace(/\D/g,'').slice(0,10); });
</script>
<?php include __DIR__ . '/inc/foot.php'; ?>

