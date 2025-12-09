<?php

declare(strict_types=1);

namespace App\Tests\Greeting\Repository;

use App\Enum\Status;
use App\Greeting\Entity\GreetingContact;
use App\Greeting\Entity\GreetingLog;
use App\Greeting\Enum\GreetingLanguage;
use Doctrine\DBAL\Exception\NotNullConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Random\RandomException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class GreetingLogRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->entityManager->close();
    }

    /**
     * @throws RandomException
     */
    public function testSaveAndFindGreetingLog(): void
    {
        // 1. Create and persist a GreetingContact first
        $email = \sprintf('log_contact_%s@example.com', bin2hex(random_bytes(8)));
        $contact = new GreetingContact();
        $contact->setEmail($email);
        $contact->setLanguage(GreetingLanguage::English);
        $contact->setStatus(Status::Active);

        $this->entityManager->persist($contact);
        $this->entityManager->flush();

        $contactId = $contact->getId();
        $this->assertNotNull($contactId, 'Contact ID should be generated after persist/flush');

        // 2. Create and persist a GreetingLog
        $year = (int) (new \DateTimeImmutable())->format('Y');
        $log = new GreetingLog($contact, $year);

        $this->entityManager->persist($log);
        $this->entityManager->flush();

        $logId = $log->getId();
        $this->assertNotNull($logId, 'Log ID should be generated after persist/flush');

        // Clear the entity manager to ensure we retrieve from the database
        $this->entityManager->clear();

        // 3. Retrieve the GreetingLog
        $retrievedLog = $this->entityManager->getRepository(GreetingLog::class)->find($logId);

        $this->assertNotNull($retrievedLog);
        // @phpstan-ignore-next-line
        $this->assertSame($logId->toRfc4122(), $retrievedLog->getId()->toRfc4122());
        $this->assertSame($year, $retrievedLog->getYear());
        $this->assertEqualsWithDelta(
            $log->getSentAt(),
            $retrievedLog->getSentAt(),
            1 // 1 second tolerance
        );

        // 4. Verify the associated GreetingContact
        $retrievedContact = $retrievedLog->getContact();
        $this->assertNotNull($retrievedContact);
        // @phpstan-ignore-next-line
        $this->assertSame($contactId->toRfc4122(), $retrievedContact->getId()->toRfc4122());
        $this->assertSame($email, $retrievedContact->getEmail());
    }

    /**
     * @throws RandomException
     */
    public function testCascadeDelete(): void
    {
        // Create and persist contact
        $email = \sprintf('cascade_contact_%s@example.com', bin2hex(random_bytes(8)));
        $contact = new GreetingContact();
        $contact->setEmail($email);
        $this->entityManager->persist($contact);

        // Create and persist log
        $year = (int) (new \DateTimeImmutable())->format('Y');
        $log = new GreetingLog($contact, $year);
        $this->entityManager->persist($log);
        $this->entityManager->flush();

        $logId = $log->getId();

        // Remove contact and flush – log should be deleted
        $this->entityManager->remove($contact);
        $this->entityManager->flush();

        // Clear and check log is gone
        $this->entityManager->clear();
        $retrievedLog = $this->entityManager->getRepository(GreetingLog::class)->find($logId);
        $this->assertNull($retrievedLog, 'Log should be deleted via CASCADE');
    }

    public function testContactNotNullableViolation(): void
    {
        // Temp contact for constructor
        $tempContact = new GreetingContact();
        $tempContact->setEmail('temp@example.com'); // To pass potential validation

        $log = new GreetingLog($tempContact, 2025);
        $log->setContact(null); // Violate constraint

        $this->entityManager->persist($log);
        $this->expectException(NotNullConstraintViolationException::class);
        $this->entityManager->flush();
    }
}
