<?php
/**
 * Created by PhpStorm.
 * User: fherrero
 * Date: 5/27/14
 * Time: 12:01 PM
 */

namespace Service\FirstData;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Stream\Stream;

class Transactions
{
    /**
     * @var \GuzzleHttp\Client
     */
    private $httpClient;

    /**
     * @var string
     */
    private $key_id;

    /**
     * @var string
     */
    private $hmac_key;

    /**
     * @var string
     */
    private $url;

    /**
     * @var string
     */
    private $endpoint;

    /**
     * @var string
     */
    private $account;

    /**
     * @param string $key
     * @param string $hmac_key
     * @param string $url
     * @param string $endpoint
     * @param Client $httpClient
     * @param string $account
     */
    public function __construct($key, $hmac_key, $url, $endpoint, $httpClient, $account)
    {
        $this->key_id = $key;
        $this->hmac_key = $hmac_key;
        $this->url = $url;
        $this->endpoint = $endpoint;
        $this->httpClient = $httpClient;
        $this->account = $account;
    }

    /**
     * @param string $transaction_type
     * @param array $transaction_data
     * @return mixed
     * @throws Exception Cuando la accion no esta programada
     */
    public function execute($transaction_type, array $transaction_data)
    {
        if (!method_exists($this, $transaction_type)) {
            throw new Exception;
        }

        return call_user_func(array($this, $transaction_type), $transaction_data);
    }

    public function taggedVoid(array $transaction)
    {
        $transaction['transaction_type'] = '33';
        return $this->taggedRefund($transaction);
    }

    public function taggedPreAuthComp(array $transaction)
    {
        $transaction['transaction_type'] = '32';
        return $this->taggedRefund($transaction);
    }

    /**
     * @param array $transaction
     * @return \GuzzleHttp\Message\ResponseInterface
     * @throws ClientException
     */
    private function taggedRefund(array $transaction)
    {
        $requestBody = array_merge([
            'gateway_id' => 'AE8689-05',
            'password' => '8h5i7dud',
            'transaction_type' => '34',
            'reference_3' => $transaction['transaction_tag'],
            'customer_ref' => $this->account

        ], $transaction);

        $body = json_encode($requestBody);

        $headers = $this->calcHMAC($body);
        return $this->httpClient->post($this->endpoint, [
            "headers" => $headers,
            "body" => $body,
            "config" => [
                "curl" => [
                    CURLOPT_SSLVERSION => 3,
                    CURLOPT_SSL_CIPHER_LIST => 'SSLv3'
                ]
            ]
        ]);
    }

    public function newTransaction(array $transaction)
    {
        $transaction['cc_expiry'] = $this->formatExpiryDate($transaction['cc_expiry']);
        $requestBody = array_merge([
            'gateway_id' => 'AE8689-05',
            'password' => '8h5i7dud',
            'transaction_type' => '00',
            'customer_ref' => $this->account
        ], $transaction);

        $body = json_encode($requestBody);

        $headers = $this->calcHMAC($body);
        return $this->httpClient->post($this->endpoint, [
            "headers" => $headers,
            "body" => $body,
            "config" => [
                "curl" => [
                    CURLOPT_SSLVERSION => 3,
                    CURLOPT_SSL_CIPHER_LIST => 'SSLv3'
                ]
            ]
        ]);
    }

    public function preAuth(array $transaction)
    {
        $transaction['cc_expiry'] = $this->formatExpiryDate($transaction['cc_expiry']);
        $requestBody = array_merge([
            'gateway_id' => 'AE8689-05',
            'password' => '8h5i7dud',
            'transaction_type' => '01',
            'customer_ref' => $this->account
        ], $transaction);

        $body = json_encode($requestBody);

        $headers = $this->calcHMAC($body);
        return $this->httpClient->post($this->endpoint, [
            "headers" => $headers,
            "body" => $body,
            "config" => [
                "curl" => [
                    CURLOPT_SSLVERSION => 3,
                    CURLOPT_SSL_CIPHER_LIST => 'SSLv3'
                ]
            ]
        ]);
    }

    /**
     * @param array $transaction
     * @return \GuzzleHttp\Message\ResponseInterface
     * @throws ClientException
     */
    private function refund(array $transaction)
    {
        $transaction['cc_expiry'] = $this->formatExpiryDate($transaction['cc_expiry']);
        $requestBody = array_merge([
            'gateway_id' => 'AE8689-05',
            'password' => '8h5i7dud',
            'transaction_type' => '04',
            'customer_ref' => $this->account
        ], $transaction);

        $body = json_encode($requestBody);

        $headers = $this->calcHMAC($body);
        return $this->httpClient->post($this->endpoint, [
            "headers" => $headers,
            "body" => $body,
            "config" => [
                "curl" => [
                    CURLOPT_SSLVERSION => 3,
                    CURLOPT_SSL_CIPHER_LIST => 'SSLv3'
                ]
            ]
        ]);
    }

    private function formatExpiryDate($expiryDate)
    {
        return str_replace('/', '', $expiryDate);
    }

    private function calcHMAC($requestBody)
    {
        $gge4_date = gmdate('Y-m-d\TH:i:s') . 'Z';
        $method = 'POST';
        $content_digest = sha1($requestBody);
        $content_type = 'application/json';

        $hmac_data = $method . "\n" . $content_type . "\n" . $content_digest . "\n" . $gge4_date . "\n" . $this->endpoint;

        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => $content_type,
            'X-GGe4-Content-SHA1' => $content_digest,
            'X-GGe4-Date' => $gge4_date,
            'Authorization' => 'GGE4_API ' . $this->key_id . ':' . base64_encode(hash_hmac('sha1', $hmac_data, $this->hmac_key, true))
        ];

        return $headers;
    }
}