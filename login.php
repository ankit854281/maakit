<?php
// ============================================================
//  एक ही लॉगिन — दुकानदार और Maakit टीम, दोनों यहीं से
//
//  Do tarah ke log andar aate hain:
//    - Dukandar : mobile + code (password nahi — gaon me yaad
//                 nahi rehta). Andar jaake /shop.php khulta hai.
//    - Team     : username + password (admin, BPO, delivery,
//                 designer). Apne-apne panel me jaate hain.
//
//  Dukandar pehle rakhe gaye hain kyunki unki ginti team se
//  bahut jyada hogi.
// ============================================================
require_once __DIR__ . '/inc/fn.php';
require_once __DIR__ . '/inc/salon.php';
$no_tabbar = true;
$page_title = 'लॉगिन — Maakit';
$err = '';

// pehle se andar hain to seedha apne panel me
if (user()) { redirect(panel_home(user()['role'])); }
if (shop_login_business($pdo)) { redirect('/shop.php'); }

// kaun sa form dikhe
$as = get('as') === 'team' ? 'team' : 'dukan';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_ok()) {

    // ---------- दुकानदार ----------
    if (post('do') === 'dukan') {
        $as   = 'dukan';
        $mob  = preg_replace('/\D/', '', post('mobile'));
        $code = strtoupper(trim(post('code')));
        $ip   = $_SERVER['REMOTE_ADDR'] ?? '';

        // Wahi hadd jo team ke login par hai — ek number par 6, aur ek
        // IP par 30. Pehle yahan sirf number ki ginti thi, IP ki nahi,
        // isliye ek hi jagah se sau number par koshish ho sakti thi.
        if (auth_attempt_try($pdo, 'dukan', $mob)) {
            $err = auth_attempt_error();
        } else {
            $st = $pdo->prepare("SELECT * FROM businesses WHERE mobile=? AND status='approved'");
            $st->execute([$mob]);
            $row = $st->fetch();
            $okc = $row && $row['access_code'] && hash_equals(strtoupper($row['access_code']), $code);
            $pdo->prepare("INSERT INTO shop_login_log (business_id, mobile, ok, ip) VALUES (?,?,?,?)")
                ->execute([$row['id'] ?? null, $mob, $okc ? 1 : 0, $ip]);
            if ($okc) {
                auth_attempt_ok($pdo, 'dukan', $mob);
                shop_start_session($pdo, $row['id']);
                redirect('/shop.php');
            }
            // New approved partner accounts keep the existing mobile + code door.
            //
            // Pehle yahan sirf role='vendor' tha. Rider ka khata
            // role='rider' banta hai (partner-apply.php), isliye manzoori
            // ke baad bhi rider andar nahi aa pata tha — "number ya code
            // sahi nahi hai" aata tha, jabki dono sahi hote the.
            // Rider ke liye apna darwaza /sarathi/ hai, par ye bhi chale.
            $st=$pdo->prepare("SELECT * FROM users WHERE username=? AND role IN ('vendor','rider') AND active=1");
            $st->execute([$mob]);$partner=$st->fetch();
            if ($partner && password_verify($code,$partner['password'])) {
                auth_attempt_ok($pdo,'dukan',$mob);
                Maakit\Api\revoke_legacy_sessions($pdo);session_regenerate_id(true);
                $_SESSION['user']=['id'=>$partner['id'],'name'=>$partner['name'],'role'=>$partner['role']];
                redirect(panel_home($partner['role']));
            }
            $err = 'नंबर या कोड सही नहीं है। Maakit से अपना कोड पूछ लीजिए।';
        }
    }

    // ---------- टीम ----------
    elseif (post('do') === 'team') {
        $as = 'team';
        $username = post('username');
        $st = $pdo->prepare("SELECT * FROM users WHERE username=? AND active=1");
        $st->execute([$username]);
        $u = $st->fetch();
        // Ginti ki chaabi khate ka ASLI naam ho — warna "ädmin" jaisi
        // likhawat ek hi khata kholkar nayi ginti shuru kar deti hai.
        $kunji = $u ? $u['username'] : $username;
        if (auth_attempt_try($pdo, 'team', $kunji)) {
            $err = auth_attempt_error();
        } elseif ($u && password_verify(post('password'), $u['password'])) {
            auth_attempt_ok($pdo, 'team', $kunji);
            Maakit\Api\revoke_legacy_sessions($pdo);
            session_regenerate_id(true);
            $_SESSION['user'] = ['id'=>$u['id'], 'name'=>$u['name'], 'role'=>$u['role']];
            redirect(panel_home($u['role']));
        } else {
            $err = t('Username or password is wrong.','यूज़रनेम या पासवर्ड ग़लत है।');
        }

    }
}

include __DIR__ . '/inc/head.php';
?>
<section class="lg">
  <div class="lg-wrap">
    <aside class="lg-side" aria-hidden="true">
      <div class="lg-brand"><?= maakit_logo_svg(44) ?><span>Maakit<small><?= t('Partner & team login', 'साथी और टीम लॉगिन') ?></small></span></div>
      <h1><?= t('Run your shop from your phone.', 'अपनी दुकान, अपने फ़ोन से।') ?></h1>
      <ul>
        <li><?= t('Add products and your own prices', 'अपना सामान और अपना दाम डालिए') ?></li>
        <li><?= t('See new orders the moment they arrive', 'नया ऑर्डर आते ही देखिए') ?></li>
        <li><?= t('Daily accounts and your ledger', 'रोज़ का हिसाब और अपना खाता') ?></li>
        <li><?= t('Payments straight to your UPI', 'पैसा सीधा आपके UPI में') ?></li>
      </ul>
    </aside>

    <div class="lg-card">
      <div class="lg-tabs" role="tablist">
        <a role="tab" aria-selected="<?= $as === 'dukan' ? 'true' : 'false' ?>" class="<?= $as === 'dukan' ? 'on' : '' ?>" href="/login.php"><?= t('Shopkeeper', 'दुकानदार') ?></a>
        <a role="tab" aria-selected="<?= $as === 'team' ? 'true' : 'false' ?>" class="<?= $as === 'team' ? 'on' : '' ?>" href="/login.php?as=team"><?= t('Maakit team', 'Maakit टीम') ?></a>
      </div>

      <?php if ($err): ?><div class="lg-err" role="alert"><?= h($err) ?></div><?php endif; ?>

      <?php if ($as === 'dukan'): ?>
        <h2><?= t('Welcome back', 'नमस्ते') ?></h2>
        <p class="lg-sub"><?= t('Sign in with your mobile number and the code Maakit sent you.', 'अपना मोबाइल नंबर और Maakit से मिला कोड डालिए।') ?></p>
        <form method="post" class="lg-form" data-remember="dukan">
          <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
          <input type="hidden" name="do" value="dukan">
          <label class="lg-field"><span><?= t('Mobile number', 'मोबाइल नंबर') ?></span>
            <span class="lg-in"><em>+91</em><input type="tel" name="mobile" inputmode="numeric" autocomplete="tel-national" maxlength="10" value="<?= h(post('mobile')) ?>" required autofocus data-mobile></span></label>
          <label class="lg-field"><span><?= t('Login code', 'लॉगिन कोड') ?></span>
            <span class="lg-in"><input type="text" name="code" placeholder="ABC-1234" autocapitalize="characters" autocomplete="one-time-code" spellcheck="false" required data-code></span></label>
          <label class="lg-check"><input type="checkbox" data-keep checked> <?= t('Remember my number on this phone', 'इस फ़ोन पर मेरा नंबर याद रखिए') ?></label>
          <button class="lg-btn" type="submit"><span><?= t('Sign in', 'खोलिए') ?></span></button>
          <p class="lg-note"><?= t('Stays signed in on this phone for 90 days.', 'इस फ़ोन पर 90 दिन तक खुला रहेगा।') ?></p>
        </form>
        <div class="lg-help">
          <b><?= t('No code yet?', 'कोड नहीं मिला?') ?></b>
          <span><?= t('Register your shop first — we send the code on WhatsApp.', 'पहले दुकान दर्ज कीजिए — कोड WhatsApp पर भेज देंगे।') ?></span>
          <div><a href="/register-business.php"><?= t('Register your shop', 'दुकान दर्ज कीजिए') ?> →</a><a href="tel:<?= h(MAAKIT_PHONE) ?>"><?= t('Call', 'फ़ोन') ?> <?= h(MAAKIT_NUMBER_SHOW) ?></a></div>
        </div>
      <?php else: ?>
        <h2><?= t('Team sign in', 'टीम लॉगिन') ?></h2>
        <p class="lg-sub"><?= t('For Maakit staff only.', 'सिर्फ़ Maakit टीम के लिए।') ?></p>
        <form method="post" class="lg-form" data-remember="team">
          <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
          <input type="hidden" name="do" value="team">
          <label class="lg-field"><span><?= t('Username', 'यूज़रनेम') ?></span>
            <span class="lg-in"><input type="text" name="username" autocomplete="username" autocapitalize="off" spellcheck="false" required autofocus data-user></span></label>
          <label class="lg-field"><span><?= t('Password', 'पासवर्ड') ?></span>
            <span class="lg-in"><input type="password" name="password" autocomplete="current-password" required data-pass>
              <button type="button" class="lg-eye" data-eye aria-label="<?= h(t('Show password', 'पासवर्ड दिखाइए')) ?>"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg></button></span></label>
          <label class="lg-check"><input type="checkbox" data-keep checked> <?= t('Remember my username', 'मेरा यूज़रनेम याद रखिए') ?></label>
          <button class="lg-btn" type="submit"><span><?= t('Sign in', 'लॉगिन') ?></span></button>
        </form>
      <?php endif; ?>
      <p class="lg-foot"><span class="mk-lang" role="group" aria-label="Language"><a href="<?= h(lang_url('en')) ?>"<?= is_hi() ? '' : ' class="on"' ?>>EN</a><a href="<?= h(lang_url('hi')) ?>"<?= is_hi() ? ' class="on"' : '' ?>>हिं</a></span>
        <a href="/"><?= t('Back to website', 'वेबसाइट पर वापस') ?></a></p>
    </div>
  </div>
</section>
<script>
/* login: number/username yaad, code apne aap bade akshar, password dikhaiye, bhejte waqt ruko */
(function(){
  var f = document.querySelector('.lg-form'); if (!f) return;
  var key = 'mk_login_' + f.getAttribute('data-remember'), keep = f.querySelector('[data-keep]');
  var field = f.querySelector('[data-mobile]') || f.querySelector('[data-user]');
  try { var v = localStorage.getItem(key); if (v && field && !field.value) { field.value = v; var nx = f.querySelector('[data-code],[data-pass]'); if (nx) nx.focus(); } } catch (_) {}
  var m = f.querySelector('[data-mobile]');
  if (m) m.addEventListener('input', function(){ var d = m.value.replace(/\D/g, ''); if (d.length > 10 && d.indexOf('91') === 0) d = d.slice(2); m.value = d.slice(0, 10); if (m.value.length === 10) { var c = f.querySelector('[data-code]'); if (c && !c.value) c.focus(); } });
  var c = f.querySelector('[data-code]');
  if (c) c.addEventListener('input', function(){ var p = c.selectionStart; c.value = c.value.toUpperCase().replace(/\s+/g, ''); try { c.setSelectionRange(p, p); } catch (_) {} });
  var eye = f.querySelector('[data-eye]'), pw = f.querySelector('[data-pass]');
  if (eye && pw) eye.addEventListener('click', function(){ pw.type = pw.type === 'password' ? 'text' : 'password'; eye.classList.toggle('on'); pw.focus(); });
  f.addEventListener('submit', function(){
    try { if (keep && keep.checked && field) localStorage.setItem(key, field.value.trim()); else localStorage.removeItem(key); } catch (_) {}
    var b = f.querySelector('.lg-btn'); b.classList.add('busy'); b.setAttribute('aria-busy', 'true');
  });
})();
</script>
<?php include __DIR__ . '/inc/foot.php'; ?>
