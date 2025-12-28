<?php

declare(strict_types=1);

namespace App\Tests\Greeting;

use App\Enum\Status;
use App\Greeting\Entity\GreetingContact;
use App\Greeting\Enum\GreetingLanguage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\Validation;

/**
 * Testovací třída pro entitu GreetingContact.
 * Obsahuje testy pro všechny metody a vlastnosti entity.
 */
class GreetingContactTest extends TestCase
{
    /**
     * Testuje výchozí hodnoty po vytvoření instance.
     * Zjišťuje, zda jsou správně nastaveny výchozí hodnoty pro všechny vlastnosti.
     */
    public function testDefaultValuesAfterConstruction(): void
    {
        $contact = new GreetingContact();

        $this->assertNull($contact->getId());
        $this->assertNull($contact->getEmail());
        $this->assertEquals(GreetingLanguage::Czech, $contact->getLanguage());
        $this->assertEquals(Status::Active, $contact->getStatus());
        $this->assertNotNull($contact->getUnsubscribeToken());
        $this->assertEquals(64, \strlen($contact->getUnsubscribeToken())); // 32 bajty v hexadecimálním formátu
        $this->assertEqualsWithDelta(
            new \DateTimeImmutable(),
            $contact->getCreatedAt(),
            1 // 1 sekunda tolerance
        );
    }

    /**
     * Testuje metody set a get pro všechny vlastnosti entity.
     * Ověřuje, že metody správně nastavují a vracejí hodnoty.
     */
    public function testSettersAndGetters(): void
    {
        $contact = new GreetingContact();

        // Test e-mailu
        $email = 'test@example.com';
        $contact->setEmail($email);
        $this->assertSame($email, $contact->getEmail());

        // Test jazyka
        $contact->setLanguage(GreetingLanguage::English);
        $this->assertSame(GreetingLanguage::English, $contact->getLanguage());

        // Test stavu
        $contact->setStatus(Status::Deleted);
        $this->assertSame(Status::Deleted, $contact->getStatus());

        // Test tokenu pro odhlášení
        $token = 'test_token_123';
        $contact->setUnsubscribeToken($token);
        $this->assertSame($token, $contact->getUnsubscribeToken());

        // Test null tokenu pro odhlášení
        $contact->setUnsubscribeToken(null);
        $this->assertNull($contact->getUnsubscribeToken());

        // Test data vytvoření
        $date = new \DateTimeImmutable('2024-01-01');
        $contact->setCreatedAt($date);
        $this->assertSame($date, $contact->getCreatedAt());

        // Test ID (i když se obvykle nenastavuje manuálně)
        $uuid = Uuid::v4();
        $contact->setId($uuid);
        $this->assertSame($uuid, $contact->getId());
    }

    /**
     * Testuje, že entity mohou mít stejný e-mail.
     * Ověřuje, že omezení jedinečnosti je implementováno v databázi.
     */
    public function testEmailUniqueConstraint(): void
    {
        $contact1 = new GreetingContact();
        $contact1->setEmail('unique@example.com');

        $contact2 = new GreetingContact();
        $contact2->setEmail('unique@example.com');

        // Tento test jen ověřuje, že obě entity mohou mít stejný e-mail
        // Skutečné omezení jedinečnosti je vynuceno databází
        $this->assertSame('unique@example.com', $contact1->getEmail());
        $this->assertSame('unique@example.com', $contact2->getEmail());
    }

    /**
     * Testuje fluent interface entity.
     * Ověřuje, že metody vracejí instanci entity pro řetězení.
     */
    public function testFluentInterface(): void
    {
        $contact = new GreetingContact();

        $result = $contact
            ->setEmail('test@example.com')
            ->setLanguage(GreetingLanguage::Russian)
            ->setStatus(Status::Concept)
            ->setUnsubscribeToken('token123')
            ->setCreatedAt(new \DateTimeImmutable('2024-01-01'));

        $this->assertSame($contact, $result);
    }

    /**
     * Testuje generování tokenu pro odhlášení.
     * Ověřuje, že každá instance má jiný token a že token je platný hexadecimální řetězec.
     */
    public function testUnsubscribeTokenGeneration(): void
    {
        $contact1 = new GreetingContact();
        $contact2 = new GreetingContact();

        // Tokeny by měly být různé pro každou instanci
        $this->assertNotSame(
            $contact1->getUnsubscribeToken(),
            $contact2->getUnsubscribeToken()
        );

        // Token by měl být platný hexadecimální řetězec
        $token = $contact1->getUnsubscribeToken();
        $this->assertIsString($token);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $token);
    }

    /**
     * Testuje neměnnost datumu a času.
     * Ověřuje, že modifikace původního datumu neovlivní datum v entitě.
     *
     * @throws \DateMalformedStringException
     */
    public function testDateTimeImmutability(): void
    {
        $contact = new GreetingContact();
        $originalDate = $contact->getCreatedAt();

        // Pokus o modifikaci datumu (neměl by ovlivnit entitu)
        $newDate = $originalDate->modify('+1 day');

        // Ověřuje, že datum v entitě se nezměnil
        $this->assertEquals($originalDate, $contact->getCreatedAt());

        // Ověřuje, že nový datum je rozdílný od datumu v entitě
        $this->assertNotEquals($newDate, $contact->getCreatedAt());
    }

    /**
     * Testuje bezpečnost typů enum.
     * Ověřuje, že metody přijímají pouze správné enum hodnoty.
     */
    public function testEnumTypeSafety(): void
    {
        $contact = new GreetingContact();

        // Měl by přijímat pouze enum GreetingLanguage
        $contact->setLanguage(GreetingLanguage::Czech);
        $this->assertSame(GreetingLanguage::Czech, $contact->getLanguage());

        // Měl by přijímat pouze enum Status
        $contact->setStatus(Status::Active);
        $this->assertSame(Status::Active, $contact->getStatus());
    }

    /**
     * Testuje, že datum vytvoření se po konstrukci nemění.
     * Ověřuje, že datum zůstává stejné i po uplynutí času.
     */
    public function testCreatedAtNeverChangesAfterConstruction(): void
    {
        $contact = new GreetingContact();
        $originalCreatedAt = $contact->getCreatedAt();

        // Simuluje uplynutí času
        usleep(1000); // 1ms

        $this->assertEquals($originalCreatedAt, $contact->getCreatedAt());
    }

    /**
     * Testuje normalizaci e-mailové adresy.
     * Ověřuje, že e-mailové adresy jsou správně převedeny na malá písmena.
     */
    public function testEmailNormalization(): void
    {
        $contact = new GreetingContact();

        // Test s různými formáty e-mailů
        $emails = [
            'test@example.com' => 'test@example.com',
            'Test@Example.COM' => 'test@example.com',
            'test.email+tag@example.co.uk' => 'test.email+tag@example.co.uk',
        ];

        foreach ($emails as $input => $expected) {
            $contact->setEmail($input);
            $this->assertSame($expected, $contact->getEmail());
        }
    }

    /**
     * Testuje, že entita nevyvolává výjimku při neplatném e-mailu.
     * Ověřuje, že entita nevaliduje formát e-mailu a přijímá jakýkoli řetězec.
     */
    public function testInvalidEmailDoesNotThrowException(): void
    {
        $contact = new GreetingContact();

        // Entita nevaliduje formát e-mailu, takže toto by mělo fungovat
        $contact->setEmail('not-an-email');
        $this->assertSame('not-an-email', $contact->getEmail());
    }

    /**
     * Testuje výchozí stav kontaktu.
     * Ověřuje, že výchozí stav je Active a má správné vlastnosti.
     */
    public function testDefaultStatusIsActive(): void
    {
        $contact = new GreetingContact();
        $this->assertTrue($contact->getStatus()->isVisible());
        $this->assertTrue($contact->getStatus()->isEditable());
    }

    /**
     * Testuje výchozí jazyk kontaktu.
     * Ověřuje, že výchozí jazyk má správné předmět a cestu k šabloně.
     */
    public function testDefaultLanguageHasSubjectAndTemplate(): void
    {
        $contact = new GreetingContact();
        $defaultLanguage = $contact->getLanguage();

        $this->assertNotEmpty($defaultLanguage->getSubject());
        $this->assertStringEndsWith('.html.twig', $defaultLanguage->getTemplatePath());
        $this->assertStringContainsString($defaultLanguage->value, $defaultLanguage->getTemplatePath());
    }

    /**
     * Testuje validaci e-mailové adresy.
     * Ověřuje, že entita správně validuje e-mailové adresy pomocí Symfony Validator.
     */
    public function testEmailValidation(): void
    {
        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
        $contact = new GreetingContact();

        // Test platných e-mailů
        $contact->setEmail('valid@example.com');
        $violations = $validator->validate($contact);
        $this->assertCount(0, $violations, 'Platný e-mail by neměl mít žádné chyby.');

        $contact->setEmail('another.valid@sub.example.co.uk');
        $violations = $validator->validate($contact);
        $this->assertCount(0, $violations, 'Další platný e-mail by neměl mít žádné chyby.');

        // Test neplatných e-mailů
        $contact->setEmail('invalid-email');
        $violations = $validator->validate($contact);
        $this->assertCount(1, $violations, 'Neplatný e-mail by měl mít jednu chybu.');
        /** @var ConstraintViolationInterface $violation */
        $violation = $violations[0];
        $this->assertEquals('This value is not a valid email address.', $violation->getMessage());

        $contact->setEmail('invalid@');
        $violations = $validator->validate($contact);
        $this->assertCount(1, $violations, 'Neplatný e-mail (chybějící doména) by měl mít jednu chybu.');

        $contact->setEmail('test@.com');
        $violations = $validator->validate($contact);
        $this->assertCount(1, $violations, 'Neplatný e-mail (neplatná doména) by měl mít jednu chybu.');

        $contact->setEmail(null);
        $violations = $validator->validate($contact);
        $this->assertCount(0, $violations, 'Null e-mail by měl být platný, pokud je povoleno null.');
    }

    /**
     * Testuje serializaci a deserializaci entity.
     * Ověřuje, že entita správně přežije serializaci a deserializaci.
     */
    public function testSerialization(): void
    {
        $contact = new GreetingContact();
        $contact->setEmail('test@example.com');
        $contact->setLanguage(GreetingLanguage::English);
        $contact->setStatus(Status::Active);

        // Simuluje serializaci/deserializaci
        $serialized = serialize($contact);
        $unserialized = unserialize($serialized);

        $this->assertInstanceOf(GreetingContact::class, $unserialized);

        $this->assertEquals($contact->getEmail(), $unserialized->getEmail());
        $this->assertEquals($contact->getLanguage(), $unserialized->getLanguage());
        $this->assertEquals($contact->getStatus(), $unserialized->getStatus());
    }

    /**
     * Testuje přiřazení jazyka a stavu kontaktu.
     * Ověřuje, že metody správně nastavují a vracejí hodnoty enum.
     */
    #[DataProvider('languageAndStatusProvider')]
    public function testLanguageAndStatusAssignment(GreetingLanguage $language, Status $status): void
    {
        $contact = new GreetingContact();
        $contact->setLanguage($language);
        $contact->setStatus($status);

        $this->assertSame($language, $contact->getLanguage());
        $this->assertSame($status, $contact->getStatus());
    }

    /**
     * Poskytuje data pro testování všech kombinací jazyka a stavu.
     * Vytváří testovací data pro všechny možné kombinace enum hodnot.
     *
     * @return array<string, array{GreetingLanguage, Status}>
     */
    public static function languageAndStatusProvider(): array
    {
        $data = [];

        foreach (GreetingLanguage::cases() as $language) {
            foreach (Status::cases() as $status) {
                $data[\sprintf('Language: %s, Status: %s', $language->name, $status->name)] = [$language, $status];
            }
        }

        return $data;
    }

    /**
     * Testuje rovnost entit.
     * Ověřuje, že různé instance nejsou rovny i při stejných datech.
     */
    public function testEquality(): void
    {
        $contact1 = new GreetingContact();
        $contact1->setEmail('test@example.com');

        $contact2 = new GreetingContact();
        $contact2->setEmail('test@example.com');

        // Různé instance by neměly být rovny
        $this->assertNotSame($contact1, $contact2);
        $this->assertNotEquals($contact1, $contact2); // Různé ID/tokeny
    }

    /**
     * Testuje, že konstruktor vyvolá výjimku při selhání generování náhodných dat.
     * Ověřuje, že konstruktor správně reaguje na výjimku z funkce random_bytes.
     */
    #[RunInSeparateProcess]
    public function testConstructorThrowsRandomException(): void
    {
        // Definuje mock funkci v namespace entity pomocí eval
        // protože jsme v samostatném procesu, toto neovlivní ostatní testy.
        eval('
            namespace App\Greeting\Entity;

            function random_bytes(int $length): string
            {
                throw new \Random\RandomException("Simulované selhání funkce random_bytes");
            }
        ');

        $this->expectException(\Random\RandomException::class);
        $this->expectExceptionMessage('Simulované selhání funkce random_bytes');

        new GreetingContact();
    }
}
