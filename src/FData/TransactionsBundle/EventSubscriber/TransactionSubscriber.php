<?php
/**
 * Created by PhpStorm.
 * User: fherrero
 * Date: 7/30/14
 * Time: 4:54 PM
 */

namespace FData\TransactionsBundle\EventSubscriber;


use Doctrine\ORM\EntityManager;
use FData\TransactionsBundle\Entity\TransactionRepository;
use FData\TransactionsBundle\Events\TransactionCompleteEvent;
use FData\TransactionsBundle\Events\TransactionEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class TransactionSubscriber implements EventSubscriberInterface
{

    /**
     * @var \Doctrine\ORM\EntityManager
     */
    private $manager;

    /**
     * @param EntityManager $manager
     */
    public function __construct(EntityManager $manager)
    {
        $this->manager = $manager;
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

    public function onTransactionComplete(TransactionCompleteEvent $event)
    {
        $id = $event->getTransaction()['transaction_tag'];

        /** @var TransactionRepository $repository */
        $repository = $this->manager->getRepository('FDataTransactionsBundle:Transaction');
        $transaction = $repository->getOrCreate($id);

        $transaction->setUsuario($event->getUser()->getUsername());
        $this->manager->persist($transaction);
        $this->manager->flush();
    }
}