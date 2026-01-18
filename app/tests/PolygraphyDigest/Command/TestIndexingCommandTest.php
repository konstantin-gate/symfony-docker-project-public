<?php

declare(strict_types=1);

namespace App\Tests\PolygraphyDigest\Command;

use App\PolygraphyDigest\Command\TestIndexingCommand;
use App\PolygraphyDigest\Entity\Article;
use App\PolygraphyDigest\Enum\ArticleStatusEnum;
use App\PolygraphyDigest\Service\Search\SearchIndexer;
use Elastic\Elasticsearch\Exception\ClientResponseException;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Http\Message\ResponseInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Testuje příkaz pro testovací indexaci do Elasticsearch.
 * Zajišťuje, že se správně vytvářejí testovací data a volá se indexer.
 */
class TestIndexingCommandTest extends KernelTestCase
{
    private MockObject&SearchIndexer $searchIndexer;
    private CommandTester $commandTester;

    /**
     * Nastavuje testovací prostředí, vytváří mock indexeru a připravuje CommandTester.
     */
    protected function setUp(): void
    {
        $this->searchIndexer = $this->createMock(SearchIndexer::class);
        $command = new TestIndexingCommand($this->searchIndexer);

        $this->commandTester = new CommandTester($command);
    }

    /**
     * Ověřuje úspěšný průběh indexace testovacího článku (Scenario 1).
     * Kontroluje, zda jsou všechna data v entitě Article správně nastavena a předána indexeru.
     */
    public function testExecuteSuccess(): void
    {
        $this->searchIndexer->expects($this->once())
            ->method('indexArticle')
            ->with($this->callback(function (Article $article) {
                // Kontrola dat podle plánu
                return $article->getTitle() === 'Testovací článek o moderní polygrafii'
                    && $article->getStatus() === ArticleStatusEnum::PROCESSED
                    && $article->getSource()?->getName() === 'Testovací Zdroj'
                    && $article->getId() !== null // Ověření reflexe
                    && str_starts_with($article->getUrl(), 'https://example.com/test-article-')
                    && $article->getPublishedAt() instanceof \DateTimeImmutable;
            }));

        $this->commandTester->execute([]);

        $output = $this->commandTester->getDisplay();

        // Ověření výstupu
        $this->assertStringContainsString('Testovací indexace do Elasticsearch', $output);
        $this->assertStringContainsString('Článek byl úspěšně odeslán do Elasticsearch.', $output);
        $this->assertStringContainsString('Můžete ověřit existenci dokumentu v indexu polygraphy_articles.', $output);

        // Ověření návratového kódu
        $this->assertSame(0, $this->commandTester->getStatusCode());
    }

    /**
     * Ověřuje zpracování výjimek při selhání indexace (Scenario 2).
     * Kontroluje, zda příkaz správně zachytí chybu, vypíše chybové hlášení a vrátí FAILURE (1).
     */
    public function testExecuteFailure(): void
    {
        $errorMessage = 'Elasticsearch is unreachable';
        
        $this->searchIndexer->expects($this->once())
            ->method('indexArticle')
            ->willThrowException(new \RuntimeException($errorMessage));

        $this->commandTester->execute([]);

        $output = $this->commandTester->getDisplay();

        // Ověření výstupu
        $this->assertStringContainsString('Chyba při testovací indexaci: ' . $errorMessage, $output);
        
        // Ověření návratového kódu (Command::FAILURE = 1)
        $this->assertSame(1, $this->commandTester->getStatusCode());
    }

    /**
     * Ověřuje zpracování specifických výjimek z Elasticsearch klienta (Scenario 3).
     * Simuluje chybu odpovědi serveru a ověřuje, že ji příkaz správně zachytí a vypíše.
     */
    public function testExecuteElasticClientException(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        
        // Simulace chyby odpovědi od Elasticu (4xx chyba)
        $exception = new ClientResponseException('400 Bad Request');
        $exception->setResponse($response);

        $this->searchIndexer->expects($this->once())
            ->method('indexArticle')
            ->willThrowException($exception);

        $this->commandTester->execute([]);

        $output = $this->commandTester->getDisplay();

        // Ověření, že výstup obsahuje informaci o chybě
        $this->assertStringContainsString('Chyba při testovací indexaci: 400 Bad Request', $output);
        $this->assertSame(1, $this->commandTester->getStatusCode());
    }

    /**
     * Ověřuje, že generované URL pro články jsou unikátní při opakovaném spuštění příkazu.
     * Zajišťuje, že random_bytes() správně generuje náhodnou část URL.
     */
    public function testUrlUniqueness(): void
    {
        $urls = [];
        $this->searchIndexer->expects($this->exactly(2))
            ->method('indexArticle')
            ->with($this->callback(function (Article $article) use (&$urls) {
                $urls[] = $article->getUrl();
                return true;
            }));

        $this->commandTester->execute([]);
        $this->commandTester->execute([]);

        $this->assertCount(2, $urls);
        $this->assertNotEquals($urls[0], $urls[1]);
    }

    /**
     * Ověřuje integritu dat v entitě Article, zejména zachování HTML tagů v obsahu
     * a úspěšné nastavení UUID pomocí reflexe.
     */
    public function testDataIntegrityAndReflection(): void
    {
        $this->searchIndexer->expects($this->once())
            ->method('indexArticle')
            ->with($this->callback(function (Article $article) {
                // Kontrola HTML tagů v obsahu (nesmí být odstraněny v této fázi)
                $hasHtml = str_contains($article->getContent(), '<strong>testovací obsah</strong>');
                
                // Kontrola nastavení UUID (id nesmí být null)
                $hasUuid = $article->getId() !== null;

                return $hasHtml && $hasUuid;
            }));

        $this->commandTester->execute([]);
        
        $this->assertSame(0, $this->commandTester->getStatusCode());
    }
}
