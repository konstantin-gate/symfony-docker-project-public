<?php

declare(strict_types=1);

namespace App\PolygraphyDigest\Service\Search;

use Elastic\Elasticsearch\ClientInterface as BaseClientInterface;
use Elastic\Elasticsearch\Endpoints\Indices;

/**
 * Rozšířené rozhraní pro Elasticsearch klienta, které obsahuje metodu indices().
 * Toto rozhraní řeší fakt, že základní ClientInterface v elasticsearch-php neobsahuje všechny metody dostupné v Client.
 */
interface ElasticsearchClientInterface extends BaseClientInterface
{
    public function indices(): Indices;
}
