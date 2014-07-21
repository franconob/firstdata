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
            $addresses = array_filter($addresses, function($address) {
                return $address !== "";
            });

            return $addresses;
        } else {
            return null;
        }
    }
} 