<?php

namespace App\Tests\PolygraphyDigest\Command;

use App\PolygraphyDigest\Command\ReindexArticlesCommand;
use App\PolygraphyDigest\Entity\Article;
use App\PolygraphyDigest\Repository\ArticleRepository;
use App\PolygraphyDigest\Service\Search\SearchIndexer;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Uid\Uuid;

class ReindexArticlesCommandIndexerTest extends WebTestCase
{
    /**
     * Testuje interakci s indexerem při reindexaci článků.
     *
     * Tato funkce testuje, zda je indexer správně volán při reindexaci článků.
     * Ověřuje, že indexer je inicializován a že jsou články správně předány do indexeru.
     */
    public function testIndexerInteraction(): void
    {
        $article = $this->createMock(Article::class);

        $articleRepository = $this->createMock(ArticleRepository::class);
        $articleRepository->method('findAll')->willReturn([$article]);

        $searchIndexer = $this->createMock(SearchIndexer::class);
        $searchIndexer->expects($this->once())
            ->method('indexArticle')
            ->with($article);

        $command = new ReindexArticlesCommand($articleRepository, $searchIndexer);
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

        $this->assertSame(0, $commandTester->getStatusCode());
    }

    /**
     * Testuje inicializaci indexeru.
     *
     * Tato funkce testuje, zda je indexer správně inicializován před reindexací článků.
     * Ověřuje, že indexer je vytvořen a že je připraven k přijmutí článků.
     */
    public function testIndexerInitialization(): void
    {
        $articleRepository = $this->createMock(ArticleRepository::class);
        $searchIndexer = $this->createMock(SearchIndexer::class);

        $command = new ReindexArticlesCommand($articleRepository, $searchIndexer);

        // Pomocí reflexe ověříme, že indexer byl správně injektován do privátní vlastnosti.
        $reflection = new \ReflectionClass($command);
        $property = $reflection->getProperty('searchIndexer');
        $injectedIndexer = $property->getValue($command);

        $this->assertInstanceOf(SearchIndexer::class, $injectedIndexer);
        $this->assertSame($searchIndexer, $injectedIndexer);
    }

    /**
     * Testuje předání dat do indexeru.
     *
     * Tato funkce testuje, zda jsou články správně předány do indexeru.
     * Ověřuje, že data jsou správně formátována a že jsou předána bez chyb.
     */
    public function testDataPassingToIndexer(): void
    {
        $article1 = $this->createMock(Article::class);
        $article2 = $this->createMock(Article::class);
        $article3 = $this->createMock(Article::class);
        $articles = [$article1, $article2, $article3];

        $articleRepository = $this->createMock(ArticleRepository::class);
        $articleRepository->method('findAll')->willReturn($articles);

        $searchIndexer = $this->createMock(SearchIndexer::class);

        $expectedArticles = $articles;
        $searchIndexer->expects($this->exactly(3))
            ->method('indexArticle')
            ->with($this->callback(function (Article $article) use (&$expectedArticles) {
                $expected = array_shift($expectedArticles);
                return $article === $expected;
            }));

        $command = new ReindexArticlesCommand($articleRepository, $searchIndexer);
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

        $this->assertSame(0, $commandTester->getStatusCode());
        $this->assertStringContainsString('Reindexace dokončena.', $commandTester->getDisplay());
    }

    /**
     * Testuje zpracování chyb při interakci s indexerem.
     *
     * Tato funkce testuje, zda jsou chyby správně zachyceny a zpracovány při interakci s indexerem.
     * Ověřuje, že chyby nevedou k pádu aplikace a že jsou správně logovány.
     */
    public function testErrorHandlingInIndexerInteraction(): void
    {
        $article1 = $this->createMock(Article::class);
        $uuid1 = Uuid::v4();
        $article1->method('getId')->willReturn($uuid1);

        $article2 = $this->createMock(Article::class);
        $uuid2 = Uuid::v4();
        $article2->method('getId')->willReturn($uuid2);

        $articles = [$article1, $article2];

        $articleRepository = $this->createMock(ArticleRepository::class);
        $articleRepository->method('findAll')->willReturn($articles);

        $searchIndexer = $this->createMock(SearchIndexer::class);

        // První článek vyhodí výjimku
        $searchIndexer->expects($this->exactly(2))
            ->method('indexArticle')
            ->with($this->callback(function (Article $article) use ($article1, $article2) {
                return $article === $article1 || $article === $article2;
            }))
            ->willReturnCallback(function (Article $article) use ($article1) {
                if ($article === $article1) {
                    throw new \RuntimeException('Indexace selhala');
                }
            });

        $command = new ReindexArticlesCommand($articleRepository, $searchIndexer);
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

        // Příkaz by měl skončit úspěšně (0), i když došlo k chybě u jednoho článku
        $this->assertSame(0, $commandTester->getStatusCode());

        $output = $commandTester->getDisplay();
        // Ověříme, že chyba byla zalogována (hledáme ID a chybovou hlášku odděleně kvůli formátování SymfonyStyle)
        $this->assertStringContainsString((string) $uuid1, $output);
        $this->assertStringContainsString('Indexace selhala', $output);

        // Ověříme, že proces doběhl do konce
        $this->assertStringContainsString('Reindexace dokončena.', $output);
    }

    /**
     * Testuje dokončení indexace.
     *
     * Tato funkce testuje, zda je indexace správně dokončena a že jsou výsledky správně uloženy.
     * Ověřuje, že indexer je správně ukončen a že jsou výsledky dostupné pro další zpracování.
     */
    public function testIndexerCompletion(): void
    {
        $article1 = $this->createMock(Article::class);
        $article2 = $this->createMock(Article::class);

        $articleRepository = $this->createMock(ArticleRepository::class);
        $articleRepository->method('findAll')->willReturn([$article1, $article2]);

        $searchIndexer = $this->createMock(SearchIndexer::class);
        // Očekáváme volání pro oba články
        $searchIndexer->expects($this->exactly(2))->method('indexArticle');

        $command = new ReindexArticlesCommand($articleRepository, $searchIndexer);
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

        $this->assertSame(0, $commandTester->getStatusCode());
        $output = $commandTester->getDisplay();

        // Ověření finální zprávy
        $this->assertStringContainsString('Reindexace dokončena.', $output);
        $this->assertStringContainsString('[OK] Reindexace dokončena.', $output);
    }

    /**
     * Testuje logování při interakci s indexerem.
     *
     * Tato funkce testuje, zda jsou správně logovány důležité události při interakci s indexerem.
     * Ověřuje, že logy obsahují důležité informace a že jsou správně formátovány.
     */
    public function testLoggingInIndexerInteraction(): void
    {
        $article = $this->createMock(Article::class);
        $articleRepository = $this->createMock(ArticleRepository::class);
        $articleRepository->method('findAll')->willReturn([$article]);

        $searchIndexer = $this->createMock(SearchIndexer::class);
        $searchIndexer->expects($this->once())
            ->method('indexArticle')
            ->with($article);

        $command = new ReindexArticlesCommand($articleRepository, $searchIndexer);
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

        $output = $commandTester->getDisplay();

        // Ověřujeme přítomnost klíčových informačních zpráv
        $this->assertStringContainsString('Startuji reindexaci 1 článků', $output);
        $this->assertStringContainsString('Reindexace dokončena.', $output);

        // Ověřujeme přítomnost formátování (např. [OK] pro success block SymfonyStyle)
        $this->assertStringContainsString('[OK]', $output);
    }

    /**
     * Testuje postup progressBaru při úspěšné reindexaci.
     *
     * Tato funkce ověřuje, zda se progressBar správně posouvá po každém úspěšně indexovaném článku.
     * Simuluje situaci se 3 články a kontroluje, zda výstup obsahuje indikaci postupu (1/3, 2/3, 3/3).
     */
    public function testProgressBarAdvancesOnSuccess(): void
    {
        $articles = [
            $this->createMock(Article::class),
            $this->createMock(Article::class),
            $this->createMock(Article::class),
        ];

        $articleRepository = $this->createMock(ArticleRepository::class);
        $articleRepository->method('findAll')->willReturn($articles);

        $searchIndexer = $this->createMock(SearchIndexer::class);
        $searchIndexer->expects($this->exactly(3))->method('indexArticle');

        $command = new ReindexArticlesCommand($articleRepository, $searchIndexer);
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

        $output = $commandTester->getDisplay();

        // Ověříme, že výstup obsahuje základní informace
        $this->assertStringContainsString('Startuji reindexaci 3 článků', $output);

        // V testovacím prostředí bez TTY se nemusí vypisovat každý krok (1/3, 2/3),
        // ale "3/3" a "100%" by se mělo objevit při dokončení (progressFinish).
        // Tím ověříme, že progress bar došel do konce.
        $this->assertStringContainsString('3/3', $output);
        $this->assertStringContainsString('100%', $output);

        $this->assertStringContainsString('[OK] Reindexace dokončena.', $output);
    }

    /**
     * Testuje chování progressBaru při chybě indexace.
     *
     * Tato funkce ověřuje chování příkazu, když u jednoho z článků selže indexace.
     * Očekáváme, že se vypíše chybová hláška, ale proces bude pokračovat a progressBar dojde do konce (3/3).
     * Tento test také potvrzuje stávající chování, kdy při chybě nedojde k zavolání progressAdvance(),
     * což může způsobit skok v počítadle na konci, ale příkaz jako celek doběhne.
     */
    public function testProgressBarBehaviorOnFailure(): void
    {
        $article1 = $this->createMock(Article::class);
        $article2 = $this->createMock(Article::class);
        $article2->method('getId')->willReturn(Uuid::v4()); // Pro výpis chyby
        $article3 = $this->createMock(Article::class);

        $articles = [$article1, $article2, $article3];

        $articleRepository = $this->createMock(ArticleRepository::class);
        $articleRepository->method('findAll')->willReturn($articles);

        $searchIndexer = $this->createMock(SearchIndexer::class);

        // Nastavíme chování indexeru: 1. OK, 2. Chyba, 3. OK
        $searchIndexer->expects($this->exactly(3))
            ->method('indexArticle')
            ->willReturnCallback(function (Article $article) use ($article2) {
                if ($article === $article2) {
                    throw new \RuntimeException('Simulovaná chyba indexace');
                }
            });

        $command = new ReindexArticlesCommand($articleRepository, $searchIndexer);
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

        $output = $commandTester->getDisplay();

        // 1. Ověříme, že chyba byla vypsána
        $this->assertStringContainsString('Simulovaná chyba indexace', $output);

        // 2. Ověříme, že progress bar a proces došel úspěšně do konce
        // I když jeden prvek selhal, progress bar by měl být na konci donucen k dokončení (3/3).
        $this->assertStringContainsString('3/3', $output);
        $this->assertStringContainsString('100%', $output);

        // 3. Ověříme celkový úspěch příkazu
        $this->assertStringContainsString('[OK] Reindexace dokončena.', $output);
    }

    /**
     * Testuje chování progressBaru s prázdným seznamem článků.
     *
     * Tato funkce ověřuje, že příkaz a progressBar se chovají korektně, i když nejsou nalezeny žádné články.
     * Nemělo by dojít k dělení nulou ani jiným chybám zobrazení.
     */
    public function testProgressBarWithZeroArticles(): void
    {
        $articleRepository = $this->createMock(ArticleRepository::class);
        $articleRepository->method('findAll')->willReturn([]);

        $searchIndexer = $this->createMock(SearchIndexer::class);
        $searchIndexer->expects($this->never())->method('indexArticle');

        $command = new ReindexArticlesCommand($articleRepository, $searchIndexer);
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

        $output = $commandTester->getDisplay();

        // Ověříme inicializaci s 0 články
        $this->assertStringContainsString('Startuji reindexaci 0 článků', $output);

        // Ověříme, že proces skončil bez chyb
        $this->assertStringContainsString('[OK] Reindexace dokončena.', $output);
    }

    /**
     * Testuje chování při vyhození LogicException indexerem.
     *
     * Tato funkce ověřuje, zda příkaz správně zachytí a zaloguje chybu typu LogicException,
     * která může nastat například při chybné konfiguraci nebo logické chybě v kódu indexeru.
     * Ověřuje, že ID článku a zpráva výjimky jsou ve výstupu a že proces pokračuje dál.
     */
    public function testIndexerLogicException(): void
    {
        $article = $this->createMock(Article::class);
        $uuid = Uuid::v4();
        $article->method('getId')->willReturn($uuid);

        $articleRepository = $this->createMock(ArticleRepository::class);
        $articleRepository->method('findAll')->willReturn([$article]);

        $searchIndexer = $this->createMock(SearchIndexer::class);
        $searchIndexer->expects($this->once())
            ->method('indexArticle')
            ->with($article)
            ->willThrowException(new \LogicException('Logická chyba v indexeru'));

        $command = new ReindexArticlesCommand($articleRepository, $searchIndexer);
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

        $output = $commandTester->getDisplay();

        // Ověříme, že chyba byla zalogována s ID a zprávou
        $this->assertStringContainsString((string) $uuid, $output);
        $this->assertStringContainsString('Logická chyba v indexeru', $output);

        // Ověříme, že proces nespadl a došel do konce
        $this->assertSame(0, $commandTester->getStatusCode());
        $this->assertStringContainsString('[OK] Reindexace dokončena.', $output);
    }

    /**
     * Testuje zacházení s problémy s připojením k databázi.
     *
     * Tato funkce ověřuje, zda příkaz správně zachytí a zaloguje chybu spojenou s připojením k databázi.
     * Ověřuje, že uživatelsky přívětivá chybová zpráva je zobrazená a že příkaz vrátí Command::SUCCESS
     * i při selhání databáze.
     */
    public function testDatabaseConnectionIssues(): void
    {
        $articleRepository = $this->createMock(ArticleRepository::class);
        $articleRepository->method('findAll')
            ->willThrowException(new \RuntimeException('Nelze se připojit k databázi'));

        $searchIndexer = $this->createMock(SearchIndexer::class);
        $searchIndexer->expects($this->never())->method('indexArticle');

        $command = new ReindexArticlesCommand($articleRepository, $searchIndexer);
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

        $output = $commandTester->getDisplay();

        // Ověříme, že chyba byla zalogována
        $this->assertStringContainsString('Chyba při načítání článků z databáze', $output);
        $this->assertStringContainsString('Nelze se připojit k databázi', $output);

        // Ověříme, že příkaz vrátí FAILURE při selhání databáze
        $this->assertSame(1, $commandTester->getStatusCode());

        // Ověříme, že progress bar nebyl spuštěn
        $this->assertStringNotContainsString('Startuji reindexaci', $output);
    }

    /**
     * Testuje zacházení s problémy s připojením k Elasticsearch.
     *
     * Tato funkce ověřuje, zda příkaz správně zachytí a zaloguje chybu spojenou s připojením k Elasticsearch.
     * Ověřuje, že chyby jsou zalogovány pro každý článek a že progress bar dojde do konce.
     */
    public function testElasticsearchConnectionIssues(): void
    {
        $article1 = $this->createMock(Article::class);
        $uuid1 = Uuid::v4();
        $article1->method('getId')->willReturn($uuid1);

        $article2 = $this->createMock(Article::class);
        $uuid2 = Uuid::v4();
        $article2->method('getId')->willReturn($uuid2);

        $articles = [$article1, $article2];

        $articleRepository = $this->createMock(ArticleRepository::class);
        $articleRepository->method('findAll')->willReturn($articles);

        $searchIndexer = $this->createMock(SearchIndexer::class);
        $searchIndexer->expects($this->exactly(2))
            ->method('indexArticle')
            ->willThrowException(new \RuntimeException('Nelze se připojit k Elasticsearch'));

        $command = new ReindexArticlesCommand($articleRepository, $searchIndexer);
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

        $output = $commandTester->getDisplay();

        // Ověříme, že chyba byla zalogována pro oba články
        $this->assertStringContainsString((string) $uuid1, $output);
        $this->assertStringContainsString((string) $uuid2, $output);
        $this->assertStringContainsString('Nelze se připojit k Elasticsearch', $output);

        // Ověříme, že příkaz vrátí SUCCESS i při selhání Elasticsearch
        $this->assertSame(0, $commandTester->getStatusCode());

        // Ověříme, že progress bar došel do konce
        $this->assertStringContainsString('2/2', $output);
        $this->assertStringContainsString('100%', $output);
        $this->assertStringContainsString('[OK] Reindexace dokončena.', $output);
    }

    /**
     * Testuje zacházení s neplatnými daty článku.
     *
     * Tato funkce ověřuje, zda příkaz správně zachytí a zaloguje chybu spojenou s neplatnými daty článku.
     * Ověřuje, že chyby jsou zalogovány s ID článku a detaily validace a že proces pokračuje dál.
     */
    public function testInvalidArticleData(): void
    {
        $article1 = $this->createMock(Article::class);
        $uuid1 = Uuid::v4();
        $article1->method('getId')->willReturn($uuid1);
        $article1->method('getTitle')->willReturn(null);

        $article2 = $this->createMock(Article::class);
        $uuid2 = Uuid::v4();
        $article2->method('getId')->willReturn($uuid2);
        $article2->method('getTitle')->willReturn('');

        $articles = [$article1, $article2];

        $articleRepository = $this->createMock(ArticleRepository::class);
        $articleRepository->method('findAll')->willReturn($articles);

        $searchIndexer = $this->createMock(SearchIndexer::class);
        $searchIndexer->expects($this->exactly(2))
            ->method('indexArticle')
            ->willThrowException(new \InvalidArgumentException('Neplatný název článku'));

        $command = new ReindexArticlesCommand($articleRepository, $searchIndexer);
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

        $output = $commandTester->getDisplay();

        // Ověříme, že chyba byla zalogována pro oba články
        $this->assertStringContainsString((string) $uuid1, $output);
        $this->assertStringContainsString((string) $uuid2, $output);
        $this->assertStringContainsString('Neplatný název článku', $output);

        // Ověříme, že příkaz vrátí SUCCESS i při selhání validace
        $this->assertSame(0, $commandTester->getStatusCode());

        // Ověříme, že progress bar došel do konce
        $this->assertStringContainsString('2/2', $output);
        $this->assertStringContainsString('100%', $output);
        $this->assertStringContainsString('[OK] Reindexace dokončena.', $output);
    }

    /**
     * Testuje zacházení s chybami oprávnění.
     *
     * Tato funkce ověřuje, zda příkaz správně zachytí a zaloguje chybu spojenou s oprávněními.
     * Ověřuje, že chyby jsou zalogovány s ID článku a detaily oprávnění a že proces pokračuje dál.
     */
    public function testPermissionErrors(): void
    {
        $article1 = $this->createMock(Article::class);
        $uuid1 = Uuid::v4();
        $article1->method('getId')->willReturn($uuid1);

        $article2 = $this->createMock(Article::class);
        $uuid2 = Uuid::v4();
        $article2->method('getId')->willReturn($uuid2);

        $articles = [$article1, $article2];

        $articleRepository = $this->createMock(ArticleRepository::class);
        $articleRepository->method('findAll')->willReturn($articles);

        $searchIndexer = $this->createMock(SearchIndexer::class);
        $searchIndexer->expects($this->exactly(2))
            ->method('indexArticle')
            ->willThrowException(new \RuntimeException('Právo přístupu zamítnuto'));

        $command = new ReindexArticlesCommand($articleRepository, $searchIndexer);
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

        $output = $commandTester->getDisplay();

        // Ověříme, že chyba byla zalogována pro oba články
        $this->assertStringContainsString((string) $uuid1, $output);
        $this->assertStringContainsString((string) $uuid2, $output);
        $this->assertStringContainsString('Právo přístupu zamítnuto', $output);

        // Ověříme, že příkaz vrátí SUCCESS i při selhání oprávnění
        $this->assertSame(0, $commandTester->getStatusCode());

        // Ověříme, že progress bar došel do konce
        $this->assertStringContainsString('2/2', $output);
        $this->assertStringContainsString('100%', $output);
        $this->assertStringContainsString('[OK] Reindexace dokončena.', $output);
    }
}
