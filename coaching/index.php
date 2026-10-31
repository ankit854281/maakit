<?php
// Coaching Saathi — ek hi login (mobile + password) owner, teacher aur parent sabke liye.
// Owner khud password rakh kar coaching banata hai. Teacher / parent ko pehla password
// coaching ka owner deta hai (app.php), wo pehli login par apna naya rakhte hain (pw.php).
require __DIR__ . '/inc.php';

$err = '';
$s = cs_session();

function cs_choices(string $mobile): array {
    $staff = cs_all("SELECT u.id, u.role, c.naam, c.city FROM cs_user u JOIN cs_coaching c ON c.id=u.coaching_id
                     WHERE u.mobile=? AND u.active=1 ORDER BY c.naam", [$mobile]);
    $kids = (int)cs_val("SELECT COUNT(*) FROM cs_student WHERE mobile=? AND status='active'", [$mobile]);
    return [$staff, $kids];
}
function cs_slug(string $naam, string $city): string {
    $b = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $naam . ' ' . $city), '-'));
    $b = rtrim(substr($b !== '' ? $b : 'coaching', 0, 30), '-') ?: 'coaching';
    $slug = $b;
    for ($i = 2; cs_val('SELECT 1 FROM cs_coaching WHERE slug=?', [$slug]); $i++) $slug = $b . '-' . $i;
    return $slug;
}
function cs_enter(string $mobile, string $pick): void {
    $acc = cs_account($mobile);
    if (!$acc || (int)$acc['temp'] === 1) cs_go('pw.php?first=1');   // pehla password badle bina andar nahi
    if ($pick === 'family') { cs_logout(); cs_session_start('family', $mobile); cs_go('me.php'); }
    $u = cs_row('SELECT * FROM cs_user WHERE id=? AND mobile=? AND active=1', [(int)$pick, $mobile]);
    if (!$u) cs_go('index.php?m=err');
    cs_logout(); cs_session_start('staff', $mobile, (int)$u['coaching_id'], (int)$u['id']); cs_go('app.php');
}
/** Password sahi hone ke baad: pehla password ho to badalwao, ek hi raasta ho to seedha andar */
function cs_after_login(string $mobile): void {
    $a = cs_account($mobile);
    cs_logout(); cs_session_start('verified', $mobile);
    if ($a && (int)$a['temp'] === 1) cs_go('pw.php?first=1');
    [$staff, $kids] = cs_choices($mobile);
    if (count($staff) + ($kids ? 1 : 0) === 1) cs_enter($mobile, $staff ? (string)$staff[0]['id'] : 'family');
    cs_go('index.php?choose=1');
}
function cs_new_coaching(string $mobile, array $f): void {
    cs_q('INSERT INTO cs_coaching (slug,naam,ctype,phone,city,reg_no) VALUES (?,?,?,?,?,?)',
        [cs_slug($f['naam'], $f['city']), $f['naam'], $f['type'], $mobile, $f['city'], $f['reg']]);
    $cid = (int)cs_db()->lastInsertId();
    cs_q("INSERT INTO cs_user (coaching_id,mobile,naam,role) VALUES (?,?,?,'owner')", [$cid, $mobile, $f['own']]);
    $uid = (int)cs_db()->lastInsertId();
    cs_logout(); cs_session_start('staff', $mobile, $cid, $uid);
    cs_go('app.php?p=courses&m=ok&new=1');
}
function cs_form_coaching(): ?array {
    $f = ['naam' => mb_substr(cs_post('naam'), 0, 120), 'city' => mb_substr(cs_post('city'), 0, 60), 'own' => mb_substr(cs_post('own'), 0, 80),
          'type' => array_key_exists(cs_post('ctype'), CS_TYPES) ? cs_post('ctype') : 'tuition', 'reg' => mb_substr(cs_post('reg'), 0, 60)];
    return mb_strlen($f['naam']) >= 3 && $f['city'] !== '' && $f['own'] !== '' ? $f : null;
}
$loginMsg = ['wrong' => 'Mobile ya password galat hai.', 'locked' => 'Bahut baar galat password. 15 minute baad koshish kariye.',
             'busy' => 'Bahut koshish ho gayi. 1 ghante baad kariye.', 'none' => 'Is number ka account nahi hai. Coaching wale se password maangiye, ya neeche nayi coaching banaiye.'];

if ($s && cs_get('choose') === '' && cs_get('after') === '' && cs_get('reset') === '' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    if ($s['kind'] === 'staff') cs_go('app.php');
    if ($s['kind'] === 'family') cs_go('me.php');
}

if ($s && cs_get('after') !== '' && $s['kind'] === 'verified') cs_after_login($s['mobile']);   // password badalne ke baad

$a = cs_post('a');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($a === 'login') {
        $mobile = cs_mobile(cs_post('mobile'));
        $r = $mobile ? cs_check_login($mobile, (string)($_POST['pass'] ?? '')) : 'wrong';
        if ($r === 'ok') cs_after_login($mobile);
        $err = $loginMsg[$r] ?? $loginMsg['wrong'];
    } elseif ($a === 'signup') {
        // Naya owner: number + password + coaching, ek hi form me
        $mobile = cs_mobile(cs_post('mobile')); $pass = (string)($_POST['pass'] ?? ''); $f = cs_form_coaching();
        if (!$mobile || !$f) $err = 'Coaching ka naam, aapka naam, mobile aur shehar bhariye.';
        elseif (!cs_take('signup-ip:' . cs_ip(), 10, 3600)) $err = $loginMsg['busy'];
        elseif ($acc = cs_account($mobile)) {
            // Number pehle se hai: usi ka password chahiye (warna koi bhi kisi ke number par coaching bana le)
            $r = cs_check_login($mobile, $pass);
            if ($r === 'ok' && (int)$acc['temp'] === 0) cs_new_coaching($mobile, $f);
            $err = $r === 'ok' ? 'Pehle login karke apna naya password rakhiye, phir coaching banaiye.' : 'Ye number pehle se juda hai — uska sahi password daaliye. ' . ($loginMsg[$r] ?? '');
        } elseif (!cs_pass_ok($pass)) $err = 'Password kam se kam ' . CS_PASS_MIN . ' akshar/ank ka rakhiye.';
        elseif ($pass !== (string)($_POST['pass2'] ?? '')) $err = 'Dono password ek jaise nahi hain.';
        else { cs_set_password($mobile, $pass, false); cs_new_coaching($mobile, $f); }
    } elseif ($a === 'create' && $s && in_array($s['kind'], ['verified', 'staff', 'family'], true) && cs_csrf_ok()) {
        // Login hai: isi number par ek aur coaching
        $acc = cs_account($s['mobile']);
        if (($f = cs_form_coaching()) && $acc && (int)$acc['temp'] === 0) cs_new_coaching($s['mobile'], $f);
        $err = 'Coaching ka naam, aapka naam aur shehar bhariye.';
    } elseif ($a === 'pick' && $s && cs_csrf_ok()) {
        cs_enter($s['mobile'], cs_post('pick'));
    } elseif ($a === 'reset') {
        // Owner password bhool gaya: sirf Maakit (website ki UPDATE_KEY) badal sakta hai
        $key = (string)cs_cfg('UPDATE_KEY'); $mobile = cs_mobile(cs_post('mobile')); $pass = (string)($_POST['pass'] ?? '');
        if (!cs_take('reset-ip:' . cs_ip(), 10, 3600)) $err = $loginMsg['busy'];
        elseif (strlen($key) < 12 || !hash_equals($key, (string)($_POST['key'] ?? ''))) $err = 'Chaabi galat hai.';
        elseif (!$mobile || !cs_account($mobile)) $err = 'Is number ka account nahi hai.';
        elseif (!cs_pass_ok($pass)) $err = 'Password kam se kam ' . CS_PASS_MIN . ' akshar/ank ka rakhiye.';
        else { cs_set_password($mobile, $pass, false); cs_go('index.php?m=ok'); }
    } elseif ($a === 'out') {
        cs_logout(); cs_go('index.php');
    } else {
        $err = 'Page purana tha — dobara bhariye.';
    }
}

function cs_coaching_fields(): void {
    echo '<label>Coaching ka naam</label><input name="naam" required maxlength="120" placeholder="jaise: Gupta Classes" value="', h(cs_post('naam')), '">',
         '<label>Aapka naam</label><input name="own" required maxlength="80" value="', h(cs_post('own')), '">',
         '<label>Coaching kis tarah ki hai</label><select name="ctype">';
    foreach (CS_TYPES as $k => $v) echo '<option value="', $k, '"', cs_post('ctype') === $k ? ' selected' : '', '>', h($v), '</option>';
    echo '</select><label>Shehar</label><input name="city" required maxlength="60" value="', h(cs_post('city', 'Lucknow')), '">',
         '<label>UP coaching registration no. (agar hai)</label><input name="reg" maxlength="60" value="', h(cs_post('reg')), '">';
}

cs_head('Coaching Saathi — Login');
echo '<div class="hero"><div class="logo" style="margin:auto">CS</div><h1>Coaching Saathi</h1>',
     '<p>Fees ka khata · Haaziri · Parent ko khabar · Apni website</p><p class="note" style="color:#fff">Coaching owner ke liye bilkul free</p></div><div class="wrap">';
echo cs_get('m') === 'ok' ? '<div class="flash">Password badal gaya. Ab login kariye.</div>' : cs_flash();
if ($err) echo '<div class="flash bad">', h($err), '</div>';

if ($s && (cs_get('choose') !== '' || $a === 'create')) {
    [$staff, $kids] = cs_choices($s['mobile']);
    if ($staff || $kids) {
        echo '<div class="card"><h1>Kahan jaana hai?</h1>';
        foreach ($staff as $u)
            echo '<form method="post">', cs_csrf_field(), '<input type="hidden" name="a" value="pick"><input type="hidden" name="pick" value="', (int)$u['id'], '">',
                 '<button class="plan"><strong>', h($u['naam']), '</strong><br><span class="note">', h($u['city']), ' · ', $u['role'] === 'owner' ? 'Owner' : 'Teacher', '</span></button></form>';
        if ($kids)
            echo '<form method="post">', cs_csrf_field(), '<input type="hidden" name="a" value="pick"><input type="hidden" name="pick" value="family">',
                 '<button class="plan"><strong>Student / Parent app</strong><br><span class="note">', $kids, ' bachche is number par</span></button></form>';
        echo '</div>';
    }
    echo '<details class="card"', $staff || $kids ? '' : ' open', '><summary><b>+ Nayi coaching jodiye</b></summary>',
         '<form method="post" action="?choose=1">', cs_csrf_field(), '<input type="hidden" name="a" value="create">';
    cs_coaching_fields();
    echo '<button class="btn full">Coaching banaiye</button></form></details>',
         '<div class="btns"><a class="btn ghost" href="pw.php">Password badlein</a></div>',
         '<form method="post" class="noprint"><input type="hidden" name="a" value="out"><button class="btn ghost full">Logout</button></form>';
} elseif (cs_get('reset') !== '') {
    echo '<div class="card"><h1>Password bhool gaye?</h1>',
         '<p><b>Teacher / parent:</b> apni coaching ke owner se kahiye — wo app me "Login bhejiye" dabakar naya password WhatsApp kar denge.</p>',
         '<p><b>Coaching owner:</b> Maakit ko call kariye: <a href="tel:+918429393903">84293 93903</a></p></div>',
         '<details class="card"><summary class="note">Sirf Maakit ke liye</summary><form method="post"><input type="hidden" name="a" value="reset">',
         '<label>Website ki chaabi (UPDATE_KEY)</label><input type="password" name="key" required autocomplete="off">',
         '<label>Kis number ka password</label><input name="mobile" inputmode="numeric" required maxlength="13">',
         '<label>Naya password</label><input name="pass" required minlength="', CS_PASS_MIN, '" autocomplete="new-password">',
         '<button class="btn full">Badliye</button></form></details><p><a href="index.php">‹ Login</a></p>';
} else {
    echo '<div class="card"><h1>Login</h1><p class="note">Owner, teacher aur parent — sab apne mobile number aur password se.</p>',
         '<form method="post"><input type="hidden" name="a" value="login"><label>Mobile number</label>',
         '<input name="mobile" inputmode="numeric" maxlength="13" required placeholder="10 ank" autocomplete="username" value="', h(cs_post('a') === 'login' ? cs_post('mobile') : ''), '">',
         '<label>Password</label><input type="password" name="pass" required autocomplete="current-password">',
         '<button class="btn full">Login</button></form><p><a href="?reset=1">Password bhool gaye?</a></p></div>';
    echo '<details class="card"', cs_post('a') === 'signup' ? ' open' : '', '><summary><b>Coaching owner? Apni coaching free me jodiye</b></summary>',
         '<form method="post"><input type="hidden" name="a" value="signup">';
    cs_coaching_fields();
    echo '<label>Aapka mobile (isi se login)</label><input name="mobile" inputmode="numeric" required maxlength="13" autocomplete="username" value="', h(cs_post('a') === 'signup' ? cs_post('mobile') : ''), '">',
         '<div class="two"><div><label>Password</label><input type="password" name="pass" required minlength="', CS_PASS_MIN, '" autocomplete="new-password"></div>',
         '<div><label>Dobara password</label><input type="password" name="pass2" autocomplete="new-password"></div></div>',
         '<p class="note">Kam se kam ', CS_PASS_MIN, ' akshar ya ank. Kisi ko mat bataiye.</p>',
         '<button class="btn full">Coaching banaiye</button></form></details>';
}
echo '</div>';
cs_foot();
