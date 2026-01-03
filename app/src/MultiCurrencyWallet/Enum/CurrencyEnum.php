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

    public function getTranslationKey(): string
    {
        return 'card.' . strtolower($this->value);
    }

    public function getDecimals(): int
    {
        return match ($this) {
            self::JPY => 0,
            self::BTC => 8,
            default => 2,
        };
    }

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
