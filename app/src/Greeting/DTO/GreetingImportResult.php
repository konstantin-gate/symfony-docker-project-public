<?php

declare(strict_types=1);

namespace App\Greeting\DTO;

readonly class GreetingImportResult
{
    public function __construct(
        public int $count,
        public ?string $errorKey = null,
        public bool $isSuccess = true,
    ) {
    }

    public static function success(int $count): self
    {
        return new self($count, null, true);
    }

    public static function failure(string $errorKey): self
    {
        return new self(0, $errorKey, false);
    }
}
