<?php

declare(strict_types=1);

namespace App\Greeting\DTO;

/**
 * Přepravka (DTO) nesoucí výsledek procesu importu kontaktů.
 */
readonly class GreetingImportResult
{
    public function __construct(
        public int $count,
        public ?string $errorKey = null,
        public bool $isSuccess = true,
    ) {
    }

    /**
     * Vytvoří objekt reprezentující úspěšný import.
     */
    public static function success(int $count): self
    {
        return new self($count, null, true);
    }

    /**
     * Vytvoří objekt reprezentující chybu při importu.
     */
    public static function failure(string $errorKey): self
    {
        return new self(0, $errorKey, false);
    }
}
