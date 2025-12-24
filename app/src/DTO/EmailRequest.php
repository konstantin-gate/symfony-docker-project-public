<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * Přepravka (DTO) pro data potřebná k odeslání e-mailu.
 * Slouží k předávání informací o příjemci, předmětu, šabloně a kontextu mezi službami a do fronty zpráv.
 */
readonly class EmailRequest
{
    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        public string $to,
        public string $subject,
        public string $template,
        public array $context = [],
    ) {
    }
}
