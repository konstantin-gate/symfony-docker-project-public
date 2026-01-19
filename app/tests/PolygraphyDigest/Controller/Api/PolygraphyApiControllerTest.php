<?php

declare(strict_types=1);

namespace App\Tests\PolygraphyDigest\Controller\Api;

use App\PolygraphyDigest\Controller\Api\PolygraphyApiController;
use App\PolygraphyDigest\DTO\Search\SearchCriteria;
use App\PolygraphyDigest\DTO\Search\SearchResult;
use App\PolygraphyDigest\Entity\Article;
use App\PolygraphyDigest\Enum\ArticleStatusEnum;
use App\PolygraphyDigest\Repository\ArticleRepository;
use App\PolygraphyDigest\Repository\SourceRepository;
use App\PolygraphyDigest\Service\Crawler\CrawlerService;
use App\PolygraphyDigest\Service\Search\SearchIndexer;
use App\PolygraphyDigest\Service\Search\SearchService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\SerializerInterface;

class PolygraphyApiControllerTest extends TestCase
{
    private MockObject&SearchService $searchService;
    private MockObject&SerializerInterface $serializer;
    private MockObject&ArticleRepository $articleRepository;
    private MockObject&EntityManagerInterface $entityManager;
    private MockObject&SearchIndexer $searchIndexer;
    private MockObject&CrawlerService $crawlerService;
    private MockObject&SourceRepository $sourceRepository;
    private PolygraphyApiController $controller;

    /**
     * Inicializace prostředí pro testy.
     * Vytváří mock objekty pro všechny závislosti kontroleru a instanciuje samotný kontroler.
     */
    protected function setUp(): void
    {
        $this->searchService = $this->createMock(SearchService::class);
        $this->serializer = $this->createMock(SerializerInterface::class);
        $this->articleRepository = $this->createMock(ArticleRepository::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->searchIndexer = $this->createMock(SearchIndexer::class);
        $this->crawlerService = $this->createMock(CrawlerService::class);
        $this->sourceRepository = $this->createMock(SourceRepository::class);

        $this->controller = new PolygraphyApiController(
            $this->searchService,
            $this->serializer,
            $this->articleRepository,
            $this->entityManager,
            $this->searchIndexer,
            $this->crawlerService,
            $this->sourceRepository
        );
    }

    /**
     * Testuje úspěšné spuštění manuálního parsování (crawl).
     * Ověřuje, že metoda vrací HTTP 200 a JSON se statistikami.
     *
     * @throws \JsonException
     */
    public function testCrawlAllSuccess(): void
    {
        $stats = ['processed' => 5, 'errors' => 0];
        $this->crawlerService->expects($this->once())
            ->method('processAllSources')
            ->willReturn($stats);

        $response = $this->controller->crawlAll();

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(json_encode($stats, JSON_THROW_ON_ERROR), $response->getContent());
    }

    /**
     * Testuje chybu při spouštění parsování.
     * Ověřuje, že v případě výjimky v servisu kontroler vrátí HTTP 500 a chybovou hlášku.
     *
     * @throws \JsonException
     */
    public function testCrawlAllError(): void
    {
        $errorMessage = 'Crawler error';
        $this->crawlerService->expects($this->once())
            ->method('processAllSources')
            ->willThrowException(new \RuntimeException($errorMessage));

        $response = $this->controller->crawlAll();

        $this->assertEquals(500, $response->getStatusCode());
        $content = json_decode((string)$response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertArrayHasKey('error', $content);
        $this->assertEquals($errorMessage, $content['error']);
    }

    /**
     * Testuje základní vyhledávání článků bez filtrů.
     * Ověřuje, že služba vyhledávání je zavolána s prázdnými kritérii a výsledek je serializován.
     */
    public function testSearchArticlesBasic(): void
    {
        $request = new Request();
        $searchResult = new SearchResult([], 0, [], 1, 0);
        $jsonResult = '{"items": [], "total": 0}';

        $this->searchService->expects($this->once())
            ->method('searchArticles')
            ->with($this->isInstanceOf(SearchCriteria::class))
            ->willReturn($searchResult);

        $this->sourceRepository->expects($this->once())
            ->method('findLatestScrapedAt')
            ->with(null)
            ->willReturn(null);

        $this->serializer->expects($this->once())
            ->method('serialize')
            ->with($searchResult, 'json')
            ->willReturn($jsonResult);

        $response = $this->controller->searchArticles($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals($jsonResult, $response->getContent());
    }

    /**
     * Testuje vyhledávání článků s komplexními kritérii (stránkování, řazení, filtry).
     * Ověřuje, že parametry z Requestu jsou správně namapovány do objektu SearchCriteria předaného službě.
     */
    public function testSearchArticlesComplexCriteria(): void
    {
        $request = new Request([
            'page' => 2,
            'limit' => 50,
            'sort' => ['published_at' => 'desc'],
            'filters' => ['source_id' => 'uuid-123', 'status' => 'new']
        ]);

        $searchResult = new SearchResult([], 0, [], 2, 0);

        $this->searchService->expects($this->once())
            ->method('searchArticles')
            ->with($this->callback(function (SearchCriteria $criteria) {
                return $criteria->page === 2
                    && $criteria->limit === 50
                    && $criteria->sort === ['published_at' => 'desc']
                    && $criteria->filters === ['source_id' => 'uuid-123', 'status' => 'new'];
            }))
            ->willReturn($searchResult);

        $this->serializer->expects($this->once())
            ->method('serialize')
            ->with($searchResult, 'json')
            ->willReturn('{}');

        $response = $this->controller->searchArticles($request);

        $this->assertEquals(200, $response->getStatusCode());
    }

    /**
     * Testuje vyhledávání článků s filtrem podle zdroje.
     * Ověřuje, že je správně načteno datum posledního scrapování pro daný zdroj a přidáno do výsledku.
     */
    public function testSearchArticlesWithSourceFilter(): void
    {
        $sourceId = 'some-uuid';
        $request = new Request(['filters' => ['source_id' => $sourceId]]);
        $searchResult = new SearchResult([], 0, [], 1, 0);
        $lastScrapedAt = new \DateTimeImmutable('2023-01-01 12:00:00');

        $this->searchService->expects($this->once())
            ->method('searchArticles')
            ->willReturn($searchResult);

        $this->sourceRepository->expects($this->once())
            ->method('findLatestScrapedAt')
            ->with($sourceId)
            ->willReturn($lastScrapedAt);

        $this->serializer->expects($this->once())
            ->method('serialize')
            ->with(
                $this->callback(function (SearchResult $result) use ($lastScrapedAt) {
                    return $result->lastUpdatedAt === $lastScrapedAt->format(\DateTimeInterface::ATOM);
                }),
                'json'
            )
            ->willReturn('{}');

        $response = $this->controller->searchArticles($request);

        $this->assertEquals(200, $response->getStatusCode());
    }

    /**
     * Testuje chybu při vyhledávání článků.
     * Ověřuje, že v případě výjimky v servisu kontroler vrátí HTTP 500.
     *
     * @throws \JsonException
     */
    public function testSearchArticlesError(): void
    {
        $request = new Request();
        $this->searchService->expects($this->once())
            ->method('searchArticles')
            ->willThrowException(new \RuntimeException('Search error'));

        $response = $this->controller->searchArticles($request);

        $this->assertEquals(500, $response->getStatusCode());
        $content = json_decode((string)$response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertArrayHasKey('error', $content);
    }

    /**
     * Testuje úspěšnou aktualizaci stavu článku.
     * Ověřuje nalezení článku, nastavení stavu, uložení do DB a reindexaci.
     *
     * @throws \JsonException
     */
    public function testUpdateArticleStatusSuccess(): void
    {
        $id = 'article-uuid';
        $statusValue = 'hidden';
        $request = new Request([], [], [], [], [], [], json_encode(['status' => $statusValue], JSON_THROW_ON_ERROR));
        $article = $this->createMock(Article::class);

        $this->articleRepository->expects($this->once())
            ->method('find')
            ->with($id)
            ->willReturn($article);

        $article->expects($this->once())
            ->method('setStatus')
            ->with(ArticleStatusEnum::HIDDEN);

        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->searchIndexer->expects($this->once())
            ->method('indexArticle')
            ->with($article);

        $response = $this->controller->updateArticleStatus($id, $request);

        $this->assertEquals(200, $response->getStatusCode());
        $content = json_decode((string)$response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertEquals('success', $content['status']);
        $this->assertEquals($statusValue, $content['new_status']);
    }

    /**
     * Testuje aktualizaci stavu s chybějícím polem 'status'.
     * Ověřuje návratový kód 400.
     *
     * @throws \JsonException
     */
    public function testUpdateArticleStatusMissingField(): void
    {
        $id = 'article-uuid';
        $request = new Request([], [], [], [], [], [], json_encode(['other' => 'value']));

        $response = $this->controller->updateArticleStatus($id, $request);

        $this->assertEquals(400, $response->getStatusCode());
        $content = json_decode((string)$response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertEquals('Status is required', $content['error']);
    }

    /**
     * Testuje aktualizaci stavu s neplatnou hodnotou.
     * Ověřuje návratový kód 400.
     *
     * @throws \JsonException
     */
    public function testUpdateArticleStatusInvalidEnum(): void
    {
        $id = 'article-uuid';
        $request = new Request([], [], [], [], [], [], json_encode(['status' => 'INVALID_STATUS']));

        $response = $this->controller->updateArticleStatus($id, $request);

        $this->assertEquals(400, $response->getStatusCode());
        $content = json_decode((string)$response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertEquals('Invalid status', $content['error']);
    }

    /**
     * Testuje aktualizaci stavu pro neexistující článek.
     * Ověřuje návratový kód 404.
     *
     * @throws \JsonException
     */
    public function testUpdateArticleStatusNotFound(): void
    {
        $id = 'non-existent-uuid';
        $request = new Request([], [], [], [], [], [], json_encode(['status' => 'hidden']));

        $this->articleRepository->expects($this->once())
            ->method('find')
            ->with($id)
            ->willReturn(null);

        $response = $this->controller->updateArticleStatus($id, $request);

        $this->assertEquals(404, $response->getStatusCode());
        $content = json_decode((string)$response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertEquals('Article not found', $content['error']);
    }

    /**
     * Testuje chybu při ukládání stavu článku.
     * Ověřuje návratový kód 500 při chybě EntityManageru.
     *
     * @throws \JsonException
     */
    public function testUpdateArticleStatusStorageError(): void
    {
        $id = 'article-uuid';
        $request = new Request([], [], [], [], [], [], json_encode(['status' => 'hidden']));
        $article = $this->createMock(Article::class);

        $this->articleRepository->expects($this->once())
            ->method('find')
            ->with($id)
            ->willReturn($article);

        $this->entityManager->expects($this->once())
            ->method('flush')
            ->willThrowException(new \RuntimeException('DB Error'));

        $response = $this->controller->updateArticleStatus($id, $request);

        $this->assertEquals(500, $response->getStatusCode());
        $content = json_decode((string)$response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertArrayHasKey('error', $content);
        $this->assertEquals('DB Error', $content['error']);
    }

    /**
     * Testuje vyhledávání produktů.
     * Ověřuje, že služba vyhledávání je zavolána s kritérii a výsledek je serializován.
     */
    public function testSearchProductsSuccess(): void
    {
        $request = new Request();
        $searchResult = new SearchResult([], 0, [], 1, 0);
        $jsonResult = '{"items": [], "total": 0}';

        $this->searchService->expects($this->once())
            ->method('searchProducts')
            ->with($this->isInstanceOf(SearchCriteria::class))
            ->willReturn($searchResult);

        $this->serializer->expects($this->once())
            ->method('serialize')
            ->with($searchResult, 'json')
            ->willReturn($jsonResult);

        $response = $this->controller->searchProducts($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals($jsonResult, $response->getContent());
    }

    /**
     * Testuje chybu při vyhledávání produktů.
     * Ověřuje, že v případě výjimky v servisu kontroler vrátí HTTP 500.
     *
     * @throws \JsonException
     */
    public function testSearchProductsError(): void
    {
        $request = new Request();
        $this->searchService->expects($this->once())
            ->method('searchProducts')
            ->willThrowException(new \RuntimeException('Product search error'));

        $response = $this->controller->searchProducts($request);

        $this->assertEquals(500, $response->getStatusCode());
        $content = json_decode((string)$response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertArrayHasKey('error', $content);
    }

    /**
     * Testuje našeptávač (autocomplete).
     * Ověřuje, že metoda volá službu s dotazem a vrací seznam návrhů.
     *
     * @throws \JsonException
     */
    public function testSuggestSuccess(): void
    {
        $query = 'poly';
        $suggestions = ['polygraphy', 'polymers'];
        $request = new Request(['q' => $query]);

        $this->searchService->expects($this->once())
            ->method('suggest')
            ->with($query)
            ->willReturn($suggestions);

        $response = $this->controller->suggest($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(json_encode($suggestions, JSON_THROW_ON_ERROR), $response->getContent());
    }

    /**
     * Testuje našeptávač s prázdným dotazem.
     * Ověřuje, že se služba zavolá s prázdným řetězcem, pokud parametr 'q' chybí.
     */
    public function testSuggestEmptyQuery(): void
    {
        $request = new Request(); // No q param

        $this->searchService->expects($this->once())
            ->method('suggest')
            ->with('')
            ->willReturn([]);

        $response = $this->controller->suggest($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('[]', $response->getContent());
    }

    /**
     * Testuje získání statistik (agregací).
     * Ověřuje, že služba je zavolána s limit=0 a vrací agregace a celkový počet.
     *
     * @throws \JsonException
     */
    public function testStatsSuccess(): void
    {
        $request = new Request();
        $aggregations = ['category' => ['buckets' => []]];
        $total = 100;
        $searchResult = new SearchResult([], $total, $aggregations, 1, 0);

        $this->searchService->expects($this->once())
            ->method('searchArticles')
            ->with($this->callback(function (SearchCriteria $criteria) {
                return $criteria->limit === 0;
            }))
            ->willReturn($searchResult);

        $response = $this->controller->stats($request);

        $this->assertEquals(200, $response->getStatusCode());
        $content = json_decode((string)$response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertEquals($aggregations, $content['aggregations']);
        $this->assertEquals($total, $content['total']);
    }

    /**
     * Testuje chybu při získávání statistik.
     * Ověřuje, že v případě výjimky v servisu kontroler vrátí HTTP 500.
     *
     * @throws \JsonException
     */
    public function testStatsError(): void
    {
        $request = new Request();
        $this->searchService->expects($this->once())
            ->method('searchArticles')
            ->willThrowException(new \RuntimeException('Stats error'));

        $response = $this->controller->stats($request);

        $this->assertEquals(500, $response->getStatusCode());
        $content = json_decode((string)$response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertArrayHasKey('error', $content);
        $this->assertEquals('Stats error', $content['error']);
    }
}
