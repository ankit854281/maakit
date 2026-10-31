<?php
// Coaching Saathi — apna password badlein. Pehli login (owner ka diya password) par yahi zaroori hai.
require __DIR__ . '/inc.php';

$s = cs_session();
if (!$s) cs_go('index.php');
$acc = cs_account($s['mobile']);
if (!$acc) { cs_logout(); cs_go('index.php'); }
$first = (int)$acc['temp'] === 1;
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new = (string)($_POST['pass'] ?? '');
    if (!cs_csrf_ok()) $err = 'Page purana tha — dobara bhariye.';
    elseif (!$first && cs_check_login($s['mobile'], (string)($_POST['old'] ?? '')) !== 'ok') $err = 'Purana password galat hai.';
    elseif (!cs_pass_ok($new)) $err = 'Password kam se kam ' . CS_PASS_MIN . ' akshar/ank ka rakhiye.';
    elseif ($new !== (string)($_POST['pass2'] ?? '')) $err = 'Dono password ek jaise nahi hain.';
    elseif (password_verify($new, $acc['pass_hash'])) $err = 'Naya password purane se alag rakhiye.';
    else {
        $mobile = $s['mobile'];
        cs_set_password($mobile, $new, false);            // baaki saare phone ka login band
        cs_session_start('verified', $mobile);
        cs_go('index.php?after=1');
    }
}

cs_head('Password — Coaching Saathi');
echo '<div class="top"><a href="index.php?choose=1">‹</a><b>', $first ? 'Apna password rakhiye' : 'Password badlein', '</b></div><div class="wrap">';
if ($err) echo '<div class="flash bad">', h($err), '</div>';
echo '<form method="post" class="card">', cs_csrf_field();
if ($first) echo '<p>Coaching ne aapko pehla password diya tha. Ab apna naya password rakhiye — sirf aapko pata hoga.</p>';
else echo '<label>Purana password</label><input type="password" name="old" required autocomplete="current-password">';
echo '<input type="hidden" name="username" value="', h($s['mobile']), '" autocomplete="username">',
     '<label>Naya password</label><input type="password" name="pass" required minlength="', CS_PASS_MIN, '" autocomplete="new-password">',
     '<label>Dobara naya password</label><input type="password" name="pass2" required autocomplete="new-password">',
     '<p class="note">Kam se kam ', CS_PASS_MIN, ' akshar ya ank. Badalte hi doosre phone se login band ho jayega.</p>',
     '<button class="btn full">Save kariye</button></form></div>';
cs_foot();
