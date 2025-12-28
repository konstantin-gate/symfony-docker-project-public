<?php

declare(strict_types=1);

namespace App\Tests\TestFixtures;

/**
 * Rozhraní definující kontrakt pro stavové enumy v testech.
 * Zajišťuje, že testovací přípravky implementují všechny potřebné metody pro lokalizaci, barvu a viditelnost.
 */
interface StatusInterface
{
    public function getTranslationKey(): string;

    public function getColor(): string;

    public function isVisible(): bool;

    public function isEditable(): bool;
}
