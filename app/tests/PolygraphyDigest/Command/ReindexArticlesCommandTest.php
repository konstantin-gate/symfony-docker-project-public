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
 * Komplexní testy pro příkaz ReindexArticlesCommand.
 * Sjednocuje testy základní funkcionality, práce s repozitářem a zpracování chyb.
 */
class ReindexArticlesCommandTest extends TestCase
{
    private ArticleRepository|MockObject $articleRepository;
    private SearchIndexer|MockObject $searchIndexer;
    private CommandTester $commandTester;
    private ReindexArticlesCommand $command;

    protected function setUp(): void
    {
        $this->articleRepository = $this->createMock(ArticleRepository::class);
        $this->searchIndexer = $this->createMock(SearchIndexer::class);

        $this->command = new ReindexArticlesCommand(
            $this->articleRepository,
            $this->searchIndexer
        );
        $this->commandTester = new CommandTester($this->command);
    }

    /**
     * Testuje spuštění příkazu s prázdným seznamem článků.
     * Ověřuje, že příkaz úspěšně dokončí bez chyb a nevolá indexer.
     */
    public function testExecuteWithEmptyDatabase(): void
    {
        $this->articleRepository->expects($this->once())
            ->method('findAll')
            ->willReturn([]);

        $this->searchIndexer->expects($this->never())
            ->method('indexArticle');

        $exitCode = $this->commandTester->execute([]);
        $output = $this->commandTester->getDisplay();

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('Startuji reindexaci 0 článků', $output);
        $this->assertStringContainsString('Reindexace dokončena.', $output);
    }

    /**
     * Testuje úspěšný průběh reindexace (Happy Path) s více články.
     * Ověřuje, že všechny články jsou předány indexeru.
     */
    public function testExecuteHappyPathWithMultipleArticles(): void
    {
        $article1 = $this->createMock(Article::class);
        $article2 = $this->createMock(Article::class);
        $articles = [$article1, $article2];

        $this->articleRepository->expects($this->once())
            ->method('findAll')
            ->willReturn($articles);

        // Ověříme, že se indexer zavolá postupně pro každý článek
        $matcher = $this->exactly(2);
        $this->searchIndexer->expects($matcher)
            ->method('indexArticle')
            ->willReturnCallback(function (Article $article) use ($matcher, $article1, $article2) {
                match ($matcher->numberOfInvocations()) {
                    1 => $this->assertSame($article1, $article),
                    2 => $this->assertSame($article2, $article),
                    default => throw new \LogicException('Unexpected call count'),
                };
            });

        $exitCode = $this->commandTester->execute([]);
        $output = $this->commandTester->getDisplay();

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('Startuji reindexaci 2 článků', $output);
        $this->assertStringContainsString('Reindexace dokončena.', $output);
    }

    /**
     * Testuje chování aplikace při selhání načítání z databáze.
     * Příkaz by měl skončit s kódem SUCCESS (graceful shutdown) a vypsat chybu.
     */
    public function testExecuteHandlesDatabaseFailure(): void
    {
        $exceptionMessage = 'Connection refused';
        $this->articleRepository->expects($this->once())
            ->method('findAll')
            ->willThrowException(new \RuntimeException($exceptionMessage));

        $this->searchIndexer->expects($this->never())
            ->method('indexArticle');

        $exitCode = $this->commandTester->execute([]);
        $output = $this->commandTester->getDisplay();

        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString('Chyba při načítání článků z databáze', $output);
        $this->assertStringContainsString($exceptionMessage, $output);
    }

    /**
     * Testuje částečné selhání indexace.
     * Pokud jeden článek selže, ostatní by se měly zpracovat.
     */
    public function testExecuteHandlesPartialIndexingFailure(): void
    {
        $articleSuccess = $this->createMock(Article::class);
        
        $articleFail = $this->createMock(Article::class);
        $uuid = Uuid::v4();
        $articleFail->method('getId')->willReturn($uuid);

        $this->articleRepository->expects($this->once())
            ->method('findAll')
            ->willReturn([$articleSuccess, $articleFail]);

        $this->searchIndexer->expects($this->exactly(2))
            ->method('indexArticle')
            ->willReturnCallback(function (Article $article) use ($articleFail) {
                if ($article === $articleFail) {
                    throw new \RuntimeException('Elasticsearch down');
                }
            });

        $exitCode = $this->commandTester->execute([]);
        $output = $this->commandTester->getDisplay();

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('Chyba při indexaci článku ID ' . $uuid->toRfc4122(), $output);
        $this->assertStringContainsString('Elasticsearch down', $output);
        $this->assertStringContainsString('Reindexace dokončena.', $output);
    }

    /**
     * Testuje validaci typů v konstruktoru.
     * Ověřuje, že nelze předat null.
     */
    public function testConstructorValidation(): void
    {
        $this->expectException(\TypeError::class);
        new ReindexArticlesCommand(null, $this->searchIndexer);
    }

    /**
     * Testuje konfiguraci příkazu (název a popis).
     */
    public function testCommandConfiguration(): void
    {
        $this->assertSame('polygraphy:search:reindex', $this->command->getName());
        $this->assertStringContainsString('Reindexuje všechny články', $this->command->getDescription());
    }
}
