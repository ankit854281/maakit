<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../../config/db.php';
require_once __DIR__.'/../../controllers/riderController.php';
[$auth,$body]=json_decode(file_get_contents($argv[1]),true,32,JSON_THROW_ON_ERROR);
echo json_encode(Maakit\Api\rider_progress(Maakit\Api\database(),$auth,$body),JSON_THROW_ON_ERROR);
