<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mime\Address;

/**
 * Abstraktní třída pro služby odesílání e-mailů.
 * Sjednocuje logiku vytváření objektu TemplatedEmail.
 */
abstract class AbstractEmailService implements EmailSenderInterface
{
    public function __construct(
        private readonly string $senderEmail,
        private readonly string $senderName,
    ) {
    }

    /**
     * @param array<string, mixed> $context
     */
    public function send(string $to, string $subject, string $template, array $context = []): void
    {
        $email = (new TemplatedEmail())
            ->from(new Address($this->senderEmail, $this->senderName))
            ->subject($subject)
            ->htmlTemplate($template)
            ->context($context);

        // Podpora pro více příjemců oddělených čárkou
        foreach (explode(',', $to) as $address) {
            $email->addTo(trim($address));
        }

        $this->sendEmail($email);
    }

    /**
     * Abstraktní metoda pro zpracování vytvořeného e-mailu (odeslání nebo uložení).
     */
    abstract protected function sendEmail(TemplatedEmail $email): void;
}
