<?php

declare(strict_types=1);

namespace App\Tests\PolygraphyDigest\Command;

use App\PolygraphyDigest\Command\ReindexArticlesCommand;
use App\PolygraphyDigest\Repository\ArticleRepository;
use App\PolygraphyDigest\Service\Search\SearchIndexer;
use App\PolygraphyDigest\Entity\Article;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Uid\Uuid;

/**
 * Testovací třída pro testování sledování průběhu (progress tracking) v příkazu ReindexArticlesCommand.
 * Obsahuje testy pro inicializaci, aktualizaci a dokončení progress baru.
 */
class ReindexArticlesCommandProgressTrackingTest extends TestCase
{
    /**
     * Testuje inicializaci progress baru s nulovým počtem článků.
     * Zjišťuje, zda je progress bar správně inicializován s počtem 0 a okamžitě dokončen.
     */
    public function testProgressBarInitializationWithZeroArticles(): void
    {
        $articleRepository = $this->createMock(ArticleRepository::class);
        $articleRepository->method('findAll')->willReturn([]);

        $searchIndexer = $this->createMock(SearchIndexer::class);

        $command = new ReindexArticlesCommand($articleRepository, $searchIndexer);
        $commandTester = new CommandTester($command);
        $exitCode = $commandTester->execute([]);

        $this->assertSame(0, $exitCode);
        $output = $commandTester->getDisplay();
        
        $this->assertStringContainsString('Startuji reindexaci 0 článků', $output);
        $this->assertStringContainsString('Reindexace dokončena.', $output);
        // U 0 článků progress bar končí okamžitě, ověříme přítomnost bloků úspěchu
        $this->assertStringContainsString('[OK]', $output);
    }

    /**
     * Testuje inicializaci a postup progress baru s jedním článkem.
     * Zjišťuje, zda progress bar dosáhne 100 %.
     */
    public function testProgressBarInitializationWithOneArticle(): void
    {
        $article = $this->createMock(Article::class);
        $article->method('getId')->willReturn(Uuid::v4());

        $articleRepository = $this->createMock(ArticleRepository::class);
        $articleRepository->method('findAll')->willReturn([$article]);

        $searchIndexer = $this->createMock(SearchIndexer::class);
        $searchIndexer->expects($this->once())->method('indexArticle')->with($article);

        $command = new ReindexArticlesCommand($articleRepository, $searchIndexer);
        $commandTester = new CommandTester($command);
        $exitCode = $commandTester->execute([]);

        $this->assertSame(0, $exitCode);
        $output = $commandTester->getDisplay();
        
        // Poznámka: Kód v příkazu používá fixní "článků" i pro 1.
        $this->assertStringContainsString('Startuji reindexaci 1 článků', $output);
        $this->assertStringContainsString('1/1', $output);
        $this->assertStringContainsString('100%', $output);
    }

    /**
     * Testuje aktualizaci progress baru u více článků.
     * Ověřuje, že se progress bar posouvá (indikace kroků).
     */
    public function testProgressBarUpdatesWithMultipleArticles(): void
    {
        $articles = [];
        for ($i = 0; $i < 5; $i++) {
            $article = $this->createMock(Article::class);
            $article->method('getId')->willReturn(Uuid::v4());
            $articles[] = $article;
        }

        $articleRepository = $this->createMock(ArticleRepository::class);
        $articleRepository->method('findAll')->willReturn($articles);

        $searchIndexer = $this->createMock(SearchIndexer::class);
        $searchIndexer->expects($this->exactly(5))->method('indexArticle');

        $command = new ReindexArticlesCommand($articleRepository, $searchIndexer);
        $commandTester = new CommandTester($command);
        $exitCode = $commandTester->execute([]);

        $this->assertSame(0, $exitCode);
        $output = $commandTester->getDisplay();
        
        $this->assertStringContainsString('Startuji reindexaci 5 článků', $output);
        $this->assertStringContainsString('5/5', $output);
        $this->assertStringContainsString('100%', $output);
        $this->assertStringContainsString('[OK] Reindexace dokončena.', $output);
    }

    /**
     * Testuje chování progress baru při výskytu chyby.
     * Ověřuje, že progress bar i přes chybu u jednoho článku doběhne do konce.
     */
    public function testProgressBarWithPartialFailures(): void
    {
        $article1 = $this->createMock(Article::class);
        $article1->method('getId')->willReturn(Uuid::v4());

        $article2 = $this->createMock(Article::class);
        $article2->method('getId')->willReturn(Uuid::v4());

        $articles = [$article1, $article2];

        $articleRepository = $this->createMock(ArticleRepository::class);
        $articleRepository->method('findAll')->willReturn($articles);

        $searchIndexer = $this->createMock(SearchIndexer::class);
        $searchIndexer->expects($this->exactly(2))
            ->method('indexArticle')
            ->willReturnCallback(function ($article) use ($article1) {
                if ($article === $article1) {
                    throw new \RuntimeException('Chyba');
                }
            });

        $command = new ReindexArticlesCommand($articleRepository, $searchIndexer);
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

        $output = $commandTester->getDisplay();

        // I při jedné chybě by progress bar měl být nucen k dokončení (2/2)
        $this->assertStringContainsString('2/2', $output);
        $this->assertStringContainsString('100%', $output);
        $this->assertStringContainsString('Chyba při indexaci článku ID', $output);
        $this->assertStringContainsString('[OK] Reindexace dokončena.', $output);
    }

    /**
     * Testuje chování progress baru při selhání všech článků.
     * Ověřuje, že progress bar je donucen k dokončení (100%) i když všechny položky selhaly.
     */
    public function testProgressBarWithAllFailures(): void
    {
        $articles = [];
        for ($i = 0; $i < 3; $i++) {
            $article = $this->createMock(Article::class);
            $article->method('getId')->willReturn(Uuid::v4());
            $articles[] = $article;
        }

        $articleRepository = $this->createMock(ArticleRepository::class);
        $articleRepository->method('findAll')->willReturn($articles);

        $searchIndexer = $this->createMock(SearchIndexer::class);
        $searchIndexer->expects($this->exactly(3))
            ->method('indexArticle')
            ->willThrowException(new \RuntimeException('Chyba indexace'));

        $command = new ReindexArticlesCommand($articleRepository, $searchIndexer);
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

        $output = $commandTester->getDisplay();

        // I když vše selhalo, na konci se volá progressFinish(), takže bar skočí na 100%
        $this->assertStringContainsString('3/3', $output);
        $this->assertStringContainsString('100%', $output);
        
        $this->assertStringContainsString('[OK] Reindexace dokončena.', $output);
        $this->assertStringContainsString('Startuji reindexaci 3 článků', $output);
    }
}
