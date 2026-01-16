<?php

declare(strict_types=1);

namespace App\PolygraphyDigest\Service\Search;

use App\PolygraphyDigest\DTO\Search\ArticleDocument;
use App\PolygraphyDigest\DTO\Search\ProductDocument;
use App\PolygraphyDigest\Entity\Article;
use App\PolygraphyDigest\Entity\Product;
use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\Exception\ClientResponseException;
use Elastic\Elasticsearch\Exception\MissingParameterException;
use Elastic\Elasticsearch\Exception\ServerResponseException;

/**
 * Služba pro indexaci dokumentů do Elasticsearch.
 */
class SearchIndexer
{
    private const string INDEX_ARTICLES = 'polygraphy_articles';
    private const string INDEX_PRODUCTS = 'polygraphy_products';

    public function __construct(
        private readonly Client $client,
    ) {
    }

    /**
     * Zaindexuje článek do Elasticsearch.
     *
     * @throws ClientResponseException
     * @throws ServerResponseException
     * @throws MissingParameterException
     */
    public function indexArticle(Article $article): void
    {
        if ($article->getId() === null) {
            throw new \InvalidArgumentException('Article id is required for indexing.');
        }

        $dto = $this->transformArticle($article);

        $params = [
            'index' => self::INDEX_ARTICLES,
            'id' => $article->getId()->toRfc4122(),
            'body' => $dto->toArray(),
        ];

        $this->client->index($params);
    }

    /**
     * Zaindexuje produkt do Elasticsearch.
     *
     * @throws ClientResponseException
     * @throws ServerResponseException
     * @throws MissingParameterException
     */
    public function indexProduct(Product $product): void
    {
        if ($product->getId() === null) {
            throw new \InvalidArgumentException('Product id is required for indexing.');
        }

        $dto = $this->transformProduct($product);

        $params = [
            'index' => self::INDEX_PRODUCTS,
            'id' => $product->getId()->toRfc4122(),
            'body' => $dto->toArray(),
        ];

        $this->client->index($params);
    }

    /**
     * Odstraní článek z indexu.
     *
     * @throws ClientResponseException
     * @throws ServerResponseException
     * @throws MissingParameterException
     */
    public function removeArticle(string $uuid): void
    {
        $params = [
            'index' => self::INDEX_ARTICLES,
            'id' => $uuid,
        ];

        try {
            $this->client->delete($params);
        } catch (ClientResponseException $e) {
            // Ignorujeme chybu 404 (dokument neexistuje)
            if ($e->getResponse()->getStatusCode() !== 404) {
                throw $e;
            }
        }
    }

    /**
     * Převede entitu Article na DTO pro indexaci.
     */
    private function transformArticle(Article $article): ArticleDocument
    {
        $id = $article->getId();

        if ($id === null) {
            throw new \InvalidArgumentException('Article id is required for indexing.');
        }

        return new ArticleDocument(
            id: $id->toRfc4122(),
            title: $article->getTitle() ?? '',
            summary: $article->getSummary() ?? '',
            content: strip_tags($article->getContent() ?? ''),
            url: $article->getUrl() ?? '',
            publishedAt: $article->getPublishedAt()?->format('c') ?? $article->getFetchedAt()->format('c'),
            sourceName: $article->getSource()?->getName() ?? 'Unknown',
            status: $article->getStatus()->value,
        );
    }

    /**
     * Převede entitu Product na DTO pro indexaci.
     */
    private function transformProduct(Product $product): ProductDocument
    {
        $id = $product->getId();

        if ($id === null) {
            throw new \InvalidArgumentException('Product id is required for indexing.');
        }

        return new ProductDocument(
            id: $id->toRfc4122(),
            name: $product->getName() ?? '',
            description: '', // TODO: Doplnit popis, pokud bude v entitě
            price: $product->getPrice() ?? '0.0', // Předpokládáme, že getPrice() vrací numeric string
            currency: $product->getCurrency() ?? 'CZK',
            articleId: $product->getArticle()?->getId()?->toRfc4122() ?? '',
        );
    }
}
