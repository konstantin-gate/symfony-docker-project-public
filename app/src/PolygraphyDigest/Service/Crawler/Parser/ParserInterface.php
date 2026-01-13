<?php

declare(strict_types=1);

namespace App\PolygraphyDigest\Service\Crawler\Parser;

use App\PolygraphyDigest\Entity\Article;
use App\PolygraphyDigest\Entity\Source;

/**
 * Rozhraní pro parsery obsahu z různých zdrojů.
 */
interface ParserInterface
{
    /**
     * Parsuje obsah a vrací pole entit Article.
     *
     * @param string $content Surový obsah ke zpracování.
     * @param Source $source Zdroj, ke kterému články patří.
     * @return Article[]
     */
    public function parse(string $content, Source $source): array;
}
