<?php

declare(strict_types=1);

namespace App\Tests\MultiCurrencyWallet\Integration\Api;

use App\MultiCurrencyWallet\Entity\ExchangeRate;
use App\MultiCurrencyWallet\Enum\CurrencyEnum;
use Brick\Math\BigDecimal;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Integrační testy pro AvailableDatesController.
 *
 * Třída ověřuje funkčnost API endpointu, který vrací seznam unikátních datumů,
 * pro které jsou v systému dostupné směnné kurzy.
 */
class AvailableDatesControllerTest extends WebTestCase
{
    /**
     * Ověřuje, že endpoint vrací unikátní seznam datumů ve formátu 'YYYY-MM-DD'.
     * Test vytváří několik kurzů pro různé měny ve stejné dny a ověřuje,
     * že se ve výstupu každý den objeví pouze jednou.
     *
     * @throws \JsonException
     */
    public function testGetAvailableDatesReturnsUniqueDates(): void
    {
        $client = self::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $em->createQuery('DELETE App\MultiCurrencyWallet\Entity\ExchangeRate e')->execute();

        // Přidání kurzů pro 2023-01-01 a 2023-01-02
        $date1 = new \DateTimeImmutable('2023-01-01 10:00:00');
        $date2 = new \DateTimeImmutable('2023-01-02 12:00:00');

        $rate1 = new ExchangeRate(CurrencyEnum::USD, CurrencyEnum::EUR, BigDecimal::of('0.9'), $date1);
        $rate2 = new ExchangeRate(CurrencyEnum::USD, CurrencyEnum::CZK, BigDecimal::of('22.0'), $date1);
        $rate3 = new ExchangeRate(CurrencyEnum::USD, CurrencyEnum::EUR, BigDecimal::of('0.91'), $date2);

        $em->persist($rate1);
        $em->persist($rate2);
        $em->persist($rate3);
        $em->flush();

        $client->request('GET', '/api/multi-currency-wallet/available-dates');

        self::assertResponseIsSuccessful();
        $data = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        $this->assertTrue($data['success']);
        $dates = $data['dates'];

        $this->assertContains('2023-01-01', $dates);
        $this->assertContains('2023-01-02', $dates);
        $this->assertCount(2, $dates);
    }

    /**
     * Ověřuje, že endpoint vrací prázdný seznam datumů, pokud v databázi neexistují žádné kurzy.
     *
     * @throws \JsonException
     */
    public function testGetAvailableDatesEmpty(): void
    {
        $client = self::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);
        $em->createQuery('DELETE App\MultiCurrencyWallet\Entity\ExchangeRate e')->execute();

        $client->request('GET', '/api/multi-currency-wallet/available-dates');

        self::assertResponseIsSuccessful();
        $data = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        $this->assertEmpty($data['dates']);
    }
}
