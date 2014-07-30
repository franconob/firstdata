<?php
/**
 * Created by PhpStorm.
 * User: fherrero
 * Date: 7/30/14
 * Time: 4:22 PM
 */

namespace FData\TransactionsBundle\Events;


final class TransactionEvents
{
    /**
     * Este evento se dispara cada vez que se ejecuta una transaccion
     * satisfactoriamente
     */
    const TRANSACTION_SUCCESS = 'transaction.completed';
} 