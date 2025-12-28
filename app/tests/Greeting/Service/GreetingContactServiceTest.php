<?php

declare(strict_types=1);

namespace App\Tests\Greeting\Service;

use App\Enum\Status;
use App\Greeting\Entity\GreetingContact;
use App\Greeting\Enum\GreetingLanguage;
use App\Greeting\Exception\ContactAlreadyDeletedException;
use App\Greeting\Exception\ContactAlreadyInactiveException;
use App\Greeting\Factory\GreetingContactFactory;
use App\Greeting\Repository\GreetingContactRepository;
use App\Greeting\Service\GreetingContactService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Testovací třída pro GreetingContactService.
 * Obsahuje testy pro metody saveContacts, delete a deactivate.
 */
class GreetingContactServiceTest extends TestCase
{
    private GreetingContactRepository&MockObject $repository;
    private GreetingContactFactory&MockObject $factory;
    private EntityManagerInterface&MockObject $entityManager;
    private LoggerInterface&MockObject $logger;
    private GreetingContactService $service;

    /**
     * Inicializuje mocky závislostí a vytváří instanci GreetingContactService pro testování.
     */
    protected function setUp(): void
    {
        // Vytvoření mocků všech závislostí služby
        $this->repository = $this->createMock(GreetingContactRepository::class);
        $this->factory = $this->createMock(GreetingContactFactory::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        // Inicializace testované služby
        $this->service = new GreetingContactService(
            $this->repository,
            $this->factory,
            $this->entityManager,
            $this->logger
        );
    }

    /**
     * Testuje, že metoda delete změní status kontaktu na Deleted.
     * Ověřuje, že je voláno flush() a logování.
     */
    public function testDeleteChangesStatusToDeleted(): void
    {
        // Příprava testovacího kontaktu s aktivním statusem
        $contact = new GreetingContact();
        $contact->setEmail('test@example.com');
        $contact->setStatus(Status::Active);

        // Očekávání volání metody flush a logování
        $this->entityManager->expects($this->once())->method('flush');
        $this->logger->expects($this->once())->method('info');

        // Volání testované metody
        $this->service->delete($contact);

        // Ověření, že status byl změněn na Deleted
        $this->assertEquals(Status::Deleted, $contact->getStatus());
    }

    /**
     * Testuje, že metoda delete vyhodí výjimku, pokud je kontakt již smazán.
     * Ověřuje, že není voláno flush() a logování.
     */
    public function testDeleteThrowsExceptionIfAlreadyDeleted(): void
    {
        // Příprava kontaktu se statusem Deleted
        $contact = new GreetingContact();
        $contact->setStatus(Status::Deleted);

        // Očekávání výjimky a absence volání flush
        $this->expectException(ContactAlreadyDeletedException::class);
        $this->entityManager->expects($this->never())->method('flush');

        // Volání metody, která by měla vyhodit výjimku
        $this->service->delete($contact);
    }

    /**
     * Testuje, že metoda deactivate změní status kontaktu na Inactive.
     * Ověřuje, že je voláno flush() a logování.
     */
    public function testDeactivateChangesStatusToInactive(): void
    {
        // Příprava testovacího kontaktu s aktivním statusem
        $contact = new GreetingContact();
        $contact->setEmail('test@example.com');
        $contact->setStatus(Status::Active);

        // Očekávání volání metody flush a logování
        $this->entityManager->expects($this->once())->method('flush');
        $this->logger->expects($this->once())->method('info');

        // Volání testované metody
        $this->service->deactivate($contact);

        // Ověření, že status byl změněn na Inactive
        $this->assertEquals(Status::Inactive, $contact->getStatus());
    }

    /**
     * Testuje, že metoda deactivate vyhodí výjimku, pokud je kontakt již neaktivní.
     * Ověřuje, že není voláno flush() a logování.
     */
    public function testDeactivateThrowsExceptionIfAlreadyInactive(): void
    {
        // Příprava kontaktu se statusem Inactive
        $contact = new GreetingContact();
        $contact->setStatus(Status::Inactive);

        // Očekávání výjimky a absence volání flush
        $this->expectException(ContactAlreadyInactiveException::class);
        $this->entityManager->expects($this->never())->method('flush');

        // Volání metody, která by měla vyhodit výjimku
        $this->service->deactivate($contact);
    }

    /**
     * Testuje, že metoda deactivate vyhodí výjimku, pokud je kontakt smazán.
     * Ověřuje, že není voláno flush() a logování.
     */
    public function testDeactivateThrowsExceptionIfDeleted(): void
    {
        // Příprava kontaktu se statusem Deleted
        $contact = new GreetingContact();
        $contact->setStatus(Status::Deleted);

        // Očekávání výjimky a absence volání flush
        $this->expectException(ContactAlreadyInactiveException::class);
        $this->entityManager->expects($this->never())->method('flush');

        // Volání metody, která by měla vyhodit výjimku
        $this->service->deactivate($contact);
    }

    /**
     * Testuje uložení nových kontaktů bez duplikátů.
     * Ověřuje, že jsou vytvořeny nové entity a uloženy do databáze.
     */
    public function testSaveUniqueContacts(): void
    {
        // Seznam e-mailů pro testování
        $emails = ['test1@example.com', 'test2@example.com', 'test3@example.com'];

        // Očekává se volání metody findNonExistingEmails s všemi e-maily (všechny jsou nové)
        $this->repository->expects($this->once())
            ->method('findNonExistingEmails')
            ->willReturn($emails);

        // Očekává se vytvoření tří kontaktů
        $this->factory->expects($this->exactly(3))
            ->method('create')
            ->willReturn(new GreetingContact());

        // Očekává se uložení tří entit
        $this->entityManager->expects($this->exactly(3))
            ->method('persist');

        // Očekává se jedno volání flush
        $this->entityManager->expects($this->once())
            ->method('flush');

        // Volání testované metody
        $count = $this->service->saveContacts($emails);

        // Ověření, že bylo uloženo 3 kontakty
        $this->assertEquals(3, $count);
    }

    /**
     * Testuje filtrování již existujících kontaktů z databáze.
     * Ověřuje, že jsou uloženy pouze nové kontakty.
     */
    public function testFiltersExistingContactsFromDatabase(): void
    {
        // Seznam e-mailů, kde jeden již existuje
        $emails = ['new@test.com', 'existing@test.com'];

        // Mock: pouze 'new@test.com' je vrácen jako neexistující
        $this->repository->expects($this->once())
            ->method('findNonExistingEmails')
            ->willReturn(['new@test.com']);

        // Očekává se vytvoření jednoho kontaktu
        $this->factory->expects($this->once())
            ->method('create')
            ->with('new@test.com')
            ->willReturn(new GreetingContact());

        // Očekává se uložení jedné entity
        $this->entityManager->expects($this->once())->method('persist');

        // Očekává se jedno volání flush
        $this->entityManager->expects($this->once())->method('flush');

        // Volání testované metody
        $count = $this->service->saveContacts($emails);

        // Ověření, že byl uložen pouze 1 kontakt
        $this->assertEquals(1, $count);
    }

    /**
     * Testuje, že služba odstraní duplikáty z vstupního seznamu e-mailů.
     * Ověřuje, že je volána metoda findNonExistingEmails pouze s jedním e-mailem.
     */
    public function testFiltersDuplicateEmailsInInput(): void
    {
        // Seznam e-mailů s duplikátem
        $emails = ['double@test.com', 'double@test.com'];

        // Služba odstraní duplikáty před voláním repozitáře
        // Předá repozitáři pouze unikátní seznam
        $this->repository->expects($this->once())
            ->method('findNonExistingEmails')
            ->with($this->callback(fn ($args) => \count($args) === 1 && $args[0] === 'double@test.com'))
            ->willReturn(['double@test.com']);

        $this->factory->expects($this->once())->method('create')->willReturn(new GreetingContact());
        $this->entityManager->expects($this->once())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        $count = $this->service->saveContacts($emails);
        $this->assertEquals(1, $count);
    }

    /**
     * Testuje, že služba správně zpracovává e-maily s různou velikostí písmen.
     * Ověřuje, že jsou považovány za duplikáty a není uložen více kontaktů.
     */
    public function testHandleCaseInsensitiveEmails(): void
    {
        // Seznam e-mailů s různou velikostí písmen
        $emails = ['UPPER@test.com', 'upper@test.com'];

        // Služba odstraní duplikáty bez ohledu na velikost písmen
        // 'UPPER@test.com' je zachováno jako reprezentant klíče
        // Implementace zachovává první nalezený e-mail:
        // if (!isset($uniqueEmailsMap[$lower])) { $uniqueEmailsMap[$lower] = $cleaned; }
        // Takže 'UPPER@test.com' bude předáno repozitáři

        $this->repository->expects($this->once())
            ->method('findNonExistingEmails')
            ->with(['UPPER@test.com'])
            ->willReturn(['UPPER@test.com']);

        $this->factory->expects($this->once())->method('create')->willReturn(new GreetingContact());
        $this->entityManager->expects($this->once())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        $count = $this->service->saveContacts($emails);
        $this->assertEquals(1, $count);
    }

    /**
     * Testuje, že služba vrátí 0 při prázdném vstupním seznamu.
     * Ověřuje, že nejsou volány žádné metody repozitáře nebo entity manageru.
     */
    public function testReturnsZeroOnEmptyInput(): void
    {
        // Očekává se, že nebudou volány žádné metody
        $this->repository->expects($this->never())->method('findNonExistingEmails');
        $this->factory->expects($this->never())->method('create');
        $this->entityManager->expects($this->never())->method('persist');
        $this->entityManager->expects($this->never())->method('flush');

        // Volání metody s prázdným seznamem
        $count = $this->service->saveContacts([]);
        $this->assertEquals(0, $count);
    }

    /**
     * Testuje komplexní scénář importu s duplikáty a již existujícími kontakty.
     * Ověřuje, že jsou správně filtrovány duplikáty a existující kontakty.
     */
    public function testComplexImportScenario(): void
    {
        // Vstupní data:
        $emails = [
            'New@test.com',
            'new@test.com',       // Duplikát prvního
            'EXISTING@test.com',   // Již v DB
            'another-new@test.com',
        ];

        // Logika služby:
        // 1. Odstraní duplikáty -> ['New@test.com', 'EXISTING@test.com', 'another-new@test.com']

        // Mock repozitáře:
        // Vrátí pouze ty, které nejsou v DB
        // 'EXISTING@test.com' by mělo být repozitářem vyfiltrováno
        $this->repository->expects($this->once())
            ->method('findNonExistingEmails')
            ->willReturn(['New@test.com', 'another-new@test.com']);

        // Factory bude volána dvakrát
        $this->factory->expects($this->exactly(2))
            ->method('create')
            ->with($this->callback(fn ($email) => \in_array($email, ['New@test.com', 'another-new@test.com'])))
            ->willReturn(new GreetingContact());

        $this->entityManager->expects($this->exactly(2))->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        $count = $this->service->saveContacts($emails);
        $this->assertEquals(2, $count);
    }

    /**
     * Testuje, že metoda saveContacts používá výchozí jazyk (ruština) při vytváření kontaktů.
     * Ověřuje, že je používána stejná instance DateTimeImmutable pro všechny kontakty v batchu.
     */
    public function testFactoryParametersWithDefaultLanguage(): void
    {
        // Seznam e-mailů pro testování
        $emails = ['test1@example.com', 'test2@example.com'];
        $this->repository->method('findNonExistingEmails')->willReturn($emails);

        // Seznam pro zachycení instancí DateTime
        $capturedDates = [];
        $this->factory->expects($this->exactly(2))
            ->method('create')
            ->willReturnCallback(function (string $email, GreetingLanguage $language, \DateTimeImmutable $date) use (&$capturedDates) {
                // Ověření, že je používán výchozí jazyk (ruština)
                $this->assertEquals(GreetingLanguage::Russian, $language);
                $capturedDates[] = $date;

                return new GreetingContact();
            });

        // Volání testované metody
        $this->service->saveContacts($emails);

        // Ověření, že byly zachyceny dvě instance DateTime
        $this->assertCount(2, $capturedDates);
        // Ověření, že je používána stejná instance DateTime pro všechny kontakty v batchu
        $this->assertSame($capturedDates[0], $capturedDates[1], 'The same DateTime instance should be used for all contacts in a batch');
    }

    /**
     * Testuje, že metoda saveContacts používá explicitně zadaný jazyk při vytváření kontaktů.
     * Ověřuje, že je jazyk správně předáván factory.
     */
    public function testFactoryParametersWithExplicitLanguage(): void
    {
        // Seznam e-mailů pro testování
        $emails = ['test@example.com'];
        $this->repository->method('findNonExistingEmails')->willReturn($emails);

        // Očekává se volání factory s explicitně zadaným jazykem (angličtina)
        $this->factory->expects($this->once())
            ->method('create')
            ->with(
                'test@example.com',
                GreetingLanguage::English,
                $this->isInstanceOf(\DateTimeImmutable::class)
            )
            ->willReturn(new GreetingContact());

        // Volání testované metody s explicitně zadaným jazykem
        $this->service->saveContacts($emails, GreetingLanguage::English);
    }

    /**
     * Testuje, že metoda saveContacts filtruje prázdné e-maily.
     * Ověřuje, že nejsou volány žádné metody repozitáře nebo factory.
     */
    public function testFiltersEmptyEmails(): void
    {
        // Seznam e-mailů s prázdnými hodnotami
        $emails = ['', '   ', "\n"];

        // Očekává se, že nebudou volány žádné metody
        $this->repository->expects($this->never())->method('findNonExistingEmails');
        $this->factory->expects($this->never())->method('create');
        $this->entityManager->expects($this->never())->method('flush');

        // Volání testované metody
        $count = $this->service->saveContacts($emails);

        // Ověření, že bylo vráceno 0
        $this->assertEquals(0, $count);
    }

    /**
     * Testuje výkon metody saveContacts s velkým množstvím kontaktů.
     * Ověřuje, že metoda zpracuje 1000 kontaktů rychleji než 1 sekunda.
     */
    public function testPerformanceWithLargeBatch(): void
    {
        // Velikost batchu pro testování
        $batchSize = 1000;
        // Vytvoření seznamu e-mailů
        $emails = array_map(static fn ($i) => "user$i@example.com", range(1, $batchSize));

        // Očekává se jedno volání s velkým polem
        $this->repository->expects($this->once())
            ->method('findNonExistingEmails')
            ->with($this->callback(fn ($args) => \count($args) === $batchSize))
            ->willReturn($emails); // Všechny jsou nové

        // Očekává se 1000 volání persist
        $this->entityManager->expects($this->exactly($batchSize))
            ->method('persist');

        // Očekává se jedno volání flush
        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->factory->method('create')->willReturn(new GreetingContact());

        // Měření času zpracování
        $startTime = microtime(true);
        $count = $this->service->saveContacts($emails);
        $duration = microtime(true) - $startTime;

        // Ověření, že bylo zpracováno správné množství kontaktů
        $this->assertEquals($batchSize, $count);
        // Ověření, že zpracování trvalo méně než 1 sekunda
        $this->assertLessThan(1.0, $duration, "Processing $batchSize items took too long: {$duration}s");
    }

    /**
     * Testuje, že metoda saveContacts správně propaguje výjimky při chybě při flushování.
     * Ověřuje, že výjimka z databáze je správně předána volajícímu.
     */
    public function testPropagatesExceptionOnFlushError(): void
    {
        // Seznam e-mailů pro testování chyby
        $emails = ['error@test.com'];
        $this->repository->method('findNonExistingEmails')->willReturn($emails);
        $this->factory->method('create')->willReturn(new GreetingContact());

        // Mock chyby při flushování
        $this->entityManager->expects($this->once())
            ->method('flush')
            ->willThrowException(new \RuntimeException('Database integrity violation'));

        // Očekává se výjimka RuntimeException s konkrétní zprávou
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Database integrity violation');

        // Volání metody, která by měla vyhodit výjimku
        $this->service->saveContacts($emails);
    }

    /**
     * Testuje reprodukci problému s citlivostí na velikost písmen v e-mailech.
     * Ověřuje, že služba správně detekuje duplikáty s různou velikostí písmen.
     */
    public function testCaseSensitivityIssueReproduction(): void
    {
        // 1. Simulace existujícího e-mailu v DB (velká písmena)
        $existingEmailInDb = 'Choteticka1@seznam.cz';
        // 2. Pokus o import stejného e-mailu (malá písmena)
        $newEmailToImport = 'choteticka1@seznam.cz';

        // Metoda findNonExistingEmails by měla používat logiku LOWER()
        // Pokud 'Choteticka1@seznam.cz' je v DB, dotaz na 'choteticka1@seznam.cz' by měl vrátit ho jako existující
        // Proto by findNonExistingEmails mělo vrátit prázdné pole (žádné nové e-maily nenalezeny)

        $this->repository->expects($this->once())
            ->method('findNonExistingEmails')
            ->with([$newEmailToImport])
            ->willReturn([]); // Prázdný seznam nových e-mailů -> znamená, že byl nalezen duplikát

        $count = $this->service->saveContacts([$newEmailToImport]);

        // Očekává se 0, protože služba by měla vyfiltrovat existující e-mail
        $this->assertEquals(0, $count, 'Service failed to detect duplicate email with different case.');
    }
}
