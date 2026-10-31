<?php
// Pure fixture driver: no database, credentials or live school data.
require __DIR__.'/../../school/academics_schedule.php';
$fixture=json_decode(file_get_contents('php://stdin'),true,512,JSON_THROW_ON_ERROR);
echo json_encode(ac_bells($fixture['date'],$fixture['data']),JSON_THROW_ON_ERROR);
