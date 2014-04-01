<?php

ini_set("memory_limit", "4g");

require_once dirname(__DIR__) . '/vendor/autoload.php';

$config = require dirname(__DIR__) . '/config.php';

$app = new Cilex\Application("PHPLOCParser");

$app['debug'] = $config['debug'];

$app->register(new Cilex\Provider\DoctrineServiceProvider(), [
    'db.options' => $config['database'],
]);

$app->command(new PHPLOCParser\Setup);
$app->command(new PHPLOCParser\Slave);
$app->command(new PHPLOCParser\Build);

return $app;
