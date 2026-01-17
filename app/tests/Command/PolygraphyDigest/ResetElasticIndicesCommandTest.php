<?php

declare(strict_types=1);

namespace App\Tests\Command\PolygraphyDigest;

use App\PolygraphyDigest\Command\ResetElasticIndicesCommand;
use App\PolygraphyDigest\Service\Search\ElasticsearchClientInterface;
use App\PolygraphyDigest\Service\Search\IndexInitializer;
use Elastic\Elasticsearch\Endpoints\Indices;
use Elastic\Elasticsearch\Exception\ClientResponseException;
use Elastic\Elasticsearch\Response\Elasticsearch;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class ResetElasticIndicesCommandTest extends KernelTestCase
{
    /** @var ElasticsearchClientInterface&MockObject */
    private ElasticsearchClientInterface $client;

    /** @var IndexInitializer&MockObject */
    private IndexInitializer $indexInitializer;

    private CommandTester $commandTester;

    /**
     * @description Nastaví prostředí pro testy. Vytvoří mocky závislostí a zaregistruje příkaz.
     */
    protected function setUp(): void
    {
        $this->client = $this->createMock(ElasticsearchClientInterface::class);
        $this->indexInitializer = $this->createMock(IndexInitializer::class);

        $command = new ResetElasticIndicesCommand($this->client, $this->indexInitializer);

        $kernel = self::bootKernel();
        $application = new Application($kernel);
        $application->addCommand($command);

        $command = $application->find('polygraphy:search:reset');
        $this->commandTester = new CommandTester($command);
    }

    /**
     * @description Otestuje scénář, kdy uživatel odmítne potvrzení smazání indexů.
     * Očekává se, že se neprovede žádná akce s Elasticsearchem.
     */
    public function testExecuteUserDenies(): void
    {
        // Assert: Ensure delete is never called
        $this->client->expects($this->never())
            ->method('indices');

        $this->indexInitializer->expects($this->never())
            ->method('initializeArticlesIndex');
        $this->indexInitializer->expects($this->never())
            ->method('initializeProductsIndex');

        // Act
        $this->commandTester->execute([], ['interactive' => true]);

        // Input "no" to the confirmation question
        $this->commandTester->setInputs(['no']);

        // Assert
        $this->commandTester->assertCommandIsSuccessful();
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Akce zrušena.', $output);
    }

    /**
     * @description Otestuje scénář, kdy uživatel potvrdí smazání indexů.
     * Očekává se, že proběhne smazání a následná inicializace indexů.
     */
    public function testExecuteUserConfirms(): void
    {
        // Arrange
        $indices = $this->createMock(Indices::class);

        $indices->expects($this->exactly(2))
            ->method('delete')
            ->willReturnCallback(function (array $params) {
                $this->assertArrayHasKey('index', $params);
                $this->assertContains($params['index'], ['polygraphy_articles', 'polygraphy_products']);
                /** @var Elasticsearch $response */
                $response = $this->createMock(Elasticsearch::class);
                return $response;
            });

        $this->client->expects($this->exactly(2))
            ->method('indices')
            ->willReturn($indices);

        $this->indexInitializer->expects($this->once())
            ->method('initializeArticlesIndex');
        $this->indexInitializer->expects($this->once())
            ->method('initializeProductsIndex');

        // Act
        $this->commandTester->setInputs(['yes']);
        $this->commandTester->execute([]);

        // Assert
        $this->commandTester->assertCommandIsSuccessful();
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Indexy byly úspěšně resetovány a znovu vytvořeny.', $output);
    }

    /**
     * @description Otestuje scénář s použitím přepínače --force.
     * Očekává se, že příkaz proběhne bez dotazu na potvrzení a úspěšně resetuje indexy.
     */
    public function testExecuteForceMode(): void
    {
        // Arrange
        $indices = $this->createMock(Indices::class);

        $indices->expects($this->exactly(2))
            ->method('delete')
            ->willReturn($this->createMock(Elasticsearch::class));

        $this->client->expects($this->exactly(2))
            ->method('indices')
            ->willReturn($indices);

        $this->indexInitializer->expects($this->once())
            ->method('initializeArticlesIndex');
        $this->indexInitializer->expects($this->once())
            ->method('initializeProductsIndex');

        // Act
        // Neposkytujeme žádný vstup (setInputs), protože se neočekává dotaz na potvrzení
        $this->commandTester->execute(['--force' => true]);

        // Assert
        $this->commandTester->assertCommandIsSuccessful();
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Indexy byly úspěšně resetovány a znovu vytvořeny.', $output);
    }

    /**
     * @description Otestuje scénář, kdy indexy v Elasticsearch neexistují (chyba 404).
     * Očekává se, že příkaz tuto chybu zachytí, vypíše informativní zprávu a pokračuje v práci.
     */
    public function testExecuteIndexNotFound(): void
    {
        // Arrange
        $indices = $this->createMock(Indices::class);

        $response = $this->createMock(Elasticsearch::class);
        $response->method('getStatusCode')->willReturn(404);

        $exception = new ClientResponseException('Not Found', 404);
        $exception->setResponse($response);

        $indices->expects($this->exactly(2))
            ->method('delete')
            ->willThrowException($exception);

        $this->client->expects($this->exactly(2))
            ->method('indices')
            ->willReturn($indices);

        $this->indexInitializer->expects($this->once())
            ->method('initializeArticlesIndex');
        $this->indexInitializer->expects($this->once())
            ->method('initializeProductsIndex');

        // Act
        $this->commandTester->execute(['--force' => true]);

        // Assert
        $this->commandTester->assertCommandIsSuccessful();
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Index polygraphy_articles neexistuje, přeskakuji mazání.', $output);
        $this->assertStringContainsString('Index polygraphy_products neexistuje, přeskakuji mazání.', $output);
        $this->assertStringContainsString('Indexy byly úspěšně resetovány a znovu vytvořeny.', $output);
    }

    /**
     * @description Otestuje scénář, kdy při mazání indexů dojde ke kritické chybě (např. chyba 500).
     * Očekává se, že příkaz selže a vypíše chybové hlášení.
     */
    public function testExecuteCriticalErrorDuringDeletion(): void
    {
        // Arrange
        $indices = $this->createMock(Indices::class);

        $response = $this->createMock(Elasticsearch::class);
        $response->method('getStatusCode')->willReturn(500);

        $exception = new ClientResponseException('Internal Server Error', 500);
        $exception->setResponse($response);

        $indices->expects($this->once()) // Selže hned u prvního indexu
            ->method('delete')
            ->willThrowException($exception);

        $this->client->expects($this->once())
            ->method('indices')
            ->willReturn($indices);

        // Act
        $result = $this->commandTester->execute(['--force' => true]);

        // Assert
        $this->assertSame(Command::FAILURE, $result);
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Chyba: Internal Server Error', $output);
    }

    /**
     * @description Otestuje scénář, kdy dojde k chybě během vytváření nových indexů.
     * Očekává se, že příkaz zachytí výjimku z IndexInitializeru, vypíše chybu a vrátí status selhání.
     */
    public function testExecuteErrorDuringInitialization(): void
    {
        // Arrange
        $indices = $this->createMock(Indices::class);
        $indices->method('delete')->willReturn($this->createMock(Elasticsearch::class));

        $this->client->method('indices')->willReturn($indices);

        // Simulujeme chybu při inicializaci článků
        $this->indexInitializer->expects($this->once())
            ->method('initializeArticlesIndex')
            ->willThrowException(new \Exception('Initialization Failed'));

        // Act
        $result = $this->commandTester->execute(['--force' => true]);

        // Assert
        $this->assertSame(\Symfony\Component\Console\Command\Command::FAILURE, $result);
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Chyba: Initialization Failed', $output);
    }
}
