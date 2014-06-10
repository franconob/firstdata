<?php

use Service\Providers\HttpClientServiceProvider;
use Service\Providers\TransactionsServiceProvider;
use Service\Providers\UserProvider;
use Silex\Application;
use Silex\Provider\TwigServiceProvider;
use Silex\Provider\UrlGeneratorServiceProvider;
use Silex\Provider\ValidatorServiceProvider;
use Silex\Provider\ServiceControllerServiceProvider;
use Symfony\Component\Security\Core\Encoder\PlaintextPasswordEncoder;

$app = new Application();
$app->register(new UrlGeneratorServiceProvider());
$app->register(new ValidatorServiceProvider());
$app->register(new ServiceControllerServiceProvider());
$app->register(new TwigServiceProvider());
$app->register(new Silex\Provider\DoctrineServiceProvider(), array( // DB options in prod.php and dev.php
));
$app->register(new Silex\Provider\SessionServiceProvider());
$app->register(new Silex\Provider\SecurityServiceProvider(), array(
    'security.firewalls'      => array(
        'login' => array(
            'pattern' => '^/login$'
        ),
        'main'  => [
            'pattern' => '^.*$',
            'form'    => array('login_path' => '/login', 'check_path' => '/login_check', 'default_target_path' => '/'),
            'logout' => array('logout_path' => '/logout'),
            'users'   => $app->share(function () use ($app) {
                    return new UserProvider($app['db']);
                })
        ]
    ),
    'security.encoder.digest' => $app->share(function ($app) {
            return new PlaintextPasswordEncoder();
        })
));
$app['twig'] = $app->share($app->extend('twig', function ($twig, $app) {
    // add custom globals, filters, tags, ...

    return $twig;
}));

// Servicios de FirstData
$app->register(new HttpClientServiceProvider());
$app->register(new TransactionsServiceProvider(), array(
    'firstdata.transactions.key_id'   => '131774',
    'firstdata.transactions.hmac_key' => 'FSwc7KeT6JFadZKRE1s6Pi6xAYQLVn84',
    'firstdata.transactions.url'      => 'https://api.demo.globalgatewaye4.firstdata.com',
    'firstdata.transactions.endpoint' => '/transaction/v13'
));

return $app;
