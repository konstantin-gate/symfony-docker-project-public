<?php

declare(strict_types=1);

namespace App\PolygraphyDigest\Service\Crawler\Parser;

use App\PolygraphyDigest\Entity\Article;
use App\PolygraphyDigest\Entity\Source;
use Exception;

/**
 * Jednoduchý parser pro JSON API.
 */
final class ApiParser implements ParserInterface
{
    /**
     * @inheritDoc
     */
    public function parse(string $content, Source $source): array
    {
        try {
            $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (Exception) {
            return [];
        }

        $articles = [];
        
        // Předpokládáme, že data jsou pole článků nebo obsahují klíč 'articles'/'data'
        $items = $data['articles'] ?? $data['data'] ?? (is_array($data) ? $data : []);

        foreach ($items as $item) {
            if (!isset($item['title'], $item['url'])) {
                continue;
            }

            $article = new Article();
            $article->setSource($source);
            $article->setTitle((string) $item['title']);
            $article->setUrl((string) $item['url']);
            $article->setExternalId((string) ($item['id'] ?? $item['url']));
            $article->setSummary((string) ($item['summary'] ?? $item['description'] ?? ''));
            
            $articles[] = $article;
        }

        return $articles;
    }
}
