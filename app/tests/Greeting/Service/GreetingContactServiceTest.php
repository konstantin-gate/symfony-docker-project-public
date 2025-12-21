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

class GreetingContactServiceTest extends TestCase
{
    private GreetingContactRepository&MockObject $repository;
    private GreetingContactFactory&MockObject $factory;
    private EntityManagerInterface&MockObject $entityManager;
    private GreetingContactService $service;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(GreetingContactRepository::class);
        $this->factory = $this->createMock(GreetingContactFactory::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);

        $this->service = new GreetingContactService(
            $this->repository,
            $this->factory,
            $this->entityManager
        );
    }

    public function testSaveUniqueContacts(): void
    {
        $emails = ['test1@example.com', 'test2@example.com', 'test3@example.com'];

        $this->repository->expects($this->once())
            ->method('findBy')
            ->with(['email' => $emails])
            ->willReturn([]);

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

        $existingContact = $this->createMock(GreetingContact::class);
        $existingContact->method('getEmail')->willReturn('existing@test.com');

        $this->repository->expects($this->once())
            ->method('findBy')
            ->willReturn([$existingContact]);

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

        $this->repository->expects($this->once())
            ->method('findBy')
            ->with(['email' => ['double@test.com']])
            ->willReturn([]);

        $this->factory->expects($this->once())->method('create')->willReturn(new GreetingContact());
        $this->entityManager->expects($this->once())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        $count = $this->service->saveContacts($emails);
        $this->assertEquals(1, $count);
    }

    public function testHandleCaseInsensitiveEmails(): void
    {
        $emails = ['UPPER@test.com', 'upper@test.com'];

        $this->repository->expects($this->once())
            ->method('findBy')
            ->with(['email' => ['upper@test.com']])
            ->willReturn([]);

        $this->factory->expects($this->once())->method('create')->willReturn(new GreetingContact());
        $this->entityManager->expects($this->once())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        $count = $this->service->saveContacts($emails);
        $this->assertEquals(1, $count);
    }

    public function testReturnsZeroOnEmptyInput(): void
    {
        $this->repository->expects($this->never())->method('findBy');
        $this->factory->expects($this->never())->method('create');
        $this->entityManager->expects($this->never())->method('persist');
        $this->entityManager->expects($this->never())->method('flush');

        $count = $this->service->saveContacts([]);
        $this->assertEquals(0, $count);
    }

    public function testComplexImportScenario(): void
    {
        // Входные данные с дублями в разном регистре и пересечением с БД
        $emails = [
            'New@test.com',
            'new@test.com',       // Дубликат
            'EXISTING@test.com',   // Уже есть в БД (в другом регистре)
            'another-new@test.com',
        ];

        // Ожидаем, что сервис нормализует их до:
        // ['new@test.com', 'existing@test.com', 'another-new@test.com']

        $existingContact = $this->createMock(GreetingContact::class);
        $existingContact->method('getEmail')->willReturn('existing@test.com');

        $this->repository->expects($this->once())
            ->method('findBy')
            ->with($this->callback(function (array $criteria) {
                $emails = $criteria['email'];

                return \count($emails) === 3
                    && \in_array('new@test.com', $emails, true)
                    && \in_array('existing@test.com', $emails, true)
                    && \in_array('another-new@test.com', $emails, true);
            }))
            ->willReturn([$existingContact]);

        // Должны быть созданы только 2 новых контакта
        $this->factory->expects($this->exactly(2))
            ->method('create')
            ->with($this->callback(fn ($email) => \in_array($email, ['new@test.com', 'another-new@test.com'])))
            ->willReturn(new GreetingContact());

        $this->entityManager->expects($this->exactly(2))->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        $count = $this->service->saveContacts($emails);
        $this->assertEquals(2, $count);
    }

    public function testFactoryParametersWithDefaultLanguage(): void
    {
        $emails = ['test1@example.com', 'test2@example.com'];
        $this->repository->method('findBy')->willReturn([]);

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
        $this->repository->method('findBy')->willReturn([]);

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

        $this->repository->expects($this->never())->method('findBy');
        $this->factory->expects($this->never())->method('create');
        $this->entityManager->expects($this->never())->method('flush');

        $count = $this->service->saveContacts($emails);
        $this->assertEquals(0, $count);
    }

    public function testPerformanceWithLargeBatch(): void
    {
        $batchSize = 1000;
        $emails = array_map(static fn ($i) => "user{$i}@example.com", range(1, $batchSize));

        // Ожидаем один вызов findBy с массивом из 1000 элементов
        $this->repository->expects($this->once())
            ->method('findBy')
            ->with($this->callback(fn ($criteria) => \count($criteria['email']) === $batchSize))
            ->willReturn([]);

        // Ожидаем 1000 вызовов persist
        $this->entityManager->expects($this->exactly($batchSize))
            ->method('persist');

        // Ожидаем только один flush
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
        $this->repository->method('findBy')->willReturn([]);
        $this->factory->method('create')->willReturn(new GreetingContact());

        // Настраиваем flush на выброс исключения
        $this->entityManager->expects($this->once())
            ->method('flush')
            ->willThrowException(new \RuntimeException('Database integrity violation'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Database integrity violation');

        $this->service->saveContacts($emails);
    }
}
