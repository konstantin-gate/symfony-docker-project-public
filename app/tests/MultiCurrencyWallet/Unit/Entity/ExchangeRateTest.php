<?php

declare(strict_types=1);

namespace App\Tests\MultiCurrencyWallet\Unit\Entity;

use App\MultiCurrencyWallet\Entity\ExchangeRate;
use App\MultiCurrencyWallet\Enum\CurrencyEnum;
use Brick\Math\BigDecimal;
use PHPUnit\Framework\TestCase;

/**
 * Unit testy pro entitu ExchangeRate.
 * Ověřuje správné nastavení hodnot v konstruktoru a funkčnost getterů.
 */
class ExchangeRateTest extends TestCase
{
    /**
     * Testuje, zda konstruktor správně nastavuje všechny předané hodnoty.
     */
    public function testConstructorSetsValues(): void
    {
        $rateValue = BigDecimal::of('24.50');
        $fetchedAt = new \DateTimeImmutable('2023-01-01 12:00:00');

        $rate = new ExchangeRate(
            CurrencyEnum::USD,
            CurrencyEnum::CZK,
            $rateValue,
            $fetchedAt
        );

        $this->assertSame(CurrencyEnum::USD, $rate->getBaseCurrency());
        $this->assertSame(CurrencyEnum::CZK, $rate->getTargetCurrency());
        $this->assertTrue($rateValue->isEqualTo($rate->getRate()));
        $this->assertSame($fetchedAt, $rate->getFetchedAt());
    }

    /**
     * Testuje, zda konstruktor automaticky nastaví aktuální čas stažení, pokud není v parametru zadán.
     */
    public function testConstructorSetsDefaultFetchedAt(): void
    {
        $rateValue = BigDecimal::of('1.0');
        $rate = new ExchangeRate(
            CurrencyEnum::EUR,
            CurrencyEnum::USD,
            $rateValue
        );

        // Kontrola, zda je fetchedAt aktuální (v rozmezí 1 sekundy)
        $diff = (new \DateTimeImmutable())->getTimestamp() - $rate->getFetchedAt()->getTimestamp();
        $this->assertLessThanOrEqual(1, $diff);
    }

    /**
     * Testuje, zda metoda getRate vrací objekt BigDecimal se správnou hodnotou kurzu.
     */
    public function testGetRateReturnsBigDecimal(): void
    {
        $rate = new ExchangeRate(
            CurrencyEnum::USD,
            CurrencyEnum::EUR,
            BigDecimal::of('0.9')
        );

        $this->assertSame('0.9', (string) $rate->getRate());
    }
}
