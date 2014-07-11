<?php
/**
 * Created by PhpStorm.
 * User: fherrero
 * Date: 7/2/14
 * Time: 1:04 PM
 */

namespace FData\SecurityBundle\User;


use Symfony\Component\Security\Core\Encoder\EncoderAwareInterface;
use Symfony\Component\Security\Core\Role\Role;
use Symfony\Component\Security\Core\User\UserInterface;

class User implements UserInterface, \Serializable, EncoderAwareInterface
{

    /**
     * @var int
     */
    private $id;

    /**
     * @var string
     */
    private $name;

    /**
     * @var string
     */
    private $username;

    /**
     * @var string
     */
    private $password;

    /**
     * @var array
     */
    private $roles;

    /**
     * @param int $id
     * @param string $username
     * @param string $password
     * @param string $salt
     * @param string $name
     * @param array $roles
     */
    public function __construct($id, $username, $password, $salt, $name, array $roles)
    {
        $this->id       = $id;
        $this->username = $username;
        $this->password = $password;
        $this->name     = $name;
        $this->roles    = $roles;
    }

    public function __toString()
    {
        return $this->name;
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Returns the roles granted to the user.
     *
     * <code>
     * public function getRoles()
     * {
     *     return array('ROLE_USER');
     * }
     * </code>
     *
     * Alternatively, the roles might be stored on a ``roles`` property,
     * and populated in any number of different ways when the user object
     * is created.
     *
     * @return Role[] The user roles
     */
    public function getRoles()
    {
        return $this->roles;
    }

    /**
     * Returns the password used to authenticate the user.
     *
     * This should be the encoded password. On authentication, a plain-text
     * password will be salted, encoded, and then compared to this value.
     *
     * @return string The password
     */
    public function getPassword()
    {
        return $this->password;
    }

    /**
     * Returns the salt that was originally used to encode the password.
     *
     * This can return null if the password was not encoded using a salt.
     *
     * @return string|null The salt
     */
    public function getSalt()
    {
        return null;
    }

    /**
     * Returns the username used to authenticate the user.
     *
     * @return string The username
     */
    public function getUsername()
    {
        return $this->username;
    }

    /**
     * Removes sensitive data from the user.
     *
     * This is important if, at any given point, sensitive information like
     * the plain-text password is stored on this object.
     */
    public function eraseCredentials()
    {
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @see \Serializable::serialize()
     */
    public function serialize()
    {
        return serialize(array(
            $this->id,
            $this->username,
            $this->password,
            $this->name,
        ));
    }

    /**
     * @see \Serializable::unserialize()
     * @param string $serialized
     * @return array
     */
    public function unserialize($serialized)
    {
        return list(
            $this->id,
            $this->username,
            $this->password,
            $this->name
            ) = unserialize($serialized);
    }

    /**
     * Gets the name of the encoder used to encode the password.
     *
     * If the method returns null, the standard way to retrieve the encoder
     * will be used instead.
     *
     * @return string
     */
    public function getEncoderName()
    {
        return in_array("ROLE_ADMIN", $this->roles) ? "crypt" : "plaintext";
    }
}