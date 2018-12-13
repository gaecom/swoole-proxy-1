<?php

require_once __DIR__ . '/../vendor/autoload.php';


date_default_timezone_set('PRC');


$server = new \lea21st\proxy\HtppProxy('0.0.0.0', 9501, [
    'log_file' => __DIR__ . '/log/swoole.log',
]);
$server->set('log_dir', __DIR__ . '/log');
$server->start();