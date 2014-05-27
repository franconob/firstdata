<?php
/**
 * Created by PhpStorm.
 * User: fherrero
 * Date: 5/23/14
 * Time: 4:53 PM
 */

namespace Service\Providers;


use GuzzleHttp\Client;
use Silex\Application;
use Silex\ServiceProviderInterface;

class HttpClientServiceProvider implements ServiceProviderInterface
{

    private $url;

    /**
     * @inheritdoc
     */
    public function register(Application $app)
    {
        $app['firstdata.http'] = $app->share(function () use ($app) {
            return new Client([
                "base_url" => $app['firstdata.transactions.url'],
                "method" => "POST",
            ]);
        });
    }

    /**
     * @inheritdoc
     */
    public function boot(Application $app)
    {
        // TODO: Implement boot() method.
    }
}