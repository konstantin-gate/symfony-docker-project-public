<?php

declare(strict_types=1);

namespace App\Tests\Greeting\MessageHandler;

use App\DTO\EmailRequest;
use App\Greeting\Message\BulkEmailDispatchMessage;
use App\Greeting\MessageHandler\BulkEmailDispatchMessageHandler;
use App\Greeting\Repository\GreetingContactRepository;
use App\Service\EmailSequenceService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Exception\ExceptionInterface;

class BulkEmailDispatchMessageHandlerTest extends TestCase
{
    /**
     * @throws ExceptionInterface
     */
    public function testHandlerProcessChunksCorrectly(): void
    {
        // Setup mocks
        $repository = $this->createMock(GreetingContactRepository::class);
        $emailService = $this->createMock(EmailSequenceService::class);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        // Generate 150 IDs to test 2 chunks (100 + 50)
        $allIds = array_map(static fn ($i) => "id-$i", range(1, 150));

        // Mock Repository behavior: return emails for IDs
        $repository->expects($this->exactly(2))
            ->method('findEmailsByIds')
            ->willReturnCallback(fn (array $ids) => array_map(static fn ($id) => "$id@example.com", $ids));

        // Mock EmailSequenceService: expect 2 calls (one per chunk)
        $emailService->expects($this->exactly(2))
            ->method('sendSequence')
            ->with($this->callback(function (array $requests) {
                // Check if we receive EmailRequest objects
                return \count($requests) > 0 && $requests[0] instanceof EmailRequest;
            }));

        // Mock EntityManager: expect clear() to be called twice
        $entityManager->expects($this->exactly(2))
            ->method('clear');

        $handler = new BulkEmailDispatchMessageHandler(
            $repository,
            $emailService,
            $entityManager,
            $logger
        );

        $message = new BulkEmailDispatchMessage(
            contactIds: $allIds,
            subject: 'Test Subject',
            body: 'Test Body'
        );

        // Invoke handler
        $handler($message);
    }

    /**
     * @throws ExceptionInterface
     */
    public function testHandlesEmptyList(): void
    {
        $repository = $this->createMock(GreetingContactRepository::class);
        $emailService = $this->createMock(EmailSequenceService::class);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        $repository->expects($this->never())->method('findEmailsByIds');
        $emailService->expects($this->never())->method('sendSequence');

        $handler = new BulkEmailDispatchMessageHandler(
            $repository,
            $emailService,
            $entityManager,
            $logger
        );

        $handler(new BulkEmailDispatchMessage([], 'Sub', 'Body'));
    }
}
