<?php

declare(strict_types=1);

namespace App\PolygraphyDigest\Service\Crawler;

use App\PolygraphyDigest\Enum\SourceTypeEnum;
use App\PolygraphyDigest\Service\Crawler\Parser\ApiParser;
use App\PolygraphyDigest\Service\Crawler\Parser\HtmlParser;
use App\PolygraphyDigest\Service\Crawler\Parser\ParserInterface;
use App\PolygraphyDigest\Service\Crawler\Parser\RssParser;

/**
 * Továrna pro získání správného parseru podle typu zdroje.
 */
final class ParserProvider
{
    public function getParser(SourceTypeEnum $type): ParserInterface
    {
        return match ($type) {
            SourceTypeEnum::RSS => new RssParser(),
            SourceTypeEnum::HTML => new HtmlParser(),
            SourceTypeEnum::API => new ApiParser(),
        };
    }
}
