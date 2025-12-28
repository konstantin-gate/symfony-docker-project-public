<?php

declare(strict_types=1);

namespace App\Tests\Greeting;

use App\Greeting\Entity\GreetingContact;
use App\Greeting\Entity\GreetingLog;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Validation;

/**
 * Testovací třída pro entitu GreetingLog.
 * Obsahuje testy pro validaci, konstruktor, gettery, settery a další funkce.
 */
class GreetingLogTest extends TestCase
{
    /**
     * Testuje validaci entity GreetingLog.
     * Zkouší platný a neplatný rok a ověřuje, zda validace funguje správně.
     */
    public function testValidation(): void
    {
        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
        $contact = $this->createMock(GreetingContact::class);

        // Platný rok
        $log = new GreetingLog($contact, 2025);
        $violations = $validator->validate($log);
        $this->assertCount(0, $violations);

        // Neplatný rok (záporný)
        $log = new GreetingLog($contact, -5);
        $violations = $validator->validate($log);
        $this->assertCount(1, $violations);
        $this->assertSame('This value should be either positive or zero.', $violations->get(0)->getMessage());
    }

    /**
     * Testuje konstruktor a gettery entity GreetingLog.
     * Ověřuje, že konstruktor správně nastaví všechny vlastnosti a gettery je správně vrátí.
     */
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

    /**
     * Testuje settery entity GreetingLog.
     * Ověřuje, že settery správně nastaví hodnoty a gettery je správně vrátí.
     */
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
     * Testuje neměnnost vlastnosti sentAt.
     * Ověřuje, že změna data v objektu DateTimeImmutable neovlivní entitu.
     *
     * @throws \DateMalformedStringException
     */
    public function testSentAtImmutability(): void
    {
        $contact = $this->createMock(GreetingContact::class);
        $year = 2025;

        $log = new GreetingLog($contact, $year);
        $originalSentAt = $log->getSentAt();

        // Pokus o změnu data (nemělo by ovlivnit entitu)
        $newDate = $originalSentAt->modify('+1 day');

        // Ověření, že datum entity se nezměnilo
        $this->assertEquals($originalSentAt, $log->getSentAt());

        // Ověření, že nové datum je rozdílné od data entity
        $this->assertNotEquals($newDate, $log->getSentAt());
    }

    /**
     * Testuje rovnost a nerovnost entit GreetingLog.
     * Ověřuje, že různé instance s různými kontakty a časem odeslání jsou nerovné.
     */
    public function testEquality(): void
    {
        $contact1 = $this->createMock(GreetingContact::class);
        $contact2 = $this->createMock(GreetingContact::class);

        $log1 = new GreetingLog($contact1, 2025);
        // Simuluje různé časy vytvoření nebo ID (i když ID je null)
        sleep(1);
        $log2 = new GreetingLog($contact2, 2025);

        $this->assertNotSame($log1, $log2);
        // Různé sentAt (díky sleep) a různé mocky kontaktů by měly být nerovné
        $this->assertNotEquals($log1, $log2);
    }

    /**
     * Testuje hraniční hodnoty roku.
     * Ověřuje, že entita správně zachází s minimálním, nulovým a budoucím rokem.
     */
    public function testYearBoundaryValues(): void
    {
        $contact = $this->createMock(GreetingContact::class);

        // Test s minimálním rozumným rokem
        $log = new GreetingLog($contact, 1970);
        $this->assertSame(1970, $log->getYear());

        // Test s nulovým rokem (pokud je to povoleno business logikou)
        $log = new GreetingLog($contact, 0);
        $this->assertSame(0, $log->getYear());

        // Test s budoucím rokem
        $futureYear = (int) date('Y') + 10;
        $log = new GreetingLog($contact, $futureYear);
        $this->assertSame($futureYear, $log->getYear());
    }

    /**
     * Testuje nastavení kontaktu na null.
     * Ověřuje, že je možné nastavit kontakt na null a poté ho znovu nastavit.
     */
    public function testSetContactToNull(): void
    {
        $contact = $this->createMock(GreetingContact::class);
        $log = new GreetingLog($contact, 2024);

        // Ověřujeme, že lze nastavit null (pokud je to povoleno logikou)
        $log->setContact(null);
        $this->assertNull($log->getContact());

        // A znovu nastavit kontakt
        $log->setContact($contact);
        $this->assertSame($contact, $log->getContact());
    }

    /**
     * Testuje fluent interface entity GreetingLog.
     * Ověřuje, že settery vracejí instanci sama sebe pro řetězení volání.
     */
    public function testFluentInterface(): void
    {
        $contact = $this->createMock(GreetingContact::class);
        $log = new GreetingLog($contact, 2024);

        $this->assertSame($log, $log->setId(Uuid::v4()));
        $this->assertSame($log, $log->setContact($contact));
        $this->assertSame($log, $log->setYear(2025));
        $this->assertSame($log, $log->setSentAt(new \DateTimeImmutable()));
    }

    /**
     * Testuje bezpečnost typů v konstruktoru entity GreetingLog.
     * Ověřuje, že konstruktor vyvolá výjimku TypeError při špatném typu roku.
     */
    public function testTypeSafety(): void
    {
        $contact = $this->createMock(GreetingContact::class);

        // Ověřujeme, že konstruktor vyžaduje správné typy
        $this->expectException(\TypeError::class);
        // Tohle by mělo vyvolat TypeError, protože se očekává int pro rok
        // @phpstan-ignore-next-line
        new GreetingLog($contact, '2024'); // Řetězec místo int
    }

    /**
     * Testuje vyvolání výjimky při neplatném typu roku.
     * Ověřuje, že konstruktor vyvolá TypeError pro různé neplatné typy roku.
     *
     * @param mixed $invalidYear Neplatný typ roku
     */
    #[DataProvider('invalidYearProvider')]
    public function testInvalidYearThrowsException(mixed $invalidYear): void
    {
        $contact = $this->createMock(GreetingContact::class);

        $this->expectException(\TypeError::class);
        new GreetingLog($contact, $invalidYear);
    }

    /**
     * Poskytuje datové sady pro test neplatných typů roku.
     * Vrací pole s různými neplatnými typy, které by měly vyvolat výjimku.
     *
     * @return array<array<mixed>> Pole s neplatnými typy roku
     */
    public static function invalidYearProvider(): array
    {
        return [
            ['string'],        // Řetězec místo int
            [2024.5],          // Float místo int
            [null],            // Null místo int
            [[]],              // Pole místo int
        ];
    }
}
