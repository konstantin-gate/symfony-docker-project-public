<?php

declare(strict_types=1);

namespace App\Tests\Greeting;

use App\Greeting\Entity\GreetingContact;
use App\Greeting\Entity\GreetingLog;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Validation;

class GreetingLogTest extends TestCase
{
    public function testValidation(): void
    {
        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
        $contact = $this->createMock(GreetingContact::class);

        // Valid year
        $log = new GreetingLog($contact, 2025);
        $violations = $validator->validate($log);
        $this->assertCount(0, $violations);

        // Invalid year (negative)
        $log = new GreetingLog($contact, -5);
        $violations = $validator->validate($log);
        $this->assertCount(1, $violations);
        $this->assertSame('This value should be either positive or zero.', $violations->get(0)->getMessage());
    }

    public function testConstructorAndGetters(): void
    {
        $contact = $this->createMock(GreetingContact::class);
        $year = 2025;

        $log = new GreetingLog($contact, $year);

        $this->assertNull($log->getId());
        $this->assertSame($contact, $log->getContact());
        $this->assertSame($year, $log->getYear());
        $this->assertEqualsWithDelta(
            new \DateTimeImmutable(),
            $log->getSentAt(),
            1 // 1 second tolerance
        );
    }

    public function testSetters(): void
    {
        $contact1 = $this->createMock(GreetingContact::class);
        $contact2 = $this->createMock(GreetingContact::class);
        $year = 2025;

        $log = new GreetingLog($contact1, $year);

        $uuid = Uuid::v4();
        $log->setId($uuid);
        $this->assertSame($uuid, $log->getId());

        $log->setContact($contact2);
        $this->assertSame($contact2, $log->getContact());

        $log->setYear(2026);
        $this->assertSame(2026, $log->getYear());

        $sentAt = new \DateTimeImmutable('2024-01-01');
        $log->setSentAt($sentAt);
        $this->assertSame($sentAt, $log->getSentAt());
    }

    /**
     * @throws \DateMalformedStringException
     */
    public function testSentAtImmutability(): void
    {
        $contact = $this->createMock(GreetingContact::class);
        $year = 2025;

        $log = new GreetingLog($contact, $year);
        $originalSentAt = $log->getSentAt();

        // Try to modify the date (should not affect the entity)
        $newDate = $originalSentAt->modify('+1 day');

        // Assert that the entity's date has not changed
        $this->assertEquals($originalSentAt, $log->getSentAt());

        // Assert that the new date is different from the entity's date
        $this->assertNotEquals($newDate, $log->getSentAt());
    }

    public function testEquality(): void
    {
        $contact1 = $this->createMock(GreetingContact::class);
        $contact2 = $this->createMock(GreetingContact::class);

        $log1 = new GreetingLog($contact1, 2025);
        // Simulate different creation time or ID (though ID is null here)
        sleep(1);
        $log2 = new GreetingLog($contact2, 2025);

        $this->assertNotSame($log1, $log2);
        // Different sentAt (due to sleep) and different contact mocks should make them unequal
        $this->assertNotEquals($log1, $log2);
    }

    public function testYearBoundaryValues(): void
    {
        $contact = $this->createMock(GreetingContact::class);

        // Тест с минимальным разумным годом
        $log = new GreetingLog($contact, 1970);
        $this->assertSame(1970, $log->getYear());

        // Тест с нулевым годом (если это допустимо бизнес-логикой)
        $log = new GreetingLog($contact, 0);
        $this->assertSame(0, $log->getYear());

        // Тест с будущим годом
        $futureYear = (int) date('Y') + 10;
        $log = new GreetingLog($contact, $futureYear);
        $this->assertSame($futureYear, $log->getYear());
    }

    public function testSetContactToNull(): void
    {
        $contact = $this->createMock(GreetingContact::class);
        $log = new GreetingLog($contact, 2024);

        // Проверяем, что можно установить null (если это разрешено логикой)
        $log->setContact(null);
        $this->assertNull($log->getContact());

        // И снова установить контакт
        $log->setContact($contact);
        $this->assertSame($contact, $log->getContact());
    }

    public function testFluentInterface(): void
    {
        $contact = $this->createMock(GreetingContact::class);
        $log = new GreetingLog($contact, 2024);

        $this->assertSame($log, $log->setId(Uuid::v4()));
        $this->assertSame($log, $log->setContact($contact));
        $this->assertSame($log, $log->setYear(2025));
        $this->assertSame($log, $log->setSentAt(new \DateTimeImmutable()));
    }

    public function testTypeSafety(): void
    {
        $contact = $this->createMock(GreetingContact::class);

        // Проверяем, что конструктор требует правильные типы
        $this->expectException(\TypeError::class);
        // Это должно вызвать TypeError, так как ожидается int для года
        // @phpstan-ignore-next-line
        new GreetingLog($contact, '2024'); // Строка вместо int
    }

    #[DataProvider('invalidYearProvider')]
    public function testInvalidYearThrowsException(mixed $invalidYear): void
    {
        $contact = $this->createMock(GreetingContact::class);

        $this->expectException(\TypeError::class);
        new GreetingLog($contact, $invalidYear);
    }

    /**
     * @return array<array<mixed>>
     */
    public static function invalidYearProvider(): array
    {
        return [
            ['string'],        // Строка вместо int
            [2024.5],          // Float вместо int
            [null],            // Null вместо int
            [[]],              // Массив вместо int
        ];
    }
}
