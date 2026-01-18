<?php

declare(strict_types=1);

namespace App\Tests\PolygraphyDigest\Command;

use App\PolygraphyDigest\Command\ReindexArticlesCommand;
use App\PolygraphyDigest\Entity\Article;
use App\PolygraphyDigest\Repository\ArticleRepository;
use App\PolygraphyDigest\Service\Search\SearchIndexer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Uid\Uuid;

/**
 * Testovací třída pro příkaz ReindexArticlesCommand.
 * Pokrývá:
 * 1. Úspěšnou reindexaci (Happy Path).
 * 2. Chybu při načítání z DB.
 * 3. Chybu při indexaci konkrétní položky.
 */
class ReindexArticlesCommandTest extends TestCase
{
    private ArticleRepository|MockObject $articleRepository;
    private SearchIndexer|MockObject $searchIndexer;
    private CommandTester $commandTester;

    /**
     * Inicializace testovacího prostředí.
     * Vytvoří mock objekty pro repozitář a indexer a instanci testovaného příkazu.
     */
    protected function setUp(): void
    {
        $this->articleRepository = $this->createMock(ArticleRepository::class);
        $this->searchIndexer = $this->createMock(SearchIndexer::class);

        $command = new ReindexArticlesCommand(
            $this->articleRepository,
            $this->searchIndexer
        );
        $this->commandTester = new CommandTester($command);
    }

    /**
     * Testuje úspěšný průběh reindexace (Happy Path).
     *
     * Ověřuje, že:
     * - Všechny články z repozitáře jsou předány indexeru.
     * - Výstup obsahuje informaci o počtu článků a úspěšném dokončení.
     * - Nevyskytují se žádné chybové hlášky.
     */
    public function testExecuteHappyPath(): void
    {
        // 1. Příprava dat (2 články)
        $article1 = $this->createMock(Article::class);
        $article2 = $this->createMock(Article::class);
        $articles = [$article1, $article2];

        // 2. Nastavení očekávání
        $this->articleRepository->expects($this->once())
            ->method('findAll')
            ->willReturn($articles);

        // Očekáváme, že se indexer zavolá 2x s instancí Article
        $this->searchIndexer->expects($this->exactly(2))
            ->method('indexArticle')
            ->with($this->isInstanceOf(Article::class));

        // 3. Spuštění
        $exitCode = $this->commandTester->execute([]);

        // 4. Ověření
        $output = $this->commandTester->getDisplay();

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('Startuji reindexaci 2 článků', $output);
        $this->assertStringContainsString('Reindexace dokončena.', $output);
        $this->assertStringNotContainsString('Chyba', $output);
    }

    /**
     * Testuje chování aplikace při selhání databáze.
     *
     * Ověřuje, že:
     * - Pokud `findAll` vyhodí výjimku, indexace se nespustí.
     * - Uživatel je informován o chybě načítání dat.
     * - Příkaz skončí s kódem SUCCESS (graceful shutdown).
     */
    public function testExecuteHandlesDatabaseFailure(): void
    {
        // 1. Simulace chyby DB
        $exceptionMessage = 'Connection refused';
        $this->articleRepository->expects($this->once())
            ->method('findAll')
            ->willThrowException(new \Exception($exceptionMessage));

        // Indexer by se neměl vůbec zavolat
        $this->searchIndexer->expects($this->never())
            ->method('indexArticle');

        // 2. Spuštění
        $exitCode = $this->commandTester->execute([]);
        $output = $this->commandTester->getDisplay();

        // 3. Ověření
        $this->assertSame(Command::SUCCESS, $exitCode); // Příkaz vrací SUCCESS i při chybě (dle implementace)
        $this->assertStringContainsString('Chyba při načítání článků z databáze', $output);
        $this->assertStringContainsString($exceptionMessage, $output);
    }

    /**
     * Testuje částečné selhání indexace.
     *
     * Scénář: Jeden článek se podaří zaindexovat, druhý selže.
     * Ověřuje, že:
     * - Chyba u jednoho článku nezastaví zpracování ostatních.
     * - Je vypsána konkrétní chyba s ID problematického článku.
     * - Celkový proces doběhne do konce.
     */
    public function testExecuteHandlesPartialIndexingFailure(): void
    {
        // 1. Příprava dat (2 články)
        $articleSuccess = $this->createMock(Article::class);
        
        $articleFail = $this->createMock(Article::class);
        $uuid = Uuid::v4();
        $articleFail->method('getId')->willReturn($uuid);

        // 2. Nastavení očekávání
        $this->articleRepository->expects($this->once())
            ->method('findAll')
            ->willReturn([$articleSuccess, $articleFail]);

        // Simulace: První projde, druhý vyhodí chybu
        $this->searchIndexer->expects($this->exactly(2))
            ->method('indexArticle')
            ->willReturnCallback(function (Article $article) use ($articleFail) {
                if ($article === $articleFail) {
                    throw new \Exception('Elasticsearch down');
                }
            });

        // 3. Spuštění
        $exitCode = $this->commandTester->execute([]);
        $output = $this->commandTester->getDisplay();

        // 4. Ověření
        $this->assertSame(Command::SUCCESS, $exitCode);
        
        // Musí obsahovat chybu pro konkrétní ID
        $this->assertStringContainsString('Chyba při indexaci článku ID ' . $uuid->toRfc4122(), $output);
        $this->assertStringContainsString('Elasticsearch down', $output);
        
        // Musí ale doběhnout do konce
        $this->assertStringContainsString('Reindexace dokončena.', $output);
    }
}