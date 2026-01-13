<?php

declare(strict_types=1);

namespace App\PolygraphyDigest\DTO\Search;

/**
 * DTO pro dokument produktu v Elasticsearch.
 */
final readonly class ProductDocument
{
    /**
     * @param string $id UUID produktu
     * @param string $name Název produktu
     * @param string $description Popis produktu
     * @param string|float $price Cena (pro scaled_float v ES)
     * @param string $currency Kód měny (ISO)
     * @param string $articleId UUID referenčního článku
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $description,
        public string|float $price,
        public string $currency,
        public string $articleId,
    ) {
    }

    /**
     * Převede DTO na pole pro Elasticsearch.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'price' => $this->price,
            'currency' => $this->currency,
            'article_id' => $this->articleId,
        ];
    }
}
