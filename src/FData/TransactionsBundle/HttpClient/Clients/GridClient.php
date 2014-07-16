<?php
/**
 * Created by PhpStorm.
 * User: fherrero
 * Date: 7/9/14
 * Time: 7:39 AM
 */

namespace FData\TransactionsBundle\HttpClient\Clients;


use FData\SecurityBundle\User\User;
use FData\TransactionsBundle\HttpClient\HttpClient;

class GridClient extends HttpClient
{
    /**
     * @var User
     */
    private $user;

    /**
     * @var string
     */
    public $username;

    /**
     * @var string
     */
    public $password;

    public function setUsername($username)
    {
        $this->username = $username;
    }

    public function setPassword($password)
    {
        $this->password = $password;
    }

    public function setUser(User $user)
    {
        $this->user = $user;
    }

    /**
     * @param string $body
     * @param array $options
     * @return $this|\GuzzleHttp\Message\RequestInterface
     */
    public function createRequest($body = "", $options = array())
    {
        $options['auth'] = [$this->username, $this->password];
        parent::createRequest($body, $options);

        $this->headers = [
            "Acecpt", "text/search-v3+csv"
        ];

        $now         = new \DateTime();
        $last6Months = $now->sub(new \DateInterval('P6M'));

        $this->request->setQuery(["search_field" => "custref", "search" => $this->user->getSearchString(), "start_date" => $last6Months->format('Y-m-d')]);

        return $this;
    }
} 