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
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Bridge\Twig\TwigEngine;
use Symfony\Component\Validator\ValidatorInterface;

class NotificationMailer
{

    /**
     * @var string
     */
    private $from;

    private $subject = "";

    /**
     * @var \Swift_Mailer
     */
    private $mailer;

    /**
     * @var TwigEngine
     */
    private $templating;

    /**
     * @var \FData\SecurityBundle\User\UserRepository
     */
    private $user_repository;

    /**
     * @var ValidatorInterface
     */
    private $validator;

    /**
     * @param \Swift_Mailer $mailer
     * @param \Symfony\Bridge\Twig\TwigEngine| $templating
     * @param \FData\SecurityBundle\User\UserRepository $userRepository
     * @param string $from
     * @param \Symfony\Component\Validator\ValidatorInterface $validator
     */
    public function __construct(\Swift_Mailer $mailer, TwigEngine $templating, UserRepository $userRepository, $from, ValidatorInterface $validator)
    {
        $this->mailer          = $mailer;
        $this->templating      = $templating;
        $this->user_repository = $userRepository;
        $this->from            = $from;
        $this->validator       = $validator;
    }

    /**
     * @param array $mail_data
     * @param User $user
     * @return int
     */
    public function createAndSend($mail_data, User $user)
    {
        $this->subject = sprintf("Transacción de cliente: %s", $user->getName());
        $ctr           = $mail_data['ctr'];
        unset($mail_data['ctr']);
        $body    = $this->templating->render(new TemplateReference('FDataTransactionsBundle', 'Mails', 'notification', 'html', 'twig'), [
            'transaction' => $mail_data,
            'ctr'         => $ctr
        ]);
        $message = new \Swift_Message($this->subject, $body, 'text/html', 'utf-8');
        $message->setFrom($this->from);
        //$message->setTo($user->getUsername());

        $copies = $this->user_repository->getComunicaciones();
        $tos = [$user->getUsername()];
        foreach ($copies as $copy) {
            $errors = $this->validator->validateValue($copy, new Email());
            if (count($errors) > 1) {
                continue;
            } else {
                //$message->addCc($copy);
                $tos[] = $copy;
            }
        }
        $message->setTo($tos);

        try {
            return $this->mailer->send($message);
        } catch (\Exception $e) {
            return false;
        }
    }
}