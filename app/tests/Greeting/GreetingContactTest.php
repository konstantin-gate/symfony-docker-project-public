<?php

declare(strict_types=1);

namespace App\Tests\Greeting;

use App\Enum\Status;
use App\Greeting\Entity\GreetingContact;
use App\Greeting\Enum\GreetingLanguage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Validation;

class GreetingContactTest extends TestCase
{
    public function testDefaultValuesAfterConstruction(): void
    {
        $contact = new GreetingContact();

        $this->assertNull($contact->getId());
        $this->assertNull($contact->getEmail());
        $this->assertEquals(GreetingLanguage::Czech, $contact->getLanguage());
        $this->assertEquals(Status::Active, $contact->getStatus());
        $this->assertNotNull($contact->getUnsubscribeToken());
        $this->assertEquals(64, \strlen($contact->getUnsubscribeToken())); // 32 bytes in hex
        $this->assertEqualsWithDelta(
            new \DateTimeImmutable(),
            $contact->getCreatedAt(),
            1 // 1 second tolerance
        );
    }

    public function testSettersAndGetters(): void
    {
        $contact = new GreetingContact();

        // Test email
        $email = 'test@example.com';
        $contact->setEmail($email);
        $this->assertSame($email, $contact->getEmail());

        // Test language
        $contact->setLanguage(GreetingLanguage::English);
        $this->assertSame(GreetingLanguage::English, $contact->getLanguage());

        // Test status
        $contact->setStatus(Status::Deleted);
        $this->assertSame(Status::Deleted, $contact->getStatus());

        // Test unsubscribe token
        $token = 'test_token_123';
        $contact->setUnsubscribeToken($token);
        $this->assertSame($token, $contact->getUnsubscribeToken());

        // Test null unsubscribe token
        $contact->setUnsubscribeToken(null);
        $this->assertNull($contact->getUnsubscribeToken());

        // Test createdAt
        $date = new \DateTimeImmutable('2024-01-01');
        $contact->setCreatedAt($date);
        $this->assertSame($date, $contact->getCreatedAt());

        // Test ID (though typically not set manually)
        $uuid = Uuid::v4();
        $contact->setId($uuid);
        $this->assertSame($uuid, $contact->getId());
    }

    public function testEmailUniqueConstraint(): void
    {
        $contact1 = new GreetingContact();
        $contact1->setEmail('unique@example.com');

        $contact2 = new GreetingContact();
        $contact2->setEmail('unique@example.com');

        // This test just verifies that both entities can have the same email
        // The actual unique constraint is enforced by the database
        $this->assertSame('unique@example.com', $contact1->getEmail());
        $this->assertSame('unique@example.com', $contact2->getEmail());
    }

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

    public function testUnsubscribeTokenGeneration(): void
    {
        $contact1 = new GreetingContact();
        $contact2 = new GreetingContact();

        // Tokens should be different for each instance
        $this->assertNotSame(
            $contact1->getUnsubscribeToken(),
            $contact2->getUnsubscribeToken()
        );

        // Token should be a valid hex string
        $token = $contact1->getUnsubscribeToken();
        $this->assertIsString($token);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $token);
    }

    /**
     * @throws \DateMalformedStringException
     */
    public function testDateTimeImmutability(): void
    {
        $contact = new GreetingContact();
        $originalDate = $contact->getCreatedAt();

        // Try to modify the date (should not affect the entity)
        $newDate = $originalDate->modify('+1 day');

        // Assert that the entity's date has not changed
        $this->assertEquals($originalDate, $contact->getCreatedAt());

        // Assert that the new date is different from the entity's date
        $this->assertNotEquals($newDate, $contact->getCreatedAt());
    }

    public function testEnumTypeSafety(): void
    {
        $contact = new GreetingContact();

        // Should accept only GreetingLanguage enum
        $contact->setLanguage(GreetingLanguage::Czech);
        $this->assertSame(GreetingLanguage::Czech, $contact->getLanguage());

        // Should accept only Status enum
        $contact->setStatus(Status::Active);
        $this->assertSame(Status::Active, $contact->getStatus());
    }

    public function testCreatedAtNeverChangesAfterConstruction(): void
    {
        $contact = new GreetingContact();
        $originalCreatedAt = $contact->getCreatedAt();

        // Simulate some time passing
        usleep(1000); // 1ms

        $this->assertEquals($originalCreatedAt, $contact->getCreatedAt());
    }

    public function testEmailNormalization(): void
    {
        $contact = new GreetingContact();

        // Test with different email formats
        $emails = [
            'test@example.com',
            'Test@Example.COM',
            'test.email+tag@example.co.uk',
        ];

        foreach ($emails as $email) {
            $contact->setEmail($email);
            $this->assertSame($email, $contact->getEmail());
        }
    }

    public function testInvalidEmailDoesNotThrowException(): void
    {
        $contact = new GreetingContact();

        // Entity doesn't validate email format, so this should work
        $contact->setEmail('not-an-email');
        $this->assertSame('not-an-email', $contact->getEmail());
    }

    public function testDefaultStatusIsActive(): void
    {
        $contact = new GreetingContact();
        $this->assertTrue($contact->getStatus()->isVisible());
        $this->assertTrue($contact->getStatus()->isEditable());
    }

    public function testDefaultLanguageHasSubjectAndTemplate(): void
    {
        $contact = new GreetingContact();
        $defaultLanguage = $contact->getLanguage();

        $this->assertNotEmpty($defaultLanguage->getSubject());
        $this->assertStringEndsWith('.html.twig', $defaultLanguage->getTemplatePath());
        $this->assertStringContainsString($defaultLanguage->value, $defaultLanguage->getTemplatePath());
    }

    public function testEmailValidation(): void
    {
        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
        $contact = new GreetingContact();

        // Test valid emails
        $contact->setEmail('valid@example.com');
        $violations = $validator->validate($contact);
        $this->assertCount(0, $violations, 'Valid email should have no violations.');

        $contact->setEmail('another.valid@sub.example.co.uk');
        $violations = $validator->validate($contact);
        $this->assertCount(0, $violations, 'Another valid email should have no violations.');

        // Test invalid emails
        $contact->setEmail('invalid-email');
        $violations = $validator->validate($contact);
        $this->assertCount(1, $violations, 'Invalid email should have one violation.');
        /** @var \Symfony\Component\Validator\ConstraintViolationInterface $violation */
        $violation = $violations[0];
        $this->assertEquals('This value is not a valid email address.', $violation->getMessage());

        $contact->setEmail('invalid@');
        $violations = $validator->validate($contact);
        $this->assertCount(1, $violations, 'Invalid email (missing domain) should have one violation.');

        $contact->setEmail('test@.com');
        $violations = $validator->validate($contact);
        $this->assertCount(1, $violations, 'Invalid email (invalid domain) should have one violation.');

        $contact->setEmail(null);
        $violations = $validator->validate($contact);
        $this->assertCount(0, $violations, 'Null email should be valid if nullable.');
    }

    public function testSerialization(): void
    {
        $contact = new GreetingContact();
        $contact->setEmail('test@example.com');
        $contact->setLanguage(GreetingLanguage::English);
        $contact->setStatus(Status::Active);

        // Simulate serialization/deserialization
        $serialized = serialize($contact);
        $unserialized = unserialize($serialized);

        $this->assertInstanceOf(GreetingContact::class, $unserialized);

        $this->assertEquals($contact->getEmail(), $unserialized->getEmail());
        $this->assertEquals($contact->getLanguage(), $unserialized->getLanguage());
        $this->assertEquals($contact->getStatus(), $unserialized->getStatus());
    }

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
     * @return array<string, array{GreetingLanguage, Status}>
     */
    public static function languageAndStatusProvider(): array
    {
        $data = [];
        foreach (GreetingLanguage::cases() as $language) {
            foreach (Status::cases() as $status) {
                $data[sprintf('Language: %s, Status: %s', $language->name, $status->name)] = [$language, $status];
            }
        }

        return $data;
    }
}
