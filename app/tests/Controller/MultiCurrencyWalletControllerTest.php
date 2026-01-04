<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Testuje dostupnost a správné vykreslení hlavní stránky modulu Multi-Currency Wallet.
 * Ověřuje, zda se načte React aplikace, zda jsou přítomny potřebné data atributy
 * a zda se načítají správné assety.
 */
class MultiCurrencyWalletControllerTest extends WebTestCase
{
    /**
     * Ověřuje, že stránka peněženky se správně načte, obsahuje kontejner pro React
     * a má nastavené všechny potřebné datové atributy (initial state).
     *
     * @throws \JsonException
     */
    public function testPageMountsCorrectly(): void
    {
        $client = self::createClient();
        // Používáme explicitně anglickou lokalizaci pro konzistenci testu
        $client->request('GET', '/en/multi-currency-wallet');

        self::assertResponseIsSuccessful();

        // 1. Kontrola přítomnosti kořenového elementu pro React
        self::assertSelectorExists('#multi-currency-wallet-root');

        // 2. Kontrola datových atributů
        $root = $client->getCrawler()->filter('#multi-currency-wallet-root');

        $this->assertNotEmpty($root->attr('data-balances'), 'Počáteční zůstatky by měly být přítomny');
        $this->assertNotEmpty($root->attr('data-config'), 'Konfigurace peněženky by měla být přítomna');
        $this->assertNotEmpty($root->attr('data-translations'), 'Překlady by měly být přítomny');

        // 3. Ověření struktury JSON překladů (vzorek)
        $translations = json_decode((string) $root->attr('data-translations'), true, 512, \JSON_THROW_ON_ERROR);
        $this->assertArrayHasKey('dashboard_title', $translations);
        $this->assertEquals('Multi-Currency Wallet', $translations['dashboard_title']); // Předpokládáme angličtinu

        // 4. Kontrola assetů (JS a CSS)
        // Encore tagy generují script a link tagy.
        // Hledáme např. <script src="/build/multi_currency_wallet.js">

        // Poznámka: V testovacím prostředí nemusí být assety sestaveny nebo může chybět manifest,
        // ale šablona by se měla pokusit je vykreslit.

        self::assertSelectorExists('link[href*="multi_currency_wallet"]');
        self::assertSelectorExists('script[src*="multi_currency_wallet"]');
    }
}
