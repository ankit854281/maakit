<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') { http_response_code(404);exit; }
require_once __DIR__.'/../config/db.php';
require_once __DIR__.'/../controllers/orderLifecycle.php';
try { $db=Maakit\Api\database();$expired=Maakit\Api\expire_reservations($db);echo Maakit\Api\json_data(['expired_reservations'=>$expired]+Maakit\Api\dispatch_tick($db)).PHP_EOL; }
catch (Throwable $e) { fwrite(STDERR,'Dispatch unavailable; check private configuration.'.PHP_EOL);exit(1); }
