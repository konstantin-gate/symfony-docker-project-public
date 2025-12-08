<?php

declare(strict_types=1);

namespace App\Tests\TestFixtures;

enum EmptyStatusEnum: string
{
    // Žádné cases - prázdný enum

    public function getTranslationKey(): string
    {
        return '';
    }

    public function getColor(): string
    {
        return '';
    }

    public function isVisible(): bool
    {
        return false;
    }

    public function isEditable(): bool
    {
        return false;
    }
}
