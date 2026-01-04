<?php

declare(strict_types=1);

namespace App\Tests\MultiCurrencyWallet\Integration\Api;

use App\MultiCurrencyWallet\Entity\Balance;
use App\MultiCurrencyWallet\Entity\ExchangeRate;
use App\MultiCurrencyWallet\Enum\CurrencyEnum;
use Brick\Math\BigDecimal;
use Brick\Money\Exception\UnknownCurrencyException;
use Brick\Money\Money;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Integrační testy pro endpoint výpočtu celkové hodnoty peněženky.
 * @see \App\MultiCurrencyWallet\Controller\Api\CalculateTotalController
 */
class CalculateTotalControllerTest extends WebTestCase
{
    /**
     * Testuje výpočet celkové hodnoty peněženky v cílové měně.
     *
     * @throws UnknownCurrencyException
     * @throws \JsonException
     */
    public function testCalculateTotal(): void
    {
        $client = self::createClient();
        $container = $client->getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);

        // Vyčištění tabulek
        $em->createQuery('DELETE FROM App\MultiCurrencyWallet\Entity\Balance')->execute();
        $em->createQuery('DELETE FROM App\MultiCurrencyWallet\Entity\ExchangeRate')->execute();

        // 1. Příprava dat
        // Zůstatek 100 USD
        $balanceUsd = new Balance(CurrencyEnum::USD);
        $balanceUsd->setMoney(Money::of(100, 'USD'));

        // Zůstatek 100 EUR
        $balanceEur = new Balance(CurrencyEnum::EUR);
        $balanceEur->setMoney(Money::of(100, 'EUR'));

        $em->persist($balanceUsd);
        $em->persist($balanceEur);

        // Kurz USD -> EUR = 0.85 (tedy 1 USD = 0.85 EUR)
        $rate = new ExchangeRate(CurrencyEnum::USD, CurrencyEnum::EUR, BigDecimal::of('0.85'), new \DateTimeImmutable());
        $em->persist($rate);

        $em->flush();

        // 2. Počítáme celkovou hodnotu v USD
        // 100 USD (beze změny) + 100 EUR (převedeno na USD)
        // 100 EUR / 0.85 ~= 117.65 USD
        // Celkem ~= 217.65 USD
        $client->request(
            'POST',
            '/api/multi-currency-wallet/calculate-total',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['targetCurrency' => 'USD'], \JSON_THROW_ON_ERROR)
        );

        self::assertResponseIsSuccessful();
        $data = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        $this->assertEquals('USD', $data['currency']);
        $this->assertEqualsWithDelta(217.65, (float) $data['total'], 0.01);
    }

    /**
     * Testuje validaci neplatné cílové měny při výpočtu celku.
     *
     * @throws \JsonException
     */
    public function testCalculateTotalInvalidCurrency(): void
    {
        $client = self::createClient();
        $client->request(
            'POST',
            '/api/multi-currency-wallet/calculate-total',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['targetCurrency' => 'XXX'], \JSON_THROW_ON_ERROR)
        );

        self::assertResponseStatusCodeSame(400);
    }

    /**
     * Testuje přesnost výpočtu pro velmi velké částky.
     *
     * @throws \JsonException
     */
    public function testCalculateTotalLargeAmounts(): void
    {
        $client = self::createClient();
        $container = $client->getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);

        $em->createQuery('DELETE FROM App\MultiCurrencyWallet\Entity\Balance')->execute();

        // 1 bilion JPY
        $largeJpy = new Balance(CurrencyEnum::JPY, '1000000000000');
        $em->persist($largeJpy);
        $em->flush();

        $client->request(
            'POST',
            '/api/multi-currency-wallet/calculate-total',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['targetCurrency' => 'JPY'], \JSON_THROW_ON_ERROR)
        );

        self::assertResponseIsSuccessful();
        $data = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        $this->assertEquals('1000000000000', $data['total']);
    }

    /**
     * Testuje chování při chybějícím směnném kurzu.
     * Očekáváme 400 Bad Request s chybovou hláškou, nikoliv 500 Error.
     *
     * @throws \JsonException|UnknownCurrencyException
     */
    public function testCalculateTotalMissingRate(): void
    {
        $client = self::createClient();
        $container = $client->getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);

        $em->createQuery('DELETE FROM App\MultiCurrencyWallet\Entity\Balance')->execute();
        $em->createQuery('DELETE FROM App\MultiCurrencyWallet\Entity\ExchangeRate')->execute();

        // 1. Zůstatek 100 CZK
        $balanceCzk = new Balance(CurrencyEnum::CZK);
        $balanceCzk->setMoney(Money::of(100, 'CZK'));
        $em->persist($balanceCzk);
        $em->flush();

        // 2. Žádný kurz CZK -> USD neexistuje

        // 3. Požadavek na přepočet do USD
        $client->request(
            'POST',
            '/api/multi-currency-wallet/calculate-total',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['targetCurrency' => 'USD'], \JSON_THROW_ON_ERROR)
        );

        self::assertResponseStatusCodeSame(400);

        $data = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $this->assertArrayHasKey('error', $data);
    }
}
