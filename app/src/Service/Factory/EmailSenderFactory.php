<?php

declare(strict_types=1);

namespace App\Service\Factory;

use App\Service\EmailSenderInterface;
use App\Service\EmailService;
use App\Service\FileEmailService;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Továrna pro vytváření instancí odesílače e-mailů.
 * Na základě konfiguračního režimu vrací buď SMTP odesílač nebo odesílač do souboru.
 */
readonly class EmailSenderFactory
{
    public function __construct(
        #[Autowire(service: EmailService::class)]
        private EmailSenderInterface $smtpSender,
        #[Autowire(service: FileEmailService::class)]
        private EmailSenderInterface $fileSender,
    ) {
    }

    /**
     * Vytvoří a vrátí instanci odesílače podle zadaného režimu.
     *
     * @throws \InvalidArgumentException pokud je zadán neplatný režim
     */
    public function create(string $deliveryMode): EmailSenderInterface
    {
        return match ($deliveryMode) {
            'smtp' => $this->smtpSender,
            'file' => $this->fileSender,
            default => throw new \InvalidArgumentException(\sprintf('Neplatný režim doručování e-mailů: "%s". Povolené hodnoty jsou "smtp" nebo "file".', $deliveryMode)),
        };
    }
}
