<?php
/**
 * Created by PhpStorm.
 * User: fherrero
 * Date: 7/4/14
 * Time: 2:00 PM
 */

namespace FData\TransactionsBundle\HttpClient;


use GuzzleHttp\Client;

abstract class HttpClient
{

    /**
     * @var string
     */
    protected $url;

    /**
     * @var string
     */
    protected $endpoint;

    /**
     * @var string
     */
    protected $method;

    /**
     * @var array
     */
    protected $headers;

    /**
     * @var \GuzzleHttp\Client
     */
    protected $client;

    /**
     * @var \GuzzleHttp\Message\RequestInterface
     */
    protected $request;

    /**
     * @param string $url
     * @param string $endpoint
     * @param string $method
     */
    public function __construct($url, $endpoint, $method)
    {
        $this->url      = $url;
        $this->endpoint = $endpoint;
        $this->method   = $method;
        $this->client   = new Client([
            "base_url" => $this->url,
            "method"   => $this->method,
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

    /**
     * @param string $body
     * @param array $options
     * @return $this
     */
    public function createRequest($body = "", $options = array())
    {
        $this->request = $this->client->createRequest($this->method, $this->endpoint, array_merge([
            "body" => $body,
        ], $options));

        return $this;
    }

    /**
     * @return string
     */
    public function getEndpoint()
    {
        return $this->endpoint;
    }

    /**
     * @return \GuzzleHttp\Message\RequestInterface
     */
    public function getRequest()
    {
        return $this->request;
    }

    /**
     * @return Client
     */
    public function getClient()
    {
        return $this->client;
    }

    /**
     * @return \GuzzleHttp\Message\ResponseInterface
     */
    public function send()
    {
        return $this->client->send($this->request);
    }
} 