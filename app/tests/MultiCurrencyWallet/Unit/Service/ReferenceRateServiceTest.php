<?php

declare(strict_types=1);

namespace App\Tests\MultiCurrencyWallet\Unit\Service;

use App\MultiCurrencyWallet\Enum\CurrencyEnum;
use App\MultiCurrencyWallet\Service\CurrencyConverter;
use App\MultiCurrencyWallet\Service\ReferenceRateService;
use Brick\Money\Money;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit testy pro ReferenceRateService.
 * Testuje logiku "chytrých" částek (smart amounts) a generování referenčních párů kurzů.
 */
class ReferenceRateServiceTest extends TestCase
{
    private CurrencyConverter&MockObject $currencyConverter;
    private ReferenceRateService $service;

    /**
     * Příprava mock objektů před každým testem.
     */
    protected function setUp(): void
    {
        $this->currencyConverter = $this->createMock(CurrencyConverter::class);
        $this->service = new ReferenceRateService($this->currencyConverter);
    }

    /**
     * Testuje, zda jsou pro konkrétní měny použity správné základní částky (např. 100 pro CZK, 1000 pro JPY).
     */
    public function testGetSmartAmountLogicInternal(): void
    {
        // Použijeme getReferenceRates k implicitnímu otestování logiky (nebo bychom mohli použít reflexi).
        // Nebo ještě lépe, zkontrolujeme 'amount' ve výsledku getReferenceRates.
        // Mock converteru nastavíme tak, aby vracel stejnou částku pro zjednodušení.

        $this->currencyConverter->method('convert')
            ->willReturnCallback(fn (Money $money, CurrencyEnum $target) => Money::of($money->getAmount(), $target->toBrickCurrency()));

        $rates = $this->service->getReferenceRates(null, CurrencyEnum::CZK);

        // Kontrola párů začínajících na CZK
        foreach ($rates as $rate) {
            if ($rate['source_currency'] === 'CZK') {
                $this->assertSame('100.00', $rate['source_amount'], 'CZK by měla používat 100 jako základní částku');
            }
        }

        $ratesJpy = $this->service->getReferenceRates(null, CurrencyEnum::JPY);

        foreach ($ratesJpy as $rate) {
            if ($rate['source_currency'] === 'JPY') {
                $this->assertSame('1000', $rate['source_amount'], 'JPY by měl používat 1000 jako základní částku');
            }
        }

        $ratesUsd = $this->service->getReferenceRates(null, CurrencyEnum::USD);

        foreach ($ratesUsd as $rate) {
            if ($rate['source_currency'] === 'USD') {
                $this->assertSame('1.00', $rate['source_amount'], 'USD by měl používat 1 jako základní částku');
            }
        }
    }

    /**
     * Testuje, zda je generována správná sada měnových párů na základě hlavní měny.
     */
    public function testGetReferenceRatesGeneratesCorrectPairs(): void
    {
        // Mock converteru nastavíme tak, aby vracel fixní fiktivní převod
        $this->currencyConverter->method('convert')
            ->willReturnCallback(function (Money $money, CurrencyEnum $target) {
                // Vrátí fiktivní kurz 2.0
                return Money::of($money->getAmount()->multipliedBy(2), $target->toBrickCurrency());
            });

        // Test s hlavní měnou = CZK
        $rates = $this->service->getReferenceRates(null, CurrencyEnum::CZK);

        // 1. Kontrola, že máme páry CZK -> [Ostatní]
        // Ostatní: USD, EUR, RUB, BTC, JPY (5 položek)
        $czkSources = array_filter($rates, fn ($r) => $r['source_currency'] === 'CZK');
        $this->assertCount(5, $czkSources);

        // 2. Kontrola doplňkových párů
        // Výchozí doplňkové: USD->EUR, EUR->USD, BTC->USD
        // Hlavní měna je CZK, takže všechny 3 by měly být přítomny.
        $supplemental = array_filter($rates, fn ($r) => $r['source_currency'] !== 'CZK');

        // Kontrola konkrétních párů
        $hasUsdToEur = false;
        $hasEurToUsd = false;
        $hasBtcToUsd = false;

        foreach ($supplemental as $r) {
            if ($r['source_currency'] === 'USD' && $r['target_currency'] === 'EUR') {
                $hasUsdToEur = true;
            }

            if ($r['source_currency'] === 'EUR' && $r['target_currency'] === 'USD') {
                $hasEurToUsd = true;
            }

            if ($r['source_currency'] === 'BTC' && $r['target_currency'] === 'USD') {
                $hasBtcToUsd = true;
            }
        }

        $this->assertTrue($hasUsdToEur, 'Chybí pár USD->EUR');
        $this->assertTrue($hasEurToUsd, 'Chybí pár EUR->USD');
        $this->assertTrue($hasBtcToUsd, 'Chybí pár BTC->USD');
    }

    /**
     * Testuje, zda se předchází duplicitním párům, pokud hlavní měna koliduje s doplňkovými páry.
     */
    public function testGetReferenceRatesAvoidsDuplicates(): void
    {
        $this->currencyConverter->method('convert')
           ->willReturnCallback(fn (Money $money, CurrencyEnum $target) => Money::of($money->getAmount(), $target->toBrickCurrency()));

        // Test s hlavní měnou = USD
        // Doplňkové páry zahrnují USD (USD->EUR, EUR->USD, BTC->USD).
        // Pokud je hlavní měna USD, pak:
        // 1. Generované: USD->EUR, USD->CZK, USD->RUB, USD->BTC, USD->JPY.
        // 2. Doplňkové:
        //    - USD->EUR (PŘESKOČIT, zdroj je hlavní měna)
        //    - EUR->USD (PŘESKOČIT, cíl je hlavní měna)
        //    - BTC->USD (PŘESKOČIT, cíl je hlavní měna)

        // Takže očekáváme pouze generované páry z hlavní měny (5 párů).

        $rates = $this->service->getReferenceRates(null, CurrencyEnum::USD);

        $this->assertCount(5, $rates);

        foreach ($rates as $rate) {
            $this->assertSame('USD', $rate['source_currency']);
        }
    }
}