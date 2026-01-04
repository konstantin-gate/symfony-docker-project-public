<?php

declare(strict_types=1);

namespace App\MultiCurrencyWallet\Service;

use App\MultiCurrencyWallet\Enum\CurrencyEnum;
use Brick\Money\Money;

/**
 * Služba pro generování seznamu referenčních směnných kurzů.
 */
readonly class ReferenceRateService
{
    public function __construct(
        private CurrencyConverter $currencyConverter,
    ) {
    }

    /**
     * Vygeneruje seznam referenčních směnných kurzů pro definované páry měn.
     * Umožňuje získat kurzy k určitému datu (pokud je zadáno) nebo nejaktuálnější dostupné.
     * Automaticky generuje páry "Základní měna -> Ostatní měny" a přidává doplňkové populární páry.
     *
     * @return array<int, array{
     *     source_amount: string,
     *     source_currency: string,
     *     target_amount: string,
     *     target_currency: string,
     *     rate: string,
     *     updated_at: string|null
     * }>
     */
    public function getReferenceRates(?\DateTimeInterface $atDate = null, CurrencyEnum $baseCurrency = CurrencyEnum::CZK): array
    {
        $pairs = [];

        // 1. Generování párů: Základní měna -> Všechny ostatní
        $smartAmount = $this->getSmartAmount($baseCurrency);
        foreach (CurrencyEnum::cases() as $currency) {
            if ($currency === $baseCurrency) {
                continue;
            }
            $pairs[] = [
                'source' => $baseCurrency,
                'target' => $currency,
                'amount' => $smartAmount,
            ];
        }

        // 2. Doplňkové populární páry (pokud již nejsou zahrnuty)
        $supplementalPairs = [
            ['source' => CurrencyEnum::USD, 'target' => CurrencyEnum::EUR, 'amount' => '1'],
            ['source' => CurrencyEnum::EUR, 'target' => CurrencyEnum::USD, 'amount' => '1'],
            ['source' => CurrencyEnum::BTC, 'target' => CurrencyEnum::USD, 'amount' => '1'],
        ];

        foreach ($supplementalPairs as $pair) {
            // Pokud je základní měna součástí páru, přeskočíme ho (již je v hlavním seznamu, nebo by to byla duplicita)
            if ($pair['source'] === $baseCurrency || $pair['target'] === $baseCurrency) {
                continue;
            }
            $pairs[] = $pair;
        }

        $results = [];

        foreach ($pairs as $pair) {
            $sourceCurrency = $pair['source'];
            $targetCurrency = $pair['target'];
            $baseAmount = $pair['amount'];

            try {
                $money = Money::of($baseAmount, $sourceCurrency->toBrickCurrency());
                $converted = $this->currencyConverter->convert($money, $targetCurrency, $atDate);

                // Získáme samotný kurz (1 jednotka source -> target)
                $oneUnit = Money::of(1, $sourceCurrency->toBrickCurrency());
                $rateValue = $this->currencyConverter->convert($oneUnit, $targetCurrency, $atDate)->getAmount();

                $results[] = [
                    'source_amount' => (string) $money->getAmount(),
                    'source_currency' => $sourceCurrency->value,
                    'target_amount' => (string) $converted->getAmount(),
                    'target_currency' => $targetCurrency->value,
                    'rate' => (string) $rateValue,
                    'updated_at' => null,
                ];
            } catch (\Exception $e) {
                // Pokud kurz chybí, pár přeskočíme nebo zalogujeme
                continue;
            }
        }

        return $results;
    }

    /**
     * Určí "pěknou" částku pro převod na základě měny.
     * Pro "levnější" měny (CZK, JPY) vrací vyšší částky (100, 1000), pro ostatní 1.
     */
    private function getSmartAmount(CurrencyEnum $currency): string
    {
        return match ($currency) {
            CurrencyEnum::CZK, CurrencyEnum::RUB => '100',
            CurrencyEnum::JPY => '1000',
            default => '1',
        };
    }
}
