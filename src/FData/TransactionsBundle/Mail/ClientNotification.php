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
use Knp\Bundle\SnappyBundle\Snappy\LoggableGenerator;
use Openbuildings\Swiftmailer\CssInlinerPlugin;
use Symfony\Bundle\TwigBundle\TwigEngine;
use Symfony\Component\Security\Core\SecurityContext;

class ClientNotification
{
    /**
     * @var \Swift_Mailer
     */
    private $mailer;

    /**
     * @var string
     */
    private $from;

    /**
     * @var TwigEngine
     */
    private $twig;

    /**
     * @var User
     */
    private $user;
    /**
     * @var string
     */
    private $crmPath;
    private $crmDomain;

    /**
     * @param \Swift_Mailer $mailer
     * @param string $from
     * @param TwigEngine $twig
     * @param SecurityContext $securityContext
     * @param string $crmPath
     * @param string $crmDomain
     */
    public function __construct(\Swift_Mailer $mailer, $from, TwigEngine $twig, SecurityContext $securityContext, $crmPath, $crmDomain)
    {
        $this->mailer = $mailer;
        $this->from = $from;
        $this->twig = $twig;
        $this->user = $securityContext->getToken()->getUser();
        $this->crmDomain = $crmDomain;
        $this->crmPath = $crmPath;
    }

    /**
     * @param Response $transaction
     * @param string $mail
     * @return int
     * @throws \Exception
     * @throws \Twig_Error
     */
    public function createAndSend(Response $transaction, $mail)
    {
        $vars = [
            'transactionType' => Transaction::$typeMap[(int)$transaction->get('transaction_type')],
            'cardholderName' => $transaction->get('cardholder_name'),
            'transactionTag' => $transaction->get('transaction_tag'),
            'hotel' => $this->user->getHotel(),
            'hotelDireccion' => $this->user->getExtraData('dir_calle') . ' ' . $this->user->getExtraData('dir_pobox'),
            'hotelProvincia' => $this->user->getExtraData('dir_provincia'),
            'hotelPais' => $this->user->getExtraData('dir_pais'),
            'amount' => $transaction->get('amount'),
            'currency' => $transaction->get('currency_code'),
            'leyenda' => $this->user->getExtraData('leyenda_recibo'),
        ];

        if ($logo = $this->user->getExtraData('logo')) {
            if (file_exists($this->crmPath . DIRECTORY_SEPARATOR . $logo)) {
                $vars['logo'] = $this->crmDomain.'/'.$logo;
            }
        }

        $body = $this->twig->render('FDataTransactionsBundle:Mails:notification.html.twig', $vars);

        $subject = sprintf("%s - Your order %s has been paid successfully", $this->user->getHotel(), $transaction->get('transaction_tag'));
        $message = new \Swift_Message($subject, $body, 'text/html');

        $message->setFrom($this->from);
        $message->setTo($mail);

        $this->mailer->registerPlugin(new CssInlinerPlugin());

        return $this->mailer->send($message);
    }
}