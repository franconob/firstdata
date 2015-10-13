<?php
/**
 * Created by PhpStorm.
 * User: fherrero
 * Date: 09/02/15
 * Time: 17:55
 */

namespace FData\TransactionsBundle\Mail;

use FData\SecurityBundle\User\User;
use FData\TransactionsBundle\Transaction\Response;
use FData\TransactionsBundle\Transaction\Transaction;
use Openbuildings\Swiftmailer\CssInlinerPlugin;
use Symfony\Bundle\TwigBundle\TwigEngine;
use Symfony\Component\Security\Core\SecurityContext;

class ClientNotification
{
    /**
     * @var \Swift_Mailer
     */
    protected $mailer;

    /**
     * @var string
     */
    protected $subject;

    /**
     * @var string
     */
    protected $from;

    /**
     * @var TwigEngine
     */
    private $twig;

    /**
     * @var User
     */
    protected $user;

    /**
     * @var string
     */
    protected $crmPath;

    /**
     * @var string
     */
    protected $crmDomain;

    /**
     * @param \Swift_Mailer   $mailer
     * @param string          $from
     * @param TwigEngine      $twig
     * @param SecurityContext $securityContext
     * @param string          $crmPath
     * @param string          $crmDomain
     */
    public function __construct(
        \Swift_Mailer $mailer,
        $from,
        TwigEngine $twig,
        SecurityContext $securityContext,
        $crmPath,
        $crmDomain
    ) {
        $this->mailer    = $mailer;
        $this->from      = $from;
        $this->twig      = $twig;
        $this->user      = $securityContext->getToken()->getUser();
        $this->crmDomain = $crmDomain;
        $this->crmPath   = $crmPath;
    }

    /**
     * @param Response $transaction
     * @param string   $mail
     * @return int
     * @throws \Exception
     * @throws \Twig_Error
     */
    public function createAndSend(Response $transaction, $mail)
    {
        $vars = [
            'transactionType' => Transaction::$typeMap[(int) $transaction->get('transaction_type')],
            'cardholderName'  => $transaction->get('cardholder_name'),
            'transactionTag'  => $transaction->get('transaction_tag'),
            'hotel'           => $this->user->getHotel(),
            'hotelDireccion'  => $this->user->getExtraData('dir_calle').' '.$this->user->getExtraData('dir_pobox'),
            'hotelProvincia'  => $this->user->getExtraData('dir_provincia'),
            'hotelPais'       => $this->user->getExtraData('dir_pais'),
            'amount'          => $transaction->get('amount'),
            'currency'        => $transaction->get('currency_code'),
            'leyenda'         => $this->user->getExtraData('leyenda_recibo'),
            'referenceNo'     => $transaction->get('reference_no'),
            'bank_message'    => $transaction->get('bank_message'),
        ];

        if ($logo = $this->user->getExtraData('logo')) {
            if (file_exists($this->crmPath.DIRECTORY_SEPARATOR.$logo)) {
                $vars['logo'] = $this->crmDomain.'/'.$logo;
            }
        }

        $body    = $this->renderBody($vars);
        $subject = $this->getSubject($transaction);

        $message = new \Swift_Message($subject, $body, 'text/html');

        $emails = explode(',', $mail);

        $emails = array_filter($emails);

        $emails = array_filter(
            $emails,
            function ($email) {
                return (bool) preg_match('/.+\@.+\..+/', $email);
            }
        );

        $message->setFrom($this->from);
        $message->setTo($emails);

        $this->mailer->registerPlugin(new CssInlinerPlugin());

        return $this->mailer->send($message);
    }

    /**
     * @param $vars
     * @return string
     * @throws \Exception
     * @throws \Twig_Error
     */
    public function renderBody($vars)
    {
        return $this->twig->render('FDataTransactionsBundle:Mails:notification.html.twig', $vars);
    }

    /**
     * @param Response $transaction
     * @return string
     */
    public function getSubject(Response $transaction)
    {
        return sprintf(
            "%s - Your order %s has been paid successfully",
            $this->user->getHotel(),
            $transaction->get('transaction_tag')
        );
    }

    /**
     * @return User|mixed
     */
    public function getUser()
    {
        return $this->user;
    }
}