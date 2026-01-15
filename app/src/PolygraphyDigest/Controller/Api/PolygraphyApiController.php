<?php

declare(strict_types=1);

namespace App\PolygraphyDigest\Controller\Api;

use App\PolygraphyDigest\DTO\Search\SearchCriteria;
use App\PolygraphyDigest\Service\Search\SearchService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * API kontroler pro modul PolygraphyDigest.
 * Poskytuje endpointy pro vyhledávání článků, produktů a další související operace.
 */
#[Route('/api/polygraphy', name: 'api_polygraphy_')]
class PolygraphyApiController extends AbstractController
{
    public function __construct(
        private readonly SearchService $searchService,
        private readonly SerializerInterface $serializer,
    ) {
    }

    /**
     * Vyhledávání článků (news/articles).
     * Endpoint: GET /api/polygraphy/articles.
     */
    #[Route('/articles', name: 'search_articles', methods: ['GET'])]
    public function searchArticles(Request $request): JsonResponse
    {
        try {
            $criteria = SearchCriteria::fromRequest($request);
            $result = $this->searchService->searchArticles($criteria);

            return new JsonResponse($this->serializer->serialize($result, 'json'), 200, [], true);
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Vyhledávání produktů.
     * Endpoint: GET /api/polygraphy/products.
     */
    #[Route('/products', name: 'search_products', methods: ['GET'])]
    public function searchProducts(Request $request): JsonResponse
    {
        try {
            $criteria = SearchCriteria::fromRequest($request);
            $result = $this->searchService->searchProducts($criteria);

            return new JsonResponse($this->serializer->serialize($result, 'json'), 200, [], true);
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Našeptávač (Autocomplete).
     * Endpoint: GET /api/polygraphy/suggest.
     */
    #[Route('/suggest', name: 'suggest', methods: ['GET'])]
    public function suggest(Request $request): JsonResponse
    {
        $query = (string) $request->query->get('q', '');
        $suggestions = $this->searchService->suggest($query);

        return new JsonResponse($suggestions);
    }

    /**
     * Získání statistik (agregací).
     * Endpoint: GET /api/polygraphy/stats.
     */
    #[Route('/stats', name: 'stats', methods: ['GET'])]
    public function stats(Request $request): JsonResponse
    {
        try {
            $criteria = SearchCriteria::fromRequest($request);
            $criteria->limit = 0; // Chceme pouze agregace

            $result = $this->searchService->searchArticles($criteria);

            return new JsonResponse([
                'aggregations' => $result->aggregations,
                'total' => $result->total,
            ]);
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }
}
