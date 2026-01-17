<?php

declare(strict_types=1);

namespace App\PolygraphyDigest\Controller\Api;

use App\PolygraphyDigest\DTO\Search\SearchCriteria;
use App\PolygraphyDigest\Enum\ArticleStatusEnum;
use App\PolygraphyDigest\Repository\ArticleRepository;
use App\PolygraphyDigest\Repository\SourceRepository;
use App\PolygraphyDigest\Service\Crawler\CrawlerService;
use App\PolygraphyDigest\Service\Search\SearchIndexer;
use App\PolygraphyDigest\Service\Search\SearchService;
use Doctrine\ORM\EntityManagerInterface;
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
        private readonly ArticleRepository $articleRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly SearchIndexer $searchIndexer,
        private readonly CrawlerService $crawlerService,
        private readonly SourceRepository $sourceRepository,
    ) {
    }

    /**
     * Spustí manuální parsování všech zdrojů.
     * Endpoint: POST /api/polygraphy/crawl.
     */
    #[Route('/crawl', name: 'crawl_all', methods: ['POST'])]
    public function crawlAll(): JsonResponse
    {
        try {
            $stats = $this->crawlerService->processAllSources();

            return new JsonResponse($stats, 200);
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
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

            $sourceName = $criteria->filters['source_id'] ?? null;
            $lastScrapedAt = $this->sourceRepository->findLatestScrapedAt($sourceName);

            if ($lastScrapedAt) {
                $result->lastUpdatedAt = $lastScrapedAt->format(\DateTimeInterface::ATOM);
            }

            return new JsonResponse($this->serializer->serialize($result, 'json'), 200, [], true);
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Aktualizace stavu článku.
     * Endpoint: PATCH /api/polygraphy/articles/{id}/status.
     */
    #[Route('/articles/{id}/status', name: 'update_article_status', methods: ['PATCH'])]
    public function updateArticleStatus(string $id, Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true, 512, \JSON_THROW_ON_ERROR);
            $statusValue = $data['status'] ?? null;

            if (!$statusValue) {
                return new JsonResponse(['error' => 'Status is required'], 400);
            }

            $status = ArticleStatusEnum::tryFrom($statusValue);

            if (!$status) {
                return new JsonResponse(['error' => 'Invalid status'], 400);
            }

            $article = $this->articleRepository->find($id);

            if (!$article) {
                return new JsonResponse(['error' => 'Article not found'], 404);
            }

            $article->setStatus($status);
            $this->entityManager->flush();

            // Reindexace v Elasticsearch
            $this->searchIndexer->indexArticle($article);

            return new JsonResponse(['status' => 'success', 'new_status' => $status->value]);
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
