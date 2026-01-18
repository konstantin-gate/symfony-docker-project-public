<?php

declare(strict_types=1);

namespace App\Tests\PolygraphyDigest\Command;

use App\PolygraphyDigest\Command\ReindexArticlesCommand;
use App\PolygraphyDigest\Repository\ArticleRepository;
use App\PolygraphyDigest\Service\Search\SearchIndexer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Testovací třída pro příkaz ReindexArticlesCommand.
 *
 * Tato třída testuje funkčnost příkazu pro reindexaci článků v systému.
 * Ověřuje správné inicializování příkazu, jeho úspěšné provedení,
 * správné výstupní zprávy a inicializaci progress baru.
 */
class ReindexArticlesCommandTest extends TestCase
{
    private ArticleRepository $articleRepository;
    private ReindexArticlesCommand $command;
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $this->articleRepository = $this->createMock(ArticleRepository::class);
        $searchIndexer = $this->createMock(SearchIndexer::class);
        $this->command = new ReindexArticlesCommand(
            $this->articleRepository,
            $searchIndexer
        );
        $this->commandTester = new CommandTester($this->command);
    }

    /**
     * Testuje, že příkaz lze vytvořit s platnými závislostmi
     */
    public function testCommandCanBeInstantiated(): void
    {
        $this->assertInstanceOf(
            ReindexArticlesCommand::class,
            $this->command
        );
    }

    /**
     * Testuje, že příkaz vrátí SUCCESS (0) při spuštění s platnými daty
     */
    public function testCommandReturnsSuccess(): void
    {
        $articles = [];
        $this->articleRepository->method('findAll')
            ->willReturn($articles);

        $exitCode = $this->commandTester->execute([]);

        $this->assertSame(0, $exitCode);
    }

    /**
     * Testuje, že příkaz vypisuje správnou zprávu s titulem
     */
    public function testCommandOutputsCorrectTitle(): void
    {
        $articles = [];
        $this->articleRepository->method('findAll')
            ->willReturn($articles);

        $this->commandTester->execute([]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString(
            'Startuji reindexaci 0 článků',
            $output
        );
    }

    /**
     * Testuje, že příkaz vypisuje správnou zprávu o úspěchu
     */
    public function testCommandOutputsSuccessMessage(): void
    {
        $articles = [];
        $this->articleRepository->method('findAll')
            ->willReturn($articles);

        $this->commandTester->execute([]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString(
            'Reindexace dokončena.',
            $output
        );
    }

    /**
     * Testuje, že příkaz správně inicializuje progress bar
     */
    public function testCommandInitializesProgressBar(): void
    {
        $articles = [];
        $this->articleRepository->method('findAll')
            ->willReturn($articles);

        $this->commandTester->execute([]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString(
            '0 [░░░░░░░░░░░░░░░░░░░░░░░░░░░░]',
            $output
        );
    }
}
