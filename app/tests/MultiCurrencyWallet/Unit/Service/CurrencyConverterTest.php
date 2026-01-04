<?php

declare(strict_types=1);

namespace App\Tests\MultiCurrencyWallet\Unit\Service;

use App\MultiCurrencyWallet\Entity\ExchangeRate;
use App\MultiCurrencyWallet\Enum\CurrencyEnum;
use App\MultiCurrencyWallet\Exception\RateNotFoundException;
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

    /**
     * Testuje vyhození výjimky RateNotFoundException, pokud kurz neexistuje.
     */
    public function testConvertThrowsRateNotFoundException(): void
    {
        $this->exchangeRateRepository->method('findLatestExchangeRate')->willReturn(null);

        $this->expectException(RateNotFoundException::class);
        $this->expectExceptionMessage('Nenalezen směnný kurz pro převod USD -> EUR');

        $amount = Money::of(100, 'USD');
        $this->currencyConverter->convert($amount, CurrencyEnum::EUR);
    }

    /**
     * Testuje zaokrouhlování (HALF_UP) pro fiat měny.
     */
    public function testRoundingFiat(): void
    {
        // USD -> EUR = 0.8544
        $rateRecord = new ExchangeRate(CurrencyEnum::USD, CurrencyEnum::EUR, BigDecimal::of('0.8544'), new \DateTimeImmutable());
        $this->exchangeRateRepository->method('findLatestExchangeRate')->willReturn($rateRecord);

        // 10.50 * 0.8544 = 8.9712 -> Round HALF_UP to 2 decimals = 8.97
        $amount = Money::of('10.50', 'USD');
        $result = $this->currencyConverter->convert($amount, CurrencyEnum::EUR);
        $this->assertEquals('8.97', (string) $result->getAmount());

        // USD -> EUR = 0.8545
        $rateRecord2 = new ExchangeRate(CurrencyEnum::USD, CurrencyEnum::EUR, BigDecimal::of('0.8545'), new \DateTimeImmutable());
        $this->exchangeRateRepository = $this->createMock(ExchangeRateRepository::class); // Reset mock
        $this->currencyConverter = new CurrencyConverter($this->exchangeRateRepository);
        $this->exchangeRateRepository->method('findLatestExchangeRate')->willReturn($rateRecord2);

        // 10.50 * 0.8545 = 8.97225 -> Round HALF_UP to 2 decimals = 8.97
        // Wait, 10.5 * 0.8545 = 8.97225. HALF_UP to 2 decimals is 8.97.
        // Let's try something that rounds up.
        // 10.5 * 0.8557 = 8.98485 -> 8.98
        // 1 * 0.855 = 0.855 -> 0.86
        $amount = Money::of('1', 'USD');
        $result = $this->currencyConverter->convert($amount, CurrencyEnum::EUR);
        $this->assertEquals('0.85', (string) $result->getAmount()); // 1 * 0.8545 = 0.8545 -> 0.85

        $rateRecord3 = new ExchangeRate(CurrencyEnum::USD, CurrencyEnum::EUR, BigDecimal::of('0.855'), new \DateTimeImmutable());
        $this->exchangeRateRepository = $this->createMock(ExchangeRateRepository::class);
        $this->currencyConverter = new CurrencyConverter($this->exchangeRateRepository);
        $this->exchangeRateRepository->method('findLatestExchangeRate')->willReturn($rateRecord3);
        $result = $this->currencyConverter->convert($amount, CurrencyEnum::EUR);
        $this->assertEquals('0.86', (string) $result->getAmount()); // 1 * 0.855 = 0.855 -> 0.86
    }

    /**
     * Testuje zaokrouhlování pro kryptoměny (8 desetinných míst).
     */
    public function testRoundingCrypto(): void
    {
        // USD -> BTC = 0.000023456789
        $rateRecord = new ExchangeRate(CurrencyEnum::USD, CurrencyEnum::BTC, BigDecimal::of('0.000023456789'), new \DateTimeImmutable());
        $this->exchangeRateRepository->method('findLatestExchangeRate')->willReturn($rateRecord);

        $amount = Money::of('1', 'USD');
        $result = $this->currencyConverter->convert($amount, CurrencyEnum::BTC);

        // 0.000023456789 -> 8 decimals -> 0.00002346 (HALF_UP)
        $this->assertEquals('0.00002346', (string) $result->getAmount());
    }
}
