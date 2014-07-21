<?php
/**
 * Created by PhpStorm.
 * User: fherrero
 * Date: 7/11/14
 * Time: 11:52 AM
 */

namespace FData\TransactionsBundle\Mail;


use FData\SecurityBundle\User\User;
use FData\SecurityBundle\User\UserRepository;
use Symfony\Bundle\FrameworkBundle\Templating\TemplateReference;
use Symfony\Bundle\TwigBundle\Debug\TimedTwigEngine;

class NotificationMailer
{

    /**
     * @var string
     */
    private $from = "info@911booking.com";

    private $subject = "";

    /**
     * @var \Swift_Mailer
     */
    private $mailer;

    /**
     * @var TimedTwigEngine
     */
    private $templating;

    /**
     * @var \FData\SecurityBundle\User\UserRepository
     */
    private $user_repository;

    /**
     * @param \Swift_Mailer $mailer
     * @param \Symfony\Bundle\TwigBundle\Debug\TimedTwigEngine $templating
     * @param \FData\SecurityBundle\User\UserRepository $userRepository
     */
    public function __construct(\Swift_Mailer $mailer, TimedTwigEngine $templating, UserRepository $userRepository)
    {
        $this->mailer          = $mailer;
        $this->templating      = $templating;
        $this->user_repository = $userRepository;
    }

    /**
     * @param array $mail_data
     * @param User $user
     * @return int
     */
    public function createAndSend($mail_data, User $user)
    {
        $ctr = $mail_data['ctr'];
        unset($mail_data['ctr']);
        $body    = $this->templating->render(new TemplateReference('FDataTransactionsBundle', 'Mails', 'notification', 'html', 'twig'), [
            'transaction' => $mail_data,
            'ctr'         => $ctr
        ]);
        $message = new \Swift_Message($this->subject, $body, 'text/html', 'utf-8');
        $message->setFrom($this->from);
        $message->setTo($user->getUsername());

        $copies = $this->user_repository->getComunicaciones();
        if ($copies)
            $message->setBcc($copies);

        return $this->mailer->send($message);
    }
}