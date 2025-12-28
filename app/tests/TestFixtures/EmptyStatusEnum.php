<?php

declare(strict_types=1);

namespace App\Tests\TestFixtures;

/**
 * Prázdný enum sloužící jako testovací přípravek (fixture).
 * Používá se pro testování hraničních případů, kdy enum neobsahuje žádné hodnoty.
 */
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
