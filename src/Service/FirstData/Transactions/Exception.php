<?php
/**
 * Created by PhpStorm.
 * User: fherrero
 * Date: 5/26/14
 * Time: 4:54 PM
 */

namespace Service\FirstData\Transactions;


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
                "debug"   => (string)$this->clientException->getResponse()->getBody()
            ];
        } else {
            return [
                "success" => false,
                "reason"  => $this->response->get('exact_message'),
                "debug"   => ""
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