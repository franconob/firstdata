<?php
/**
 * Created by PhpStorm.
 * User: fherrero
 * Date: 5/26/14
 * Time: 4:54 PM
 */

namespace FData\TransactionsBundle\Transaction;


use GuzzleHttp\Exception\ClientException;

class Exception extends \Exception
{
    /**
     * @var \GuzzleHttp\Exception\ClientException
     */
    public $clientException;

    /**
     * @var Response
     */
    private $response;

    /**
     * @param ClientException $exception
     */
    public function setHttpException(ClientException $exception)
    {
        $this->clientException = $exception;
    }

    public function setResponse(Response $response)
    {
        $this->response = $response;
    }

    /**
     * Esta funcion sirve cuando Firstdata tira error 500
     * @return array
     */
    public function getDebugVars()
    {
        if ("500" == $this->response->getClientResponse()->getStatusCode()) {
            return [
                "success" => false,
                "reason"  => $this->clientException->getResponse()->getReasonPhrase(),
                "debug"   => (string)$this->clientException->getResponse()->getBody(),
                "code"    => 500
            ];
        } else {
            return [
                "success" => false,
                "reason"  => $this->response->get('exact_message'),
                "debug"   => "",
                "code"    => $this->response->getClientResponse()->getStatusCode()
            ];
        }

    }

    public function getErrorReasons()
    {
        return [
            "success" => false,
            "reason"  => ""
        ];
    }
} 