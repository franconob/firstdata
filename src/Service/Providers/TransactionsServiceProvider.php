<?php
/**
 * Created by PhpStorm.
 * User: fherrero
 * Date: 5/22/14
 * Time: 12:39 PM
 */

namespace Service\Providers;


use Service\FirstData\Transactions;
use Silex\Application;
use Silex\ServiceProviderInterface;

class TransactionsServiceProvider implements ServiceProviderInterface
{
    /**
     * Registers services on the given app.
     *
     * This method should only be used to configure services and parameters.
     * It should not get services.
     *
     * @param Application $app An Application instance
     * @return \Service\FirstData\Transactions
     */
    public function register(Application $app)
    {
        $app['firstdata.transactions'] = $app->share(function () use ($app) {

            $hmac_key = $app['firstdata.transactions.hmac_key'];
            $key_id = $app['firstdata.transactions.key_id'];
            $url = $app['firstdata.transactions.url'];
            $endpoint = $app['firstdata.transactions.endpoint'];
            $httpClient = $app['firstdata.http'];

            return new Transactions($key_id, $hmac_key, $url, $endpoint, $httpClient);
        });

    }


    /**
     * Bootstraps the application.
     *
     * This method is called after all services are registered
     * and should be used for "dynamic" configuration (whenever
     * a service must be requested).
     */
    public function boot(Application $app)
    {
    }
}