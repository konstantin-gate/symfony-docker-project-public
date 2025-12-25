<?php

declare(strict_types=1);

namespace App\Tests\Greeting\Repository;

use App\Enum\Status;
use App\Greeting\Entity\GreetingContact;
use App\Greeting\Entity\GreetingLog;
use App\Greeting\Enum\GreetingLanguage;
use Doctrine\DBAL\Exception\NotNullConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Random\RandomException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Testovací třída pro GreetingLogRepository.
 * Obsahuje testy pro uložení, načtení a mazání záznamů GreetingLog.
 * Testuje také kaskádové mazání a omezující podmínky.
 */
class GreetingLogRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    /**
     * Inicializuje testovací prostředí.
     * Spustí kernel a získává instance EntityManager pro práci s databází.
     */
    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
    }

    /**
     * Uvolňuje testovací prostředí.
     * Zavírá EntityManager a volá rodičovskou metodu pro správné ukončení testu.
     */
    protected function tearDown(): void
    {
        parent::tearDown();
        $this->entityManager->close();
    }

    /**
     * Testuje uložení a načtení záznamu GreetingLog.
     * Vytváří nový kontakt a záznam, uloží je do databáze a ověřuje jejich správné načtení.
     *
     * @throws RandomException
     * @throws ORMException
     */
    public function testSaveAndFindGreetingLog(): void
    {
        // 1. Vytvoření a uložení GreetingContact
        $email = \sprintf('log_contact_%s@example.com', bin2hex(random_bytes(8)));
        $contact = new GreetingContact();
        $contact->setEmail($email);
        $contact->setLanguage(GreetingLanguage::English);
        $contact->setStatus(Status::Active);

        $this->entityManager->persist($contact);
        $this->entityManager->flush();

        $contactId = $contact->getId();
        $this->assertNotNull($contactId, 'ID kontaktu by mělo být generováno po uložení');

        // 2. Vytvoření a uložení GreetingLog
        $year = (int) (new \DateTimeImmutable())->format('Y');
        $log = new GreetingLog($contact, $year);

        $this->entityManager->persist($log);
        $this->entityManager->flush();

        $logId = $log->getId();
        $this->assertNotNull($logId, 'ID záznamu by mělo být generováno po uložení');

        // Vyčistíme EntityManager pro zajištění načtení z databáze
        $this->entityManager->clear();

        // 3. Načtení GreetingLog
        $retrievedLog = $this->entityManager->getRepository(GreetingLog::class)->find($logId);

        $this->assertNotNull($retrievedLog);
        $retrievedLogId = $retrievedLog->getId();
        $this->assertNotNull($retrievedLogId);
        $this->assertSame($logId->toRfc4122(), $retrievedLogId->toRfc4122());
        $this->assertSame($year, $retrievedLog->getYear());
        $this->assertEqualsWithDelta(
            $log->getSentAt(),
            $retrievedLog->getSentAt(),
            1 // 1 second tolerance
        );

        // 4. Ověření asociovaného GreetingContact
        $retrievedContact = $retrievedLog->getContact();
        $this->assertNotNull($retrievedContact);
        $retrievedContactId = $retrievedContact->getId();
        $this->assertNotNull($retrievedContactId);
        $this->assertSame($contactId->toRfc4122(), $retrievedContactId->toRfc4122());
        $this->assertSame($email, $retrievedContact->getEmail());
    }

    /**
     * Testuje kaskádové mazání záznamů GreetingLog při smazání kontaktu.
     * Vytváří kontakt a záznam, smaže kontakt a ověřuje, že záznam byl také smazán.
     *
     * @throws RandomException
     * @throws ORMException
     */
    public function testCascadeDelete(): void
    {
        // Vytvoření a uložení kontaktu
        $email = \sprintf('cascade_contact_%s@example.com', bin2hex(random_bytes(8)));
        $contact = new GreetingContact();
        $contact->setEmail($email);
        $this->entityManager->persist($contact);

        // Vytvoření a uložení záznamu
        $year = (int) (new \DateTimeImmutable())->format('Y');
        $log = new GreetingLog($contact, $year);
        $this->entityManager->persist($log);
        $this->entityManager->flush();

        $logId = $log->getId();

        // Smazání kontaktu – záznam by měl být smazán kaskádově
        $this->entityManager->remove($contact);
        $this->entityManager->flush();

        // Vyčistíme a ověříme, že záznam byl smazán
        $this->entityManager->clear();
        $retrievedLog = $this->entityManager->getRepository(GreetingLog::class)->find($logId);
        $this->assertNull($retrievedLog, 'Záznam by měl být smazán kaskádově');
    }

    /**
     * Testuje porušení omezující podmínky NOT NULL pro kontakt.
     * Vytváří záznam bez kontaktu a ověřuje, že je vyvolána výjimka.
     *
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function testContactNotNullableViolation(): void
    {
        // Dočasný kontakt pro konstruktor
        $tempContact = new GreetingContact();
        $tempContact->setEmail('temp@example.com'); // Pro potenciální validaci

        $log = new GreetingLog($tempContact, 2025);
        $log->setContact(null); // Porušení omezující podmínky

        $this->entityManager->persist($log);
        $this->expectException(NotNullConstraintViolationException::class);
        $this->entityManager->flush();
    }
}
