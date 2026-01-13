<?php

declare(strict_types=1);

namespace App\PolygraphyDigest\DTO\Search;

/**
 * DTO pro výsledky vyhledávání.
 * Obsahuje nalezené položky, celkový počet, agregace a informace o stránkování.
 */
class SearchResult
{
    /**
     * @param array<string, mixed> $aggregations
     */
    public function __construct(
        public array $items,
        public int $total,
        public array $aggregations,
        public int $page,
        public int $totalPages,
    ) {
    }
}
