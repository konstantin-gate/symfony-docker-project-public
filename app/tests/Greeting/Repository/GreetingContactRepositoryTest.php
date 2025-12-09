<?php

declare(strict_types=1);

namespace App\Tests\Greeting\Repository;

use App\Enum\Status;
use App\Greeting\Entity\GreetingContact;
use App\Greeting\Enum\GreetingLanguage;
use App\Greeting\Repository\GreetingContactRepository;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Random\RandomException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class GreetingContactRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private GreetingContactRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);

        /** @var GreetingContactRepository $repository */
        $repository = $this->entityManager->getRepository(GreetingContact::class);
        $this->repository = $repository;
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->entityManager->close();
    }

    /**
     * @throws RandomException
     * @throws Exception
     */
    public function testSaveAndFind(): void
    {
        $email = \sprintf('test_save_find_%s@example.com', bin2hex(random_bytes(8)));

        $contact = new GreetingContact();
        $contact->setEmail($email);
        $contact->setLanguage(GreetingLanguage::Czech);
        $contact->setStatus(Status::Active);

        $this->entityManager->persist($contact);
        $this->entityManager->flush();

        $id = $contact->getId();
        $this->assertNotNull($id, 'ID should be generated after persist/flush');

        // Clear the entity manager to ensure we retrieve from the database
        $this->entityManager->clear();

        $retrievedContact = $this->repository->find($id);

        $this->assertNotNull($retrievedContact);
        $this->assertSame($email, $retrievedContact->getEmail());
        $this->assertSame(GreetingLanguage::Czech, $retrievedContact->getLanguage());
        $this->assertSame(Status::Active, $retrievedContact->getStatus());
        $this->assertNotNull($retrievedContact->getUnsubscribeToken());

        // Verify raw storage values
        $connection = $this->entityManager->getConnection();
        $rawResult = $connection->fetchAssociative(
            'SELECT language, status FROM greeting_contact WHERE id = ?',
            [(string) $id]
        );

        $this->assertIsArray($rawResult);
        $this->assertSame('cs', $rawResult['language']);
        $this->assertSame('active', $rawResult['status']);
    }

    /**
     * @throws RandomException
     */
    public function testEmailUniqueConstraint(): void
    {
        $email = \sprintf('collision_%s@example.com', bin2hex(random_bytes(8)));

        $contact1 = new GreetingContact();
        $contact1->setEmail($email);
        $this->entityManager->persist($contact1);
        $this->entityManager->flush();

        $contact2 = new GreetingContact();
        $contact2->setEmail($email);
        $this->entityManager->persist($contact2);

        $this->expectException(UniqueConstraintViolationException::class);
        $this->entityManager->flush();
    }
}
