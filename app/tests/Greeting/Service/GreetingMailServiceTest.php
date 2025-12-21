<?php

declare(strict_types=1);

namespace App\Tests\Greeting\Service;

use App\DTO\EmailRequest;
use App\Greeting\Entity\GreetingContact;
use App\Greeting\Repository\GreetingContactRepository;
use App\Greeting\Service\GreetingMailService;
use App\Service\EmailSequenceService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\Exception\TransportException;
use Symfony\Component\Uid\Uuid;

class GreetingMailServiceTest extends TestCase
{
    private GreetingContactRepository&MockObject $repository;
    private EmailSequenceService&MockObject $emailSequenceService;
    private LoggerInterface&MockObject $logger;
    private GreetingMailService $service;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(GreetingContactRepository::class);
        $this->emailSequenceService = $this->createMock(EmailSequenceService::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->service = new GreetingMailService(
            $this->repository,
            $this->emailSequenceService,
            $this->logger
        );
    }

    /**
     * @throws ExceptionInterface
     */
    public function testSendToEmptyList(): void
    {
        $this->emailSequenceService->expects($this->never())->method('sendSequence');
        $this->repository->expects($this->never())->method('find');
        $this->logger->expects($this->never())->method('info');

        $result = $this->service->sendGreetings([], 'Subject', 'Body');
        $this->assertSame(0, $result);
    }

    /**
     * @throws ExceptionInterface
     */
    public function testSendWithNonExistentContacts(): void
    {
        $contactIds = ['uuid-1', 'uuid-2'];

        $this->repository->expects($this->exactly(2))
            ->method('find')
            ->willReturnMap([
                ['uuid-1', null],
                ['uuid-2', null],
            ]);

        $this->logger->expects($this->exactly(2))
            ->method('error')
            ->with($this->stringContains('not found'));

        $this->logger->expects($this->never())->method('info');

        $result = $this->service->sendGreetings($contactIds, 'Subject', 'Body');
        $this->assertSame(0, $result);
    }

    /**
     * @throws ExceptionInterface
     */
    public function testSuccessfulOrchestrationAndDataIntegrity(): void
    {
        $contact = $this->createMock(GreetingContact::class);
        $contact->method('getEmail')->willReturn('test@example.com');
        $contact->method('getId')->willReturn(Uuid::v4());

        $this->repository->expects($this->once())
            ->method('find')
            ->with('uuid-1')
            ->willReturn($contact);

        $subject = 'Happy Holidays!';
        $body = 'Best wishes to you.';

        $this->emailSequenceService->expects($this->once())
            ->method('sendSequence')
            ->with($this->callback(function (array $requests) use ($subject, $body) {
                if (\count($requests) !== 1) {
                    return false;
                }
                /** @var EmailRequest $request */
                $request = $requests[0];

                return $request instanceof EmailRequest
                    && $request->to === 'test@example.com'
                    && $request->subject === $subject
                    && $request->template === 'email/greeting.html.twig'
                    && ($request->context['subject'] ?? null) === $subject
                    && ($request->context['body'] ?? null) === $body;
            }));

        $this->logger->expects($this->once())->method('info');

        $result = $this->service->sendGreetings(['uuid-1'], $subject, $body);
        $this->assertSame(1, $result);
    }

    /**
     * @throws ExceptionInterface
     */
    public function testContinuesOnServiceException(): void
    {
        $contact1 = $this->createMock(GreetingContact::class);
        $contact1->method('getEmail')->willReturn('user1@example.com');

        $contact2 = $this->createMock(GreetingContact::class);
        $contact2->method('getEmail')->willReturn('user2@example.com');

        $this->repository->method('find')->willReturnMap([
            ['id-1', $contact1],
            ['id-2', $contact2],
        ]);

        // First call fails, second succeeds
        $this->emailSequenceService->expects($this->exactly(2))
            ->method('sendSequence')
            ->willReturnCallback(function (array $requests) {
                if ($requests[0]->to === 'user1@example.com') {
                    throw new \RuntimeException('API Down');
                }

                return null;
            });

        $this->logger->expects($this->once())
            ->method('error')
            ->with($this->stringContains('Failed to queue greeting'), $this->arrayHasKey('error'));

        $result = $this->service->sendGreetings(['id-1', 'id-2'], 'Sub', 'Body');
        $this->assertSame(1, $result); // Only one succeeded
    }

    /**
     * @throws ExceptionInterface
     */
    public function testContinuesOnMessengerException(): void
    {
        $contact = $this->createMock(GreetingContact::class);
        $contact->method('getEmail')->willReturn('user@example.com');

        $this->repository->method('find')->willReturn($contact);

        $this->emailSequenceService->expects($this->once())
            ->method('sendSequence')
            ->willThrowException(new TransportException('RabbitMQ is down'));

        $this->logger->expects($this->once())
            ->method('error')
            ->with($this->stringContains('Failed to queue greeting'));

        $result = $this->service->sendGreetings(['id-1'], 'Sub', 'Body');

        $this->assertSame(0, $result);
    }

    /**
     * @throws ExceptionInterface
     */
    public function testLoggingSummaryForMultipleSuccesses(): void
    {
        $contact1 = $this->createMock(GreetingContact::class);
        $contact1->method('getEmail')->willReturn('user1@example.com');

        $contact2 = $this->createMock(GreetingContact::class);
        $contact2->method('getEmail')->willReturn('user2@example.com');

        $this->repository->method('find')->willReturnMap([
            ['id-1', $contact1],
            ['id-2', $contact2],
        ]);

        $subject = 'Batch Test';
        // Ensure info is called exactly once with total count 2
        $this->logger->expects($this->once())
            ->method('info')
            ->with(
                $this->stringContains('Queued {count} greeting emails'),
                $this->callback(fn (array $context) => $context['count'] === 2 && $context['subject'] === $subject)
            );

        $result = $this->service->sendGreetings(['id-1', 'id-2'], $subject, 'Body');
        $this->assertSame(2, $result);
    }

    /**
     * @throws ExceptionInterface
     */
    public function testPerformanceWithLargeBatch(): void
    {
        $batchSize = 500;
        $ids = array_map(static fn ($i) => "uuid-$i", range(1, $batchSize));

        $contact = $this->createMock(GreetingContact::class);
        $contact->method('getEmail')->willReturn('user@example.com');

        $this->repository->method('find')->willReturn($contact);

        $startTime = microtime(true);
        $result = $this->service->sendGreetings($ids, 'Perf Test', 'Body content');
        $duration = microtime(true) - $startTime;

        $this->assertSame($batchSize, $result);

        // Ensure processing 500 items takes less than 1 second (generous for mocks)
        $this->assertLessThan(1.0, $duration, \sprintf('Processing %d items took too long: %.2fs', $batchSize, $duration));
    }

    /**
     * @throws ExceptionInterface
     */
    public function testHandlesMixedIdTypes(): void
    {
        $stringId = Uuid::v4()->toRfc4122();
        $uuidId = Uuid::v4();

        $contact = $this->createMock(GreetingContact::class);
        $contact->method('getEmail')->willReturn('test@example.com');

        $this->repository->expects($this->exactly(2))
            ->method('find')
            ->with($this->callback(fn ($id) => $id === $stringId || $id === $uuidId))
            ->willReturn($contact);

        $result = $this->service->sendGreetings([$stringId, $uuidId], 'Subject', 'Body');

        $this->assertSame(2, $result);
    }

    /**
     * @throws ExceptionInterface
     */
    public function testSkipContactWithEmptyEmail(): void
    {
        $contact = $this->createMock(GreetingContact::class);
        $contact->method('getEmail')->willReturn(null); // Simulated empty data

        $this->repository->method('find')->willReturn($contact);

        $this->logger->expects($this->once())
            ->method('error')
            ->with($this->stringContains('has no email address'));

        $this->emailSequenceService->expects($this->never())->method('sendSequence');

        $result = $this->service->sendGreetings(['uuid-1'], 'Sub', 'Body');

        $this->assertSame(0, $result);
    }
}
