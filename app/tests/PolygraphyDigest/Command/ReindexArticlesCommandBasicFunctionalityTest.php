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

/**
 * Testy základních funkcionalit příkazu ReindexArticlesCommand.
 * Ověřují správné chování při různých vstupních datech a scénářích.
 */
class ReindexArticlesCommandBasicFunctionalityTest extends TestCase
{
    /**
     * Testuje spuštění příkazu s prázdným seznamem článků.
     * Ověřuje, že příkaz úspěšně dokončí bez chyb.
     */
    public function testCommandExecutionWithEmptyArticleList(): void
    {
        // Arrange
        $articleRepository = $this->createMock(ArticleRepository::class);
        $articleRepository->method('findAll')->willReturn([]);

        $searchIndexer = $this->createMock(SearchIndexer::class);

        $command = new ReindexArticlesCommand($articleRepository, $searchIndexer);
        $commandTester = new CommandTester($command);

        // Act
        $exitCode = $commandTester->execute([]);

        // Assert
        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('Startuji reindexaci 0 článků', $commandTester->getDisplay());
        $this->assertStringContainsString('Reindexace dokončena.', $commandTester->getDisplay());
    }

    /**
     * Testuje spuštění příkazu s jedním článkem.
     * Ověřuje, že příkaz úspěšně indexuje jeden článek.
     */
    public function testCommandExecutionWithSingleArticle(): void
    {
        // Arrange
        $article = new Article();
        $article->setTitle('Testový článek');
        $article->setContent('Obsah článku');

        $articleRepository = $this->createMock(ArticleRepository::class);
        $articleRepository->method('findAll')->willReturn([$article]);

        $searchIndexer = $this->createMock(SearchIndexer::class);
        $searchIndexer->expects($this->once())
            ->method('indexArticle')
            ->with($article);

        $command = new ReindexArticlesCommand($articleRepository, $searchIndexer);
        $commandTester = new CommandTester($command);

        // Act
        $exitCode = $commandTester->execute([]);

        // Assert
        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('Startuji reindexaci 1 článků', $commandTester->getDisplay());
        $this->assertStringContainsString('Reindexace dokončena.', $commandTester->getDisplay());
    }

    /**
     * Testuje spuštění příkazu s více články.
     * Ověřuje, že příkaz úspěšně indexuje všechny články.
     */
    public function testCommandExecutionWithMultipleArticles(): void
    {
        // Arrange
        $article1 = new Article();
        $article1->setTitle('První článek');
        $article1->setContent('Obsah prvního článku');

        $article2 = new Article();
        $article2->setTitle('Druhý článek');
        $article2->setContent('Obsah druhého článku');

        $article3 = new Article();
        $article3->setTitle('Třetí článek');
        $article3->setContent('Obsah třetího článku');

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

        // Act
        $exitCode = $commandTester->execute([]);

        // Assert
        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('Startuji reindexaci 3 článků', $commandTester->getDisplay());
        $this->assertStringContainsString('Reindexace dokončena.', $commandTester->getDisplay());
    }
}
