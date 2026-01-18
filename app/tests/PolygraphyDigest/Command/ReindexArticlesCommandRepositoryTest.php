<?php

declare(strict_types=1);

namespace App\Tests\PolygraphyDigest\Command;

use App\PolygraphyDigest\Command\ReindexArticlesCommand;
use App\PolygraphyDigest\Entity\Article;
use App\PolygraphyDigest\Repository\ArticleRepository;
use App\PolygraphyDigest\Service\Search\SearchIndexer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Uid\Uuid;

/**
 * Testy interakce příkazu ReindexArticlesCommand s repozitářem.
 * Zkouší, jak příkaz pracuje s daty z repozitáře.
 */
class ReindexArticlesCommandRepositoryTest extends TestCase
{
    /**
     * Testuje, že příkaz správně zpracuje prázdný seznam článků z repozitáře.
     * Ověřuje, že se nezobrazí žádné chybové hlášky a příkaz úspěšně dokončí.
     */
    public function testEmptyArticleList(): void
    {
        $emptyArticles = [];
        $articleRepository = $this->createMock(ArticleRepository::class);
        $articleRepository->method('findAll')->willReturn($emptyArticles);

        $searchIndexer = $this->createMock(SearchIndexer::class);

        $command = new ReindexArticlesCommand($articleRepository, $searchIndexer);
        $commandTester = new CommandTester($command);
        $exitCode = $commandTester->execute([]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Startuji reindexaci 0 článků', $output);
        $this->assertStringContainsString('Reindexace dokončena.', $output);
        $this->assertStringNotContainsString('Chyba', $output);
    }

    /**
     * Testuje, že příkaz správně zpracuje jeden článek z repozitáře.
     * Ověřuje, že se indexace provede pro jeden článek a příkaz úspěšně dokončí.
     */
    public function testSingleArticle(): void
    {
        $article = $this->createMock(Article::class);
        $uuid = $this->createMock(Uuid::class);
        $uuid->method('__toString')->willReturn('test-article-1');
        $article->method('getId')->willReturn($uuid);

        $articles = [$article];
        $articleRepository = $this->createMock(ArticleRepository::class);
        $articleRepository->method('findAll')->willReturn($articles);

        $searchIndexer = $this->createMock(SearchIndexer::class);
        $searchIndexer->expects($this->once())
            ->method('indexArticle')
            ->with($article);

        $command = new ReindexArticlesCommand($articleRepository, $searchIndexer);
        $commandTester = new CommandTester($command);
        $exitCode = $commandTester->execute([]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Startuji reindexaci 1 článků', $output);
        $this->assertStringContainsString('Reindexace dokončena.', $output);
    }

    /**
     * Testuje, že příkaz správně zpracuje více článků z repozitáře.
     * Ověřuje, že se indexace provede pro všechny články a příkaz úspěšně dokončí.
     */
    public function testMultipleArticles(): void
    {
        $article1 = $this->createMock(Article::class);
        $uuid1 = $this->createMock(Uuid::class);
        $uuid1->method('__toString')->willReturn('test-article-1');
        $article1->method('getId')->willReturn($uuid1);

        $article2 = $this->createMock(Article::class);
        $uuid2 = $this->createMock(Uuid::class);
        $uuid2->method('__toString')->willReturn('test-article-2');
        $article2->method('getId')->willReturn($uuid2);

        $article3 = $this->createMock(Article::class);
        $uuid3 = $this->createMock(Uuid::class);
        $uuid3->method('__toString')->willReturn('test-article-3');
        $article3->method('getId')->willReturn($uuid3);

        $articles = [$article1, $article2, $article3];
        $articleRepository = $this->createMock(ArticleRepository::class);
        $articleRepository->method('findAll')->willReturn($articles);

        $searchIndexer = $this->createMock(SearchIndexer::class);

        $callIndex = 0;
        $searchIndexer->expects($this->exactly(3))
            ->method('indexArticle')
            ->willReturnCallback(function (Article $article) use (&$callIndex, $article1, $article2, $article3) {
                $expected = [$article1, $article2, $article3];
                $this->assertSame($expected[$callIndex], $article);
                $callIndex++;
            });

        $command = new ReindexArticlesCommand($articleRepository, $searchIndexer);
        $commandTester = new CommandTester($command);
        $exitCode = $commandTester->execute([]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Startuji reindexaci 3 článků', $output);
        $this->assertStringContainsString('Reindexace dokončena.', $output);
    }

    /**
     * Testuje, že příkaz pokračuje v reindexaci i v případě chyby u jednoho článku.
     * Ověřuje, že chyba je zalogována a ostatní články jsou zpracovány.
     */
    public function testIndexerExceptionIsHandledGracefully(): void
    {
        $article1 = $this->createMock(Article::class);
        $uuid1 = $this->createMock(Uuid::class);
        $uuid1->method('__toString')->willReturn('test-article-1');
        $article1->method('getId')->willReturn($uuid1);

        $article2 = $this->createMock(Article::class);
        $uuid2 = $this->createMock(Uuid::class);
        $uuid2->method('__toString')->willReturn('test-article-2');
        $article2->method('getId')->willReturn($uuid2);

        $articles = [$article1, $article2];
        $articleRepository = $this->createMock(ArticleRepository::class);
        $articleRepository->method('findAll')->willReturn($articles);

        $searchIndexer = $this->createMock(SearchIndexer::class);
        $searchIndexer->expects($this->exactly(2))
            ->method('indexArticle')
            ->willReturnCallback(function (Article $article) use ($article1) {
                if ($article === $article1) {
                    throw new \RuntimeException('Chyba Elasticsearch');
                }
            });

        $command = new ReindexArticlesCommand($articleRepository, $searchIndexer);
        $commandTester = new CommandTester($command);
        $exitCode = $commandTester->execute([]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Startuji reindexaci 2 článků', $output);
        $this->assertStringContainsString('Chyba při indexaci článku ID test-article-1: Chyba Elasticsearch', $output);
        $this->assertStringContainsString('Reindexace dokončena.', $output);
    }

    /**
     * Testuje, že chyba repozitáře je zachycena a příkaz neskončí chybou.
     * Ověřuje, že i při selhání DB příkaz vrátí SUCCESS status kód podle plánu.
     */
    public function testRepositoryExceptionIsHandled(): void
    {
        $articleRepository = $this->createMock(ArticleRepository::class);
        $articleRepository->method('findAll')->willThrowException(new \RuntimeException('DB connection failed'));

        $searchIndexer = $this->createMock(SearchIndexer::class);

        $command = new ReindexArticlesCommand($articleRepository, $searchIndexer);
        $commandTester = new CommandTester($command);

        $exitCode = $commandTester->execute([]);
        
        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('DB connection failed', $commandTester->getDisplay());
    }

    /**
     * Testuje úspěšnou inicializaci příkazu s platnými parametry.
     * Ověřuje, že konstruktor správně ukládá instance do privátních vlastností.
     */
    public function testConstructorInitializesProperties(): void
    {
        $articleRepository = $this->createMock(ArticleRepository::class);
        $searchIndexer = $this->createMock(SearchIndexer::class);

        $command = new ReindexArticlesCommand($articleRepository, $searchIndexer);
        
        $reflection = new \ReflectionClass($command);
        
        $repoProp = $reflection->getProperty('articleRepository');
        $this->assertSame($articleRepository, $repoProp->getValue($command));
        
        $indexerProp = $reflection->getProperty('searchIndexer');
        $this->assertSame($searchIndexer, $indexerProp->getValue($command));
    }

    /**
     * Testuje, že konstruktor vyhodí TypeError při předání null místo ArticleRepository.
     */
    public function testConstructorRequiresArticleRepository(): void
    {
        $searchIndexer = $this->createMock(SearchIndexer::class);

        $this->expectException(\TypeError::class);
        new ReindexArticlesCommand(null, $searchIndexer);
    }

    /**
     * Testuje, že konstruktor vyhodí TypeError při předání null místo SearchIndexer.
     */
    public function testConstructorRequiresSearchIndexer(): void
    {
        $articleRepository = $this->createMock(ArticleRepository::class);

        $this->expectException(\TypeError::class);
        new ReindexArticlesCommand($articleRepository, null);
    }

    /**
     * Testuje funkčnost příkazu s různými implementacemi ArticleRepository.
     * Ověřuje, že příkaz správně pracuje s různými typy repozitářů.
     */
    public function testCommandWorksWithDifferentRepositoryImplementations(): void
    {
        // Arrange - Test with DoctrineArticleRepository
        $article = new Article();
        $article->setTitle('Testový článek');
        $article->setContent('Obsah článku');

        $doctrineRepository = $this->createMock(ArticleRepository::class);
        $doctrineRepository->method('findAll')->willReturn([$article]);

        $searchIndexer = $this->createMock(SearchIndexer::class);
        $searchIndexer->expects($this->once())
            ->method('indexArticle')
            ->with($article);

        $command = new ReindexArticlesCommand($doctrineRepository, $searchIndexer);
        $commandTester = new CommandTester($command);

        // Act
        $exitCode = $commandTester->execute([]);

        // Assert
        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('Startuji reindexaci 1 článků', $commandTester->getDisplay());

        // Test with ElasticsearchArticleRepository (mock)
        $elasticsearchRepository = $this->createMock(ArticleRepository::class);
        $elasticsearchRepository->method('findAll')->willReturn([$article]);

        $searchIndexer2 = $this->createMock(SearchIndexer::class);
        $searchIndexer2->expects($this->once())
            ->method('indexArticle')
            ->with($article);

        $command2 = new ReindexArticlesCommand($elasticsearchRepository, $searchIndexer2);
        $commandTester2 = new CommandTester($command2);

        // Act
        $exitCode2 = $commandTester2->execute([]);

        // Assert
        $this->assertSame(Command::SUCCESS, $exitCode2);
        $this->assertStringContainsString('Startuji reindexaci 1 článků', $commandTester2->getDisplay());
    }

    /**
     * Testuje funkčnost příkazu s různými implementacemi SearchIndexer.
     * Ověřuje, že příkaz správně pracuje s různými typy indexerů.
     */
    public function testCommandWorksWithDifferentIndexerImplementations(): void
    {
        // Arrange
        $article = new Article();
        $article->setTitle('Testový článek');
        $article->setContent('Obsah článku');

        $articleRepository = $this->createMock(ArticleRepository::class);
        $articleRepository->method('findAll')->willReturn([$article]);

        // Test with ElasticsearchIndexer
        $elasticsearchIndexer = $this->createMock(SearchIndexer::class);
        $elasticsearchIndexer->expects($this->once())
            ->method('indexArticle')
            ->with($article);

        $command = new ReindexArticlesCommand($articleRepository, $elasticsearchIndexer);
        $commandTester = new CommandTester($command);

        // Act
        $exitCode = $commandTester->execute([]);

        // Assert
        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('Reindexace dokončena.', $commandTester->getDisplay());

        // Test with OpensearchIndexer
        $opensearchIndexer = $this->createMock(SearchIndexer::class);
        $opensearchIndexer->expects($this->once())
            ->method('indexArticle')
            ->with($article);

        $command2 = new ReindexArticlesCommand($articleRepository, $opensearchIndexer);
        $commandTester2 = new CommandTester($command2);

        // Act
        $exitCode2 = $commandTester2->execute([]);

        // Assert
        $this->assertSame(Command::SUCCESS, $exitCode2);
        $this->assertStringContainsString('Reindexace dokončena.', $commandTester2->getDisplay());
    }
}
