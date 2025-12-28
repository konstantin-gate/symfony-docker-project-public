<?php

declare(strict_types=1);

namespace App\Tests\Greeting\Service;

use App\Greeting\DTO\GreetingImportResult;
use App\Greeting\Enum\GreetingLanguage;
use App\Greeting\Service\GreetingContactService;
use App\Greeting\Service\GreetingEmailParser;
use App\Greeting\Service\GreetingImportHandler;
use App\Greeting\Service\GreetingXmlParser;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Testovací třída pro GreetingImportHandler.
 * Obsahuje testy pro import kontaktů z XML a textového obsahu.
 */
class GreetingImportHandlerTest extends TestCase
{
    private GreetingXmlParser&MockObject $xmlParser;
    private GreetingEmailParser&MockObject $emailParser;
    private GreetingContactService&MockObject $contactService;
    private EntityManagerInterface&MockObject $entityManager;
    private LoggerInterface&MockObject $logger;
    private GreetingImportHandler $handler;

    /**
     * Inicializuje mocky a vytváří instanci GreetingImportHandler pro testování.
     * Tato metoda se volá před každým testem a zajišťuje čisté prostředí.
     */
    protected function setUp(): void
    {
        // Vytvoření mocků všech závislostí
        $this->xmlParser = $this->createMock(GreetingXmlParser::class);
        $this->emailParser = $this->createMock(GreetingEmailParser::class);
        $this->contactService = $this->createMock(GreetingContactService::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        // Inicializace testované třídy s mocky
        $this->handler = new GreetingImportHandler(
            $this->xmlParser,
            $this->emailParser,
            $this->contactService,
            $this->entityManager,
            $this->logger
        );
    }

    /**
     * Generuje e-mailové adresy pro testování streamování XML.
     * Tato metoda slouží jako náhrada za skutečný XML parser a umožňuje testovat zpracování velkých datových sad.
     *
     * @param string[] $emails Seznam e-mailových adres k generování
     * @return \Generator<string> Generator e-mailových adres
     */
    private function mockXmlGenerator(array $emails): \Generator
    {
        // Prochází seznamem e-mailů a generuje je jeden po druhém
        foreach ($emails as $email) {
            yield $email;
        }
    }

    /**
     * Testuje úspěšný import kontaktů z XML souboru.
     * Ověřuje, že handler správně zpracuje XML soubor, uloží kontakty a vrátí úspěšný výsledek.
     */
    public function testImportSuccess(): void
    {
        // Příprava testovacích dat
        $xmlFile = '/tmp/test.xml';
        $emails = ['test@example.com'];

        // Konfigurace mocků
        $this->xmlParser->expects($this->once())
            ->method('parse')
            ->with($xmlFile)
            ->willReturn($this->mockXmlGenerator($emails));

        $this->contactService->expects($this->once())
            ->method('saveContacts')
            ->with($emails, GreetingLanguage::Russian)
            ->willReturn(1);

        $this->entityManager->expects($this->once())->method('clear');

        // Volání testované metody
        $result = $this->handler->handleImport($xmlFile, null);

        // Ověření výsledku
        $this->assertInstanceOf(GreetingImportResult::class, $result);
        $this->assertTrue($result->isSuccess);
        $this->assertEquals(1, $result->count);
        $this->assertNull($result->errorKey);
    }

    /**
     * Testuje import kontaktů pouze z textového obsahu.
     * Ověřuje, že handler správně zpracuje textový obsah, extrahuje e-mailové adresy a uloží je.
     */
    public function testImportFromTextOnly(): void
    {
        // Příprava testovacích dat
        $textContent = 'text@test.com';
        $emails = ['text@test.com'];

        // Konfigurace mocků
        $this->emailParser->expects($this->once())
            ->method('parse')
            ->with($textContent)
            ->willReturn($emails);

        $this->contactService->expects($this->once())
            ->method('saveContacts')
            ->with($emails, GreetingLanguage::Russian)
            ->willReturn(1);

        // Volání testované metody
        $result = $this->handler->handleImport(null, $textContent);

        // Ověření výsledku
        $this->assertTrue($result->isSuccess);
        $this->assertEquals(1, $result->count);
    }

    /**
     * Testuje import kontaktů z obou zdrojů - XML souboru i textového obsahu.
     * Ověřuje, že handler správně zpracuje obě zdroje a uloží kontakty z obou.
     */
    public function testImportFromTextAndXml(): void
    {
        // Příprava testovacích dat
        $xmlFile = '/tmp/test.xml';
        $textContent = 'text@test.com';

        // Konfigurace mocků pro obě zdroje
        $this->xmlParser->method('parse')->willReturn($this->mockXmlGenerator(['xml@test.com']));
        $this->emailParser->method('parse')->willReturn(['text@test.com']);

        // Očekáváme dva volání saveContacts - jedno pro každý zdroj
        $this->contactService->expects($this->exactly(2))
            ->method('saveContacts')
            ->willReturnOnConsecutiveCalls(1, 1);

        $this->entityManager->expects($this->once())->method('clear');

        // Volání testované metody
        $result = $this->handler->handleImport($xmlFile, $textContent);

        // Ověření výsledku
        $this->assertTrue($result->isSuccess);
        $this->assertEquals(2, $result->count);
    }

    /**
     * Testuje, že handler správně předává zadaný jazyk do služby pro uložení kontaktů.
     * Ověřuje, že parametr language je správně předáván a používán při uložení kontaktů.
     */
    public function testPassesCustomLanguageToContactService(): void
    {
        // Příprava testovacích dat
        $xmlFile = '/tmp/test.xml';

        // Konfigurace mocků
        $this->xmlParser->method('parse')->willReturn($this->mockXmlGenerator(['test@example.com']));
        $this->contactService->expects($this->once())
            ->method('saveContacts')
            ->with(['test@example.com'], GreetingLanguage::English)
            ->willReturn(1);

        $this->entityManager->expects($this->once())->method('clear');

        // Volání testované metody s explicitně zadaným jazykem
        $result = $this->handler->handleImport($xmlFile, null, GreetingLanguage::English);

        // Ověření výsledku
        $this->assertTrue($result->isSuccess);
        $this->assertEquals(1, $result->count);
    }

    /**
     * Testuje situaci, kdy parsery nevrací žádné e-mailové adresy.
     * Ověřuje, že handler vrátí úspěšný výsledek s počtem 0, i když nebyly nalezeny žádné kontakty.
     */
    public function testReturnsSuccessZeroWhenParsersReturnEmpty(): void
    {
        // Konfigurace mocků pro prázdné výsledky
        $this->xmlParser->method('parse')->willReturn($this->mockXmlGenerator([]));
        $this->emailParser->method('parse')->willReturn([]);

        // Očekáváme, že saveContacts nebude voláno
        $this->contactService->expects($this->never())->method('saveContacts');

        // Volání testované metody
        $result = $this->handler->handleImport('/tmp/empty.xml', 'some text');

        // Ověření výsledku - úspěch s počtem 0
        $this->assertTrue($result->isSuccess);
        $this->assertEquals(0, $result->count);
    }

    /**
     * Testuje situaci, kdy není poskytnut žádný zdroj dat (ani XML soubor, ani textový obsah).
     * Ověřuje, že handler vrátí chybový výsledek s odpovídajícím kódem chyby.
     */
    public function testImportWithNoDataReturnsError(): void
    {
        // Očekáváme, že parsery nebude voláno
        $this->xmlParser->expects($this->never())->method('parse');
        $this->emailParser->expects($this->never())->method('parse');
        $this->contactService->expects($this->never())->method('saveContacts');

        // Volání testované metody bez dat
        $result = $this->handler->handleImport(null, null);

        // Ověření chybového výsledku
        $this->assertFalse($result->isSuccess);
        $this->assertEquals('import.error_no_data', $result->errorKey);
    }

    /**
     * Testuje zpracování chybného XML souboru.
     * Ověřuje, že handler správně zachytí výjimku při parsování XML a vrátí chybový výsledek.
     */
    public function testImportReturnsErrorOnInvalidXml(): void
    {
        // Příprava testovacích dat
        $xmlFile = '/tmp/invalid.xml';

        // Konfigurace mocků pro vyvolání výjimky
        $this->xmlParser->expects($this->once())
            ->method('parse')
            ->willThrowException(new \RuntimeException('Syntax error'));

        // Očekáváme, že bude zapsána chybová zpráva do loggeru
        $this->logger->expects($this->once())
            ->method('error')
            ->with(
                $this->stringContains('XML parsing/saving failed'),
                $this->arrayHasKey('file')
            );

        // Volání testované metody
        $result = $this->handler->handleImport($xmlFile, null);

        // Ověření chybového výsledku
        $this->assertFalse($result->isSuccess);
        $this->assertEquals('import.error_xml_parsing', $result->errorKey);
    }

    /**
     * Testuje zpracování velkého XML souboru v dávkách.
     * Ověřuje, že handler správně rozděluje data do dávkových volání a správně počítá celkový počet kontaktů.
     */
    public function testBatchProcessingCallsSaveContactsMultipleTimes(): void
    {
        // Příprava testovacích dat
        $xmlFile = '/tmp/large.xml';

        // Generování 1001 e-mailových adres (velikost dávky je 500)
        $emails = [];
        for ($i = 0; $i < 1001; ++$i) {
            $emails[] = "user{$i}@example.com";
        }

        // Konfigurace mocků
        $this->xmlParser->method('parse')->willReturn($this->mockXmlGenerator($emails));

        // Očekáváme tři volání saveContacts (500 + 500 + 1)
        $this->contactService->expects($this->exactly(3))
            ->method('saveContacts')
            ->willReturnOnConsecutiveCalls(500, 500, 1);

        // Očekáváme tři volání clear pro vyčištění identity map
        $this->entityManager->expects($this->exactly(3))->method('clear');

        // Volání testované metody
        $result = $this->handler->handleImport($xmlFile, null);

        // Ověření celkového počtu kontaktů
        $this->assertEquals(1001, $result->count);
    }
}
