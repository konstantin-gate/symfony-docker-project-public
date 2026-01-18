<?php

declare(strict_types=1);

namespace App\MultiCurrencyWallet\Service;

use App\MultiCurrencyWallet\Enum\CurrencyEnum;
use App\MultiCurrencyWallet\Repository\ExchangeRateRepository;
use Brick\Money\Money;

/**
 * Služba pro získání historických směnných kurzů mezi měnami.
 *
 * Využívá CurrencyConverter pro správný výpočet kurzů včetně křížových kurzů přes pivot měnu (USD).
 * Poskytuje časovou řadu kurzů pro analytické účely, např. pro predikci budoucího vývoje (Smart Trend Forecaster).
 */
readonly class RateHistoryService
{
    public function __construct(
        private ExchangeRateRepository $exchangeRateRepository,
        private CurrencyConverter $currencyConverter,
    ) {}

    /**
     * Vrátí historii směnných kurzů mezi dvěma měnami za posledních N dní.
     *
     * Metoda iteruje přes dostupné dny s daty a pro každý den získá kurz pomocí CurrencyConverter,
     * který zajistí správný výpočet přímého, inverzního nebo křížového kurzu.
     *
     * @param CurrencyEnum $baseCurrency Základní měna (např. CZK)
     * @param CurrencyEnum $targetCurrency Cílová měna (např. EUR)
     * @param int $days Počet dní historie (výchozí 30)
     *
     * @return array<int, array{date: string, rate: string}> Pole záznamů s datem a kurzem
     */
    public function getHistory(CurrencyEnum $baseCurrency, CurrencyEnum $targetCurrency, int $days = 30): array
    {
        // Získáme seznam dostupných dat v databázi za požadovaný počet dní
        $availableDates = $this->exchangeRateRepository->getAvailableDatesInRange($days);

        $history = [];

        foreach ($availableDates as $dateString) {

            try {
                $date = new \DateTimeImmutable($dateString, new \DateTimeZone('Europe/Prague'));

                // Použijeme CurrencyConverter pro získání kurzu k danému datu
                // Konvertujeme 1 jednotku základní měny na cílovou měnu
                $oneUnit = Money::of(1, $baseCurrency->toBrickCurrency());
                $converted = $this->currencyConverter->convert($oneUnit, $targetCurrency, $date);

                $history[] = [
                    'date' => $dateString,
                    'rate' => (string) $converted->getAmount(),
                ];
            } catch (\Exception) {
                // Pokud kurz pro dané datum není dostupný, přeskočíme ho
                continue;
            }
        }

        // Seřadíme podle data vzestupně (nejstarší první)
        usort($history, fn(array $a, array $b) => $a['date'] <=> $b['date']);

        return $history;
    }
}
