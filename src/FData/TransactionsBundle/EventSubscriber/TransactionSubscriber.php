<?php
/**
 * Created by PhpStorm.
 * User: fherrero
 * Date: 7/30/14
 * Time: 4:54 PM
 */

namespace FData\TransactionsBundle\EventSubscriber;


use Doctrine\ORM\EntityManager;
use FData\TransactionsBundle\Entity\Transaction;
use FData\TransactionsBundle\Entity\TransactionRepository;
use FData\TransactionsBundle\Events\TransactionCompleteEvent;
use FData\TransactionsBundle\Events\TransactionEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Core\SecurityContext;

class TransactionSubscriber implements EventSubscriberInterface
{

    /**
     * @var \Doctrine\ORM\EntityManager
     */
    private $manager;

    /**
     * @var SecurityContext
     */
    private $securityContext;

    /**
     * @param EntityManager $manager
     * @param \Symfony\Component\Security\Core\SecurityContext $securityContext
     */
    public function __construct(EntityManager $manager, SecurityContext $securityContext)
    {
        $this->manager         = $manager;
        $this->securityContext = $securityContext;
    }

    /**
     * Returns an array of event names this subscriber wants to listen to.
     *
     * The array keys are event names and the value can be:
     *
     *  * The method name to call (priority defaults to 0)
     *  * An array composed of the method name to call and the priority
     *  * An array of arrays composed of the method names to call and respective
     *    priorities, or 0 if unset
     *
     * For instance:
     *
     *  * array('eventName' => 'methodName')
     *  * array('eventName' => array('methodName', $priority))
     *  * array('eventName' => array(array('methodName1', $priority), array('methodName2'))
     *
     * @return array The event names to listen to
     *
     * @api
     */
    public static function getSubscribedEvents()
    {
        return [
            TransactionEvents::TRANSACTION_SUCCESS => "onTransactionComplete"
        ];
    }

    /**
     * @param TransactionCompleteEvent $event
     */
    public function onTransactionComplete(TransactionCompleteEvent $event)
    {
        if ($this->securityContext->isGranted('ROLE_CONTACTO')) {
            $this->setUsuario($event);
        }
    }

    /**
     * @param TransactionCompleteEvent $event
     */
    private function setUsuario(TransactionCompleteEvent $event)
    {
        /** @var TransactionRepository $repository */
        $repository      = $this->manager->getRepository('FDataTransactionsBundle:Transaction');

        $transactionTag = $event->getResponse()->get('transaction_tag');
        $transaction    = $repository->findByTransactionTag($transactionTag);
        if (!$transaction) {
            $transaction = (new Transaction())->setTransactionTag($transactionTag);
        }

        $transaction->setUsuario($event->getUser()->getUsername());
        $this->manager->persist($transaction);
        $this->manager->flush();
    }
}