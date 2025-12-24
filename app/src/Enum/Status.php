<?php

declare(strict_types=1);

namespace App\Enum;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Výčet stavů entity s podporou překladů a UI vlastností.
 *
 * Definuje možné stavy (Koncept, Aktivní, atd.) a poskytuje metody
 * pro získání jejich překladových klíčů, barev pro UI a logických příznaků (viditelnost, editovatelnost).
 */
enum Status: string implements TranslatableInterface
{
    /**
     * Definice konstant (hodnoty, které se uloží do DB).
     */
    case Concept = 'concept';
    case Active = 'active';
    case Inactive = 'inactive';
    case Archived = 'archived';
    case Deleted = 'deleted';

    /**
     * Vrací klíč pro překlad (například 'status.active').
     * Samotný text překladu bude uložen v databázi nebo YAML souborech.
     */
    public function getTranslationKey(): string
    {
        return 'status.' . $this->value;
    }

    /**
     * Implementace TranslatableInterface.
     * Umožňuje používat objekt stavu přímo v filtru trans v Twig:
     * {{article.status|trans}}.
     */
    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        /* Překládáme klíč pomocí domény 'statuses' */
        return $translator->trans($this->getTranslationKey(), [], 'statuses', $locale);
    }

    /**
     * Barva/kontext pro UI (například pro CSS třídy Bootstrap/Tailwind).
     */
    public function getColor(): string
    {
        return match ($this) {
            self::Active => 'success',   // Zelený
            self::Concept => 'warning',  // Žlutý
            self::Inactive => 'secondary', // Svržená
            self::Archived => 'info',    // Modrá
            self::Deleted => 'danger',   // Červená
        };
    }

    /**
     * Je entita dostupná pro veřejné zobrazení (Nginx/App)?
     */
    public function isVisible(): bool
    {
        return match ($this) {
            self::Active => true,
            default => false,
        };
    }

    /**
     * Lze entitu v tomto stavu upravovat?
     * Například, stavy 'Deleted' a 'Archived' blokují formulář (jen pro čtení).
     */
    public function isEditable(): bool
    {
        return match ($this) {
            self::Concept, self::Active, self::Inactive => true,
            default => false,
        };
    }

    /**
     * Lze entitu z tohoto stavu obnovit?
     */
    public function isRecoverable(): bool
    {
        return match ($this) {
            self::Deleted, self::Archived => true,
            default => false,
        };
    }

    /**
     * Pomocná funkce pro Symfony Forms (ChoiceType).
     * Generuje pole ['status.concept' => Status::Concept, ...].
     *
     * @return array<string, self>
     */
    public static function getChoices(): array
    {
        $choices = [];

        foreach (self::cases() as $case) {
            // Klíč pole je klíč pro překlad, hodnota je samotný Enum
            $choices[$case->getTranslationKey()] = $case;
        }

        return $choices;
    }
}
