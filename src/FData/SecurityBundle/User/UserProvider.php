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
                "SELECT va.*, vacf.cf_721 as password from vtiger_account va INNER JOIN vtiger_accountscf vacf ON (va.accountid = vacf.accountid)
                 WHERE va.email1 = ?
                "
                , array($username));

            if (!$user) {
                throw new UsernameNotFoundException(sprintf("El usuario %s no existe", $username));
            }

            return new User($user['accountid'], $username, $user['password'], "", $user['accountname'], array('ROLE_CUENTA'));
        } else {
            $user = $this->connection->fetchAssoc(
                "SELECT * from vtiger_users WHERE user_name = ?"
                , array($username));
            if (!$user) {
                throw new UsernameNotFoundException(sprintf("El usuario %s no existe", $username));
            }

            return new User($user['id'], $username, $user['user_hash'], "", $user['first_name'] . ' ' . $user['last_name'], array('ROLE_ADMIN'));
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
    public function refreshUser(UserInterface $user)
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
    public function supportsClass($class)
    {
        return $class === "FData\SecurityBundle\User\User";
    }
}