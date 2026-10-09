<?php
// Compatibility endpoint: same CSRF, rate limit and cookie policy as the canonical exchange.
if (!isset($_SERVER['HTTP_X_CSRF_TOKEN']) && is_string($_POST['csrf']??null)) $_SERVER['HTTP_X_CSRF_TOKEN']=$_POST['csrf'];
require __DIR__.'/../api/v1/session.php';
