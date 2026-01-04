<?php

declare(strict_types=1);

namespace App\MultiCurrencyWallet\Enum;

use Brick\Money\Currency;
use Brick\Money\Exception\UnknownCurrencyException;

/**
 * Výčet podporovaných měn.
 */
enum CurrencyEnum: string
{
    case USD = 'USD';
    case EUR = 'EUR';
    case CZK = 'CZK';
    case RUB = 'RUB';
    case BTC = 'BTC';
    case JPY = 'JPY';

    /**
     * Vrátí symbol měny.
     */
    public function getSymbol(): string
    {
        return match ($this) {
            self::USD => '$',
            self::EUR => '€',
            self::CZK => 'Kč',
            self::RUB => '₽',
            self::BTC => '₿',
            self::JPY => '¥',
        };
    }

    /**
     * Vrátí ikonu (emoji vlajky nebo symbol) měny.
     */
    public function getIcon(): string
    {
        return match ($this) {
            self::USD => '🇺🇸',
            self::EUR => '🇪🇺',
            self::CZK => '🇨🇿',
            self::RUB => '🇷🇺',
            self::BTC => '₿',
            self::JPY => '🇯🇵',
        };
    }

    /**
     * Vrátí klíč pro překlad názvu měny.
     */
    public function getTranslationKey(): string
    {
        return 'card.' . strtolower($this->value);
    }

    /**
     * Vrátí počet desetinných míst používaných pro danou měnu.
     */
    public function getDecimals(): int
    {
        return match ($this) {
            self::JPY => 0,
            self::BTC => 8,
            default => 2,
        };
    }

    /**
     * Převede hodnotu výčtu na objekt měny z knihovny brick/money.
     * Zajišťuje podporu i pro ne-ISO měny (např. Bitcoin), které knihovna standardně nezná.
     *
     * @throws UnknownCurrencyException
     */
    public function toBrickCurrency(): Currency
    {
        try {
            return Currency::of($this->value);
        } catch (UnknownCurrencyException $e) {
            return match ($this) {
                self::BTC => new Currency('BTC', 0, 'Bitcoin', 8),
                default => throw $e,
            };
        }
    }
}
