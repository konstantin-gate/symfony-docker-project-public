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
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
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
     * @throws ORMException
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
     * @throws ORMException
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

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function testFindAllActiveGroupedByLanguage(): void
    {
        // 1. Prepare data
        // Using unique prefix to avoid collision with other tests if DB is shared
        $prefix = uniqid('group_test_', true);

        $contactsData = [
            ['email' => "{$prefix}_en_active@test.com", 'lang' => GreetingLanguage::English, 'status' => Status::Active],
            ['email' => "{$prefix}_ru_active_2@test.com", 'lang' => GreetingLanguage::Russian, 'status' => Status::Active],
            ['email' => "{$prefix}_ru_active_1@test.com", 'lang' => GreetingLanguage::Russian, 'status' => Status::Active], // Alphabetically first
            ['email' => "{$prefix}_cs_inactive@test.com", 'lang' => GreetingLanguage::Czech, 'status' => Status::Inactive],
            ['email' => "{$prefix}_en_deleted@test.com", 'lang' => GreetingLanguage::English, 'status' => Status::Deleted],
        ];

        foreach ($contactsData as $data) {
            $c = new GreetingContact();
            $c->setEmail($data['email']);
            $c->setLanguage($data['lang']);
            $c->setStatus($data['status']);
            $this->entityManager->persist($c);
        }
        $this->entityManager->flush();
        $this->entityManager->clear();

        // 2. Execute
        $grouped = $this->repository->findAllActiveGroupedByLanguage();

        // 3. Assert keys exist
        $this->assertArrayHasKey(GreetingLanguage::English->value, $grouped);
        $this->assertArrayHasKey(GreetingLanguage::Russian->value, $grouped);
        $this->assertArrayHasKey(GreetingLanguage::Czech->value, $grouped);

        // 4. Assert contents
        // Filter result to only include our test contacts (in case DB is not clean)
        $filterByPrefix = static fn (array $group) => array_values(array_filter(
            $group,
            static fn (GreetingContact $c) => str_starts_with((string) $c->getEmail(), $prefix)
        ));

        $enGroup = $filterByPrefix($grouped[GreetingLanguage::English->value]);
        $ruGroup = $filterByPrefix($grouped[GreetingLanguage::Russian->value]);
        $csGroup = $filterByPrefix($grouped[GreetingLanguage::Czech->value]);

        // English: 1 active
        $this->assertCount(1, $enGroup);
        $this->assertEquals("{$prefix}_en_active@test.com", $enGroup[0]->getEmail());

        // Czech: 0 active (was inactive)
        $this->assertCount(0, $csGroup);

        // Russian: 2 active
        $this->assertCount(2, $ruGroup);

        // Check Sorting: ru_active_1 should be before ru_active_2
        $this->assertEquals("{$prefix}_ru_active_1@test.com", $ruGroup[0]->getEmail());
        $this->assertEquals("{$prefix}_ru_active_2@test.com", $ruGroup[1]->getEmail());
    }
}
