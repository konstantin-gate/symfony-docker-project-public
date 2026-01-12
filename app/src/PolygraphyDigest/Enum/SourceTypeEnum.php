<?php

declare(strict_types=1);

namespace App\PolygraphyDigest\Enum;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Výčet typů zdrojů pro Polygraphy Digest.
 */
enum SourceTypeEnum: string implements TranslatableInterface
{
    case RSS = 'rss';
    case HTML = 'html';
    case API = 'api';

    /**
     * Vrátí klíč pro překlad.
     */
    public function getLabel(): string
    {
        return 'polygraphy.source_type.' . $this->value;
    }

    /**
     * Implementace TranslatableInterface.
     */
    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans($this->getLabel(), [], 'polygraphy', $locale);
    }
}
