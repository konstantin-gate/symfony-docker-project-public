<?php

declare(strict_types=1);

namespace App\PolygraphyDigest\DTO\Search;

/**
 * DTO pro dokument článku v Elasticsearch.
 */
final readonly class ArticleDocument
{
    /**
     * @param string $id          UUID článku
     * @param string $title       Titulek
     * @param string $summary     Stručný výtah
     * @param string $content     Textový obsah bez HTML
     * @param string $url         Odkaz na článek
     * @param string $publishedAt Datum publikace (ISO 8601)
     * @param string $sourceName  Název zdroje
     */
    public function __construct(
        public string $id,
        public string $title,
        public string $summary,
        public string $content,
        public string $url,
        public string $publishedAt,
        public string $sourceName,
        public string $status,
    ) {
    }

    /**
     * Převede DTO na pole pro Elasticsearch.
     *
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'summary' => $this->summary,
            'content' => $this->content,
            'url' => $this->url,
            'published_at' => $this->publishedAt,
            'source_name' => $this->sourceName,
            'status' => $this->status,
        ];
    }
}
