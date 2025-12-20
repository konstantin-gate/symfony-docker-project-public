<?php

declare(strict_types=1);

namespace App\DTO;

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
