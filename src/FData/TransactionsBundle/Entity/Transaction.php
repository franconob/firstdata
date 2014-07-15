<?php

namespace FData\TransactionsBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Transaction
 */
class Transaction
{
    /**
     * @var integer
     */
    private $id;

    /**
     * @var boolean
     */
    private $conciliada;

    public function setId($id)
    {
        $this->id = $id;

        return $this;
    }

    /**
     * Get id
     *
     * @return integer 
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set conciliada
     *
     * @param boolean $conciliada
     * @return Transaction
     */
    public function setConciliada($conciliada)
    {
        $this->conciliada = $conciliada;

        return $this;
    }

    /**
     * Is conciliada
     *
     * @return boolean 
     */
    public function isConciliada()
    {
        return $this->conciliada;
    }
}
