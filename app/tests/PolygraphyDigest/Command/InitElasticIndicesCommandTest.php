<?php

declare(strict_types=1);

namespace App\Tests\PolygraphyDigest\Command;

use App\PolygraphyDigest\Command\InitElasticIndicesCommand;
use App\PolygraphyDigest\Service\Search\IndexInitializer;
use Elastic\Elasticsearch\Exception\ClientResponseException;
use Elastic\Elasticsearch\Exception\MissingParameterException;
use Elastic\Elasticsearch\Exception\ServerResponseException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Unit testy pro příkaz inicializace Elasticsearch indexů pro modul Polygraphy Digest.
 */
class InitElasticIndicesCommandTest extends TestCase
{
    private IndexInitializer $indexInitializer;
    private InitElasticIndicesCommand $command;

    /**
     * Nastavení testovacího prostředí před každým testem (mockování závislostí).
     */
    protected function setUp(): void
    {
        $this->indexInitializer = $this->createMock(IndexInitializer::class);
        $this->command = new InitElasticIndicesCommand($this->indexInitializer);
    }

    /**
     * Testuje úspěšné provedení příkazu, kdy jsou oba indexy úspěšně inicializovány.
     */
    public function testExecuteSuccess(): void
    {
        $this->indexInitializer->expects($this->once())
            ->method('initializeArticlesIndex');

        $this->indexInitializer->expects($this->once())
            ->method('initializeProductsIndex');

        $commandTester = new CommandTester($this->command);
        $exitCode = $commandTester->execute([]);

        $this->assertSame(0, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Inicializace indexů Elasticsearch', $output);
        $this->assertStringContainsString('Vytvářím index polygraphy_articles...', $output);
        $this->assertStringContainsString('Index polygraphy_articles byl úspěšně vytvořen', $output);
        $this->assertStringContainsString('Vytvářím index polygraphy_products...', $output);
        $this->assertStringContainsString('Index polygraphy_products byl úspěšně vytvořen', $output);
    }

    /**
     * Testuje zpracování chyby klienta (ClientResponseException) při komunikaci s Elasticsearch.
     */
    public function testExecuteClientResponseException(): void
    {
        $exception = $this->getMockBuilder(ClientResponseException::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->indexInitializer->expects($this->once())
            ->method('initializeArticlesIndex')
            ->willThrowException($exception);

        $commandTester = new CommandTester($this->command);
        $exitCode = $commandTester->execute([]);

        $this->assertSame(1, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Chyba při komunikaci s Elasticsearch:', $output);
    }

    /**
     * Testuje zpracování chyby serveru (ServerResponseException) při komunikaci s Elasticsearch.
     */
    public function testExecuteServerResponseException(): void
    {
        $exception = $this->getMockBuilder(ServerResponseException::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->indexInitializer->expects($this->once())
            ->method('initializeProductsIndex')
            ->willThrowException($exception);

        $this->indexInitializer->expects($this->once())
            ->method('initializeArticlesIndex');

        $commandTester = new CommandTester($this->command);
        $exitCode = $commandTester->execute([]);

        $this->assertSame(1, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Chyba při komunikaci s Elasticsearch:', $output);
    }

    /**
     * Testuje zpracování chyby chybějícího parametru (MissingParameterException) při volání API Elasticsearch.
     */
    public function testExecuteMissingParameterException(): void
    {
        $exception = new MissingParameterException('Missing param');

        $this->indexInitializer->expects($this->once())
            ->method('initializeArticlesIndex')
            ->willThrowException($exception);

        $commandTester = new CommandTester($this->command);
        $exitCode = $commandTester->execute([]);

        $this->assertSame(1, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Chyba při komunikaci s Elasticsearch: Missing param', $output);
    }

    /**
     * Testuje zpracování neočekávané obecné výjimky během provádění příkazu.
     */
    public function testExecuteGenericException(): void
    {
        $exception = new \Exception('Unexpected error');

        $this->indexInitializer->expects($this->once())
            ->method('initializeArticlesIndex')
            ->willThrowException($exception);

        $commandTester = new CommandTester($this->command);
        $exitCode = $commandTester->execute([]);

        $this->assertSame(1, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Neočekávaná chyba: Unexpected error', $output);
    }
}
