<?php

declare(strict_types=1);

namespace App\PolygraphyDigest\DTO\Search;

use Symfony\Component\HttpFoundation\Request;

/**
 * DTO pro předávání kritérií vyhledávání.
 * Obsahuje parametry jako vyhledávací dotaz, stránkování, filtry a řazení.
 */
class SearchCriteria
{
    public ?string $query = null;
    public int $page = 1;
    public int $limit = 20;
    /** @var array<string, mixed> */
    public array $filters = [];
    /** @var array<string, string> */
    public array $sort = [];

    /**
     * Vytvoří instanci SearchCriteria z HTTP požadavku.
     */
    public static function fromRequest(Request $request): self
    {
        $criteria = new self();
        $criteria->query = $request->query->get('q');
        $criteria->page = max(1, $request->query->getInt('page', 1));
        $criteria->limit = max(1, min(100, $request->query->getInt('limit', 20)));

        // Zpracování filtrů (očekáváme pole, např. filters[source_id]=...)
        $criteria->filters = $request->query->all('filters');

        // Zpracování řazení (očekáváme pole, např. sort[published_at]=desc)
        $criteria->sort = $request->query->all('sort');

        return $criteria;
    }
}
