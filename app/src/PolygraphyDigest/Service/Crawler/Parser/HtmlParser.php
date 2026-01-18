<?php

declare(strict_types=1);

namespace App\PolygraphyDigest\Service\Crawler\Parser;

use App\PolygraphyDigest\Entity\Article;
use App\PolygraphyDigest\Entity\Source;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Základní parser pro HTML stránky.
 * V této fázi jde o jednoduchou implementaci, která může být rozšířena o specifické selektory.
 */
final class HtmlParser implements ParserInterface
{
    public function parse(string $content, Source $source): array
    {
        $crawler = new Crawler($content);
        $articles = [];

        // Velmi zjednodušená logika: hledáme všechny <a> tagy, které vypadají jako články
        // V reálném nasazení by zde byly selektory z konfigurace zdroje
        $crawler->filter('article, .post, .entry')->each(function (Crawler $node) use (&$articles, $source): void {
            $linkNode = $node->filter('a')->first();

            if ($linkNode->count() === 0) {
                return;
            }

            $title = $node->filter('h1, h2, h3, .title')->first()->text('') ?: $linkNode->text();
            $url = $linkNode->attr('href');

            if (!$url) {
                return;
            }

            // Převod relativní URL na absolutní by se měl řešit v CrawlerService

            $article = new Article();
            $article->setSource($source);
            $article->setTitle($title);
            $article->setUrl($url);
            $article->setExternalId($url);

            $articles[] = $article;
        });

        return $articles;
    }
}
