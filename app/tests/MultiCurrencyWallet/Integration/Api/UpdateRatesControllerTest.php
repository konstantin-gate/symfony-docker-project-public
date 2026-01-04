<?php

declare(strict_types=1);

namespace App\Tests\MultiCurrencyWallet\Integration\Api;

use App\MultiCurrencyWallet\Entity\ExchangeRate;
use App\MultiCurrencyWallet\Enum\CurrencyEnum;
use App\MultiCurrencyWallet\Repository\ExchangeRateRepository;
use App\MultiCurrencyWallet\Service\RateProvider\Dto\RateDto;
use App\MultiCurrencyWallet\Service\RateProvider\ExchangeRateProviderInterface;
use App\MultiCurrencyWallet\Service\RateUpdateService;
use Brick\Math\BigDecimal;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Integrační testy pro endpoint aktualizace kurzů.
 * @see \App\MultiCurrencyWallet\Controller\Api\UpdateRatesController
 */
class UpdateRatesControllerTest extends WebTestCase
{
    /**
     * Testuje, že aktualizace kurzů je přeskočena, pokud jsou v databázi čerstvá data (méně než hodinu stará).
     *
     * @throws \JsonException
     */
    public function testUpdateRatesSkippedIfFresh(): void
    {
        $client = self::createClient();
        $container = $client->getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);

        // Vyčištění tabulky kurzů
        $em->createQuery('DELETE FROM App\MultiCurrencyWallet\Entity\ExchangeRate')->execute();

        // 1. Vložíme "čerstvý" kurz (stáří 5 minut)
        $rate = new ExchangeRate(
            CurrencyEnum::USD,
            CurrencyEnum::EUR,
            BigDecimal::of('0.85'),
            new \DateTimeImmutable('-5 minutes')
        );
        $em->persist($rate);
        $em->flush();

        // 2. Voláme API endpoint pro aktualizaci
        $client->request('POST', '/api/multi-currency-wallet/update-rates');

        self::assertResponseIsSuccessful();
        $data = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        // Očekáváme, že služba vrátí status 'skipped'
        $this->assertTrue($data['success']);
        $this->assertTrue($data['skipped']);
        $this->assertEquals('skipped', $data['provider']);
    }

    /**
     * Testuje úspěšnou aktualizaci kurzů pomocí mockovaného poskytovatele.
     * Ověřuje "happy path", kdy jsou data získána a uložena do DB.
     *
     * @throws \Exception
     */
    public function testUpdateRatesSuccess(): void
    {
        $client = self::createClient();
        // Získáme kontejner. V testovacím prostředí umožňuje manipulaci se službami.
        $container = $client->getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);

        // Vyčištění tabulky kurzů
        $em->createQuery('DELETE FROM App\MultiCurrencyWallet\Entity\ExchangeRate')->execute();

        // 1. Vytvoření Mock providera
        $mockProvider = $this->createMock(ExchangeRateProviderInterface::class);
        $mockProvider->method('getName')->willReturn('mock_provider');

        $mockRateDto = new RateDto(
            'USD',
            'EUR',
            '1.2345'
        );
        $mockProvider->method('fetchRates')->willReturn([$mockRateDto]);

        // 2. Vytvoření instance RateUpdateService s mockem
        $repo = $container->get(ExchangeRateRepository::class);
        $logger = $container->get(LoggerInterface::class);

        // RateUpdateService očekává iterable providerů
        $service = new RateUpdateService(
            [$mockProvider],
            $em,
            $repo,
            $logger
        );

        // 3. Podměna služby v kontejneru
        $container->set(RateUpdateService::class, $service);

        // 4. Volání API
        $client->request('POST', '/api/multi-currency-wallet/update-rates');

        self::assertResponseIsSuccessful();
        $data = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        $this->assertTrue($data['success']);
        $this->assertFalse($data['skipped']);
        $this->assertEquals('mock_provider', $data['provider']);

        // 5. Ověření v databázi
        $em->clear(); // Vyčistit Identity Map, abychom načetli čerstvá data z DB
        $savedRate = $em->getRepository(ExchangeRate::class)->findOneBy([
            'baseCurrency' => CurrencyEnum::USD,
            'targetCurrency' => CurrencyEnum::EUR,
        ]);

        $this->assertNotNull($savedRate, 'Kurz USD -> EUR nebyl v databázi nalezen.');
        $this->assertEqualsWithDelta(1.2345, $savedRate->getRate()->toFloat(), 0.0001);
    }

    /**
     * Testuje scénář, kdy selžou všichni poskytovatelé (nebo služba vyhodí výjimku).
     * Očekáváme 500 Internal Server Error a chybovou hlášku v JSONu.
     *
     * @throws \JsonException
     */
    public function testUpdateRatesAllProvidersFail(): void
    {
        $client = self::createClient();
        $container = $client->getContainer();

        // 1. Mock RateUpdateService, aby vyhodila výjimku
        $mockService = $this->createMock(RateUpdateService::class);
        $mockService->method('updateRates')
            ->willThrowException(new \RuntimeException('All providers failed'));

        // 2. Podměna služby v kontejneru
        $container->set(RateUpdateService::class, $mockService);

        // 3. Volání API
        $client->request('POST', '/api/multi-currency-wallet/update-rates');

        // 4. Assert
        self::assertResponseStatusCodeSame(500);

        $data = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $this->assertArrayHasKey('error', $data);
        $this->assertEquals('All providers failed', $data['error']);
    }
}
