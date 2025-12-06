<?php

declare(strict_types=1);

namespace App\Enum;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

enum Status: string implements TranslatableInterface
{
    // Определение констант (значения, которые попадут в БД)
    case Concept = 'concept';
    case Active = 'active';
    case Inactive = 'inactive';
    case Archived = 'archived';
    case Deleted = 'deleted';

    /**
     * Возвращает ключ перевода (например, 'status.active').
     * Сам текст перевода будет лежать в базе или yaml файлах.
     */
    public function getTranslationKey(): string
    {
        return 'status.'.$this->value;
    }

    /**
     * Реализация TranslatableInterface.
     * Позволяет использовать объект статуса напрямую в фильтре trans в Twig:
     * {{article.status|trans}}.
     */
    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        // Переводим ключ, используя домен 'messages' или специальный 'statuses'
        return $translator->trans($this->getTranslationKey(), [], 'statuses', $locale);
    }

    /**
     * Цвет/контекст для UI (например, для CSS классов Bootstrap/Tailwind).
     */
    public function getColor(): string
    {
        return match ($this) {
            self::Active => 'success',   // Зеленый
            self::Concept => 'warning',  // Желтый
            self::Inactive => 'secondary', // Серый
            self::Archived => 'info',    // Голубой
            self::Deleted => 'danger',   // Красный
        };
    }

    /**
     * Доступна ли сущность для публичного просмотра (Nginx/App).
     */
    public function isVisible(): bool
    {
        return match ($this) {
            self::Active => true,
            default => false,
        };
    }

    /**
     * Можно ли редактировать сущность в этом статусе.
     * Например, 'Deleted' и 'Archived' блокируют форму (Read-only).
     */
    public function isEditable(): bool
    {
        return match ($this) {
            self::Concept, self::Active, self::Inactive => true,
            default => false,
        };
    }

    /**
     * Можно ли восстановить сущность из этого статуса.
     */
    public function isRecoverable(): bool
    {
        return match ($this) {
            self::Deleted, self::Archived => true,
            default => false,
        };
    }

    /**
     * Хелпер для Symfony Forms (ChoiceType).
     * Генерирует массив ['status.concept' => Status::Concept, ...].
     *
     * @return array<string, self>
     */
    public static function getChoices(): array
    {
        $choices = [];

        foreach (self::cases() as $case) {
            // Ключ массива — это ключ перевода, значение — сам Enum
            $choices[$case->getTranslationKey()] = $case;
        }

        return $choices;
    }
}
