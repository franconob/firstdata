<?php

use Silex\Provider\MonologServiceProvider;
use Silex\Provider\WebProfilerServiceProvider;

// include the prod configuration
require __DIR__.'/prod.php';

// enable the debug mode
$app['debug'] = true;

$app['db.options'] = array(
    'driver' => 'pdo_mysql',
    'dbname' => 'crm_winpax_finderit_local',
    'user'   => 'root',
    'password' => 'echesortufc',
    'charset' => 'utf8'
);

$app->register(new MonologServiceProvider(), array(
    'monolog.logfile' => __DIR__.'/../var/logs/silex_dev.log',
));

$app->register($p = new WebProfilerServiceProvider(), array(
    'profiler.cache_dir' => __DIR__.'/../var/cache/profiler',
));
$app->mount('/_profiler', $p);
