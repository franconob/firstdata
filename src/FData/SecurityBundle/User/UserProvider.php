<?php
/**
 * Created by PhpStorm.
 * User: fherrero
 * Date: 7/2/14
 * Time: 1:09 PM
 */

namespace FData\SecurityBundle\User;


use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Routing\Router;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class UserProvider implements UserProviderInterface
{

    /**
     * @var \Doctrine\DBAL\Connection
     */
    private $connection;

    /**
     * @var Router
     */
    private $router;

    private static $roles = [
        "NEW_TRANSACTION",
        "REFUND",
        "TAGGED_VOID",
        "RECIBIR_MAIL",
        "PRE_AUTH",
        "EXPORT_CSV",
        "TAGGED_REFUND",
        "TAGGED_PRE_AUTH_COMP"
    ];

    /**
     * @param \Doctrine\DBAL\Connection $connection
     * @param \Symfony\Bundle\FrameworkBundle\Routing\Router $router
     */
    public function __construct(Connection $connection, Router $router)
    {
        $this->connection = $connection;
        $this->router     = $router;
    }

    /**
     * Loads the user for the given username.
     *
     * This method must throw UsernameNotFoundException if the user is not
     * found.
     *
     * @param string $username The username
     *
     * @return UserInterface
     *
     * @see UsernameNotFoundException
     *
     * @throws UsernameNotFoundException if the user is not found
     *
     */
    public function loadUserByUsername($username)
    {
        if (false !== strpos($this->router->getContext()->getHost(), 'extranet')) {
            $user = $this->connection->fetchAssoc(
                "Select vtiger_contactdetails.contactid as contactid ,accountname as HOTEL ,
                CONCAT_WS(' ',vtiger_contactdetails.firstname, vtiger_contactdetails.lastname) as nombre,
cf_1217 as 'NEW_TRANSACTION',
cf_1219 as 'REFUND',
cf_1221 as 'TAGGED_VOID',
cf_1223 as 'RECIBIR_MAIL',
cf_1218 as 'PRE_AUTH',
cf_1220 as 'EXPORT_CSV',
cf_1222 as 'TAGGED_REFUND',
cf_1232 as 'TAGGED_PRE_AUTH_COMP',
cf_851 as password
from vtiger_contactdetails
inner join vtiger_crmentity
on vtiger_crmentity.crmid=vtiger_contactdetails.contactid
Inner join vtiger_contactscf on  vtiger_contactdetails.contactid= vtiger_contactscf.contactid
inner join vtiger_account on vtiger_contactdetails.accountid=vtiger_account.accountid
where vtiger_crmentity.deleted<>1 and email= ?
                "
                , array($username));

            if (!$user) {
                throw new UsernameNotFoundException(sprintf("El usuario %s no existe", $username));
            }

            $roles = ['ROLE_CONTACTO'];
            foreach (self::$roles as $role) {
                if (isset($user[$role]) && $user[$role] !== '0')
                    $roles[] = 'ROLE_' . $role;
            }

            return new User($user['contactid'], $username, $user['password'], "", $user['nombre'], $user['HOTEL'], $roles);

        } else {
            $user = $this->connection->fetchAssoc(
                "SELECT * from vtiger_users WHERE user_name = ?"
                , array($username));
            if (!$user) {
                throw new UsernameNotFoundException(sprintf("El usuario %s no existe", $username));
            }

            return new User($user['id'], $username, $user['user_hash'], "", $user['first_name'] . ' ' . $user['last_name'], "", array('ROLE_USUARIO', 'ROLE_CONCILIAR'));
        }

    }

    /**
     * Refreshes the user for the account interface.
     *
     * It is up to the implementation to decide if the user data should be
     * totally reloaded (e.g. from the database), or if the UserInterface
     * object can just be merged into some internal array of users / identity
     * map.
     * @param UserInterface $user
     *
     * @return UserInterface
     *
     * @throws UnsupportedUserException if the account is not supported
     */
    public
    function refreshUser(UserInterface $user)
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(
                sprintf('Instances of "%s" are not supported.', get_class($user))
            );
        }

        return $this->loadUserByUsername($user->getUsername());
    }

    /**
     * Whether this provider supports the given user class
     *
     * @param string $class
     *
     * @return bool
     */
    public
    function supportsClass($class)
    {
        return $class === "FData\SecurityBundle\User\User";
    }
}