<?php

use Service\Providers\HttpClientServiceProvider;
use Service\Providers\TransactionsServiceProvider;
use Silex\Application;
use Silex\Provider\TwigServiceProvider;
use Silex\Provider\UrlGeneratorServiceProvider;
use Silex\Provider\ValidatorServiceProvider;
use Silex\Provider\ServiceControllerServiceProvider;

$app = new Application();
$app->register(new UrlGeneratorServiceProvider());
$app->register(new ValidatorServiceProvider());
$app->register(new ServiceControllerServiceProvider());
$app->register(new TwigServiceProvider());
$app->register(new Silex\Provider\SessionServiceProvider());
$app['twig'] = $app->share($app->extend('twig', function($twig, $app) {
    // add custom globals, filters, tags, ...

    return $twig;
}));

// Servicios de FirstData
$app->register(new HttpClientServiceProvider());
$app->register(new TransactionsServiceProvider(), array(
    'firstdata.transactions.key_id' => '131774',
    'firstdata.transactions.hmac_key' => 'RJ8O7H9wvYqaxPUcMqYDkMCwAq26rMHA',
    'firstdata.transactions.url' => 'https://api.demo.globalgatewaye4.firstdata.com',
    'firstdata.transactions.endpoint' => '/transaction/v13'
));

return $app;
