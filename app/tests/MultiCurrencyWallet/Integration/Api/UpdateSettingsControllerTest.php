<?php

declare(strict_types=1);

namespace App\Tests\MultiCurrencyWallet\Integration\Api;

use App\MultiCurrencyWallet\Entity\WalletConfiguration;
use App\MultiCurrencyWallet\Enum\CurrencyEnum;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Integrační testy pro aktualizaci nastavení peněženky.
 */
class UpdateSettingsControllerTest extends WebTestCase
{
    /**
     * Testuje, zda endpoint správně aktualizuje hlavní měnu v konfiguraci.
     *
     * @throws \JsonException
     */
    public function testUpdateSettingsChangesMainCurrency(): void
    {
        $client = self::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        // Zajistí existenci výchozí konfigurace
        $em->createQuery('DELETE App\MultiCurrencyWallet\Entity\WalletConfiguration e')->execute();
        $config = new WalletConfiguration();
        $config->setMainCurrency(CurrencyEnum::CZK);
        $em->persist($config);
        $em->flush();

        $client->request('POST', '/api/multi-currency-wallet/settings', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'mainCurrency' => 'USD',
            'autoUpdateEnabled' => true,
        ], \JSON_THROW_ON_ERROR));

        self::assertResponseIsSuccessful();

        // Ověření uložení do databáze
        $em->clear();
        $updatedConfig = $em->getRepository(WalletConfiguration::class)->findOneBy([]);

        self::assertNotNull($updatedConfig);
        self::assertSame(CurrencyEnum::USD, $updatedConfig->getMainCurrency());
    }
}
