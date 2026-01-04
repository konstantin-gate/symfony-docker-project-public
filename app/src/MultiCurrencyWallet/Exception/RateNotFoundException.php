<?php

declare(strict_types=1);

namespace App\MultiCurrencyWallet\Exception;

/**
 * Výjimka vyhozená v případě, že nebyl nalezen požadovaný směnný kurz.
 */
class RateNotFoundException extends \RuntimeException
{
}
