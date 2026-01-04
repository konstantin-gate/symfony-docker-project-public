<?php

declare(strict_types=1);

namespace App\Tests\MultiCurrencyWallet\Integration\Api;

use App\MultiCurrencyWallet\Entity\ExchangeRate;
use App\MultiCurrencyWallet\Entity\WalletConfiguration;
use App\MultiCurrencyWallet\Enum\CurrencyEnum;
use Brick\Math\BigDecimal;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Integrační testy pro endpoint získání referenčních kurzů.
 * @see \App\MultiCurrencyWallet\Controller\Api\GetReferenceRatesController
 */
class GetReferenceRatesControllerTest extends WebTestCase
{
    /**
     * Testuje získání seznamu referenčních kurzů.
     * Ověřuje, že API vrací správně vypočítané křížové kurzy na základě nastavené hlavní měny.
     *
     * @throws \JsonException
     */
    public function testGetReferenceRates(): void
    {
        $client = self::createClient();
        $container = $client->getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);

        // Vyčištění databáze
        $em->createQuery('DELETE FROM App\MultiCurrencyWallet\Entity\ExchangeRate')->execute();
        $em->createQuery('DELETE FROM App\MultiCurrencyWallet\Entity\WalletConfiguration')->execute();

        // 1. Nastavení konfigurace (Hlavní měna = USD)
        $config = new WalletConfiguration();
        $config->setMainCurrency(CurrencyEnum::USD);
        $em->persist($config);

        // 2. Příprava testovacích kurzů
        // USD -> CZK = 22.50
        $rateCzk = new ExchangeRate(CurrencyEnum::USD, CurrencyEnum::CZK, BigDecimal::of('22.50'), new \DateTimeImmutable());
        $em->persist($rateCzk);

        // USD -> EUR = 0.85
        $rateEur = new ExchangeRate(CurrencyEnum::USD, CurrencyEnum::EUR, BigDecimal::of('0.85'), new \DateTimeImmutable());
        $em->persist($rateEur);

        $em->flush();

        // 3. Volání API endpointu
        $client->request('GET', '/api/multi-currency-wallet/reference-rates');

        self::assertResponseIsSuccessful();
        $data = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        $this->assertTrue($data['success']);
        $this->assertIsArray($data['rates']);

        // Očekáváme, že v odpovědi bude pár USD -> CZK
        // ReferenceRateService vrací pole s klíči 'source_amount', 'source_currency', 'target_amount', 'target_currency', 'rate'
        $czkFound = false;

        foreach ($data['rates'] as $row) {
            if ($row['source_currency'] === 'USD' && $row['target_currency'] === 'CZK') {
                $czkFound = true;
                // Smart Amount pro USD je 1.00
                $this->assertEquals('1.00', $row['source_amount']);

                // Kurz: 22.50
                $this->assertEqualsWithDelta(22.5, (float) $row['rate'], 0.0001);

                // Cílová částka: 1 * 22.50 = 22.50
                $this->assertEqualsWithDelta(22.5, (float) $row['target_amount'], 0.0001);
            }
        }

        $this->assertTrue($czkFound, 'Referenční kurz USD -> CZK nebyl nalezen');
    }

    /**
     * Testuje dynamickou volbu "hezké částky" (smart amount) na základě hlavní měny.
     *
     * @throws \JsonException
     */
    public function testSmartAmountSelection(): void
    {
        $client = self::createClient();
        $container = $client->getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);

        $em->createQuery('DELETE FROM App\MultiCurrencyWallet\Entity\ExchangeRate')->execute();
        $em->createQuery('DELETE FROM App\MultiCurrencyWallet\Entity\WalletConfiguration')->execute();

        // 1. CZK -> 100
        $config = new WalletConfiguration();
        $config->setMainCurrency(CurrencyEnum::CZK);
        $em->persist($config);
        $em->persist(new ExchangeRate(CurrencyEnum::CZK, CurrencyEnum::USD, BigDecimal::of('0.04'), new \DateTimeImmutable()));
        $em->flush();

        $client->request('GET', '/api/multi-currency-wallet/reference-rates');
        $data = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        $this->assertEquals('100.00', $data['rates'][0]['source_amount']);

        // 2. JPY -> 1000
        $em->createQuery('DELETE FROM App\MultiCurrencyWallet\Entity\WalletConfiguration')->execute();
        $configJpy = new WalletConfiguration();
        $configJpy->setMainCurrency(CurrencyEnum::JPY);
        $em->persist($configJpy);
        // Musíme přidat alespoň jeden kurz pro JPY, aby ReferenceRateService mohl vygenerovat páry
        $em->persist(new ExchangeRate(CurrencyEnum::JPY, CurrencyEnum::USD, BigDecimal::of('0.007'), new \DateTimeImmutable()));
        $em->flush();

        $client->request('GET', '/api/multi-currency-wallet/reference-rates');
        $dataJpy = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        // Najdeme první, který začíná na JPY
        $jpyRate = null;

        foreach ($dataJpy['rates'] as $r) {
            if ($r['source_currency'] === 'JPY') {
                $jpyRate = $r;
                break;
            }
        }
        $this->assertNotNull($jpyRate);
        $this->assertEquals('1000', $jpyRate['source_amount']);
    }
}
