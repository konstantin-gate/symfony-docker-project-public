<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\MultiCurrencyWallet\Entity\Balance;
use App\MultiCurrencyWallet\Entity\ExchangeRate;
use App\MultiCurrencyWallet\Enum\CurrencyEnum;
use Brick\Math\BigDecimal;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

/**
 * Fixture pro naplnění tabulek wallet_balance a wallet_exchange_rate testovacími daty.
 */
class WalletFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        // 1. Vytvoření zůstatků pro všechny podporované měny
        $displayOrderMap = [
            CurrencyEnum::CZK->value => 1,
            CurrencyEnum::EUR->value => 2,
            CurrencyEnum::USD->value => 3,
            CurrencyEnum::RUB->value => 4,
            CurrencyEnum::JPY->value => 5,
            CurrencyEnum::BTC->value => 6,
        ];

        foreach (CurrencyEnum::cases() as $currency) {
            // Náhodná částka mezi 100 a 5000 (pro kryptoměny menší, pro CZK/RUB větší)
            $randomAmount = match ($currency) {
                CurrencyEnum::BTC => (string) (random_int(1, 50) / 1000), // 0.001 - 0.050 BTC
                CurrencyEnum::CZK, CurrencyEnum::RUB, CurrencyEnum::JPY => (string) random_int(5000, 50000),
                default => (string) random_int(500, 5000),
            };

            $balance = new Balance($currency, $randomAmount);
            $balance->setDisplayOrder($displayOrderMap[$currency->value]);
            $manager->persist($balance);
        }

        // 2. Vytvoření směnných kurzů (přibližné kurzy k USD k 3.1.2026)
        // USD je základní měna
        $rates = [
            'CZK' => '24.50',
            'EUR' => '0.92',
            'RUB' => '90.00',
            'BTC' => '0.0000105', // 1 USD = 0.0000105 BTC (cca 95k USD za BTC)
            'JPY' => '150.00',
            'USD' => '1.00',
        ];

        foreach ($rates as $code => $rate) {
            $currency = CurrencyEnum::from($code);
            $exchangeRate = new ExchangeRate(
                CurrencyEnum::USD,
                $currency,
                BigDecimal::of($rate)
            );
            $manager->persist($exchangeRate);
        }

        $manager->flush();
    }
}
