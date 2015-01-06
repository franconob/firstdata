<?php
/**
 * Created by PhpStorm.
 * User: fherrero
 * Date: 7/3/14
 * Time: 7:48 PM
 */

namespace FData\TransactionsBundle\Transaction;

use Doctrine\ORM\EntityManager;
use FData\SecurityBundle\User\User;
use FData\TransactionsBundle\Events\TransactionCompleteEvent;
use FData\TransactionsBundle\Events\TransactionEvents;
use FData\TransactionsBundle\HttpClient\Clients\TransactionClient;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Message\ResponseInterface;
use FData\TransactionsBundle\Entity\Transaction as TransactionEntity;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class Transaction
{

    /** @var  EntityManager */
    private $entity_manager;

    /** @var  TransactionClient */
    private $http_client;

    /** @var  string */
    private $key_id;

    /** @var  string */
    private $hmac_key;

    /** @var  string */
    private $gateway_id;

    /** @var  User */
    private $user;

    /** @var  string */
    private $password;

    /** @var  Response */
    private $response;

    /**
     * @var EventDispatcherInterface
     */
    private $dispatcher;

    public function __construct(EntityManager $entity_manager, $http_client, $key_id, $hmac_key, $gatewat_id, $password, User $user, EventDispatcherInterface $dispatcher)
    {
        $this->entity_manager = $entity_manager;
        $this->http_client    = $http_client;
        $this->key_id         = $key_id;
        $this->hmac_key       = $hmac_key;
        $this->gateway_id     = $gatewat_id;
        $this->password       = $password;
        $this->user           = $user;
        $this->dispatcher     = $dispatcher;
    }

    /**
     * @param string $transaction_type
     * @param array $transaction_data
     * @throws Exception
     * @throws \Exception Cuando la accion no esta programada
     * @return mixed
     */
    public function execute($transaction_type, array $transaction_data)
    {
        if (!method_exists($this, $transaction_type)) {
            throw new \Exception;
        }

        try {
            $response = call_user_func(array($this, $transaction_type), $transaction_data);
            $this->setResponse($response);
            if ($this->getResponse()->hasFailed()) {
                $ex = new Exception();
                $ex->setResponse($this->getResponse());
                throw $ex;
            }

            $this->dispatcher->dispatch(TransactionEvents::TRANSACTION_SUCCESS, new TransactionCompleteEvent(
                $transaction_data,
                $this->response,
                $this->user
            ));

        } catch (ClientException $e) {
            $ex = new Exception();
            $ex->setResponse(new Response($e->getResponse(), $this->user));
            $ex->setHttpException($e);
            throw $ex;
        }
    }

    public function conciliar(array $transaction_data)
    {
        /** @var TransactionEntity $transaction */
        $transaction = $this->entity_manager->getRepository('FDataTransactionsBundle:Transaction')->getOrCreate($transaction_data['transaction_tag']);

        $transaction->setFechaConciliacion(new \DateTime());
        $transaction->setConciliada();

        $this->entity_manager->persist($transaction);
        $this->entity_manager->flush();
    }

    public function taggedVoid(array $transaction)
    {
        $transaction['transaction_type'] = '33';

        return $this->taggedRefund($transaction);
    }

    public function taggedPreAuthComp(array $transaction)
    {
        $transaction['transaction_type'] = '32';

        return $this->taggedRefund($transaction);
    }

    /**
     * @param array $transaction
     * @return \GuzzleHttp\Message\ResponseInterface
     * @throws ClientException
     */
    private function taggedRefund(array $transaction)
    {
        $requestBody = array_merge([
            'gateway_id'       => $this->gateway_id,
            'password'         => $this->password,
            'transaction_type' => '34',
            'cvd_presence_ind' => '0',
            'reference_3'      => $transaction['transaction_tag'],
            'customer_ref'     => $this->user->getSearchString()

        ], $transaction);

        return $this->runTransaction($requestBody);
    }

    public function newTransaction(array $transaction)
    {
        $transaction['cc_expiry'] = $this->formatExpiryDate($transaction['cc_expiry']);
        $requestBody              = array_merge([
            'gateway_id'       => $this->gateway_id,
            'password'         => $this->password,
            'transaction_type' => '00',
            'cvd_presence_ind' => '1',
            'customer_ref'     => $this->user->getSearchString()
        ], $transaction);

        return $this->runTransaction($requestBody);
    }

    public function preAuth(array $transaction)
    {
        $transaction['cc_expiry'] = $this->formatExpiryDate($transaction['cc_expiry']);
        $requestBody              = array_merge([
            'gateway_id'       => $this->gateway_id,
            'password'         => $this->password,
            'transaction_type' => '01',
            'cvd_presence_ind' => '1',
            'customer_ref'     => $this->user->getSearchString()
        ], $transaction);

        return $this->runTransaction($requestBody);
    }

    /**
     * @param array $transaction
     * @return \GuzzleHttp\Message\ResponseInterface
     * @throws ClientException
     */
    private function refund(array $transaction)
    {
        $transaction['cc_expiry'] = $this->formatExpiryDate($transaction['cc_expiry']);
        $requestBody              = array_merge([
            'gateway_id'       => $this->gateway_id,
            'password'         => $this->password,
            'transaction_type' => '04',
            'cvd_presence_ind' => '1',
            'customer_ref'     => $this->user->getSearchString()
        ], $transaction);

        return $this->runTransaction($requestBody);
    }

    /**
     * @return Response
     */
    public function getResponse()
    {
        return $this->response;
    }


    private function formatExpiryDate($expiryDate)
    {
        return str_replace('/', '', $expiryDate);
    }

    private function calcHMAC($requestBody)
    {
        $gge4_date      = gmdate('Y-m-d\TH:i:s') . 'Z';
        $method         = 'POST';
        $content_digest = sha1($requestBody);
        $content_type   = 'application/json';

        $hmac_data = $method . "\n" . $content_type . "\n" . $content_digest . "\n" . $gge4_date . "\n" . $this->http_client->getEndpoint();

        $headers = [
            'Accept'              => 'application/json',
            'Content-Type'        => $content_type,
            'X-GGe4-Content-SHA1' => $content_digest,
            'X-GGe4-Date'         => $gge4_date,
            'Authorization'       => 'GGE4_API ' . $this->key_id . ':' . base64_encode(hash_hmac('sha1', $hmac_data, $this->hmac_key, true))
        ];

        return $headers;
    }

    /**
     * @param array $body Body SIN ENCODEAR A JSON
     * @return \GuzzleHttp\Message\ResponseInterface
     * @throws ClientException|null
     */
    private function runTransaction($body)
    {
        $body = json_encode($body);

        $headers = $this->calcHMAC($body);

        return $this->http_client->createRequest($body, ["headers" => $headers])->send();
    }

    /**
     * @param ResponseInterface $response
     */
    private function setResponse(ResponseInterface $response)
    {
        $this->response = new Response($response, $this->user);
    }

} 