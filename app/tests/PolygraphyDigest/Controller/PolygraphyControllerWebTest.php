<?php

declare(strict_types=1);

namespace App\Tests\PolygraphyDigest\Controller;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class PolygraphyControllerWebTest extends WebTestCase
{
    /**
     * Testuje základní cestu bez lokality.
     * Ověřuje, že /polygraphy vrací HTTP 200 OK.
     */
    public function testDefaultRoute(): void
    {
        $client = static::createClient();
        $client->request('GET', '/polygraphy');

        self::assertResponseIsSuccessful();
    }

    /**
     * Testuje cesty s podporovanými lokalitami.
     * Ověřuje, že /cs/polygraphy, /en/polygraphy a /ru/polygraphy vrací HTTP 200 OK.
     */
    #[DataProvider('provideLocales')]
    public function testLocaleRoutes(string $locale): void
    {
        $client = static::createClient();
        $client->request('GET', sprintf('/%s/polygraphy', $locale));

        self::assertResponseIsSuccessful();
    }

    /**
     * Testuje React routing (wildcard parametry).
     * Ověřuje, že libovolná cesta za /polygraphy (kromě /api) vrací HTTP 200 OK.
     */
    #[DataProvider('provideReactRoutes')]
    public function testReactRouting(string $url): void
    {
        $client = static::createClient();
        $client->request('GET', $url);

        self::assertResponseIsSuccessful();
    }

    /**
     * Testuje, že cesty obsahující /api nejsou zachyceny tímto kontrolérem (očekává se 404 nebo jiný handler).
     */
    #[DataProvider('provideApiRoutes')]
    public function testApiRoutesAreExcluded(string $url): void
    {
        $client = static::createClient();
        $client->request('GET', $url);

        // V Symfony 8, pokud cesta neodpovídá requirements (v našem případě ^(?!api).+),
        // tak router hodí 404, pokud neexistuje jiná cesta.
        self::assertResponseStatusCodeSame(404);
    }

    /**
     * Testuje nepodporované lokality.
     * Ověřuje, že nepodporovaná lokalita vrací HTTP 404.
     */
    public function testUnsupportedLocale(): void
    {
        $client = static::createClient();
        $client->request('GET', '/de/polygraphy');

        self::assertResponseStatusCodeSame(404);
    }

    /**
     * Testuje nepodporované HTTP metody.
     * Ověřuje, že jiné metody než GET vrací HTTP 405 Method Not Allowed.
     */
    #[DataProvider('provideInvalidMethods')]
    public function testInvalidMethods(string $method): void
    {
        $client = static::createClient();
        $client->request($method, '/polygraphy');

        self::assertResponseStatusCodeSame(405);
    }

    /**
     * Testuje obsah odpovědi.
     * Ověřuje, že HTML obsahuje základní elementy pro inicializaci React aplikace.
     */
    public function testResponseContent(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/polygraphy');

        self::assertResponseIsSuccessful();

        // Ověření existence root elementu pro React
        $this->assertGreaterThan(0, $crawler->filter('#polygraphy-digest-app')->count());

        // Ověření, že stránka obsahuje spinner pro načítání
        self::assertSelectorExists('.spinner-border');

        // Ověření, že stránka obsahuje zmínku o Polygraphy (z Twig šablony)
        self::assertSelectorTextContains('title', 'Polygraphy');
    }

    /**
     * Poskytuje seznam URL obsahujících /api.
     */
    public static function provideApiRoutes(): array
    {
        return [
            ['/polygraphy/api'],
            ['/polygraphy/api/test'],
            ['/en/polygraphy/api/data'],
        ];
    }

    /**
     * Poskytuje seznam neplatných HTTP metod.
     */
    public static function provideInvalidMethods(): array
    {
        return [
            ['POST'],
            ['PUT'],
            ['DELETE'],
            ['PATCH'],
        ];
    }

    /**
     * Poskytuje seznam podporovaných lokalit pro testování.
     */
    public static function provideLocales(): array
    {
        return [
            ['cs'],
            ['en'],
            ['ru'],
        ];
    }

    /**
     * Poskytuje seznam URL pro testování React routingu.
     */
    public static function provideReactRoutes(): array
    {
        return [
            ['/polygraphy/dashboard'],
            ['/en/polygraphy/articles/123'],
            ['/ru/polygraphy/settings'],
            ['/polygraphy/some-path'],
        ];
    }
}
