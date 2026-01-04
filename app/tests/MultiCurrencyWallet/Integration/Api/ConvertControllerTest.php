<?php

declare(strict_types=1);

namespace App\Tests\MultiCurrencyWallet\Integration\Api;

use App\MultiCurrencyWallet\Entity\ExchangeRate;
use App\MultiCurrencyWallet\Enum\CurrencyEnum;
use Brick\Math\BigDecimal;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Integrační testy pro ConvertController API.
 *
 * Tato třída ověřuje funkčnost API endpointu pro konverzi měn (/api/multi-currency-wallet/convert).
 * Zahrnuje testy úspěšné konverze, zpracování neznámých měn a chování při chybějících směnných kurzech.
 */
class ConvertControllerTest extends WebTestCase
{
    /**
     * Testuje úspěšnou konverzi měny s použitím definovaného směnného kurzu v databázi.
     *
     * @throws \JsonException
     */
    public function testConvertSuccess(): void
    {
        $client = self::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        // Vymazání existujících kurzů
        $em->createQuery('DELETE App\MultiCurrencyWallet\Entity\ExchangeRate e')->execute();

        // Přidání kurzu USD -> CZK = 25.00
        $rate = new ExchangeRate(
            CurrencyEnum::USD,
            CurrencyEnum::CZK,
            BigDecimal::of('25.00'),
            new \DateTimeImmutable()
        );
        $em->persist($rate);
        $em->flush();

        // Konverze 10 USD na CZK
        $client->request('POST', '/api/multi-currency-wallet/convert', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'amount' => '10',
            'from' => 'USD',
            'to' => 'CZK',
        ], \JSON_THROW_ON_ERROR));

        self::assertResponseIsSuccessful();
        $data = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        $this->assertTrue($data['success']);
        $this->assertSame('250.00', $data['amount']); // 10 * 25
    }

    /**
     * Testuje chování API při pokusu o konverzi s neplatným kódem měny.
     *
     * @throws \JsonException
     */
    public function testConvertUnknownCurrency(): void
    {
        $client = self::createClient();

        $client->request('POST', '/api/multi-currency-wallet/convert', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'amount' => '10',
            'from' => 'XYZ', // Neplatná měna
            'to' => 'CZK',
        ], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(400); // Nebo 500 v závislosti na implementaci
    }

    /**
     * Testuje chování API, když pro požadovaný pár měn neexistuje směnný kurz.
     *
     * @throws \JsonException
     */
    public function testConvertMissingRate(): void
    {
        $client = self::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);
        $em->createQuery('DELETE App\MultiCurrencyWallet\Entity\ExchangeRate e')->execute();

        // Konverze USD na CZK bez kurzů v DB
        $client->request('POST', '/api/multi-currency-wallet/convert', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'amount' => '10',
            'from' => 'USD',
            'to' => 'CZK',
        ], \JSON_THROW_ON_ERROR));

        // Implementace může vrátit 500 nebo specifickou chybu
        self::assertResponseStatusCodeSame(500); // Očekáváme RuntimeException
    }
}
