<?php

declare(strict_types=1);

namespace App\Tests\PolygraphyDigest\Service\Search;

use App\PolygraphyDigest\Service\Search\ElasticsearchClientInterface;
use App\PolygraphyDigest\Service\Search\IndexInitializer;
use Elastic\Elasticsearch\Endpoints\Indices;
use Elastic\Elasticsearch\Exception\ClientResponseException;
use Elastic\Elasticsearch\Exception\MissingParameterException;
use Elastic\Elasticsearch\Exception\ServerResponseException;
use Elastic\Elasticsearch\Response\Elasticsearch;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class IndexInitializerTest extends TestCase
{
    private Indices&MockObject $indices;
    private IndexInitializer $initializer;

    protected function setUp(): void
    {
        $client = $this->createMock(ElasticsearchClientInterface::class);
        $this->indices = $this->createMock(Indices::class);

        $client->method('indices')->willReturn($this->indices);

        $this->initializer = new IndexInitializer($client);
    }

    /**
     * @throws ServerResponseException
     * @throws ClientResponseException
     * @throws MissingParameterException
     */
    public function testInitializeArticlesIndexWhenNotExists(): void
    {
        $indexName = 'polygraphy_articles';

        $response = $this->createMock(Elasticsearch::class);
        $response->method('asBool')->willReturn(false);

        $this->indices->expects($this->once())
            ->method('exists')
            ->with(['index' => $indexName])
            ->willReturn($response);

        $this->indices->expects($this->once())
            ->method('create')
            ->with($this->callback(function (array $params) use ($indexName) {
                return $params['index'] === $indexName
                    && isset($params['body']['settings']['analysis']['analyzer']['cs_analyzer'])
                    && isset($params['body']['mappings']['properties']['title']['fields']['cs']);
            }));

        $this->initializer->initializeArticlesIndex();
    }

    /**
     * @throws ServerResponseException
     * @throws ClientResponseException
     * @throws MissingParameterException
     */
    public function testInitializeArticlesIndexWhenExists(): void
    {
        $indexName = 'polygraphy_articles';

        $response = $this->createMock(Elasticsearch::class);
        $response->method('asBool')->willReturn(true);

        $this->indices->expects($this->once())
            ->method('exists')
            ->with(['index' => $indexName])
            ->willReturn($response);

        $this->indices->expects($this->never())
            ->method('create');

        $this->initializer->initializeArticlesIndex();
    }

    /**
     * @throws ClientResponseException
     * @throws ServerResponseException
     * @throws MissingParameterException
     */
    public function testInitializeProductsIndexWhenNotExists(): void
    {
        $indexName = 'polygraphy_products';

        $response = $this->createMock(Elasticsearch::class);
        $response->method('asBool')->willReturn(false);

        $this->indices->expects($this->once())
            ->method('exists')
            ->with(['index' => $indexName])
            ->willReturn($response);

        $this->indices->expects($this->once())
            ->method('create')
            ->with($this->callback(function (array $params) use ($indexName) {
                return $params['index'] === $indexName
                    && isset($params['body']['mappings']['properties']['price'])
                    && $params['body']['mappings']['properties']['price']['type'] === 'scaled_float';
            }));

        $this->initializer->initializeProductsIndex();
    }

    /**
     * @throws ClientResponseException
     * @throws ServerResponseException
     * @throws MissingParameterException
     */
    public function testInitializeProductsIndexWhenExists(): void
    {
        $indexName = 'polygraphy_products';

        $response = $this->createMock(Elasticsearch::class);
        $response->method('asBool')->willReturn(true);

        $this->indices->expects($this->once())
            ->method('exists')
            ->with(['index' => $indexName])
            ->willReturn($response);

        $this->indices->expects($this->never())
            ->method('create');

        $this->initializer->initializeProductsIndex();
    }
}
