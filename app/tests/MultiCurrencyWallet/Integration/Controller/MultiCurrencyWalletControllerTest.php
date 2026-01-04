<?php

declare(strict_types=1);

namespace App\Tests\MultiCurrencyWallet\Integration\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Integrační testy pro MultiCurrencyWalletController.
 * Ověřuje, zda hlavní stránka peněženky správně funguje a obsahuje potřebné prvky.
 */
class MultiCurrencyWalletControllerTest extends WebTestCase
{
    /**
     * Testuje, zda se hlavní stránka peněženky úspěšně načte a obsahuje kontejner pro React.
     */
    public function testIndexPageLoads(): void
    {
        $client = self::createClient();
        $client->request('GET', '/multi-currency-wallet');

        self::assertResponseIsSuccessful();
        // Kontrola existence kořenového elementu pro React aplikaci
        self::assertSelectorExists('#multi-currency-wallet-root');
    }
}
