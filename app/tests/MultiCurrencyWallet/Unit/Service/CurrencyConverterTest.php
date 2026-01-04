<?php

declare(strict_types=1);

namespace App\Tests\MultiCurrencyWallet\Unit\Service;

use App\MultiCurrencyWallet\Entity\ExchangeRate;
use App\MultiCurrencyWallet\Enum\CurrencyEnum;
use App\MultiCurrencyWallet\Repository\ExchangeRateRepository;
use App\MultiCurrencyWallet\Service\CurrencyConverter;
use Brick\Math\BigDecimal;
use Brick\Money\Exception\MoneyMismatchException;
use Brick\Money\Exception\UnknownCurrencyException;
use Brick\Money\Money;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Jednotkové testy pro službu CurrencyConverter.
 * Ověřuje správnost finančních výpočtů a logiku vyhledávání kurzů (přímé, inverzní a křížové).
 */
class CurrencyConverterTest extends TestCase
{
    /** @var ExchangeRateRepository&MockObject */
    private ExchangeRateRepository $exchangeRateRepository;
    private CurrencyConverter $currencyConverter;

    /**
     * Nastaví prostředí pro testy (vytvoří mock repozitáře a instanci služby).
     */
    protected function setUp(): void
    {
        $this->exchangeRateRepository = $this->createMock(ExchangeRateRepository::class);
        $this->currencyConverter = new CurrencyConverter($this->exchangeRateRepository);
    }

    /**
     * Ověřuje, že při převodu na stejnou měnu služba vrátí původní částku bez dotazu do DB.
     *
     * @throws UnknownCurrencyException
     * @throws MoneyMismatchException
     */
    public function testConvertSameCurrencyReturnsOriginalAmount(): void
    {
        $amount = Money::of(100, 'USD');
        $result = $this->currencyConverter->convert($amount, CurrencyEnum::USD);

        $this->assertTrue($amount->isEqualTo($result));
        $this->exchangeRateRepository->expects($this->never())->method('findLatestExchangeRate');
    }

    /**
     * Testuje přímý převod měn, pro které existuje záznam v databázi.
     *
     * @throws UnknownCurrencyException
     */
    public function testConvertDirectRate(): void
    {
        // USD -> EUR (V DB existuje: Základ=USD, Cíl=EUR, Kurz=0.85)
        $rateRecord = new ExchangeRate(CurrencyEnum::USD, CurrencyEnum::EUR, BigDecimal::of('0.85'), new \DateTimeImmutable());

        // Mockování: findLatestExchangeRate(USD, EUR) -> vrátí záznam
        $this->exchangeRateRepository->method('findLatestExchangeRate')
            ->willReturnMap([
                [CurrencyEnum::USD, CurrencyEnum::EUR, $rateRecord],
            ]);

        $amount = Money::of(100, 'USD');
        $result = $this->currencyConverter->convert($amount, CurrencyEnum::EUR);

        // 100 * 0.85 = 85
        $this->assertEquals('85.00', (string) $result->getAmount());
        $this->assertEquals('EUR', $result->getCurrency()->getCurrencyCode());
    }

    /**
     * Testuje převod s využitím inverzního kurzu (pokud je v DB uložen opačný směr páru).
     *
     * @throws UnknownCurrencyException
     */
    public function testConvertInvertedRate(): void
    {
        // EUR -> USD (V DB existuje: Základ=USD, Cíl=EUR, Kurz=0.85)
        // Logika findRateInDb by měla vrátit 1/0.85

        $rateRecord = new ExchangeRate(CurrencyEnum::USD, CurrencyEnum::EUR, BigDecimal::of('0.85'), new \DateTimeImmutable());

        $this->exchangeRateRepository->method('findLatestExchangeRate')
            ->willReturnMap([
                [CurrencyEnum::EUR, CurrencyEnum::USD, $rateRecord], // Repozitář může být chytrý, ale služba volá (Zdroj, Cíl)
                // Pokud služba volá repository->findLatest(EUR, USD) a repozitář vrátí tento záznam
                // (protože Doctrine ho umí najít bez ohledu na pořadí, pokud je logika v repozitáři),
                // ALE v kódu CurrencyConverteru:
                // findRateInDb(Zdroj, Cíl) -> repo->find(Zdroj, Cíl).
                // Předpokládejme, že repozitář vrátí záznam, i když jsou měny prohozené (nebo že ho mockujeme tak, že pro (EUR, USD) vrátí záznam (USD, EUR)).
            ]);

        // Simulujeme, že pro dotaz findLatest(EUR, USD) vrátí záznam, kde Základ=USD, Cíl=EUR
        $this->exchangeRateRepository->expects($this->any())
            ->method('findLatestExchangeRate')
            ->with(CurrencyEnum::EUR, CurrencyEnum::USD)
            ->willReturn($rateRecord);

        $amount = Money::of(85, 'EUR');
        $result = $this->currencyConverter->convert($amount, CurrencyEnum::USD);

        // 85 * (1/0.85) = 100
        $this->assertEquals('100.00', (string) $result->getAmount());
    }

    /**
     * Testuje křížový převod přes referenční měnu USD (např. EUR -> USD -> CZK).
     *
     * @throws UnknownCurrencyException
     */
    public function testConvertCrossRateViaUsd(): void
    {
        // EUR -> CZK
        // Cesta: EUR -> USD -> CZK
        // 1. EUR -> USD:
        //    V DB: USD -> EUR = 0.85.
        //    Kurz EUR->USD = 1/0.85 ~= 1.17647...
        // 2. USD -> CZK:
        //    V DB: USD -> CZK = 22.0.

        $usdToEur = new ExchangeRate(CurrencyEnum::USD, CurrencyEnum::EUR, BigDecimal::of('0.85'), new \DateTimeImmutable());
        $usdToCzk = new ExchangeRate(CurrencyEnum::USD, CurrencyEnum::CZK, BigDecimal::of('22.0'), new \DateTimeImmutable());

        $this->exchangeRateRepository->method('findLatestExchangeRate')
            ->willReturnMap([
                // První krok: findRateInDb(EUR, CZK) -> nenajde nic
                [CurrencyEnum::EUR, CurrencyEnum::CZK, null],
                // Druhý krok (pivot):
                // findRateInDb(EUR, USD)
                [CurrencyEnum::EUR, CurrencyEnum::USD, $usdToEur],
                // findRateInDb(USD, CZK)
                [CurrencyEnum::USD, CurrencyEnum::CZK, $usdToCzk],
            ]);

        $amount = Money::of(85, 'EUR');
        $result = $this->currencyConverter->convert($amount, CurrencyEnum::CZK);

        // 85 EUR -> 100 USD
        // 100 USD -> 2200 CZK
        $this->assertEquals('2200.00', (string) $result->getAmount());
    }
}
