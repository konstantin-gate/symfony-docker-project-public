<?php

declare(strict_types=1);

namespace App\Tests\PolygraphyDigest\Command;

use App\PolygraphyDigest\Command\ReindexArticlesCommand;
use App\PolygraphyDigest\Entity\Article;
use App\PolygraphyDigest\Enum\ArticleStatusEnum;
use App\PolygraphyDigest\Repository\ArticleRepository;
use App\PolygraphyDigest\Service\Search\SearchIndexer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Uid\Uuid;

/**
 * Testovací třída pro testování hraničních případů (Edge Cases) příkazu ReindexArticlesCommand.
 * Zaměřuje se na nestandardní vstupy, duplicity, speciální znaky a různé stavy článků.
 */
class ReindexArticlesCommandEdgeCaseTest extends TestCase
{
    /**
     * Testuje chování příkazu při zpracování článku s nulovými hodnotami (pokud jsou povoleny).
     * Ověřuje, že příkaz správně zaloguje chybu, pokud indexer odmítne nulové hodnoty.
     */
    public function testArticleWithNullValues(): void
    {
        $article = $this->createMock(Article::class);
        $article->method('getId')->willReturn(Uuid::v4());
        // Simulujeme, že getter vrací null, i když setter by to třeba nedovolil (stav z DB)
        $article->method('getTitle')->willReturn(null);

        $articleRepository = $this->createMock(ArticleRepository::class);
        $articleRepository->method('findAll')->willReturn([$article]);

        $searchIndexer = $this->createMock(SearchIndexer::class);
        $searchIndexer->expects($this->once())
            ->method('indexArticle')
            ->with($article)
            ->willThrowException(new \InvalidArgumentException('Title cannot be null'));

        $command = new ReindexArticlesCommand($articleRepository, $searchIndexer);
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Title cannot be null', $output);
        $this->assertStringContainsString('[OK] Reindexace dokončena.', $output);
    }

    /**
     * Testuje chování příkazu s prázdnými poli článku.
     * Ověřuje, že příkaz zpracuje článek s prázdným obsahem, pokud to indexer dovolí.
     */
    public function testArticleWithEmptyFields(): void
    {
        $article = $this->createMock(Article::class);
        $article->method('getId')->willReturn(Uuid::v4());
        $article->method('getTitle')->willReturn('');
        $article->method('getContent')->willReturn('');

        $articleRepository = $this->createMock(ArticleRepository::class);
        $articleRepository->method('findAll')->willReturn([$article]);

        $searchIndexer = $this->createMock(SearchIndexer::class);
        // Očekáváme, že indexer to přijme (nebo vyhodí výjimku, kterou příkaz zachytí - zde testujeme průchod)
        $searchIndexer->expects($this->once())->method('indexArticle')->with($article);

        $command = new ReindexArticlesCommand($articleRepository, $searchIndexer);
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

        $this->assertSame(0, $commandTester->getStatusCode());
    }

    /**
     * Testuje zpracování článku se speciálními znaky (HTML, Unicode).
     * Ověřuje, že příkaz předá článek indexeru bez poškození dat.
     */
    public function testArticleWithSpecialCharacters(): void
    {
        $specialTitle = 'Title with <html> & "quotes" and 😊';
        $article = $this->createMock(Article::class);
        $article->method('getId')->willReturn(Uuid::v4());
        $article->method('getTitle')->willReturn($specialTitle);

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
     * Testuje zpracování článku s velmi dlouhým obsahem.
     * Ověřuje, že příkaz a indexer zvládnou velké množství dat v jednom poli.
     */
    public function testArticleWithVeryLongContent(): void
    {
        $longContent = str_repeat('Long content string. ', 5000); // Cca 100KB
        $article = $this->createMock(Article::class);
        $article->method('getId')->willReturn(Uuid::v4());
        $article->method('getContent')->willReturn($longContent);

        $articleRepository = $this->createMock(ArticleRepository::class);
        $articleRepository->method('findAll')->willReturn([$article]);

        $searchIndexer = $this->createMock(SearchIndexer::class);
        $searchIndexer->expects($this->once())->method('indexArticle')->with($article);

        $command = new ReindexArticlesCommand($articleRepository, $searchIndexer);
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

        $this->assertSame(0, $commandTester->getStatusCode());
    }

    /**
     * Testuje chování při výskytu duplicitních článků v repozitáři.
     * Ověřuje, že příkaz se pokusí indexovat oba (indexer by si s tím měl poradit, příkaz jen iteruje).
     */
    public function testDuplicateArticles(): void
    {
        $uuid = Uuid::v4();
        $article1 = $this->createMock(Article::class);
        $article1->method('getId')->willReturn($uuid);
        
        // Stejný článek (nebo jiná instance se stejným ID) podruhé
        $article2 = $this->createMock(Article::class);
        $article2->method('getId')->willReturn($uuid);

        $articleRepository = $this->createMock(ArticleRepository::class);
        $articleRepository->method('findAll')->willReturn([$article1, $article2]);

        $searchIndexer = $this->createMock(SearchIndexer::class);
        $searchIndexer->expects($this->exactly(2))->method('indexArticle');

        $command = new ReindexArticlesCommand($articleRepository, $searchIndexer);
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Startuji reindexaci 2 článků', $output);
        $this->assertStringContainsString('100%', $output);
    }

    /**
     * Testuje zpracování článků v různých stavech (publikovaný, koncept, archivovaný).
     * Ověřuje, že příkaz zpracovává všechny články bez ohledu na stav (filtrování je věc repozitáře).
     */
    public function testArticlesInDifferentStates(): void
    {
        $articlePublished = $this->createMock(Article::class);
        $articlePublished->method('getId')->willReturn(Uuid::v4());
        $articlePublished->method('getStatus')->willReturn(ArticleStatusEnum::NEW);

        $articleHidden = $this->createMock(Article::class);
        $articleHidden->method('getId')->willReturn(Uuid::v4());
        $articleHidden->method('getStatus')->willReturn(ArticleStatusEnum::HIDDEN);

        $articleRepository = $this->createMock(ArticleRepository::class);
        $articleRepository->method('findAll')->willReturn([$articlePublished, $articleHidden]);

        $searchIndexer = $this->createMock(SearchIndexer::class);
        $searchIndexer->expects($this->exactly(2))->method('indexArticle');

        $command = new ReindexArticlesCommand($articleRepository, $searchIndexer);
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

        $this->assertSame(0, $commandTester->getStatusCode());
    }
}
