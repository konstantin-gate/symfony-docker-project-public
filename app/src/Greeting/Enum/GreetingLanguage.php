<?php

declare(strict_types=1);

namespace App\Greeting\Enum;

enum GreetingLanguage: string
{
    case Czech = 'cs';
    case English = 'en';
    case Russian = 'ru';

    /**
     * Возвращает тему письма для конкретного языка.
     * Это очень удобно использовать в Mailer'е.
     */
    public function getSubject(): string
    {
        return match ($this) {
            self::Czech => 'Veselé Vánoce a šťastný nový rok!',
            self::English => 'Merry Christmas and Happy New Year!',
            self::Russian => 'С Рождеством и Новым годом!',
        };
    }

    /**
     * Возвращает путь к Twig-шаблону.
     * Например: 'emails/greeting/cs.html.twig'.
     */
    public function getTemplatePath(): string
    {
        return \sprintf('emails/greeting/%s.html.twig', $this->value);
    }
}
