<?php
/**
 * Created by PhpStorm.
 * User: fherrero
 * Date: 7/11/14
 * Time: 1:52 PM
 */

namespace FData\SecurityBundle\User;


use Doctrine\DBAL\Connection;
use Symfony\Component\Security\Core\SecurityContext;

class UserRepository
{
    /**
     * @var \Doctrine\DBAL\Connection
     */
    private $conn;

    /**
     * @var \Symfony\Component\Security\Core\SecurityContext
     */
    private $security_context;

    /**
     * @param Connection $connection
     * @param \Symfony\Component\Security\Core\SecurityContext $securityContext
     */
    public function __construct(Connection $connection, SecurityContext $securityContext)
    {
        $this->conn             = $connection;
        $this->security_context = $securityContext;
    }

    /**
     * @return array|null
     */
    public function getComunicaciones()
    {
        $mails = $this->conn->executeQuery("SELECT CONCAT_WS(',', cf_1234, cf_1235) as mails FROM vtiger_contactscf WHERE contactid = ?", [
            $this->security_context->getToken()->getUser()->getId()
        ])->fetchAll();

        if (isset($mails[0]) && !empty($mails[0]['mails'])) {
            $addresses = explode(',', $mails['0']['mails']);
            $addresses = array_map('trim', $addresses);
            $addresses = array_map('strtolower', $addresses);
            $addresses = array_filter($addresses, function ($address) {
                return $address !== "";
            });

            return $addresses;
        } else {
            return null;
        }
    }

    /**
     * @return array|bool
     * @throws \Doctrine\DBAL\DBALException
     */
    public function getFiltroPorPais()
    {
        $filtro = $this->conn->executeQuery("SELECT cf_1236 as filtro_habilitado, cf_1237 as pais FROM vtiger_accountscf vac
                            INNER JOIN vtiger_account va ON (vac.accountid = va.accountid) 
                            INNER JOIN vtiger_crmentity ce ON (va.accountid = ce.crmid) 
                            WHERE ce.deleted = 0 AND va.accountname = ?", [
            $this->security_context->getToken()->getUser()->getHotel()
        ], [\PDO::PARAM_STR])->fetchAll();

        if (isset($filtro[0])) {
            return [
                'activo' => (bool)$filtro[0]['filtro_habilitado'] && !empty($filtro[0]['pais']),
                'pais'   => $filtro[0]['pais']
            ];
        }

        return false;
    }
} 
