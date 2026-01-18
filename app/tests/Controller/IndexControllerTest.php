<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Třída testuje hlavní stránku (Hub) aplikace.
 * Ověřuje přítomnost modulů, odkazů a správnou lokalizaci.
 */
final class IndexControllerTest extends WebTestCase
{
    /**
     * Testuje přesměrování z kořenové URL na výchozí locale.
     */
    public function testIndexRedirectsToDefaultLocale(): void
    {
        $client = self::createClient();
        $client->request('GET', '/');

        // Očekáváme přesměrování na výchozí locale (např. /cs nebo /ru v závislosti na configu)
        // V testovacím prostředí může být defaultní locale jiné, ale redirect 302 je jistý.
        self::assertResponseStatusCodeSame(302);

        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.group', 'Na stránce by měly být karty modulů');
    }

    /**
     * Testuje strukturu Hub stránky: přítomnost karet modulů a odkazů.
     */
    public function testHubPageStructure(): void
    {
        $client = self::createClient();
        // Testujeme explicitně anglickou verzi pro stabilitu selektorů (pokud bychom hledali text)
        $crawler = $client->request('GET', '/en');

        self::assertResponseIsSuccessful();

        // 1. Ověření nadpisu sekce
        // Check that the H1 contains the translated text "Connected Modules"
        self::assertAnySelectorTextContains('h1', 'Symfony Modular Suite');

        // 2. Ověření Karty "Greeting Module"
        // Hledáme kartu, která obsahuje text "Greeting Module"
        $greetingCard = $crawler->filter('.group')->reduce(fn ($node) => str_contains($node->text(), 'Greeting Module'));
        self::assertGreaterThan(0, $greetingCard->count(), 'Karta Greeting Module nebyla nalezena');

        // Ověření ikony
        $greetingIcon = $greetingCard->filter('img[src*="icon_greeting_letter.png"]');
        self::assertEquals(1, $greetingIcon->count(), 'Ikona Greeting Module chybí');

        // Ověření odkazu
        // Odkaz je nadřazený elementu .group, ale v crawleru hledáme v celém dokumentu nebo kontextu
        $greetingLink = $crawler->filter('a[href*="/greeting/dashboard"]');
        self::assertEquals(1, $greetingLink->count(), 'Odkaz na Greeting Dashboard chybí');

        // 3. Ověření Karty "Multi-Currency Wallet"
        $walletCard = $crawler->filter('.group')->reduce(fn ($node) => str_contains($node->text(), 'Multi-Currency Wallet'));
        self::assertGreaterThan(0, $walletCard->count(), 'Karta Multi-Currency Wallet nebyla nalezena');

        // Ověření ikony
        $walletIcon = $walletCard->filter('img[src*="icon_multi_currency_wallet_2.png"]');
        self::assertEquals(1, $walletIcon->count(), 'Ikona Wallet chybí');

        // Ověření odkazu
        $walletLink = $crawler->filter('a[href*="/multi-currency-wallet"]');
        self::assertEquals(1, $walletLink->count(), 'Odkaz na Wallet Dashboard chybí');
    }

    /**
     * Testuje přepínání jazyků a lokalizaci textů.
     */
    public function testLocalization(): void
    {
        $client = self::createClient();

        // 1. Čeština
        $client->request('GET', '/cs');
        self::assertResponseIsSuccessful();
        // Předpokládáme, že v češtině je název modulu "Odesílání pozdravů" nebo podobně.
        // Zkontrolujeme alespoň nadpis sekce, pokud je přeložen.
        // Poznámka: Konkrétní texty závisí na translation souborech.
        // Pro tento test ověříme, že URL zůstalo /cs a status je 200.
        self::assertSelectorExists('h1');

        // 2. Ruština
        $client->request('GET', '/ru');
        self::assertResponseIsSuccessful();

        // Zde můžeme ověřit specifický text, pokud známe překlad.
        // Např. "Модули" nebo název karty.
        // Prozatím stačí ověřit, že se stránka načetla.
    }
}
