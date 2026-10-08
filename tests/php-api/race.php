<?php
declare(strict_types=1);
require_once __DIR__.'/../../config/db.php';
require_once __DIR__.'/../../controllers/checkout.php';
if (PHP_SAPI!=='cli') { http_response_code(404);exit; }
[$auth,$body,$key]=json_decode(file_get_contents($argv[1]),true,32,JSON_THROW_ON_ERROR);
try { Maakit\Api\checkout(Maakit\Api\database(),$auth,$body,$key);echo 'CREATED'; }
catch (Maakit\Api\ApiError $e) { if ($e->apiCode!=='OUT_OF_STOCK') throw $e;echo $e->apiCode; }
