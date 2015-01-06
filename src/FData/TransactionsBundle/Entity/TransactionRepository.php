<?php
/**
 * Created by PhpStorm.
 * User: fherrero
 * Date: 7/30/14
 * Time: 5:07 PM
 */

namespace FData\TransactionsBundle\Entity;


use Doctrine\ORM\EntityRepository;

class TransactionRepository extends EntityRepository
{
    /**
     * @param $transactionTag
     * @return Transaction
     */
    public function getOrCreate($transactionTag)
    {
        if($transaction = $this->findByTransactionTag($transactionTag)) {
            return $transaction;
        } else {
            return (new Transaction())->setTransactionTag($transactionTag);
        }
    }

    /**
     * @param $transactionTag
     * @return null|Transaction
     */
    public function findByTransactionTag($transactionTag)
    {
        return $this->findOneBy(array('transactionTag' => $transactionTag));
    }
} 