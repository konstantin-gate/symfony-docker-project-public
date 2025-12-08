<?php

declare(strict_types=1);

namespace App\Tests\TestFixtures;

enum FailingStatusEnum: string
{
    case TEST = 'test';

    public function getTranslationKey(): string
    {
        return 'status.test';
    }

    public function getColor(): string
    {
        // Simulace chyby při získávání barvy
        throw new \RuntimeException('Database connection failed while fetching color');
    }

    public function isVisible(): bool
    {
        return true;
    }

    public function isEditable(): bool
    {
        return true;
    }
}
