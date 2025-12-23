<?php

declare(strict_types=1);

namespace App\Greeting\Service;

use App\Greeting\Message\BulkEmailDispatchMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

readonly class GreetingMailService
{
    public function __construct(
        private MessageBusInterface $bus,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @param string[]|Uuid[] $contactIds
     *
     * @throws ExceptionInterface
     */
    public function sendGreetings(array $contactIds, string $subject, string $body): int
    {
        if (empty($contactIds)) {
            return 0;
        }

        // Normalize IDs to strings to ensure safe serialization
        $stringIds = array_map(static fn ($id) => (string) $id, $contactIds);
        $message = new BulkEmailDispatchMessage(
            contactIds: $stringIds,
            subject: $subject,
            body: $body
        );
        $this->bus->dispatch($message);
        $count = \count($stringIds);
        $this->logger->info('Dispatched bulk email job for {count} contacts.', [
            'count' => $count,
            'subject' => $subject,
        ]);

        return $count;
    }
}
