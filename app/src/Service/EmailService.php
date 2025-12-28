<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;

/**
 * Služba pro odesílání e-mailů s využitím šablon Twig.
 * Zajišťuje základní konfiguraci odesílatele a odeslání zprávy přes MailerInterface.
 */
class EmailService extends AbstractEmailService
{
    public function __construct(
        private readonly MailerInterface $mailer,
        string $senderEmail,
        string $senderName,
    ) {
        parent::__construct($senderEmail, $senderName);
    }

    /**
     * Odešle e-mail přes MailerInterface.
     *
     * @throws TransportExceptionInterface
     */
    protected function sendEmail(TemplatedEmail $email): void
    {
        $this->mailer->send($email);
    }
}
