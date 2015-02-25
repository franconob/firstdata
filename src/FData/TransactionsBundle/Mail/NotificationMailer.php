<?php
/**
 * Created by PhpStorm.
 * User: fherrero
 * Date: 7/11/14
 * Time: 11:52 AM
 */

namespace FData\TransactionsBundle\Mail;


use FData\SecurityBundle\User\UserRepository;
use FData\TransactionsBundle\Transaction\Response;
use FData\TransactionsBundle\Transaction\Transaction;
use Openbuildings\Swiftmailer\CssInlinerPlugin;
use Symfony\Bundle\FrameworkBundle\Templating\TemplateReference;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class NotificationMailer extends ClientNotification
{

    /**
     * @var UserRepository
     */
    private $userRepository;

    /**
     * @var ValidatorInterface
     */
    private $validator;

    /**
     * @param UserRepository $userRepository
     */
    public function setUserRepository(UserRepository $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    public function setValidator(ValidatorInterface $validator)
    {
        $this->validator = $validator;
    }

    /**
     * @param Response $transaction
     * @param string $mail_to
     * @return int
     */
    public function createAndSend(Response $transaction, $mail_to)
    {
        $copies = $this->userRepository->getComunicaciones();
        $tos = [$mail_to];
        foreach ($copies as $copy) {
            $errors = $this->validator->validate($copy, new Email());
            if (count($errors) > 1) {
                continue;
            } else {
                //$message->addCc($copy);
                $tos[] = $copy;
            }
        }

        $message = new \Swift_Message();
        $message->setContentType('text/html');
        $message->setFrom($this->from);
        $message->setSubject($this->getSubject($transaction));

        $vars = [
            'transactionType' => Transaction::$typeMap[(int)$transaction->get('transaction_type')],
            'cardholderName' => $transaction->get('cardholder_name'),
            'transactionTag' => $transaction->get('transaction_tag'),
            'hotel' => $this->getUser()->getHotel(),
            'hotelDireccion' => $this->getUser()->getExtraData('dir_calle') . ' ' . $this->getUser()->getExtraData('dir_pobox'),
            'hotelProvincia' => $this->getUser()->getExtraData('dir_provincia'),
            'hotelPais' => $this->getUser()->getExtraData('dir_pais'),
            'amount' => $transaction->get('amount'),
            'currency' => $transaction->get('currency_code'),
            'leyenda' => $this->getUser()->getExtraData('leyenda_recibo'),
        ];

        if ($logo = $this->user->getExtraData('logo')) {
            if (file_exists($this->crmPath . DIRECTORY_SEPARATOR . $logo)) {
                $vars['logo'] = $this->crmDomain . '/' . $logo;
            }
        }

        $this->mailer->registerPlugin(new CssInlinerPlugin());

        $message->setBody($this->renderBody($vars));

        $message->setTo($tos);

        try {
            return $this->mailer->send($message);
        } catch (\Exception $e) {
            return false;
        }
    }
}