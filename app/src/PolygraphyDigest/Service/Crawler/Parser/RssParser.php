<?php

declare(strict_types=1);

namespace App\PolygraphyDigest\Service\Crawler\Parser;

use App\PolygraphyDigest\Entity\Article;
use App\PolygraphyDigest\Entity\Source;

/**
 * Parser pro zpracování RSS kanálů.
 */
final class RssParser implements ParserInterface
{
    public function parse(string $content, Source $source): array
    {
        try {
            $xml = new \SimpleXMLElement($content);
        } catch (\Exception) {
            return [];
        }

        $articles = [];
        $items = $xml->xpath('//item');

        if (!$items) {
            return [];
        }

        foreach ($items as $item) {
            $article = new Article();
            $article->setSource($source);
            $article->setTitle((string) $item->title);
            $article->setUrl((string) $item->link);
            $article->setExternalId((string) $item->guid ?: (string) $item->link);
            $article->setSummary((string) $item->description);
            $article->setContent((string) ($item->children('content', true)->encoded ?: $item->description));

            if ($pubDate = (string) $item->pubDate) {
                try {
                    $article->setPublishedAt(new \DateTimeImmutable($pubDate));
                } catch (\Exception) {
                    // Pokud nelze datum parsovat, zůstane null
                }
            }

            $articles[] = $article;
        }

        return $articles;
    }
}
