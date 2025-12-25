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

/**
 * Třída testuje příkaz pro čištění duplicitních pozdravů.
 */
class GreetingCleanupDuplicatesCommandTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private GreetingContactRepository&MockObject $contactRepository;
    private GreetingLogRepository&MockObject $logRepository;
    private CommandTester $commandTester;

    /**
     * Připraví testovací prostředí.
     */
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

    /**
     * Testuje spuštění v režimu DRY-RUN bez nalezení duplicit.
     */
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

    /**
     * Testuje spuštění v režimu DRY-RUN s nalezením duplicit.
     */
    public function testExecuteDryRunFoundDuplicates(): void
    {
        // Mockování nalezení duplicit
        $connection = $this->createMock(Connection::class);
        $this->entityManager->method('getConnection')->willReturn($connection);

        $result = $this->createMock(Result::class);
        $result->method('fetchAllAssociative')->willReturn([
            ['e' => 'dup@test.com', 'c' => 2],
        ]);
        $connection->method('executeQuery')->willReturn($result);

        // Mockování nalezení kontaktů
        $contact1 = new GreetingContact();
        $contact1->setEmail('Dup@Test.com'); // Velká písmena
        $contact1->setCreatedAt(new \DateTimeImmutable('2024-01-01')); // Starší

        $contact2 = new GreetingContact();
        $contact2->setEmail('dup@test.com'); // Malá písmena
        $contact2->setCreatedAt(new \DateTimeImmutable('2024-01-02')); // Novější

        // Nastavení ID pro kontrolu výstupu
        // Poznámka: Generování ID je obvykle zajišťováno Doctrine, mockování/reflexe může být potřeba, pokud to testovací logika přísně vyžaduje,
        // ale pro tento unit test jsou objekty dostatečně odlišné.

        $this->contactRepository->expects($this->once())
            ->method('findByEmailsCaseInsensitive')
            ->with(['dup@test.com'])
            ->willReturn([$contact1, $contact2]);

        // Mockování kontroly logů - žádný nemá logy
        $this->logRepository->method('getContactIdsWithLogs')->willReturn([]);

        // Očekávání: Žádné změny
        $this->entityManager->expects($this->never())->method('remove');
        $this->entityManager->expects($this->never())->method('flush');

        $this->commandTester->execute([]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Found 1 emails with duplicates', $output);
        $this->assertStringContainsString('Would delete 1 contacts', $output);
        $this->assertStringContainsString('normalize 0 emails', $output); // Entita normalizuje při nastavení, takže je již v paměti normalizovaná
    }

    /**
     * Testuje vynucené smazání poraženého při čištění duplicit.
     */
    public function testExecuteForceDeletesLoser(): void
    {
        $winnerId = Uuid::v4();
        $loserId = Uuid::v4();

        // Mockování nalezení duplicit
        $connection = $this->createMock(Connection::class);
        $this->entityManager->method('getConnection')->willReturn($connection);

        $result = $this->createMock(Result::class);
        $result->method('fetchAllAssociative')->willReturn([
            ['e' => 'winner@test.com', 'c' => 2],
        ]);
        $connection->method('executeQuery')->willReturn($result);

        // Kontakty
        $winner = new GreetingContact();
        $winner->setId($winnerId);
        $winner->setEmail('winner@test.com');
        $winner->setCreatedAt(new \DateTimeImmutable('2024-01-01')); // Starší

        $loser = new GreetingContact();
        $loser->setId($loserId);
        $loser->setEmail('winner@test.com');
        $loser->setCreatedAt(new \DateTimeImmutable('2024-01-02')); // Novější

        $this->contactRepository->method('findByEmailsCaseInsensitive')
            ->willReturn([$winner, $loser]);

        // Vítěz má logy, poražený ne
        $this->logRepository->expects($this->once())
            ->method('getContactIdsWithLogs')
            ->willReturn([(string) $winnerId]);

        // Očekávání
        $this->entityManager->expects($this->once())->method('beginTransaction');
        $this->entityManager->expects($this->once())->method('remove')->with($loser);
        // Vítěz je již malými písmeny, takže není potřeba persistovat pro normalizaci
        $this->entityManager->expects($this->never())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');
        $this->entityManager->expects($this->once())->method('commit');

        $this->commandTester->execute(['--force' => true]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('CLEANUP COMPLETE', $output);
        $this->assertStringContainsString('Deleted 1 contacts', $output);
    }
}
