<?php

declare(strict_types=1);

namespace App\Tests\MultiCurrencyWallet\Integration\Repository;

use App\MultiCurrencyWallet\Entity\ExchangeRate;
use App\MultiCurrencyWallet\Enum\CurrencyEnum;
use App\MultiCurrencyWallet\Repository\ExchangeRateRepository;
use Brick\Math\BigDecimal;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Testovací třída pro ExchangeRateRepository.
 * Testuje metody pro ukládání a získávání směnných kurzů,
 * včetně vyhledávání nejnovějších kurzů a historických dat.
 */
class ExchangeRateRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private ExchangeRateRepository $repository;

    /**
     * Příprava testovacího prostředí.
     * Inicializuje kernel, entity manager a repozitář.
     * Před každým testem vymaže databázi.
     */
    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->repository = $this->entityManager->getRepository(ExchangeRate::class);

        // Vyčištění DB
        $this->entityManager->createQuery('DELETE App\MultiCurrencyWallet\Entity\ExchangeRate e')->execute();
    }

    /**
     * Úklid po testu.
     * Zavře entity manager.
     */
    protected function tearDown(): void
    {
        parent::tearDown();
        $this->entityManager->close();
    }

    /**
     * Testuje metodu findLatestExchangeRate.
     * Ověřuje, že metoda vrátí nejnovější kurz pro daný pár měn,
     * a to i v případě existence starších záznamů.
     */
    public function testFindLatestExchangeRate(): void
    {
        // 1. Starý kurz
        $oldRate = new ExchangeRate(
            CurrencyEnum::USD,
            CurrencyEnum::EUR,
            BigDecimal::of('0.90'),
            new \DateTimeImmutable('-2 hours')
        );
        $this->entityManager->persist($oldRate);

        // 2. Nový kurz
        $newRate = new ExchangeRate(
            CurrencyEnum::USD,
            CurrencyEnum::EUR,
            BigDecimal::of('0.92'),
            new \DateTimeImmutable('-1 hour')
        );
        $this->entityManager->persist($newRate);
        $this->entityManager->flush();

        // Kontrola přímého směru
        $found = $this->repository->findLatestExchangeRate(CurrencyEnum::USD, CurrencyEnum::EUR);
        $this->assertNotNull($found);
        $this->assertSame('0.92', (string) $found->getRate());

        // Kontrola reverzního směru
        $foundReverse = $this->repository->findLatestExchangeRate(CurrencyEnum::EUR, CurrencyEnum::USD);
        $this->assertNotNull($foundReverse);
        $this->assertSame('0.92', (string) $foundReverse->getRate());
    }

    /**
     * Testuje metodu findLatestUpdate.
     * Ověřuje, že metoda vrátí záznam s absolutně nejnovějším datem aktualizace v celé tabulce.
     */
    public function testFindLatestUpdate(): void
    {
        $rate1 = new ExchangeRate(
            CurrencyEnum::USD,
            CurrencyEnum::CZK,
            BigDecimal::of('22.0'),
            new \DateTimeImmutable('2023-01-01 10:00:00')
        );
        $rate2 = new ExchangeRate(
            CurrencyEnum::USD,
            CurrencyEnum::JPY,
            BigDecimal::of('130.0'),
            new \DateTimeImmutable('2023-01-02 10:00:00')
        );

        $this->entityManager->persist($rate1);
        $this->entityManager->persist($rate2);
        $this->entityManager->flush();

        $latest = $this->repository->findLatestUpdate();
        $this->assertNotNull($latest);
        $this->assertSame(CurrencyEnum::JPY, $latest->getTargetCurrency());
    }

    /**
     * Testuje metodu getAvailableUpdateDates.
     * Ověřuje, že metoda vrátí seznam unikátních dat (dnů), pro které existují záznamy.
     */
    public function testGetAvailableUpdateDates(): void
    {
        // Datum 1: 2023-01-01 (2 záznamy)
        $date1 = new \DateTimeImmutable('2023-01-01 10:00:00');
        $this->entityManager->persist(new ExchangeRate(CurrencyEnum::USD, CurrencyEnum::CZK, BigDecimal::of('22'), $date1));
        $this->entityManager->persist(new ExchangeRate(CurrencyEnum::USD, CurrencyEnum::EUR, BigDecimal::of('0.9'), $date1));

        // Datum 2: 2023-01-02
        $date2 = new \DateTimeImmutable('2023-01-02 15:00:00');
        $this->entityManager->persist(new ExchangeRate(CurrencyEnum::USD, CurrencyEnum::BTC, BigDecimal::of('0.00003'), $date2));

        $this->entityManager->flush();

        $dates = $this->repository->getAvailableUpdateDates();

        $this->assertCount(2, $dates);
        $this->assertContains('2023-01-01', $dates);
        $this->assertContains('2023-01-02', $dates);
    }

    /**
     * Testuje metodu findExchangeRateAtDate.
     * Ověřuje vyhledání kurzu platného k specifickému datu.
     */
    public function testFindExchangeRateAtDate(): void
    {
        $targetDate = new \DateTimeImmutable('2023-05-01 10:00:00');
        $otherDate = new \DateTimeImmutable('2023-05-02 10:00:00');

        $rateTarget = new ExchangeRate(
            CurrencyEnum::USD,
            CurrencyEnum::CZK,
            BigDecimal::of('21.50'),
            $targetDate
        );
        $rateOther = new ExchangeRate(
            CurrencyEnum::USD,
            CurrencyEnum::CZK,
            BigDecimal::of('22.50'),
            $otherDate
        );

        $this->entityManager->persist($rateTarget);
        $this->entityManager->persist($rateOther);
        $this->entityManager->flush();

        // Hledání pro 2023-05-01
        $found = $this->repository->findExchangeRateAtDate(CurrencyEnum::USD, CurrencyEnum::CZK, $targetDate);

        $this->assertNotNull($found);
        $this->assertSame('21.50', (string) $found->getRate());
    }

    /**
     * Testuje metodu findExchangeRateAtDate s více záznamy za jeden den.
     * Ověřuje, že metoda vrátí nejnovější kurz za daný den, nikoliv nejstarší.
     */
    public function testFindExchangeRateAtDateReturnsLatestOfDay(): void
    {
        $targetDate = new \DateTimeImmutable('2023-05-01 12:00:00');

        // 1. Ranní kurz (nejstarší za den)
        $morningRate = new ExchangeRate(
            CurrencyEnum::USD,
            CurrencyEnum::CZK,
            BigDecimal::of('20.83'),
            new \DateTimeImmutable('2023-05-01 06:00:00')
        );

        // 2. Odpolední kurz (novější)
        $afternoonRate = new ExchangeRate(
            CurrencyEnum::USD,
            CurrencyEnum::CZK,
            BigDecimal::of('20.76'),
            new \DateTimeImmutable('2023-05-01 14:00:00')
        );

        // 3. Večerní kurz (nejnovější za den)
        $eveningRate = new ExchangeRate(
            CurrencyEnum::USD,
            CurrencyEnum::CZK,
            BigDecimal::of('20.85'),
            new \DateTimeImmutable('2023-05-01 19:00:00')
        );

        $this->entityManager->persist($morningRate);
        $this->entityManager->persist($afternoonRate);
        $this->entityManager->persist($eveningRate);
        $this->entityManager->flush();

        // Metoda by měla vrátit večerní kurz (nejnovější za den)
        $found = $this->repository->findExchangeRateAtDate(CurrencyEnum::USD, CurrencyEnum::CZK, $targetDate);

        $this->assertNotNull($found);
        $this->assertSame('20.85', (string) $found->getRate(), 'Měl by být vrácen nejnovější kurz za den (19:00), nikoliv nejstarší (06:00)');
    }
}
