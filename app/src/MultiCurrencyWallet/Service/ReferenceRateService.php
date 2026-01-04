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
     * @return array<int, array{
     *     source_amount: string,
     *     source_currency: string,
     *     target_amount: string,
     *     target_currency: string,
     *     rate: string,
     *     updated_at: string|null
     * }>
     */
    public function getReferenceRates(): array
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
                $converted = $this->currencyConverter->convert($money, $targetCurrency);

                // Získáme samotný kurz (1 jednotka source -> target)
                $oneUnit = Money::of(1, $sourceCurrency->toBrickCurrency());
                $rateValue = $this->currencyConverter->convert($oneUnit, $targetCurrency)->getAmount();

                $results[] = [
                    'source_amount' => (string) $money->getAmount(),
                    'source_currency' => $sourceCurrency->value,
                    'target_amount' => (string) $converted->getAmount(),
                    'target_currency' => $targetCurrency->value,
                    'rate' => (string) $rateValue,
                    'updated_at' => null, // TODO: Pokud budeme chtít datum poslední aktualizace konkrétního kurzu
                ];
            } catch (\Exception $e) {
                // Pokud kurz chybí, pár přeskočíme nebo zalogujeme
                continue;
            }
        }

        return $results;
    }
}
