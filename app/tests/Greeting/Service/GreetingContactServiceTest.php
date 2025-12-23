<?php

declare(strict_types=1);

namespace App\Tests\Greeting\Service;

use App\Greeting\Entity\GreetingContact;
use App\Greeting\Enum\GreetingLanguage;
use App\Greeting\Factory\GreetingContactFactory;
use App\Greeting\Repository\GreetingContactRepository;
use App\Greeting\Service\GreetingContactService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class GreetingContactServiceTest extends TestCase
{
    private GreetingContactRepository&MockObject $repository;
    private GreetingContactFactory&MockObject $factory;
    private EntityManagerInterface&MockObject $entityManager;
    private LoggerInterface&MockObject $logger;
    private GreetingContactService $service;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(GreetingContactRepository::class);
        $this->factory = $this->createMock(GreetingContactFactory::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->service = new GreetingContactService(
            $this->repository,
            $this->factory,
            $this->entityManager,
            $this->logger
        );
    }

    public function testSaveUniqueContacts(): void
    {
        $emails = ['test1@example.com', 'test2@example.com', 'test3@example.com'];

        // Expect findNonExistingEmails to be called
        $this->repository->expects($this->once())
            ->method('findNonExistingEmails')
            ->willReturn($emails); // All are new

        $this->factory->expects($this->exactly(3))
            ->method('create')
            ->willReturn(new GreetingContact());

        $this->entityManager->expects($this->exactly(3))
            ->method('persist');

        $this->entityManager->expects($this->once())
            ->method('flush');

        $count = $this->service->saveContacts($emails);
        $this->assertEquals(3, $count);
    }

    public function testFiltersExistingContactsFromDatabase(): void
    {
        $emails = ['new@test.com', 'existing@test.com'];

        // Mock: only 'new@test.com' is returned as non-existing
        $this->repository->expects($this->once())
            ->method('findNonExistingEmails')
            ->willReturn(['new@test.com']);

        $this->factory->expects($this->once())
            ->method('create')
            ->with('new@test.com')
            ->willReturn(new GreetingContact());

        $this->entityManager->expects($this->once())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        $count = $this->service->saveContacts($emails);
        $this->assertEquals(1, $count);
    }

    public function testFiltersDuplicateEmailsInInput(): void
    {
        $emails = ['double@test.com', 'double@test.com'];

        // Service dedupes input before calling repository
        // It passes unique list to findNonExistingEmails
        $this->repository->expects($this->once())
            ->method('findNonExistingEmails')
            ->with($this->callback(fn ($args) => \count($args) === 1 && $args[0] === 'double@test.com'))
            ->willReturn(['double@test.com']);

        $this->factory->expects($this->once())->method('create')->willReturn(new GreetingContact());
        $this->entityManager->expects($this->once())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        $count = $this->service->saveContacts($emails);
        $this->assertEquals(1, $count);
    }

    public function testHandleCaseInsensitiveEmails(): void
    {
        $emails = ['UPPER@test.com', 'upper@test.com'];

        // Service dedupes case-insensitively. 'UPPER' comes first, so it's kept as the key representative?
        // Actually our implementation keeps the first one encountered:
        // if (!isset($uniqueEmailsMap[$lower])) { $uniqueEmailsMap[$lower] = $cleaned; }
        // So 'UPPER@test.com' will be passed to repository.

        $this->repository->expects($this->once())
            ->method('findNonExistingEmails')
            ->with(['UPPER@test.com'])
            ->willReturn(['UPPER@test.com']);

        $this->factory->expects($this->once())->method('create')->willReturn(new GreetingContact());
        $this->entityManager->expects($this->once())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        $count = $this->service->saveContacts($emails);
        $this->assertEquals(1, $count);
    }

    public function testReturnsZeroOnEmptyInput(): void
    {
        $this->repository->expects($this->never())->method('findNonExistingEmails');
        $this->factory->expects($this->never())->method('create');
        $this->entityManager->expects($this->never())->method('persist');
        $this->entityManager->expects($this->never())->method('flush');

        $count = $this->service->saveContacts([]);
        $this->assertEquals(0, $count);
    }

    public function testComplexImportScenario(): void
    {
        // Input:
        $emails = [
            'New@test.com',
            'new@test.com',       // Duplicate of first
            'EXISTING@test.com',   // Already in DB
            'another-new@test.com',
        ];

        // Service Logic:
        // 1. Dedupes input -> ['New@test.com', 'EXISTING@test.com', 'another-new@test.com']

        // Mock Repository:
        // Returns only those NOT in DB.
        // 'EXISTING@test.com' should be filtered out by repository.
        $this->repository->expects($this->once())
            ->method('findNonExistingEmails')
            ->willReturn(['New@test.com', 'another-new@test.com']);

        // Factory called twice
        $this->factory->expects($this->exactly(2))
            ->method('create')
            ->with($this->callback(fn ($email) => \in_array($email, ['New@test.com', 'another-new@test.com'])))
            ->willReturn(new GreetingContact());

        $this->entityManager->expects($this->exactly(2))->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        $count = $this->service->saveContacts($emails);
        $this->assertEquals(2, $count);
    }

    public function testFactoryParametersWithDefaultLanguage(): void
    {
        $emails = ['test1@example.com', 'test2@example.com'];
        $this->repository->method('findNonExistingEmails')->willReturn($emails);

        $capturedDates = [];
        $this->factory->expects($this->exactly(2))
            ->method('create')
            ->willReturnCallback(function (string $email, GreetingLanguage $language, \DateTimeImmutable $date) use (&$capturedDates) {
                $this->assertEquals(GreetingLanguage::Russian, $language);
                $capturedDates[] = $date;

                return new GreetingContact();
            });

        $this->service->saveContacts($emails);

        $this->assertCount(2, $capturedDates);
        $this->assertSame($capturedDates[0], $capturedDates[1], 'The same DateTime instance should be used for all contacts in a batch');
    }

    public function testFactoryParametersWithExplicitLanguage(): void
    {
        $emails = ['test@example.com'];
        $this->repository->method('findNonExistingEmails')->willReturn($emails);

        $this->factory->expects($this->once())
            ->method('create')
            ->with(
                'test@example.com',
                GreetingLanguage::English,
                $this->isInstanceOf(\DateTimeImmutable::class)
            )
            ->willReturn(new GreetingContact());

        $this->service->saveContacts($emails, GreetingLanguage::English);
    }

    public function testFiltersEmptyEmails(): void
    {
        $emails = ['', '   ', "\n"];

        $this->repository->expects($this->never())->method('findNonExistingEmails');
        $this->factory->expects($this->never())->method('create');
        $this->entityManager->expects($this->never())->method('flush');

        $count = $this->service->saveContacts($emails);
        $this->assertEquals(0, $count);
    }

    public function testPerformanceWithLargeBatch(): void
    {
        $batchSize = 1000;
        $emails = array_map(static fn ($i) => "user$i@example.com", range(1, $batchSize));

        // Expect one call with large array
        $this->repository->expects($this->once())
            ->method('findNonExistingEmails')
            ->with($this->callback(fn ($args) => \count($args) === $batchSize))
            ->willReturn($emails); // All new

        // Expect 1000 persists
        $this->entityManager->expects($this->exactly($batchSize))
            ->method('persist');

        // Expect one flush
        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->factory->method('create')->willReturn(new GreetingContact());

        $startTime = microtime(true);
        $count = $this->service->saveContacts($emails);
        $duration = microtime(true) - $startTime;

        $this->assertEquals($batchSize, $count);
        $this->assertLessThan(1.0, $duration, "Processing $batchSize items took too long: {$duration}s");
    }

    public function testPropagatesExceptionOnFlushError(): void
    {
        $emails = ['error@test.com'];
        $this->repository->method('findNonExistingEmails')->willReturn($emails);
        $this->factory->method('create')->willReturn(new GreetingContact());

        $this->entityManager->expects($this->once())
            ->method('flush')
            ->willThrowException(new \RuntimeException('Database integrity violation'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Database integrity violation');

        $this->service->saveContacts($emails);
    }

    public function testCaseSensitivityIssueReproduction(): void
    {
        // 1. Simulate existing email in DB (uppercase)
        $existingEmailInDb = 'Choteticka1@seznam.cz';
        // 2. Try to import same email (lowercase)
        $newEmailToImport = 'choteticka1@seznam.cz';

        // findNonExistingEmails should use LOWER() logic
        // If 'Choteticka1@seznam.cz' is in DB, querying for 'choteticka1@seznam.cz' should return it as existing.
        // Therefore, findNonExistingEmails should return empty array (no NEW emails found).

        $this->repository->expects($this->once())
            ->method('findNonExistingEmails')
            ->with([$newEmailToImport])
            ->willReturn([]); // Empty list of new emails -> means it found the duplicate

        $count = $this->service->saveContacts([$newEmailToImport]);

        // Expect 0, because the service should filter out the existing email
        $this->assertEquals(0, $count, 'Service failed to detect duplicate email with different case.');
    }
}
