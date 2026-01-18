<?php

declare(strict_types=1);

namespace App\Tests\MultiCurrencyWallet\Integration\Api;

use App\MultiCurrencyWallet\Entity\Balance;
use App\MultiCurrencyWallet\Enum\CurrencyEnum;
use Brick\Money\Exception\UnknownCurrencyException;
use Brick\Money\Money;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Integrační testy pro endpoint aktualizace zůstatku.
 *
 * @see \App\MultiCurrencyWallet\Controller\Api\UpdateBalanceController
 */
class UpdateBalanceControllerTest extends WebTestCase
{
    /**
     * Testuje úspěšnou aktualizaci zůstatku v konkrétní měně.
     * Ověřuje odpověď API i uložení změn v databázi.
     *
     * @throws UnknownCurrencyException
     * @throws \JsonException
     */
    public function testUpdateBalance(): void
    {
        $client = self::createClient();
        $container = $client->getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);

        // Vyčištění tabulky zůstatků
        // Protože jsme právě nastartovali jádro, provedeme úklid
        $em->createQuery('DELETE FROM App\MultiCurrencyWallet\Entity\Balance')->execute();

        // 1. Příprava záznamu o zůstatku (50 USD)
        $balance = new Balance(CurrencyEnum::USD);
        $balance->setMoney(Money::of(50, 'USD'));
        $em->persist($balance);
        $em->flush();

        // 2. Volání API pro aktualizaci na 100 USD
        $client->request(
            'POST',
            '/api/multi-currency-wallet/update-balance',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['currency' => 'USD', 'amount' => 100], \JSON_THROW_ON_ERROR)
        );

        self::assertResponseIsSuccessful();
        $responseData = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        $this->assertTrue($responseData['success']);
        $this->assertEquals('100.00', $responseData['amount']);

        // 3. Ověření v databázi
        $em->clear();
        $updatedBalance = $em->getRepository(Balance::class)->findOneBy(['currency' => CurrencyEnum::USD]);
        $this->assertNotNull($updatedBalance);
        $this->assertEquals('100.00', $updatedBalance->getAmount());
    }

    /**
     * Testuje pokus o aktualizaci neexistujícího záznamu.
     * Očekáváme 404 Not Found.
     *
     * @throws \JsonException
     */
    public function testUpdateBalanceNotFound(): void
    {
        $client = self::createClient();
        $container = $client->getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);

        // Vyčištění tabulky zůstatků
        $em->createQuery('DELETE FROM App\MultiCurrencyWallet\Entity\Balance')->execute();

        // Volání API pro aktualizaci neexistujícího zůstatku
        $client->request(
            'POST',
            '/api/multi-currency-wallet/update-balance',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['currency' => 'EUR', 'amount' => 100], \JSON_THROW_ON_ERROR)
        );

        self::assertResponseStatusCodeSame(404);
        $responseData = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $this->assertEquals('Balance record not found', $responseData['error']);
    }

    /**
     * Testuje validaci neplatné měny při aktualizaci zůstatku.
     *
     * @throws \JsonException
     */
    public function testUpdateBalanceInvalidCurrency(): void
    {
        $client = self::createClient();
        $client->request(
            'POST',
            '/api/multi-currency-wallet/update-balance',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['currency' => 'XXX', 'amount' => 100], \JSON_THROW_ON_ERROR)
        );

        self::assertResponseStatusCodeSame(400);
    }

    /**
     * Testuje validaci záporné částky.
     *
     * @throws \JsonException
     */
    public function testUpdateBalanceNegativeAmount(): void
    {
        $client = self::createClient();
        $client->request(
            'POST',
            '/api/multi-currency-wallet/update-balance',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['currency' => 'USD', 'amount' => -10], \JSON_THROW_ON_ERROR)
        );

        self::assertResponseStatusCodeSame(400);
    }

    /**
     * Testuje validaci nečíselné částky.
     *
     * @throws \JsonException
     */
    public function testUpdateBalanceNonNumericAmount(): void
    {
        $client = self::createClient();
        $client->request(
            'POST',
            '/api/multi-currency-wallet/update-balance',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['currency' => 'USD', 'amount' => 'invalid'], \JSON_THROW_ON_ERROR)
        );

        self::assertResponseStatusCodeSame(400);
    }
}
