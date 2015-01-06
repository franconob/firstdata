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
     * @var \DateTime
     */
    private $startDate = null;

    /**
     * @var \DateTime
     */
    private $endDate = null;

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
     * @param \DateTime $startDate
     * @return $this
     */
    public function setStartDate(\DateTime $startDate)
    {
        $this->startDate = $startDate;

        return $this;
    }

    /**
     * @param \DateTime $endDate
     * @return $this
     */
    public function setEndDate(\DateTime $endDate)
    {
        $this->endDate = $endDate;

        return $this;
    }

    /**
     * @return \DateTime
     */
    public function getStartDate()
    {
        return $this->startDate;
    }

    /**
     * @return \DateTime
     */
    public function getEndDate()
    {
        return $this->endDate;
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


        if (!$this->startDate) {
            $now             = new \DateTime();
            $this->startDate = $now->sub(new \DateInterval('P6M'));
        }

        $queryFields = [
            "search_field" => "custref",
            "search"       => $this->user->getSearchString(),
            "start_date"   => $this->startDate->format('Y-m-d')
        ];

        if ($this->endDate) {
            $queryFields["end_date"] = $this->endDate->format('Y-m-d');
        }

        $this->request->setQuery($queryFields);

        return $this;
    }
} 