<?php

declare(strict_types=1);

namespace App\Tests\Integration\PolygraphyDigest;

use App\PolygraphyDigest\Service\Search\IndexInitializer;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Integrační testy pro příkaz init_elastic_indices.
 */
class InitElasticIndicesCommandIntegrationTest extends KernelTestCase
{
    /**
     * Testuje úspěšné spuštění příkazu pro inicializaci indexů v kontextu Symfony aplikace.
     *
     * Ověřuje, že příkaz je správně zaregistrován v kontejneru, lze jej najít
     * a že správně komunikuje se službou IndexInitializer (která je zde mockována).
     */
    public function testExecuteSuccess(): void
    {
        // 1. Nastartujeme kernel (boot kernel)
        self::bootKernel();
        
        // 2. Získáme kontejner
        $container = static::getContainer();

        // 3. Vytvoříme mock pro IndexInitializer
        $indexInitializer = $this->createMock(IndexInitializer::class);
        
        // Očekáváme volání obou inicializačních metod
        $indexInitializer->expects($this->once())->method('initializeArticlesIndex');
        $indexInitializer->expects($this->once())->method('initializeProductsIndex');

        // 4. Nahradíme skutečnou službu v testovacím kontejneru naším mockem
        // Poznámka: V testovacím prostředí jsou služby veřejné (public), takže je můžeme nahradit.
        $container->set(IndexInitializer::class, $indexInitializer);

        // 5. Vytvoříme instanci aplikace a najdeme příkaz
        $application = new Application(self::$kernel);
        $command = $application->find('polygraphy:search:init');
        
        // 6. Spustíme příkaz pomocí CommandTester
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

        // 7. Ověříme výsledek
        $commandTester->assertCommandIsSuccessful();
        
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Vytvářím index polygraphy_articles...', $output);
        $this->assertStringContainsString('Index polygraphy_articles byl úspěšně vytvořen', $output);
        $this->assertStringContainsString('Vytvářím index polygraphy_products...', $output);
        $this->assertStringContainsString('Index polygraphy_products byl úspěšně vytvořen', $output);
    }
}
