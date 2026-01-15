<?php

declare(strict_types=1);

namespace App\PolygraphyDigest\Service\Search;

use App\PolygraphyDigest\DTO\Search\ArticleDocument;
use App\PolygraphyDigest\DTO\Search\ProductDocument;
use App\PolygraphyDigest\DTO\Search\SearchCriteria;
use App\PolygraphyDigest\DTO\Search\SearchResult;
use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\Exception\ClientResponseException;
use Elastic\Elasticsearch\Exception\ServerResponseException;
use Elastic\Elasticsearch\Response\Elasticsearch;

/**
 * Služba pro vyhledávání v Elasticsearch.
 * Poskytuje metody pro hledání článků a produktů s podporou filtrování, řazení a agregací.
 */
class SearchService
{
    private const string INDEX_ARTICLES = 'polygraphy_articles';
    private const string INDEX_PRODUCTS = 'polygraphy_products';

    public function __construct(
        private readonly Client $client,
    ) {
    }

    /**
     * Vyhledávání článků (Articles).
     *
     * @throws ClientResponseException
     * @throws ServerResponseException
     */
    public function searchArticles(SearchCriteria $criteria): SearchResult
    {
        $params = [
            'index' => self::INDEX_ARTICLES,
            'body' => [
                'from' => ($criteria->page - 1) * $criteria->limit,
                'size' => $criteria->limit,
                'query' => $this->buildArticlesQuery($criteria),
                'aggs' => [
                    'sources' => [
                        'terms' => ['field' => 'source_name'],
                    ],
                    'weekly_trend' => [
                        'filters' => [
                            'filters' => [
                                'current_week' => [
                                    'range' => [
                                        'published_at' => [
                                            'gte' => 'now-7d/d',
                                        ],
                                    ],
                                ],
                                'last_week' => [
                                    'range' => [
                                        'published_at' => [
                                            'gte' => 'now-14d/d',
                                            'lt' => 'now-7d/d',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                'highlight' => [
                    'fields' => [
                        'content' => ['fragment_size' => 150, 'number_of_fragments' => 3],
                        'title' => ['number_of_fragments' => 0],
                    ],
                ],
            ],
        ];

        $postFilter = $this->buildArticlesPostFilter($criteria);
        if ($postFilter) {
            $params['body']['post_filter'] = $postFilter;
        }

        // Přidání řazení
        if (!empty($criteria->sort)) {
            $sort = [];

            foreach ($criteria->sort as $field => $direction) {
                // Pro textová pole použijeme .keyword variantu, pokud existuje (např. title -> title.keyword)
                // Zde zjednodušeně předpokládáme správná pole. Published_at je date, to je ok.
                $sort[] = [$field => ['order' => $direction]];
            }
            $params['body']['sort'] = $sort;
        } else {
            // Defaultní řazení podle skóre (relevance), pokud není query, tak podle data?
            if (!$criteria->query) {
                $params['body']['sort'] = [['published_at' => ['order' => 'desc']]];
            }
        }

        $response = $this->client->search($params);
        \assert($response instanceof Elasticsearch);

        return $this->mapArticlesResponse($response, $criteria);
    }

    /**
     * Vyhledávání produktů (Products).
     *
     * @throws ClientResponseException
     * @throws ServerResponseException
     */
    public function searchProducts(SearchCriteria $criteria): SearchResult
    {
        $params = [
            'index' => self::INDEX_PRODUCTS,
            'body' => [
                'from' => ($criteria->page - 1) * $criteria->limit,
                'size' => $criteria->limit,
                'query' => $this->buildProductsQuery($criteria),
                'aggs' => [
                    'price_stats' => [
                        'stats' => ['field' => 'price'],
                    ],
                ],
            ],
        ];

        $postFilter = $this->buildProductsPostFilter($criteria);
        if ($postFilter) {
            $params['body']['post_filter'] = $postFilter;
        }

        // Default sort for products usually by price or relevance
        if (!empty($criteria->sort)) {
            $sort = [];

            foreach ($criteria->sort as $field => $direction) {
                $sort[] = [$field => ['order' => $direction]];
            }
            $params['body']['sort'] = $sort;
        }

        $response = $this->client->search($params);
        \assert($response instanceof Elasticsearch);

        return $this->mapProductsResponse($response, $criteria);
    }

    /**
     * Našeptávač (Autocomplete).
     *
     * @return array<string> Seznam návrhů
     */
    public function suggest(string $query): array
    {
        if (mb_strlen($query) < 3) {
            return [];
        }

        $params = [
            'index' => self::INDEX_ARTICLES,
            'body' => [
                'size' => 5,
                '_source' => ['title'],
                'query' => [
                    'match_phrase_prefix' => [
                        'title' => $query,
                    ],
                ],
            ],
        ];

        try {
            $response = $this->client->search($params);
            \assert($response instanceof Elasticsearch);
            $data = $response->asArray();
            $suggestions = [];

            foreach ($data['hits']['hits'] as $hit) {
                $suggestions[] = $hit['_source']['title'];
            }

            return array_unique($suggestions);
        } catch (\Exception) {
            return [];
        }
    }

    /**
     * Sestavení query pro články.
     *
     * @return array<string, mixed>
     */
    private function buildArticlesQuery(SearchCriteria $criteria): array
    {
        $bool = [];

        // Fulltext vyhledávání
        if ($criteria->query) {
            $bool['must'][] = [
                'multi_match' => [
                    'query' => $criteria->query,
                    'fields' => ['title^3', 'summary^2', 'content'],
                    'fuzziness' => 'AUTO',
                ],
            ];
        } else {
            $bool['must'][] = ['match_all' => new \stdClass()];
        }

        return ['bool' => $bool];
    }

    /**
     * Sestavení post_filteru pro články.
     * Umožňuje filtrovat výsledky bez ovlivnění agregací (facetů).
     *
     * @return array<string, mixed>|null
     */
    private function buildArticlesPostFilter(SearchCriteria $criteria): ?array
    {
        if (empty($criteria->filters)) {
            return null;
        }

        $filter = [];

        // Filtr podle zdroje (source_id v requestu mapujeme na source_name v ES)
        if (isset($criteria->filters['source_id'])) {
            $filter[] = ['term' => ['source_name' => $criteria->filters['source_id']]];
        }

        // Filtr podle data (published_at range)
        if (isset($criteria->filters['date_from']) || isset($criteria->filters['date_to'])) {
            $range = [];

            if (isset($criteria->filters['date_from'])) {
                $range['gte'] = $criteria->filters['date_from'];
            }

            if (isset($criteria->filters['date_to'])) {
                $range['lte'] = $criteria->filters['date_to'];
            }
            $filter[] = ['range' => ['published_at' => $range]];
        }

        if (empty($filter)) {
            return null;
        }

        return ['bool' => ['filter' => $filter]];
    }

    /**
     * Sestavení query pro produkty.
     *
     * @return array<string, mixed>
     */
    private function buildProductsQuery(SearchCriteria $criteria): array
    {
        $bool = [];

        if ($criteria->query) {
            $bool['must'][] = [
                'multi_match' => [
                    'query' => $criteria->query,
                    'fields' => ['name^3', 'description'],
                    'fuzziness' => 'AUTO',
                ],
            ];
        } else {
            $bool['must'][] = ['match_all' => new \stdClass()];
        }

        return ['bool' => $bool];
    }

    /**
     * Sestavení post_filteru pro produkty.
     *
     * @return array<string, mixed>|null
     */
    private function buildProductsPostFilter(SearchCriteria $criteria): ?array
    {
        if (empty($criteria->filters)) {
            return null;
        }

        $filter = [];

        // Cena
        if (isset($criteria->filters['price_min']) || isset($criteria->filters['price_max'])) {
            $range = [];

            if (isset($criteria->filters['price_min'])) {
                $range['gte'] = $criteria->filters['price_min'];
            }

            if (isset($criteria->filters['price_max'])) {
                $range['lte'] = $criteria->filters['price_max'];
            }
            $filter[] = ['range' => ['price' => $range]];
        }

        // Měna
        if (isset($criteria->filters['currency'])) {
            $filter[] = ['term' => ['currency' => $criteria->filters['currency']]];
        }

        if (empty($filter)) {
            return null;
        }

        return ['bool' => ['filter' => $filter]];
    }

    /**
     * Mapování odpovědi Elasticsearch na SearchResult (Articles).
     */
    private function mapArticlesResponse(Elasticsearch $response, SearchCriteria $criteria): SearchResult
    {
        // Převedeme odpověď na pole pro snadnější manipulaci a statickou analýzu
        $data = $response->asArray();

        $hits = $data['hits']['hits'] ?? [];
        $total = $data['hits']['total']['value'] ?? 0;
        $items = [];

        foreach ($hits as $hit) {
            $source = $hit['_source'];

            $items[] = new ArticleDocument(
                id: $source['id'] ?? '',
                title: $source['title'] ?? '',
                summary: $source['summary'] ?? '',
                // Použijeme highlight pokud existuje, jinak raw content (zkrácený)
                content: $hit['highlight']['content'][0] ?? mb_substr($source['content'] ?? '', 0, 200) . '...',
                url: $source['url'] ?? '',
                publishedAt: $source['published_at'] ?? '',
                sourceName: $source['source_name'] ?? ''
            );
        }

        $aggregations = $data['aggregations'] ?? [];
        $totalPages = $criteria->limit > 0 ? (int) ceil($total / $criteria->limit) : 0;

        return new SearchResult(
            items: $items,
            total: (int) $total,
            aggregations: $aggregations,
            page: $criteria->page,
            totalPages: $totalPages
        );
    }

    /**
     * Mapování odpovědi Elasticsearch na SearchResult (Products).
     */
    private function mapProductsResponse(Elasticsearch $response, SearchCriteria $criteria): SearchResult
    {
        $data = $response->asArray();

        $hits = $data['hits']['hits'] ?? [];
        $total = $data['hits']['total']['value'] ?? 0;
        $items = [];

        foreach ($hits as $hit) {
            $source = $hit['_source'];

            $items[] = new ProductDocument(
                id: $source['id'] ?? '',
                name: $source['name'] ?? '',
                description: $source['description'] ?? '',
                price: $source['price'] ?? 0,
                currency: $source['currency'] ?? '',
                articleId: $source['article_id'] ?? ''
            );
        }

        $aggregations = $data['aggregations'] ?? [];
        $totalPages = $criteria->limit > 0 ? (int) ceil($total / $criteria->limit) : 0;

        return new SearchResult(
            items: $items,
            total: (int) $total,
            aggregations: $aggregations,
            page: $criteria->page,
            totalPages: $totalPages
        );
    }
}
