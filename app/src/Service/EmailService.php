<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

readonly class EmailService
{
    public function __construct(
        private MailerInterface $mailer,
        private string $senderEmail,
        private string $senderName,
    ) {
    }

    /**
     * @param array<string, mixed> $context
     *
     * @throws TransportExceptionInterface
     */
    public function send(string $to, string $subject, string $template, array $context = []): void
    {
        $email = (new TemplatedEmail())
            ->from(new Address($this->senderEmail, $this->senderName))
            ->to($to)
            ->subject($subject)
            ->htmlTemplate($template)
//            ->textTemplate(str_replace('.html.twig', '.txt.twig', $template))
            ->context($context);

        $this->mailer->send($email);
    }
}
