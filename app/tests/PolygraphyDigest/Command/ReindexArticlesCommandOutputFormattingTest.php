<?php

namespace App\Tests\PolygraphyDigest\Command;

use App\PolygraphyDigest\Command\ReindexArticlesCommand;
use App\PolygraphyDigest\Repository\ArticleRepository;
use App\PolygraphyDigest\Service\Search\SearchIndexer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Třída pro testování formátování výstupních zpráv příkazu ReindexArticlesCommand.
 * Testuje, zda jsou tituly zpráv správně formátovány podle specifikace.
 */
class ReindexArticlesCommandOutputFormattingTest extends TestCase
{
    /**
     * Testuje, zda titulek zprávy obsahuje správný formát.
     * Zjišťuje, zda titulek obsahuje očekávaný český text a počet článků.
     */
    public function testTitleMessageFormatting(): void
    {
        $articleRepository = $this->createMock(ArticleRepository::class);
        $articleRepository->method('findAll')->willReturn([]);

        $searchIndexer = $this->createMock(SearchIndexer::class);

        $command = new ReindexArticlesCommand($articleRepository, $searchIndexer);
        $commandTester = new CommandTester($command);

        $commandTester->execute([]);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Startuji reindexaci 0 článků', $output);
    }

    /**
     * Testuje, zda titulek zprávy je správně zobrazen.
     * Zjišťuje, zda je titulek součástí výstupu a odpovídá formátu SymfonyStyle.
     */
    public function testTitleMessageLineBreak(): void
    {
        $articleRepository = $this->createMock(ArticleRepository::class);
        $articleRepository->method('findAll')->willReturn([]);

        $searchIndexer = $this->createMock(SearchIndexer::class);

        $command = new ReindexArticlesCommand($articleRepository, $searchIndexer);
        $commandTester = new CommandTester($command);

        $commandTester->execute([]);

        $output = $commandTester->getDisplay();
        
        // SymfonyStyle title is usually surrounded by spaces/newlines
        $this->assertStringContainsString('Startuji reindexaci 0 článků', $output);
        
        // Ověříme, že pod titulkem je oddělovač (SymfonyStyle standard)
        $this->assertStringContainsString('============================', $output);
    }
}