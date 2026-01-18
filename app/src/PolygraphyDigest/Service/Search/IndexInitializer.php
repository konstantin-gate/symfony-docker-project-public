<?php

declare(strict_types=1);

namespace App\PolygraphyDigest\Service\Search;

use App\PolygraphyDigest\Service\Search\ElasticsearchClientInterface;
use Elastic\Elasticsearch\Exception\ClientResponseException;
use Elastic\Elasticsearch\Exception\MissingParameterException;
use Elastic\Elasticsearch\Exception\ServerResponseException;

/**
 * Služba pro inicializaci indexů v Elasticsearch.
 */
readonly class IndexInitializer
{
    public function __construct(
        private ElasticsearchClientInterface $client,
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

        $analysisSettings = [
            'filter' => [
                'czech_stop' => [
                    'type' => 'stop',
                    'stopwords' => '_czech_',
                ],
                'czech_stemmer' => [
                    'type' => 'stemmer',
                    'language' => 'czech',
                ],
                'english_possessive_stemmer' => [
                    'type' => 'stemmer',
                    'language' => 'possessive_english',
                ],
                'english_stop' => [
                    'type' => 'stop',
                    'stopwords' => '_english_',
                ],
                'english_stemmer' => [
                    'type' => 'stemmer',
                    'language' => 'english',
                ],
                'russian_stop' => [
                    'type' => 'stop',
                    'stopwords' => '_russian_',
                ],
                'russian_stemmer' => [
                    'type' => 'stemmer',
                    'language' => 'russian',
                ],
            ],
            'analyzer' => [
                'default' => [
                    'type' => 'standard',
                ],
                'cs_analyzer' => [
                    'tokenizer' => 'standard',
                    'filter' => [
                        'lowercase',
                        'czech_stop',
                        'czech_stemmer',
                        'asciifolding',
                    ],
                ],
                'en_analyzer' => [
                    'tokenizer' => 'standard',
                    'filter' => [
                        'english_possessive_stemmer',
                        'lowercase',
                        'english_stop',
                        'english_stemmer',
                        'asciifolding',
                    ],
                ],
                'ru_analyzer' => [
                    'tokenizer' => 'standard',
                    'filter' => [
                        'lowercase',
                        'russian_stop',
                        'russian_stemmer',
                    ],
                ],
            ],
        ];

        $params = [
            'index' => $indexName,
            'body' => [
                'settings' => [
                    'number_of_shards' => 1,
                    'number_of_replicas' => 0,
                    'analysis' => $analysisSettings,
                ],
                'mappings' => [
                    'properties' => [
                        'id' => ['type' => 'keyword'],
                        'title' => [
                            'type' => 'text',
                            'analyzer' => 'standard',
                            'fields' => [
                                'cs' => ['type' => 'text', 'analyzer' => 'cs_analyzer'],
                                'en' => ['type' => 'text', 'analyzer' => 'en_analyzer'],
                                'ru' => ['type' => 'text', 'analyzer' => 'ru_analyzer'],
                                'keyword' => ['type' => 'keyword'],
                            ],
                        ],
                        'summary' => [
                            'type' => 'text',
                            'analyzer' => 'standard',
                            'fields' => [
                                'cs' => ['type' => 'text', 'analyzer' => 'cs_analyzer'],
                                'en' => ['type' => 'text', 'analyzer' => 'en_analyzer'],
                                'ru' => ['type' => 'text', 'analyzer' => 'ru_analyzer'],
                            ],
                        ],
                        'content' => [
                            'type' => 'text',
                            'analyzer' => 'standard',
                            'fields' => [
                                'cs' => ['type' => 'text', 'analyzer' => 'cs_analyzer'],
                                'en' => ['type' => 'text', 'analyzer' => 'en_analyzer'],
                                'ru' => ['type' => 'text', 'analyzer' => 'ru_analyzer'],
                            ],
                        ],
                        'url' => ['type' => 'keyword'],
                        'published_at' => ['type' => 'date'],
                        'source_name' => ['type' => 'keyword'],
                        'status' => ['type' => 'keyword'],
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

        $analysisSettings = [
            'filter' => [
                'czech_stop' => [
                    'type' => 'stop',
                    'stopwords' => '_czech_',
                ],
                'czech_stemmer' => [
                    'type' => 'stemmer',
                    'language' => 'czech',
                ],
                'english_possessive_stemmer' => [
                    'type' => 'stemmer',
                    'language' => 'possessive_english',
                ],
                'english_stop' => [
                    'type' => 'stop',
                    'stopwords' => '_english_',
                ],
                'english_stemmer' => [
                    'type' => 'stemmer',
                    'language' => 'english',
                ],
                'russian_stop' => [
                    'type' => 'stop',
                    'stopwords' => '_russian_',
                ],
                'russian_stemmer' => [
                    'type' => 'stemmer',
                    'language' => 'russian',
                ],
            ],
            'analyzer' => [
                'default' => [
                    'type' => 'standard',
                ],
                'cs_analyzer' => [
                    'tokenizer' => 'standard',
                    'filter' => [
                        'lowercase',
                        'czech_stop',
                        'czech_stemmer',
                        'asciifolding',
                    ],
                ],
                'en_analyzer' => [
                    'tokenizer' => 'standard',
                    'filter' => [
                        'english_possessive_stemmer',
                        'lowercase',
                        'english_stop',
                        'english_stemmer',
                        'asciifolding',
                    ],
                ],
                'ru_analyzer' => [
                    'tokenizer' => 'standard',
                    'filter' => [
                        'lowercase',
                        'russian_stop',
                        'russian_stemmer',
                    ],
                ],
            ],
        ];

        $params = [
            'index' => $indexName,
            'body' => [
                'settings' => [
                    'number_of_shards' => 1,
                    'number_of_replicas' => 0,
                    'analysis' => $analysisSettings,
                ],
                'mappings' => [
                    'properties' => [
                        'id' => ['type' => 'keyword'],
                        'name' => [
                            'type' => 'text',
                            'analyzer' => 'standard',
                            'fields' => [
                                'cs' => ['type' => 'text', 'analyzer' => 'cs_analyzer'],
                                'en' => ['type' => 'text', 'analyzer' => 'en_analyzer'],
                                'ru' => ['type' => 'text', 'analyzer' => 'ru_analyzer'],
                                'keyword' => ['type' => 'keyword'],
                            ],
                        ],
                        'description' => [
                            'type' => 'text',
                            'analyzer' => 'standard',
                            'fields' => [
                                'cs' => ['type' => 'text', 'analyzer' => 'cs_analyzer'],
                                'en' => ['type' => 'text', 'analyzer' => 'en_analyzer'],
                                'ru' => ['type' => 'text', 'analyzer' => 'ru_analyzer'],
                            ],
                        ],
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
