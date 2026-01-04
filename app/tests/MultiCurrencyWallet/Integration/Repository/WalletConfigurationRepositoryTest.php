<?php

declare(strict_types=1);

namespace App\Tests\MultiCurrencyWallet\Integration\Repository;

use App\MultiCurrencyWallet\Entity\WalletConfiguration;
use App\MultiCurrencyWallet\Repository\WalletConfigurationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Integrační testy pro WalletConfigurationRepository.
 * Testuje správu globálního nastavení peněženky, zejména metodu pro získání konfigurace,
 * která zajišťuje existenci alespoň jednoho záznamu v databázi.
 */
class WalletConfigurationRepositoryTest extends KernelTestCase
{
    /** @var EntityManagerInterface Správce entit pro práci s databází. */
    private EntityManagerInterface $entityManager;

    /** @var WalletConfigurationRepository Testovaný repozitář pro konfiguraci. */
    private WalletConfigurationRepository $repository;

    /**
     * Inicializace testovacího prostředí před každým testem.
     * Spouští kernel, získává služby a čistí tabulku konfigurace.
     */
    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->repository = $this->entityManager->getRepository(WalletConfiguration::class);

        // Ujistíme se, že je tabulka prázdná před začátkem testu
        $this->entityManager->createQuery('DELETE FROM App\MultiCurrencyWallet\Entity\WalletConfiguration e')->execute();
    }

    /**
     * Úklid po testu.
     * Uzavírá EntityManager.
     */
    protected function tearDown(): void
    {
        parent::tearDown();
        $this->entityManager->close();
    }

    /**
     * Testuje, že metoda getConfiguration() vytvoří novou konfiguraci, pokud tabulka zeje prázdnotou.
     * Ověřuje automatickou persistenci a přiřazení ID.
     */
    public function testGetConfigurationCreatesNewIfEmpty(): void
    {
        // 1. Ověříme, že tabulka je na začátku prázdná
        $count = $this->repository->count([]);
        $this->assertEquals(0, $count, 'Předpoklad selhal: Tabulka WalletConfiguration by měla být prázdná.');

        // 2. Provedení akce: Zavoláme getConfiguration()
        $config = $this->repository->getConfiguration();

        // 3. Ověření výsledků: Instance musí mít ID (byla uložena)
        // assertInstanceOf není potřeba díky return type hintu v repozitáři
        $this->assertNotNull($config->getId(), 'Nová konfigurace by měla mít po uložení ID.');

        // 4. Ověříme, že záznam byl skutečně uložen do databáze
        $countAfter = $this->repository->count([]);
        $this->assertEquals(1, $countAfter, 'Metoda getConfiguration() by měla vytvořit záznam, pokud žádný neexistuje.');
    }

    /**
     * Testuje, že metoda getConfiguration() vrátí již existující záznam a nevytváří duplicity.
     */
    public function testGetConfigurationReturnsExisting(): void
    {
        // 1. Příprava dat: Vytvoříme konfiguraci ručně
        $existingConfig = new WalletConfiguration();
        $this->entityManager->persist($existingConfig);
        $this->entityManager->flush();
        $existingId = $existingConfig->getId();

        // Vyčištění identity mapy pro vynucení reálného dotazu do DB
        $this->entityManager->clear();

        // 2. Provedení akce: Zavoláme getConfiguration()
        $config = $this->repository->getConfiguration();

        // 3. Ověření výsledků: Musíme dostat stejnou konfiguraci (podle ID)
        $this->assertEquals($existingId, $config->getId(), 'Metoda by měla vrátit existující konfiguraci.');

        // 4. Ověříme, že se v databázi nevytvořil nový záznam
        $count = $this->repository->count([]);
        $this->assertEquals(1, $count, 'Neměl by být vytvořen žádný další záznam konfigurace.');
    }
}
