<?php

declare(strict_types=1);

namespace App\Tests\PolygraphyDigest\Command;

use App\PolygraphyDigest\Command\ReindexArticlesCommand;
use App\PolygraphyDigest\Repository\ArticleRepository;
use App\PolygraphyDigest\Service\Search\SearchIndexer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Testovací třída pro ověření konfigurace příkazu ReindexArticlesCommand.
 * Zaměřuje se na správné nastavení názvu, popisu a validaci vstupních argumentů.
 */
class ReindexArticlesCommandConfigurationTest extends TestCase
{
    /**
     * Testuje základní konfiguraci příkazu (název a popis).
     * Ověřuje, že příkaz má správně nastavený název 'polygraphy:search:reindex' a odpovídající popis.
     */
    public function testCommandConfiguration(): void
    {
        $articleRepository = $this->createMock(ArticleRepository::class);
        $searchIndexer = $this->createMock(SearchIndexer::class);

        $command = new ReindexArticlesCommand($articleRepository, $searchIndexer);

        $this->assertSame('polygraphy:search:reindex', $command->getName());
        $this->assertSame('Reindexuje všechny články z databáze do Elasticsearch', $command->getDescription());
    }

    /**
     * Testuje chování příkazu při zadání nepodporovaných argumentů.
     * Ověřuje, že příkaz je nakonfigurován tak, aby nepřijímal žádné argumenty, a vyhodí výjimku při pokusu je předat.
     */
    public function testCommandDoesNotAcceptArguments(): void
    {
        $articleRepository = $this->createMock(ArticleRepository::class);
        $searchIndexer = $this->createMock(SearchIndexer::class);

        $command = new ReindexArticlesCommand($articleRepository, $searchIndexer);
        $commandTester = new CommandTester($command);

        $this->expectException(InvalidArgumentException::class);
        
        // Pokus o předání neexistujícího argumentu 'invalid_argument'
        $commandTester->execute(['invalid_argument' => 'value']);
    }

    /**
     * Testuje chování příkazu při zadání nepodporovaných přepínačů (options).
     * Ověřuje, že příkaz vyhodí výjimku při pokusu o použití nedefinovaného přepínače.
     */
    public function testCommandDoesNotAcceptOptions(): void
    {
        $articleRepository = $this->createMock(ArticleRepository::class);
        $searchIndexer = $this->createMock(SearchIndexer::class);

        $command = new ReindexArticlesCommand($articleRepository, $searchIndexer);
        $commandTester = new CommandTester($command);

        $this->expectException(InvalidOptionException::class);
        
        // Pokus o předání neexistujícího přepínače '--invalid-option'
        $commandTester->execute(['--invalid-option' => true]);
    }
}
