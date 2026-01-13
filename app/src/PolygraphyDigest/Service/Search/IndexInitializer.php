<?php

declare(strict_types=1);

namespace App\PolygraphyDigest\Service\Search;

use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\Exception\ClientResponseException;
use Elastic\Elasticsearch\Exception\MissingParameterException;
use Elastic\Elasticsearch\Exception\ServerResponseException;

/**
 * Služba pro inicializaci indexů v Elasticsearch.
 */
readonly class IndexInitializer
{
    public function __construct(
        private Client $client,
    ) {
    }

    /**
     * Inicializuje index pro články (articles).
     *
     * @throws ClientResponseException
     * @throws ServerResponseException
     * @throws MissingParameterException
     */
    public function initializeArticlesIndex(): void
    {
        $indexName = 'polygraphy_articles';

        if ($this->indexExists($indexName)) {
            // Index již existuje
            return;
        }

        $params = [
            'index' => $indexName,
            'body' => [
                'settings' => [
                    'number_of_shards' => 1,
                    'number_of_replicas' => 0,
                    'analysis' => [
                        'analyzer' => [
                            'default' => [
                                'type' => 'standard',
                            ],
                        ],
                    ],
                ],
                'mappings' => [
                    'properties' => [
                        'id' => ['type' => 'keyword'],
                        'title' => [
                            'type' => 'text',
                            'fields' => [
                                'keyword' => ['type' => 'keyword'],
                            ],
                        ],
                        'summary' => ['type' => 'text'],
                        'content' => ['type' => 'text'],
                        'url' => ['type' => 'keyword'],
                        'published_at' => ['type' => 'date'],
                        'source_name' => ['type' => 'keyword'],
                    ],
                ],
            ],
        ];

        $this->client->indices()->create($params);
    }

    /**
     * Inicializuje index pro produkty (products).
     *
     * @throws ClientResponseException
     * @throws ServerResponseException
     * @throws MissingParameterException
     */
    public function initializeProductsIndex(): void
    {
        $indexName = 'polygraphy_products';

        if ($this->indexExists($indexName)) {
            return;
        }

        $params = [
            'index' => $indexName,
            'body' => [
                'settings' => [
                    'number_of_shards' => 1,
                    'number_of_replicas' => 0,
                ],
                'mappings' => [
                    'properties' => [
                        'id' => ['type' => 'keyword'],
                        'name' => [
                            'type' => 'text',
                            'fields' => [
                                'keyword' => ['type' => 'keyword'],
                            ],
                        ],
                        'description' => ['type' => 'text'],
                        'price' => [
                            'type' => 'scaled_float',
                            'scaling_factor' => 100,
                        ],
                        'currency' => ['type' => 'keyword'],
                        'article_id' => ['type' => 'keyword'],
                    ],
                ],
            ],
        ];

        $this->client->indices()->create($params);
    }

    /**
     * Ověří, zda index existuje.
     *
     * @throws ClientResponseException
     * @throws MissingParameterException
     * @throws ServerResponseException
     */
    private function indexExists(string $indexName): bool
    {
        return $this->client->indices()->exists(['index' => $indexName])->asBool();
    }
}
