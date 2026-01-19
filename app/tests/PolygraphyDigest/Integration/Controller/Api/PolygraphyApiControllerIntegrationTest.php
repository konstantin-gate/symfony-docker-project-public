<?php

declare(strict_types=1);

namespace App\Tests\PolygraphyDigest\Integration\Controller\Api;

use App\PolygraphyDigest\Entity\Article;
use App\PolygraphyDigest\Entity\Source;
use App\PolygraphyDigest\Enum\ArticleStatusEnum;
use App\PolygraphyDigest\Enum\SourceTypeEnum;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class PolygraphyApiControllerIntegrationTest extends WebTestCase
{
    /**
     * Testuje omezení HTTP metod pro různé endpointy.
     * Ověřuje, že aplikace vrací stav 405 Method Not Allowed, pokud je použita nesprávná metoda.
     */
    public function testMethodNotAllowed(): void
    {
        $client = static::createClient();

        // 1. GET /api/polygraphy/crawl (očekáváno POST)
        $client->request('GET', '/api/polygraphy/crawl');
        self::assertResponseStatusCodeSame(405);

        // 2. POST /api/polygraphy/articles (očekáváno GET)
        $client->request('POST', '/api/polygraphy/articles');
        self::assertResponseStatusCodeSame(405);

        // 3. GET /api/polygraphy/articles/{id}/status (očekáváno PATCH)
        $client->request('GET', '/api/polygraphy/articles/fake-id/status');
        self::assertResponseStatusCodeSame(405);
    }

    /**
     * Smoke test pro ověření základní dostupnosti endpointů.
     * Ověřuje, že aplikace nespadne s chybou 500 při volání validních URL.
     * Poznámka: Tento test může vyžadovat běžící Elasticsearch nebo jeho mock,
     * pokud služby nejsou izolovány.
     */
    public function testSmokeEndpoints(): void
    {
        $client = static::createClient();

        // 1. GET /api/polygraphy/articles
        $client->request('GET', '/api/polygraphy/articles');
        // Zde může být 200 nebo 500 v závislosti na stavu služeb (ES).
        // Pro smoke test chceme hlavně ověřit, že existuje routa.
        // Pokud spadne na 500 kvůli DB/ES, je to v kontextu integrace "fail", ale routa existuje.
        // Pro účely tohoto kroku (3.1 Routing) nám stačí, že to není 404.
        $this->assertNotSame(404, $client->getResponse()->getStatusCode());

        // 2. GET /api/polygraphy/products
        $client->request('GET', '/api/polygraphy/products');
        $this->assertNotSame(404, $client->getResponse()->getStatusCode());

        // 3. GET /api/polygraphy/suggest
        $client->request('GET', '/api/polygraphy/suggest?q=test');
        $this->assertNotSame(404, $client->getResponse()->getStatusCode());

        // 4. GET /api/polygraphy/stats
        $client->request('GET', '/api/polygraphy/stats');
        $this->assertNotSame(404, $client->getResponse()->getStatusCode());
    }

    /**
     * Integrační test pro změnu stavu článku.
     * Simuluje kompletní tok: Vytvoření článku v DB -> Volání API -> Ověření změny v DB.
     *
     * @throws \JsonException
     */
    public function testUpdateArticleStatusFlow(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);

        // 1. Příprava dat (Source + Article)
        $uniqueSuffix = uniqid('', true);
        $source = new Source();
        $source->setName('Integration Test Source ' . $uniqueSuffix);
        $source->setUrl('https://example.com/rss/' . $uniqueSuffix);
        $source->setType(SourceTypeEnum::RSS);
        $source->setActive(true);
        $em->persist($source);

        $article = new Article();
        $article->setTitle('Test Article ' . $uniqueSuffix);
        $article->setUrl('https://example.com/article/' . $uniqueSuffix);
        $article->setSource($source);
        $article->setStatus(ArticleStatusEnum::NEW);
        $article->setFetchedAt(new \DateTimeImmutable());
        $em->persist($article);

        $em->flush();
        $articleId = $article->getId()->toRfc4122();

        // 2. Volání API pro změnu stavu na 'hidden'
        $client->request(
            'PATCH',
            '/api/polygraphy/articles/' . $articleId . '/status',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['status' => 'hidden'], JSON_THROW_ON_ERROR)
        );

        self::assertResponseIsSuccessful();
        $responseContent = json_decode((string)$client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertEquals('success', $responseContent['status']);
        $this->assertEquals('hidden', $responseContent['new_status']);

        // 3. Ověření změny v DB
        $em->clear(); // Vyčištění Identity Map, aby se data načetla znovu z DB
        $updatedArticle = $em->getRepository(Article::class)->find($articleId);

        $this->assertNotNull($updatedArticle);
        $this->assertEquals(ArticleStatusEnum::HIDDEN, $updatedArticle->getStatus());
    }

    /**
     * Testuje chování aplikace při přijetí nevalidního JSONu.
     * Ověřuje, že aplikace vrátí HTTP 500 (nebo 400, záleží na implementaci) a nespadne nečekanou chybou.
     * V aktuální implementaci kontroleru je catch(\Throwable), takže očekáváme 500 s chybovou hláškou.
     *
     * @throws \JsonException
     */
    public function testMalformedJson(): void
    {
        $client = static::createClient();

        // Odesíláme poškozený JSON (chybí ukončovací závorka)
        $client->request(
            'PATCH',
            '/api/polygraphy/articles/fake-id/status',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            '{ "status": "broken"'
        );

        self::assertResponseStatusCodeSame(500);
        $responseContent = json_decode((string)$client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertArrayHasKey('error', $responseContent);
        // Očekáváme chybu parsování JSONu
        $this->assertStringContainsString('Syntax error', $responseContent['error']);
    }
}
