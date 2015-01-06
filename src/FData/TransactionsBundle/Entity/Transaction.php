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
     * @var \DateTime
     */
    private $fecha;

    /**
     * @var string
     */
    private $usuario;

    /**
     * @var bool
     */
    private $conciliada;

    public function __construct()
    {
        $this->fecha = new \DateTime();
        $this->conciliada = false;
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
     * Set fecha
     *
     * @param \DateTime
     * @return Transaction
     */
    public function setFecha($fecha)
    {
        $this->fecha = $fecha;

        return $this;
    }

    /**
     * Get fecha
     *
     * @return \DateTime
     */
    public function getFecha()
    {
        return $this->fecha;
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

    public function setConciliada()
    {
        $this->conciliada = true;
    }

    /**
     * Set usuario
     *
     * @param string $usuario
     * @return Transaction
     */
    public function setUsuario($usuario)
    {
        $this->usuario = $usuario;

        return $this;
    }

    /**
     * Get usuario
     *
     * @return string
     */
    public function getUsuario()
    {
        return $this->usuario;
    }

    /**
     * @var string
     */
    private $transactionTag;


    /**
     * Set transactionTag
     *
     * @param string $transactionTag
     * @return Transaction
     */
    public function setTransactionTag($transactionTag)
    {
        $this->transactionTag = $transactionTag;

        return $this;
    }

    /**
     * Get transactionTag
     *
     * @return string
     */
    public function getTransactionTag()
    {
        return $this->transactionTag;
    }
    /**
     * @var \DateTime
     */
    private $fechaConciliacion;


    /**
     * Get conciliada
     *
     * @return boolean 
     */
    public function getConciliada()
    {
        return $this->conciliada;
    }

    /**
     * Set fechaConciliacion
     *
     * @param \DateTime $fechaConciliacion
     * @return Transaction
     */
    public function setFechaConciliacion($fechaConciliacion)
    {
        $this->fechaConciliacion = $fechaConciliacion;

        return $this;
    }

    /**
     * Get fechaConciliacion
     *
     * @return \DateTime 
     */
    public function getFechaConciliacion()
    {
        return $this->fechaConciliacion;
    }
}
