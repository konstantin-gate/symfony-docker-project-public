<?php

declare(strict_types=1);

namespace App\MultiCurrencyWallet\Service\RateProvider\Dto;

/**
 * Data Transfer Object (DTO) pro přenos informací o směnném kurzu.
 * Slouží k sjednocení dat o kurzu získaných z různých externích API.
 */
readonly class RateDto
{
    public function __construct(
        public string $sourceCurrency,
        public string $targetCurrency,
        public string $rate,
    ) {
    }
}
