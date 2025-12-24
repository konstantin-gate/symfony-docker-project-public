<?php

declare(strict_types=1);

namespace App\Greeting\Enum;

/**
 * Výčet podporovaných jazyků pro pozdravy.
 */
enum GreetingLanguage: string
{
    case Czech = 'cs';
    case English = 'en';
    case Russian = 'ru';

    /**
     * Vrací předmět e-mailu pro konkrétní jazyk.
     * To je velmi užitečné pro použití v Maileru.
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
     * Vrací cestu k Twig šabloně.
     * Například: 'emails/greeting/cs.html.twig'.
     */
    public function getTemplatePath(): string
    {
        return \sprintf('emails/greeting/%s.html.twig', $this->value);
    }
}
