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

/**
 * Testovací třída pro GreetingContactRepository.
 * Testuje funkčnost uložení, načítání a vyhledávání kontaktů.
 */
class GreetingContactRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private GreetingContactRepository $repository;

    /**
     * Inicializuje testovací prostředí.
     * Načte jádro Symfony a připraví entity manager a repository pro testování.
     */
    protected function setUp(): void
    {
        self::bootKernel();

        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);

        /** @var GreetingContactRepository $repository */
        $repository = $this->entityManager->getRepository(GreetingContact::class);
        $this->repository = $repository;
    }

    /**
     * Uvolňuje testovací prostředí.
     * Zavře entity manager po dokončení testu.
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        $this->entityManager->close();
    }

    /**
     * Testuje uložení a načtení kontaktu.
     * Vytvoří nový kontakt, uloží ho do databáze a ověří, že byl správně uložen a lze ho načíst.
     *
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

        // Vyčistíme entity manager, aby se kontakt načítal přímo z databáze
        $this->entityManager->clear();

        $retrievedContact = $this->repository->find($id);

        $this->assertNotNull($retrievedContact);
        $this->assertSame($email, $retrievedContact->getEmail());
        $this->assertSame(GreetingLanguage::Czech, $retrievedContact->getLanguage());
        $this->assertSame(Status::Active, $retrievedContact->getStatus());
        $this->assertNotNull($retrievedContact->getUnsubscribeToken());

        // Ověříme surová data uložená v databázi
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
     * Testuje omezující podmínku pro jedinečnost e-mailu.
     * Pokusí se uložit dva kontakty se stejným e-mailovým adresou a ověří, že je vyvolána výjimka pro porušení jedinečnosti.
     *
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
     * Testuje vyhledání všech aktivních kontaktů seskupených podle jazyka.
     * Vytváří testovací data s různými jazyky a stavy, volá metodu findAllActiveGroupedByLanguage()
     * a ověřuje, že jsou správně seskupeny a seřazeny podle e-mailu.
     *
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function testFindAllActiveGroupedByLanguage(): void
    {
        // 1. Připravíme testovací data
        // Použijeme jedinečný prefix pro vyhnutí kolize s jinými testy
        $prefix = uniqid('group_test_', true);

        $contactsData = [
            ['email' => "{$prefix}_en_active@test.com", 'lang' => GreetingLanguage::English, 'status' => Status::Active],
            ['email' => "{$prefix}_ru_active_2@test.com", 'lang' => GreetingLanguage::Russian, 'status' => Status::Active],
            ['email' => "{$prefix}_ru_active_1@test.com", 'lang' => GreetingLanguage::Russian, 'status' => Status::Active], // Abecedně první
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

        // 2. Vykonáme metodu
        $grouped = $this->repository->findAllActiveGroupedByLanguage();

        // 3. Ověříme existenci klíčů
        $this->assertArrayHasKey(GreetingLanguage::English->value, $grouped);
        $this->assertArrayHasKey(GreetingLanguage::Russian->value, $grouped);
        $this->assertArrayHasKey(GreetingLanguage::Czech->value, $grouped);

        // 4. Ověříme obsah
        // Filtrujeme výsledek, aby obsahoval pouze naše testovací kontakty
        $filterByPrefix = static fn (array $group) => array_values(array_filter(
            $group,
            static fn (GreetingContact $c) => str_starts_with((string) $c->getEmail(), $prefix)
        ));

        $enGroup = $filterByPrefix($grouped[GreetingLanguage::English->value]);
        $ruGroup = $filterByPrefix($grouped[GreetingLanguage::Russian->value]);
        $csGroup = $filterByPrefix($grouped[GreetingLanguage::Czech->value]);

        // Angličtina: 1 aktivní
        $this->assertCount(1, $enGroup);
        $this->assertEquals("{$prefix}_en_active@test.com", $enGroup[0]->getEmail());

        // Čeština: 0 aktivních (byla neaktivní)
        $this->assertCount(0, $csGroup);

        // Rusky: 2 aktivní
        $this->assertCount(2, $ruGroup);

        // Ověříme řazení: ru_active_1 by mělo být před ru_active_2
        $this->assertEquals("{$prefix}_ru_active_1@test.com", $ruGroup[0]->getEmail());
        $this->assertEquals("{$prefix}_ru_active_2@test.com", $ruGroup[1]->getEmail());
    }
}
