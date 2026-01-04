<?php

declare(strict_types=1);

namespace App\Tests\MultiCurrencyWallet\Integration\Command;

use App\MultiCurrencyWallet\Entity\ExchangeRate;
use App\MultiCurrencyWallet\Enum\CurrencyEnum;
use App\MultiCurrencyWallet\Service\RateUpdateService;
use Brick\Math\BigDecimal;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Integrační testy pro příkaz UpdateRatesCommand.
 * Ověřuje spuštění příkazu a jeho výstup v závislosti na stavu databáze a výsledku služby RateUpdateService.
 */
class UpdateRatesCommandTest extends KernelTestCase
{
    /** @var EntityManagerInterface Správce entit pro práci s databází. */
    private EntityManagerInterface $entityManager;

    /**
     * Inicializace testovacího prostředí před každým testem.
     * Spouští kernel, získává EntityManager a čistí tabulku směnných kurzů.
     */
    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);

        // Vyčištění tabulky kurzů před spuštěním testu
        $this->entityManager->createQuery('DELETE App\MultiCurrencyWallet\Entity\ExchangeRate e')->execute();
    }

    /**
     * Testuje úspěšné spuštění příkazu při simulaci úspěšné aktualizace.
     * Ověřuje, že příkaz správně komunikuje se službou a vypisuje odpovídající hlášení.
     */
    public function testExecuteSuccess(): void
    {
        $kernel = self::$kernel;

        if (null === $kernel) {
            $this->fail('Kernel is not booted.');
        }

        $application = new Application($kernel);

        // Mockování RateUpdateService pro zabránění reálným API voláním
        $rateUpdateService = $this->createMock(RateUpdateService::class);
        $rateUpdateService->method('updateRates')->willReturn('MockProvider');

        // Nahrazení služby v kontejneru jejím mockem
        self::getContainer()->set(RateUpdateService::class, $rateUpdateService);

        $command = $application->find('app:wallet:update-rates');
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

        $commandTester->assertCommandIsSuccessful();
        $output = $commandTester->getDisplay();

        $this->assertStringContainsString('Aktualizace směnných kurzů', $output);
        $this->assertStringContainsString('Kurzy byly úspěšně aktualizovány pomocí poskytovatele: MockProvider', $output);
    }

    /**
     * Testuje chování příkazu v případě, že jsou data čerstvá a aktualizace se přeskočí.
     */
    public function testExecuteSkipped(): void
    {
        // 1. Příprava dat: Vložíme čerstvý kurz (stáří 5 minut)
        $rate = new ExchangeRate(
            CurrencyEnum::USD,
            CurrencyEnum::EUR,
            BigDecimal::of('0.85'),
            new \DateTimeImmutable('-5 minutes')
        );
        $this->entityManager->persist($rate);
        $this->entityManager->flush();

        $kernel = self::$kernel;

        if (null === $kernel) {
            $this->fail('Kernel is not booted.');
        }

        $application = new Application($kernel);
        $command = $application->find('app:wallet:update-rates');
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

        $commandTester->assertCommandIsSuccessful();
        $output = $commandTester->getDisplay();

        $this->assertStringContainsString('Aktualizace byla přeskočena', $output);
    }
}
