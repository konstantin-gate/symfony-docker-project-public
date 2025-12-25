<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Třída testuje hlavní kontroler aplikace.
 */
final class IndexControllerTest extends WebTestCase
{
    /**
     * Testuje hlavní stránku aplikace a její přesměrování.
     */
    public function testIndex(): void
    {
        $client = self::createClient();
        $client->request('GET', '/');

        self::assertResponseRedirects('/cs');
        $client->followRedirect();
        self::assertResponseIsSuccessful();
    }
}
