<?php
// Coaching Saathi — ek hi login (mobile + OTP) owner, teacher aur parent/student sabke liye.
require __DIR__ . '/inc.php';

$err = ''; $demo = null; $step = 'mobile';
$s = cs_session();

function cs_choices(string $mobile): array {
    $staff = cs_all("SELECT u.id, u.role, c.naam, c.city FROM cs_user u JOIN cs_coaching c ON c.id=u.coaching_id
                     WHERE u.mobile=? AND u.active=1 ORDER BY c.naam", [$mobile]);
    $kids = (int)cs_val("SELECT COUNT(*) FROM cs_student WHERE mobile=? AND status='active'", [$mobile]);
    return [$staff, $kids];
}
function cs_slug(string $naam, string $city): string {
    $b = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $naam . ' ' . $city), '-'));
    $b = substr($b !== '' ? $b : 'coaching', 0, 30);
    $b = rtrim($b, '-') ?: 'coaching';
    $slug = $b;
    for ($i = 2; cs_val('SELECT 1 FROM cs_coaching WHERE slug=?', [$slug]); $i++) $slug = $b . '-' . $i;
    return $slug;
}
function cs_enter(string $mobile, string $pick): void {
    if ($pick === 'family') { cs_logout(); cs_session_start('family', $mobile); cs_go('me.php'); }
    $u = cs_row('SELECT * FROM cs_user WHERE id=? AND mobile=? AND active=1', [(int)$pick, $mobile]);
    if (!$u) cs_go('index.php?m=err');
    cs_logout(); cs_session_start('staff', $mobile, (int)$u['coaching_id'], (int)$u['id']); cs_go('app.php');
}

if ($s && cs_get('choose') === '' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    if ($s['kind'] === 'staff') cs_go('app.php');
    if ($s['kind'] === 'family') cs_go('me.php');
}

$a = cs_post('a');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($a === 'send') {
        $mobile = cs_mobile(cs_post('mobile'));
        if (!$mobile) { $err = 'Sahi 10 ankon ka mobile number likhiye.'; }
        else {
            [$id, $demo, $e] = cs_otp_send($mobile);
            if ($e) $err = $e;
            else { cs_cookie('cs_o', $id . '|' . $mobile, 1); $step = 'code'; }
        }
    } elseif ($a === 'verify') {
        [$id, $mobile] = array_pad(explode('|', (string)($_COOKIE['cs_o'] ?? ''), 2), 2, '');
        $mobile = cs_mobile($mobile);
        if ($mobile && cs_otp_check($id, $mobile, preg_replace('/\D/', '', cs_post('code')))) {
            cs_cookie('cs_o', '', 0);
            [$staff, $kids] = cs_choices($mobile);
            if (count($staff) + ($kids ? 1 : 0) === 1) cs_enter($mobile, $staff ? (string)$staff[0]['id'] : 'family');
            cs_logout(); cs_session_start('verified', $mobile);
            cs_go('index.php?choose=1');
        }
        $err = 'OTP galat hai ya purana ho gaya. Dobara bhejiye.'; $step = $mobile ? 'code' : 'mobile';
    } elseif ($a === 'pick' && $s && cs_csrf_ok()) {
        cs_enter($s['mobile'], cs_post('pick'));
    } elseif ($a === 'create' && $s && cs_csrf_ok()) {
        $naam = mb_substr(cs_post('naam'), 0, 120); $city = mb_substr(cs_post('city'), 0, 60);
        $type = array_key_exists(cs_post('ctype'), CS_TYPES) ? cs_post('ctype') : 'tuition';
        $own = mb_substr(cs_post('own'), 0, 80);
        if (mb_strlen($naam) < 3 || $city === '' || $own === '') { $err = 'Coaching ka naam, aapka naam aur shehar bhariye.'; }
        else {
            cs_q('INSERT INTO cs_coaching (slug,naam,ctype,phone,city,reg_no) VALUES (?,?,?,?,?,?)',
                [cs_slug($naam, $city), $naam, $type, $s['mobile'], $city, mb_substr(cs_post('reg'), 0, 60)]);
            $cid = (int)cs_db()->lastInsertId();
            cs_q("INSERT INTO cs_user (coaching_id,mobile,naam,role) VALUES (?,?,?,'owner')", [$cid, $s['mobile'], $own]);
            $uid = (int)cs_db()->lastInsertId();
            cs_logout(); cs_session_start('staff', $s['mobile'], $cid, $uid);
            cs_go('app.php?p=courses&m=ok&new=1');
        }
    } elseif ($a === 'out') {
        cs_logout(); cs_go('index.php');
    }
}

cs_head('Coaching Saathi — Login');
echo '<div class="hero"><div class="logo" style="margin:auto">CS</div><h1>Coaching Saathi</h1>',
     '<p>Fees ka khata · Haaziri · Parent ko khabar · Apni website</p><p class="note" style="color:#fff">Coaching owner ke liye bilkul free</p></div><div class="wrap">';
echo cs_flash();
if ($err) echo '<div class="flash bad">', h($err), '</div>';

if ($s && cs_get('choose') !== '' && $step === 'mobile') {
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
    echo '<div class="card"><h2 style="margin-top:0">', $staff || $kids ? 'Ya nayi coaching jodiye' : 'Apni coaching jodiye', '</h2>',
         '<p class="note">Is number par abhi koi coaching nahi hai. 1 minute me shuru kariye.</p>',
         '<form method="post">', cs_csrf_field(), '<input type="hidden" name="a" value="create">',
         '<label>Coaching ka naam</label><input name="naam" required maxlength="120" placeholder="jaise: Gupta Classes">',
         '<label>Aapka naam</label><input name="own" required maxlength="80">',
         '<label>Coaching kis tarah ki hai</label><select name="ctype">';
    foreach (CS_TYPES as $k => $v) echo '<option value="', $k, '">', h($v), '</option>';
    echo '</select><label>Shehar</label><input name="city" required maxlength="60" value="Lucknow">',
         '<label>UP coaching registration no. (agar hai)</label><input name="reg" maxlength="60">',
         '<button class="btn full">Coaching banaiye</button></form></div>';
    echo '<form method="post" class="noprint"><input type="hidden" name="a" value="out"><button class="btn ghost full">Doosre number se login</button></form>';
} elseif ($step === 'code') {
    echo '<div class="card"><h1>OTP daaliye</h1><p class="note">6 ankon ka code aapke number par bheja gaya hai.</p>';
    if ($demo) echo '<div class="flash">TEST MODE — aapka OTP: <b>', h($demo), '</b></div>';
    echo '<form method="post"><input type="hidden" name="a" value="verify"><input name="code" inputmode="numeric" maxlength="6" autocomplete="one-time-code" required autofocus>',
         '<button class="btn full">Login</button></form><p><a href="index.php">Number badlein</a></p></div>';
} else {
    echo '<div class="card"><h1>Login</h1><p class="note">Owner, teacher aur parent — sab apne mobile number se.</p>',
         '<form method="post"><input type="hidden" name="a" value="send"><label>Mobile number</label>',
         '<input name="mobile" inputmode="numeric" maxlength="13" required placeholder="10 ank" autofocus>',
         '<button class="btn full">OTP bhejiye</button></form></div>';
}
echo '</div>';
cs_foot();
