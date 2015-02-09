<?php
/**
 * Created by PhpStorm.
 * User: fherrero
 * Date: 09/02/15
 * Time: 17:55
 */

namespace FData\TransactionsBundle\Mail;


use Knp\Bundle\SnappyBundle\Snappy\LoggableGenerator;

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
     * @var LoggableGenerator
     */
    private $pdf;

    public function __construct(\Swift_Mailer $mailer, $from, LoggableGenerator $pdf)
    {
        $this->mailer = $mailer;
        $this->from = $from;
        $this->pdf = $pdf;
    }

    public function createAndSend($recibo, $mail)
    {
        $body = <<<EOF
Aca va el texto de notificacion
EOF;

        $reciboPDF = $this->pdf->getOutputFromHtml($recibo);

        $message = new \Swift_Message('Transaccion realizada', $body);

        $message->setFrom($this->from);
        $message->setTo($mail);

        $attach = \Swift_Attachment::newInstance($reciboPDF, '911-transaccion.pdf', 'application/pdf');
        $message->attach($attach);

        return $this->mailer->send($message);
    }
}