<?php
/**
 * Created by PhpStorm.
 * User: fherrero
 * Date: 6/25/14
 * Time: 10:58 AM
 */

namespace FData\TransactionsBundle\Transaction;


use FData\SecurityBundle\User\User;
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
     * @var User
     */
    private $user;

    /**
     * @param ResponseInterface $response
     * @param \FData\SecurityBundle\User\User $user
     */
    public function __construct($response, User $user)
    {
        $this->response     = $response;
        $this->responseJSON = json_decode((string)$this->response->getBody(), true);
        $this->user         = $user;
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
        $format = function ($value) {
            return ucwords(strtolower(trim($value)));
        };

        $ctr        = $this->responseJSON['ctr'];
        $ctr_arr    = explode("\n", $ctr);
        $ctr_arr[1] = $format($this->user->getExtraData('dir_calle'));
        $ctr_arr[2] = $format($this->user->getExtraData('dir_ciudad')) . ', ' . $format($this->user->getExtraData('dir_provincia')) . ', ' . $format($this->user->getExtraData('dir_code'));
        $ctr_arr[3] = $format($this->user->getExtraData('dir_pais'));
        unset($ctr_arr[4]);
        $ctr = implode("\n", $ctr_arr);
        $ctr .= "\nPlease be advice, this transaction will\nappear as 911 Booking Corp in your\ncredit card monthly statement.";
        $ctr .= "\n\n\n\n----------------------------" . "\n         Signature";

        return $ctr;
    }

    /**
     * @return string
     */
    public function getBankMessage()
    {
        return $this->responseJSON['bank_message'];
    }
} 