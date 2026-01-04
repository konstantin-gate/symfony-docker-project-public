<?php

declare(strict_types=1);

namespace App\MultiCurrencyWallet\Service;

use App\MultiCurrencyWallet\Enum\CurrencyEnum;
use App\MultiCurrencyWallet\Repository\ExchangeRateRepository;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Brick\Money\Exception\UnknownCurrencyException;
use Brick\Money\Money;

/**
 * Služba pro převod peněžních částek mezi různými měnami.
 * Zajišťuje výpočet výsledné částky na základě aktuálních nebo historických směnných kurzů uložených v databázi.
 * Podporuje přímé převody, inverzní kurzy i křížové převody přes referenční měnu (USD).
 */
readonly class CurrencyConverter
{
    public function __construct(
        private ExchangeRateRepository $exchangeRateRepository,
    ) {
    }

    /**
     * Převede částku (Money) do cílové měny.
     * Využívá mezikurz přes USD (protože všechny kurzy v DB jsou vztaženy k USD).
     *
     * Vzorec: (AmountInSource * Rate_Source_to_USD) * Rate_USD_to_Target
     * Ale pozor, v DB máme kurzy uloženy jako: Base=USD, Target=X, Rate=R.
     * To znamená: 1 USD = R jednotek X.
     * Tedy: X = USD * R  =>  USD = X / R.
     *
     * Převod Source -> Target:
     * 1. Source -> USD:  AmountUSD = AmountSource / Rate(USD->Source)
     * 2. USD -> Target:  AmountTarget = AmountUSD * Rate(USD->Target)
     *
     * Výsledný vzorec: AmountTarget = AmountSource * (Rate(USD->Target) / Rate(USD->Source))
     *
     * @throws UnknownCurrencyException
     */
    public function convert(Money $amount, CurrencyEnum $targetCurrency, ?\DateTimeInterface $atDate = null): Money
    {
        if ($amount->getCurrency()->getCurrencyCode() === $targetCurrency->value) {
            return $amount;
        }

        $sourceCurrency = CurrencyEnum::from($amount->getCurrency()->getCurrencyCode());

        // Zkusíme najít kurz (přímo nebo přes USD)
        $rate = $this->getExchangeRate($sourceCurrency, $targetCurrency, $atDate);

        if (!$rate) {
            throw new \RuntimeException(\sprintf('Nenalezen směnný kurz pro převod %s -> %s', $sourceCurrency->value, $targetCurrency->value));
        }

        $convertedAmount = $amount->getAmount()->multipliedBy($rate);

        return Money::of($convertedAmount, $targetCurrency->toBrickCurrency(), null, RoundingMode::HALF_UP);
    }

    /**
     * Získá směnný kurz mezi dvěma měnami.
     * 1. Hledá přímý pár (nebo inverzní) v DB.
     * 2. Pokud nenajde, zkusí převod přes referenční měnu (USD).
     */
    private function getExchangeRate(CurrencyEnum $source, CurrencyEnum $target, ?\DateTimeInterface $atDate = null): ?BigDecimal
    {
        // 1. Přímý (nebo inverzní) kurz z DB
        $directRate = $this->findRateInDb($source, $target, $atDate);

        if ($directRate) {
            return $directRate;
        }

        // 2. Pokud se nejedná o převod s USD, zkusíme pivot přes USD
        if ($source !== CurrencyEnum::USD && $target !== CurrencyEnum::USD) {
            $sourceToUsd = $this->findRateInDb($source, CurrencyEnum::USD, $atDate);
            $usdToTarget = $this->findRateInDb(CurrencyEnum::USD, $target, $atDate);

            if ($sourceToUsd && $usdToTarget) {
                // Rate = (Source->USD) * (USD->Target)
                return $sourceToUsd->multipliedBy($usdToTarget);
            }
        }

        return null;
    }

    /**
     * Helper pro nalezení kurzu v DB s ohledem na směr (Base/Target).
     */
    private function findRateInDb(CurrencyEnum $source, CurrencyEnum $target, ?\DateTimeInterface $atDate = null): ?BigDecimal
    {
        $record = $atDate
            ? $this->exchangeRateRepository->findExchangeRateAtDate($source, $target, $atDate)
            : $this->exchangeRateRepository->findLatestExchangeRate($source, $target);

        if (!$record) {
            return null;
        }

        $rate = $record->getRate();

        // Pokud je v DB uložen kurz Base=Source, Target=Target, vracíme rate.
        if ($record->getBaseCurrency() === $source) {
            return $rate;
        }

        // Pokud je v DB uložen kurz Base=Target, Target=Source, vracíme 1/rate.
        // Používáme vysokou přesnost (20 míst) pro dělení.
        return BigDecimal::one()->dividedBy($rate, 20, RoundingMode::HALF_UP);
    }
}
