<?php
/**
 * Created by PhpStorm.
 * User: fherrero
 * Date: 7/30/14
 * Time: 4:24 PM
 */

namespace FData\TransactionsBundle\Events;


use FData\SecurityBundle\User\User;
use FData\TransactionsBundle\Transaction\Response;
use Symfony\Component\EventDispatcher\Event;

class TransactionCompleteEvent extends Event
{
    /**
     * @var array
     */
    private $transaction;

    /**
     * @var Response
     */
    private $response;

    /**
     * @var User
     */
    private $user;

    public function __construct(array $transaction, Response $response, User $user)
    {
        $this->transaction = $transaction;
        $this->response    = $response;
        $this->user        = $user;
    }

    /**
     * @return array
     */
    public function getTransaction()
    {
        return $this->transaction;
    }

    /**
     * @return \FData\TransactionsBundle\Transaction\Response
     */
    public function getResponse()
    {
        return $this->response;
    }

    /**
     * @return \FData\SecurityBundle\User\User
     */
    public function getUser()
    {
        return $this->user;
    }

}