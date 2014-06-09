<?php
/**
 * Created by PhpStorm.
 * User: fherrero
 * Date: 6/9/14
 * Time: 1:53 PM
 */

namespace Service\Providers;


use Doctrine\DBAL\Connection;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Security\User;

class UserProvider implements UserProviderInterface
{
    /**
     * @var Connection
     */
    private $conn;

    public function __construct(Connection $connection)
    {
        $this->conn = $connection;
    }

    /**
     * @inheritdoc
     */
    public function loadUserByUsername($username)
    {
        $user = $this->conn->fetchAssoc(
            "SELECT va.*, vacf.cf_721 as password from vtiger_account va INNER JOIN vtiger_accountscf vacf ON (va.accountid = vacf.accountid)
             WHERE va.email1 = ?
            "
            , array($username));
        if (!$user) {
            throw new UsernameNotFoundException(sprintf("El usuario %s no existe", $username));
        }

        return new User($username, $user['password'], $user['accountname'], array('ROLE_USER'));
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
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', get_class($user)));
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
        return $class === 'Security\User';
    }
}