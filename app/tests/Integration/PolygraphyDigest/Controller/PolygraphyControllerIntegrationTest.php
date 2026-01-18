<?php

declare(strict_types=1);

namespace App\Tests\Integration\PolygraphyDigest\Controller;

use Psr\Cache\InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Contracts\Cache\CacheInterface;

class PolygraphyControllerIntegrationTest extends WebTestCase
{
    /**
     * Testuje integraci s LifecycleService.
     * Ověřuje, že návštěva stránky vyvolá údržbu, což se projeví zápisem do cache.
     *
     * @throws InvalidArgumentException
     */
    public function testLifecycleServiceRunOnRequest(): void
    {
        $client = static::createClient();
        $container = static::getContainer();

        /** @var CacheInterface $cache */
        $cache = $container->get(CacheInterface::class);

        $cacheKey = 'polygraphy_lifecycle_last_run';
        $cache->delete($cacheKey);

        $client->request('GET', '/polygraphy');
        self::assertResponseIsSuccessful();

        $today = (new \DateTimeImmutable())->format('Y-m-d');
        $lastRun = $cache->get($cacheKey, fn() => null);

        $this->assertEquals($today, $lastRun);
    }

    /**
     * Testuje výchozí hodnotu parametru reactRouting.
     * Ověřuje, že při přístupu na /polygraphy je parametr reactRouting roven null.
     */
    public function testDefaultReactRoutingParameter(): void
    {
        $client = static::createClient();
        $client->request('GET', '/polygraphy');

        self::assertResponseIsSuccessful();

        $request = $client->getRequest();
        $reactRouting = $request->attributes->get('reactRouting');

        $this->assertNull($reactRouting, 'Parametr reactRouting by měl být pro základní URL roven null.');
    }
}
