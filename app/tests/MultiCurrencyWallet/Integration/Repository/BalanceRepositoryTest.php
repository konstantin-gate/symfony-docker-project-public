<?php

declare(strict_types=1);

namespace App\Tests\MultiCurrencyWallet\Integration\Repository;

use App\MultiCurrencyWallet\Entity\Balance;
use App\MultiCurrencyWallet\Enum\CurrencyEnum;
use App\MultiCurrencyWallet\Repository\BalanceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Integrační testy pro BalanceRepository.
 * Ověřuje správnou funkčnost databázových operací nad entitou Balance,
 * zejména vyhledávání pomocí výčtového typu CurrencyEnum.
 */
class BalanceRepositoryTest extends KernelTestCase
{
    /** @var EntityManagerInterface Správce entit pro práci s databází. */
    private EntityManagerInterface $entityManager;

    /** @var BalanceRepository Testovaný repozitář pro zůstatky. */
    private BalanceRepository $repository;

    /**
     * Inicializace testovacího prostředí před každým testem.
     * Spouští kernel, získává služby z kontejneru a čistí tabulku zůstatků.
     */
    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->repository = $this->entityManager->getRepository(Balance::class);

        // Vyčištění tabulky před začátkem testu
        $this->entityManager->createQuery('DELETE FROM App\MultiCurrencyWallet\Entity\Balance e')->execute();
    }

    /**
     * Úklid po provedení testu.
     * Uzavírá spojení se správcem entit pro uvolnění prostředků.
     */
    protected function tearDown(): void
    {
        parent::tearDown();
        $this->entityManager->close();
    }

    /**
     * Testuje metodu findOneBy s použitím CurrencyEnum.
     * Ověřuje, že repozitář správně namapuje enum na databázový sloupec
     * a vrátí správnou instanci Balance se všemi daty.
     */
    public function testFindOneByCurrencyEnum(): void
    {
        // 1. Příprava dat: Vytvoření a uložení nového zůstatku (např. BTC)
        $currency = CurrencyEnum::BTC;
        $balance = new Balance($currency, '1.23456789');
        $this->entityManager->persist($balance);
        $this->entityManager->flush();

        // Vyčištění identity mapy, abychom vynutili reálný dotaz do databáze
        $this->entityManager->clear();

        // 2. Provedení akce: Vyhledání záznamu podle enumu
        $found = $this->repository->findOneBy(['currency' => $currency]);

        // 3. Ověření výsledků
        $this->assertNotNull($found, 'Zůstatek nebyl v databázi nalezen.');
        $this->assertEquals($currency, $found->getCurrency(), 'Nalezená měna neodpovídá očekávání.');
        $this->assertEquals('1.23456789', $found->getAmount(), 'Částka zůstatku neodpovídá uložené hodnotě.');
    }

    /**
     * Testuje řazení záznamů podle displayOrder.
     */
    public function testFindAllSortedByDisplayOrder(): void
    {
        // 1. Příprava dat s různým pořadím
        $b1 = new Balance(CurrencyEnum::CZK);
        $b1->setDisplayOrder(10);

        $b2 = new Balance(CurrencyEnum::USD);
        $b2->setDisplayOrder(5);

        $b3 = new Balance(CurrencyEnum::EUR);
        $b3->setDisplayOrder(20);

        $this->entityManager->persist($b1);
        $this->entityManager->persist($b2);
        $this->entityManager->persist($b3);
        $this->entityManager->flush();
        $this->entityManager->clear();

        // 2. Provedení akce: findBy s řazením
        $results = $this->repository->findBy([], ['displayOrder' => 'ASC']);

        // 3. Ověření: USD (5), CZK (10), EUR (20)
        $this->assertCount(3, $results);
        $this->assertEquals(CurrencyEnum::USD, $results[0]->getCurrency());
        $this->assertEquals(CurrencyEnum::CZK, $results[1]->getCurrency());
        $this->assertEquals(CurrencyEnum::EUR, $results[2]->getCurrency());
    }
}
