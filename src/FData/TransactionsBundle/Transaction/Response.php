<?php
/**
 * Created by PhpStorm.
 * User: fherrero
 * Date: 6/25/14
 * Time: 10:58 AM
 */

namespace FData\TransactionsBundle\Transaction;


use GuzzleHttp\Message\ResponseInterface;

class Response
{
    /**
     * @var \GuzzleHttp\Message\ResponseInterface
     */
    private $response;

    /**
     * @var array
     */
    private $responseJSON;

    /**
     * @param ResponseInterface $response
     */
    public function __construct($response)
    {
        $this->response = $response;
        $this->responseJSON = json_decode((string)$this->response->getBody(), true);
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return (string)$this->response;
    }

    /**
     * @return ResponseInterface
     */
    public function getClientResponse()
    {
        return $this->response;
    }

    /**
     * @return bool
     */
    public function hasFailed()
    {
        return $this->responseJSON['transaction_error'];
    }

    /**
     * @param string $field
     * @return null|string
     */
    public function get($field)
    {
        return isset($this->responseJSON[$field]) ? $this->responseJSON[$field] : null;
    }

    /**
     * @return array
     */
    public function getBody()
    {
        return $this->responseJSON;
    }

    /**
     * @return string
     */
    public function getCTR()
    {
        return $this->responseJSON['ctr'];
    }

    /**
     * @return string
     */
    public function getBankMessage()
    {
        return $this->responseJSON['bank_message'];
    }
} 