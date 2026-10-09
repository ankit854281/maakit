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
            $st=$pdo->prepare("SELECT * FROM users WHERE username=? AND role='vendor' AND active=1");
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
<section>
<div class="wrap" style="max-width:440px">

  <h2>लॉगिन</h2>

  <!-- कौन हैं आप — दो ही रास्ते -->
  <div style="display:flex;gap:8px;margin:12px 0 16px">
    <a class="btn btn-sm <?= $as==='dukan' ? 'btn-brand' : '' ?>"
       style="flex:1;text-align:center<?= $as==='dukan' ? '' : ';background:#EFEAE0' ?>"
       href="/login.php">मैं दुकानदार हूँ</a>
    <a class="btn btn-sm <?= $as==='team' ? 'btn-brand' : '' ?>"
       style="flex:1;text-align:center<?= $as==='team' ? '' : ';background:#EFEAE0' ?>"
       href="/login.php?as=team">मैं Maakit टीम से हूँ</a>
  </div>

  <?php if ($err): ?><div class="err"><?= h($err) ?></div><?php endif; ?>

  <?php if ($as === 'dukan'): ?>

    <form method="post" class="box">
      <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
      <input type="hidden" name="do" value="dukan">
      <p class="lead" style="margin:0 0 14px">अपना सामान, अपना दाम, अपने ऑर्डर और अपना हिसाब —
        सब यहीं से चलेगा।</p>
      <div class="field"><label>अपना मोबाइल नंबर</label>
        <input type="tel" name="mobile" inputmode="numeric" value="<?= h(post('mobile')) ?>" required autofocus></div>
      <div class="field"><label>Maakit से मिला कोड</label>
        <input type="text" name="code" placeholder="जैसे ABC-1234" autocapitalize="characters"
               autocomplete="off" spellcheck="false" required></div>
      <button class="btn btn-brand" type="submit" style="width:100%;font-size:17px">खोलिए</button>
      <p class="help" style="margin-top:12px">एक बार खोलने के बाद <b>90 दिन</b> तक इसी फ़ोन पर
        सीधा खुलेगा — बार-बार कुछ नहीं डालना पड़ेगा।</p>
    </form>

    <div class="box" style="margin-top:14px">
      <b>कोड नहीं मिला?</b>
      <p class="help" style="margin-top:6px">दुकान पहले Maakit पर दर्ज होनी चाहिए। उसके बाद
        हम आपको WhatsApp पर कोड भेज देते हैं।</p>
      <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:10px">
        <a class="btn btn-gold btn-sm" href="/register-business.php">अपनी दुकान दर्ज कीजिए</a>
        <a class="btn btn-sm" style="background:#EFEAE0" href="tel:<?= h(MAAKIT_PHONE) ?>">फ़ोन — <?= h(MAAKIT_NUMBER_SHOW) ?></a>
      </div>
    </div>

  <?php else: ?>

    <form method="post" class="box">
      <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
      <input type="hidden" name="do" value="team">
      <p class="lead" style="margin:0 0 14px">यह हिस्सा सिर्फ़ Maakit टीम के लिए है।</p>
      <div class="field"><label>यूज़रनेम</label>
        <input type="text" name="username" autocapitalize="off" spellcheck="false" required autofocus></div>
      <div class="field"><label>पासवर्ड</label><input type="password" name="password" required></div>
      <button class="btn btn-brand" type="submit" style="width:100%;font-size:17px">लॉगिन</button>
    </form>

  <?php endif; ?>

</div>
</section>
<?php include __DIR__ . '/inc/foot.php'; ?>

