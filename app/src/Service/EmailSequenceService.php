<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\EmailRequest;
use App\Message\SendEmailMessage;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

/**
 * Služba pro hromadné odesílání e-mailů s využitím asynchronní fronty (Messenger).
 *
 * Bezpečné pro použití v HTTP požadavcích (používá Messenger dispatch).
 * Zajišťuje sekvenční doručování s nastavenou prodlevou mezi zprávami.
 */
readonly class EmailSequenceService
{
    public function __construct(
        private MessageBusInterface $bus,
        private int $emailDelay,
    ) {
    }

    /**
     * @param EmailRequest[] $requests
     *
     * @throws ExceptionInterface
     */
    public function sendSequence(array $requests): void
    {
        foreach ($requests as $index => $request) {
            $message = SendEmailMessage::fromRequest($request);

            // Zpoždění: index * delay (v milisekundách pro DelayStamp)
            // DelayStamp očekává milisekundy, ale $emailDelay je v sekundách.
            $delayMs = $index * $this->emailDelay * 1000;
            $stamps = [];

            if ($delayMs > 0) {
                $stamps[] = new DelayStamp($delayMs);
            }

            $this->bus->dispatch($message, $stamps);
        }
    }
}
