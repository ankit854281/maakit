<?php
require_once __DIR__.'/inc/fn.php';
require_once __DIR__.'/inc/otp-accounts.php';
$otp_role = in_array(get('role'), ['shop','team'], true) ? get('role') : 'customer';
$otp_account = function($mobile) use ($pdo,$otp_role) { return mk_otp_account($pdo,$otp_role,$mobile); };
$otp_subject = 'mk_otp_subject';
$otp_finish = function($account,$name) use ($pdo) { mk_otp_finish($pdo,$account,$name); };
$otp_back = $otp_role === 'customer' ? '/account.php' : '/login.php';
require __DIR__.'/smartsarathi/inc/otp-page.php';
