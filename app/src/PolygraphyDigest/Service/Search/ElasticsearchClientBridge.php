<?php

declare(strict_types=1);

namespace App\PolygraphyDigest\Service\Search;

use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\Endpoints\Indices;
use Elastic\Elasticsearch\Response\Elasticsearch;
use Elastic\Transport\Transport;
use Psr\Http\Message\RequestInterface;
use Psr\Log\LoggerInterface;

/**
 * Bridge pro Elasticsearch klienta, který umožňuje korektní typování a testování.
 * Implementuje ElasticsearchClientInterface a deleguje volání na skutečný Client.
 */
class ElasticsearchClientBridge implements ElasticsearchClientInterface
{
    public function __construct(
        private Client $client,
    ) {
    }

    public function indices(): Indices
    {
        return $this->client->indices();
    }

    public function getTransport(): Transport
    {
        return $this->client->getTransport();
    }

    public function getLogger(): LoggerInterface
    {
        return $this->client->getLogger();
    }

    public function setAsync(bool $async): self
    {
        $this->client->setAsync($async);
        return $this;
    }

    public function getAsync(): bool
    {
        return $this->client->getAsync();
    }

    public function setElasticMetaHeader(bool $active): self
    {
        $this->client->setElasticMetaHeader($active);
        return $this;
    }

    public function getElasticMetaHeader(): bool
    {
        return $this->client->getElasticMetaHeader();
    }

    public function setResponseException(bool $active): self
    {
        $this->client->setResponseException($active);
        return $this;
    }

    public function getResponseException(): bool
    {
        return $this->client->getResponseException();
    }

    public function sendRequest(RequestInterface $request): Elasticsearch
    {
        /** @var Elasticsearch $response */
        $response = $this->client->sendRequest($request);
        return $response;
    }
}
