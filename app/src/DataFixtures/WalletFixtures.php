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
        foreach (CurrencyEnum::cases() as $currency) {
            // Náhodná částka mezi 100 a 5000 (pro kryptoměny menší, pro CZK/RUB větší)
            $randomAmount = match($currency) {
                CurrencyEnum::BTC => (string) (mt_rand(1, 50) / 1000), // 0.001 - 0.050 BTC
                CurrencyEnum::ETH => (string) (mt_rand(1, 100) / 100),  // 0.01 - 1.00 ETH
                CurrencyEnum::CZK, CurrencyEnum::RUB => (string) mt_rand(5000, 50000),
                default => (string) mt_rand(500, 5000),
            };

            $balance = new Balance($currency, $randomAmount);
            $manager->persist($balance);
        }

        // 2. Vytvoření směnných kurzů (přibližné kurzy k USD k 3.1.2026)
        // USD je základní měna
        $rates = [
            'CZK' => '24.50',
            'EUR' => '0.92',
            'RUB' => '90.00',
            'BTC' => '0.0000105', // 1 USD = 0.0000105 BTC (cca 95k USD za BTC)
            'ETH' => '0.00037',   // 1 USD = 0.00037 ETH (cca 2.7k USD za ETH)
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
