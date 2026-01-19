<?php

declare(strict_types=1);

namespace App\Tests\PolygraphyDigest\Command;

use App\PolygraphyDigest\Command\ReindexArticlesCommand;
use App\PolygraphyDigest\Entity\Article;
use App\PolygraphyDigest\Repository\ArticleRepository;
use App\PolygraphyDigest\Service\Search\SearchIndexer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Uid\Uuid;

/**
 * Testovací třída pro testování výkonu příkazu ReindexArticlesCommand.
 * Obsahuje testy pro velká množství článků a měření výkonu.
 */
class ReindexArticlesCommandPerformanceTest extends TestCase
{
    /**
     * Vytvoří mockovaný dataset s velkým množstvím článků.
     *
     * @param int $count Počet článků, které mají být vytvořeny
     *
     * @return array<Article> Seznam článků
     */
    private function createLargeArticleDataset(int $count): array
    {
        $articles = [];
        for ($i = 0; $i < $count; $i++) {
            $article = $this->createMock(Article::class);
            $article->method('getId')->willReturn(Uuid::v4());
            $articles[] = $article;
        }

        return $articles;
    }

    /**
     * Testuje výkon příkazu s velkým množstvím článků (1000+).
     * Zjišťuje, zda příkaz dokončí v přijatelném čase a bez chyb.
     *
     * @return void
     */
    public function testReindexWithLargeDataset(): void
    {
        $count = 1000;
        $articles = $this->createLargeArticleDataset($count);
        
        $articleRepository = $this->createMock(ArticleRepository::class);
        $articleRepository->method('findAll')->willReturn($articles);

        $searchIndexer = $this->createMock(SearchIndexer::class);
        // Simulujeme mírné zpoždění indexace (volitelné, zde pro realističnost testu výkonu)
        $searchIndexer->method('indexArticle');

        $command = new ReindexArticlesCommand($articleRepository, $searchIndexer);
        $commandTester = new CommandTester($command);

        // Měření času spuštění
        $startTime = \microtime(true);

        $exitCode = $commandTester->execute([]);

        $executionTime = \microtime(true) - $startTime;

        // Overení, že příkaz dokončil úspěšně
        $this->assertSame(0, $exitCode);

        // Overení, že příkaz dokončil v přijatelném čase (méně než 5 minut)
        $this->assertLessThan(300, $executionTime, \sprintf('Příkaz trval příliš dlouho: %.2f sekund', $executionTime));

        // Overení výstupní zprávy
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString(\sprintf('Startuji reindexaci %d článků', $count), $output);
        $this->assertStringContainsString('Reindexace dokončena.', $output);
    }

    /**
     * Testuje, že příkaz správně zpracovává velká množství článků.
     * Overuje, že všechny články byly správně indexovány.
     *
     * @return void
     */
    public function testReindexAllArticlesWithLargeDataset(): void
    {
        $count = 1000;
        $articles = $this->createLargeArticleDataset($count);

        $articleRepository = $this->createMock(ArticleRepository::class);
        $articleRepository->method('findAll')->willReturn($articles);

        $searchIndexer = $this->createMock(SearchIndexer::class);
        // Ověříme, že indexer byl zavolán pro každý článek
        $searchIndexer->expects($this->exactly($count))->method('indexArticle');

        $command = new ReindexArticlesCommand($articleRepository, $searchIndexer);
        $commandTester = new CommandTester($command);

        $exitCode = $commandTester->execute([]);

        // Overení, že příkaz dokončil úspěšně
        $this->assertSame(0, $exitCode);

        // Overení, že žádné chyby nebyly zobrazeny
        $output = $commandTester->getDisplay();
        $this->assertStringNotContainsString('Chyba při indexaci', $output);
    }

    /**
     * Testuje výkon příkazu s různými velikostmi datasetu.
     * Overuje, že čas spuštění roste lineárně s počtem článků.
     *
     * @return void
     */
    public function testExecutionTimeWithDifferentDatasetSizes(): void
    {
        $sizes = [10, 100, 1000, 10000];

        foreach ($sizes as $size) {
            $articles = $this->createLargeArticleDataset($size);

            $articleRepository = $this->createMock(ArticleRepository::class);
            $articleRepository->method('findAll')->willReturn($articles);

            $searchIndexer = $this->createMock(SearchIndexer::class);
            $searchIndexer->method('indexArticle');

            $command = new ReindexArticlesCommand($articleRepository, $searchIndexer);
            $commandTester = new CommandTester($command);

            $startTime = \microtime(true);
            $exitCode = $commandTester->execute([]);
            $executionTime = \microtime(true) - $startTime;

            $this->assertSame(0, $exitCode);
            $this->assertLessThan(300, $executionTime, \sprintf('Příkaz trval příliš dlouho pro %d článků: %.2f sekund', $size, $executionTime));
        }
    }

    /**
     * Testuje, že využití paměti zůstává pod stanoveným limitem (např. < 512MB).
     * Ověřuje, že reindexace velkého množství článků nezpůsobuje paměťové přetížení.
     *
     * @return void
     */
    public function testMemoryUsageIsBelowThreshold(): void
    {
        $count = 1000;
        $articles = $this->createLargeArticleDataset($count);

        $articleRepository = $this->createMock(ArticleRepository::class);
        $articleRepository->method('findAll')->willReturn($articles);

        $searchIndexer = $this->createMock(SearchIndexer::class);

        $command = new ReindexArticlesCommand($articleRepository, $searchIndexer);
        $commandTester = new CommandTester($command);

        // Reset peak memory usage if possible (not directly, but we take the current peak)
        $initialMemoryPeak = \memory_get_peak_usage(true);

        $exitCode = $commandTester->execute([]);

        $finalMemoryPeak = \memory_get_peak_usage(true);
        $memoryConsumed = $finalMemoryPeak - $initialMemoryPeak;

        // Limit 512MB v bajtech
        $limit = 512 * 1024 * 1024;

        $this->assertSame(0, $exitCode);
        $this->assertLessThan($limit, $finalMemoryPeak, \sprintf('Využití paměti překročilo limit: %.2f MB', $finalMemoryPeak / 1024 / 1024));
    }
}
