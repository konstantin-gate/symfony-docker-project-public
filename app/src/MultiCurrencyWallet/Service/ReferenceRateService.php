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
     * Pro vybrané páry (např. CZK -> EUR) používá specifické základní částky pro lepší přehlednost.
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
    public function getReferenceRates(?\DateTimeInterface $atDate = null): array
    {
        $pairs = [
            // CZK páry s specifickými částkami
            ['source' => CurrencyEnum::CZK, 'target' => CurrencyEnum::EUR, 'amount' => '100'],
            ['source' => CurrencyEnum::CZK, 'target' => CurrencyEnum::USD, 'amount' => '100'],
            ['source' => CurrencyEnum::CZK, 'target' => CurrencyEnum::RUB, 'amount' => '100'],
            ['source' => CurrencyEnum::CZK, 'target' => CurrencyEnum::JPY, 'amount' => '1000'],
            ['source' => CurrencyEnum::CZK, 'target' => CurrencyEnum::BTC, 'amount' => '10000'],

            // Doplňkové páry
            ['source' => CurrencyEnum::USD, 'target' => CurrencyEnum::EUR, 'amount' => '1'],
            ['source' => CurrencyEnum::EUR, 'target' => CurrencyEnum::USD, 'amount' => '1'],
            ['source' => CurrencyEnum::BTC, 'target' => CurrencyEnum::USD, 'amount' => '1'],
        ];

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
}
