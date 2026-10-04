<?php
require_once __DIR__ . '/inc/fn.php';
session_destroy();
redirect('/login.php');
