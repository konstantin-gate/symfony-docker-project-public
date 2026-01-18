<?php

declare(strict_types=1);

namespace App\PolygraphyDigest\Enum;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Výčet stavů zpracování článku.
 */
enum ArticleStatusEnum: string implements TranslatableInterface
{
    case NEW = 'new';
    case PROCESSED = 'processed';
    case HIDDEN = 'hidden';
    case ERROR = 'error';

    /**
     * Vrátí klíč pro překlad.
     */
    public function getLabel(): string
    {
        return 'polygraphy.article_status.' . $this->value;
    }

    /**
     * Implementace TranslatableInterface.
     */
    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans($this->getLabel(), [], 'polygraphy', $locale);
    }
}
