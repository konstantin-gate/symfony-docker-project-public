<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\GreetingCleanupDuplicatesCommand;
use App\Greeting\Entity\GreetingContact;
use App\Greeting\Repository\GreetingContactRepository;
use App\Greeting\Repository\GreetingLogRepository;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Result;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Uid\Uuid;

class GreetingCleanupDuplicatesCommandTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private GreetingContactRepository&MockObject $contactRepository;
    private GreetingLogRepository&MockObject $logRepository;
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->contactRepository = $this->createMock(GreetingContactRepository::class);
        $this->logRepository = $this->createMock(GreetingLogRepository::class);

        $command = new GreetingCleanupDuplicatesCommand(
            $this->entityManager,
            $this->contactRepository,
            $this->logRepository
        );
        $this->commandTester = new CommandTester($command);
    }

    public function testExecuteDryRunNoDuplicates(): void
    {
        $connection = $this->createMock(Connection::class);
        $this->entityManager->method('getConnection')->willReturn($connection);

        $result = $this->createMock(Result::class);
        $result->method('fetchAllAssociative')->willReturn([]);
        $connection->method('executeQuery')->willReturn($result);

        $this->commandTester->execute([]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Running in DRY-RUN mode', $output);
        $this->assertStringContainsString('No duplicate emails found', $output);
    }

    public function testExecuteDryRunFoundDuplicates(): void
    {
        // Mock finding duplicates
        $connection = $this->createMock(Connection::class);
        $this->entityManager->method('getConnection')->willReturn($connection);

        $result = $this->createMock(Result::class);
        $result->method('fetchAllAssociative')->willReturn([
            ['e' => 'dup@test.com', 'c' => 2],
        ]);
        $connection->method('executeQuery')->willReturn($result);

        // Mock finding contacts
        $contact1 = new GreetingContact();
        $contact1->setEmail('Dup@Test.com'); // Uppercase
        $contact1->setCreatedAt(new \DateTimeImmutable('2024-01-01')); // Older

        $contact2 = new GreetingContact();
        $contact2->setEmail('dup@test.com'); // Lowercase
        $contact2->setCreatedAt(new \DateTimeImmutable('2024-01-02')); // Newer

        // Set IDs for output check
        // Note: ID generation is usually handled by Doctrine, mocking/reflection might be needed if strictly required by test logic,
        // but for this unit test, the objects are distinct enough.

        $this->contactRepository->expects($this->once())
            ->method('findByEmailsCaseInsensitive')
            ->with(['dup@test.com'])
            ->willReturn([$contact1, $contact2]);

        // Mock log check - neither has logs
        $this->logRepository->method('getContactIdsWithLogs')->willReturn([]);

        // Expect NO changes
        $this->entityManager->expects($this->never())->method('remove');
        $this->entityManager->expects($this->never())->method('flush');

        $this->commandTester->execute([]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Found 1 emails with duplicates', $output);
        $this->assertStringContainsString('Would delete 1 contacts', $output);
        $this->assertStringContainsString('normalize 0 emails', $output); // Entity normalizes on set, so it appears normalized already in memory
    }

    public function testExecuteForceDeletesLoser(): void
    {
        $winnerId = Uuid::v4();
        $loserId = Uuid::v4();

        // Mock finding duplicates
        $connection = $this->createMock(Connection::class);
        $this->entityManager->method('getConnection')->willReturn($connection);

        $result = $this->createMock(Result::class);
        $result->method('fetchAllAssociative')->willReturn([
            ['e' => 'winner@test.com', 'c' => 2],
        ]);
        $connection->method('executeQuery')->willReturn($result);

        // Contacts
        $winner = new GreetingContact();
        $winner->setId($winnerId);
        $winner->setEmail('winner@test.com');
        $winner->setCreatedAt(new \DateTimeImmutable('2024-01-01')); // Older

        $loser = new GreetingContact();
        $loser->setId($loserId);
        $loser->setEmail('winner@test.com');
        $loser->setCreatedAt(new \DateTimeImmutable('2024-01-02')); // Newer

        $this->contactRepository->method('findByEmailsCaseInsensitive')
            ->willReturn([$winner, $loser]);

        // Winner has logs, Loser doesn't
        $this->logRepository->expects($this->once())
            ->method('getContactIdsWithLogs')
            ->willReturn([(string) $winnerId]);

        // Expectations
        $this->entityManager->expects($this->once())->method('beginTransaction');
        $this->entityManager->expects($this->once())->method('remove')->with($loser);
        // Winner is already lowercase, so no persist needed for normalization
        $this->entityManager->expects($this->never())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');
        $this->entityManager->expects($this->once())->method('commit');

        $this->commandTester->execute(['--force' => true]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('CLEANUP COMPLETE', $output);
        $this->assertStringContainsString('Deleted 1 contacts', $output);
    }
}
