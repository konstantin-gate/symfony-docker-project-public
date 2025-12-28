<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

/**
 * Rozhraní pro odesílání e-mailů (ať už přes SMTP nebo do souboru).
 */
interface EmailSenderInterface
{
    /**
     * @param array<string, mixed> $context
     * @throws TransportExceptionInterface
     */
    public function send(string $to, string $subject, string $template, array $context = []): void;
}
