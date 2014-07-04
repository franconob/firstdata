<?php
/**
 * Created by PhpStorm.
 * User: fherrero
 * Date: 7/4/14
 * Time: 2:00 PM
 */

namespace FData\TransactionsBundle\HttpClient;


use GuzzleHttp\Client;

class HttpClientFactory
{
    /**
     * @param string $url
     * @return Client
     */
    public static function get($url)
    {
        return new Client([
            "base_url" => $url,
            "method"   => "POST",
            "defaults" => [
                "config" => [
                    "curl" => [
                        CURLOPT_SSLVERSION      => 3,
                        CURLOPT_SSL_CIPHER_LIST => 'SSLv3'
                    ]
                ]
            ]
        ]);
    }
} 