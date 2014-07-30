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
     * @param $id
     * @return Transaction
     */
    public function getOrCreate($id)
    {
        if($transaction = $this->find($id)) {
            return $transaction;
        } else {
            return (new Transaction())->setId($id);
        }
    }
} 