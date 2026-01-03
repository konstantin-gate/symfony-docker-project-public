<?php

namespace App\MultiCurrencyWallet\Enum;

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
    case ETH = 'ETH';
}
