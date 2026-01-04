<?php

declare(strict_types=1);

namespace App\MultiCurrencyWallet\Service\RateProvider;

use App\MultiCurrencyWallet\Service\RateProvider\Dto\RateDto;

/**
 * Rozhraní pro poskytovatele směnných kurzů.
 * Definuje kontrakt pro získávání aktuálních kurzů z externích API služeb.
 */
interface ExchangeRateProviderInterface
{
    /**
     * Získá aktuální směnné kurzy z externího zdroje.
     *
     * @return RateDto[]
     */
    public function fetchRates(): array;

    /**
     * Vrátí název poskytovatele (např. 'exchangerate.host').
     */
    public function getName(): string;
}
