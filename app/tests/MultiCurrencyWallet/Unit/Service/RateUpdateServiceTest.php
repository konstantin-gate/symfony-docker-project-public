<?php

declare(strict_types=1);

namespace App\Tests\MultiCurrencyWallet\Unit\Service;

use App\MultiCurrencyWallet\Entity\ExchangeRate;
use App\MultiCurrencyWallet\Repository\ExchangeRateRepository;
use App\MultiCurrencyWallet\Service\RateProvider\Dto\RateDto;
use App\MultiCurrencyWallet\Service\RateProvider\ExchangeRateProviderInterface;
use App\MultiCurrencyWallet\Service\RateUpdateService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Jednotkové testy pro RateUpdateService.
 * Ověřuje logiku aktualizace směnných kurzů, včetně mechanismu failover (přepnutí na záložního poskytovatele)
 * a ochranu proti příliš častým aktualizacím (throttling).
 */
class RateUpdateServiceTest extends TestCase
{
    /** @var EntityManagerInterface&MockObject */
    private EntityManagerInterface $entityManager;
    /** @var ExchangeRateRepository&MockObject */
    private ExchangeRateRepository $exchangeRateRepository;
    /** @var LoggerInterface&MockObject */
    private LoggerInterface $logger;

    /**
     * Inicializace mock objektů před každým testem.
     */
    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->exchangeRateRepository = $this->createMock(ExchangeRateRepository::class);
        $this->logger = $this->createMock(LoggerInterface::class);
    }

    /**
     * Ověřuje, že aktualizace je přeskočena, pokud byla provedena nedávno (např. před 10 minutami).
     * Návratová hodnota by měla být 'skipped'.
     */
    public function testUpdateSkippedIfRecentlyUpdated(): void
    {
        $lastRate = $this->createMock(ExchangeRate::class);
        $lastRate->method('getFetchedAt')->willReturn(new \DateTimeImmutable('-10 minutes'));

        $this->exchangeRateRepository->method('findLatestUpdate')->willReturn($lastRate);

        // Poskytovatelé nejsou potřeba, protože logika k nim nedojde
        $service = new RateUpdateService(
            new \ArrayIterator([]),
            $this->entityManager,
            $this->exchangeRateRepository,
            $this->logger
        );

        $result = $service->updateRates();

        $this->assertEquals('skipped', $result);
    }

    /**
     * Ověřuje úspěšnou aktualizaci pomocí prvního (primárního) poskytovatele.
     * Mělo by dojít k uložení dat do databáze.
     */
    public function testUpdateSuccessWithFirstProvider(): void
    {
        $this->exchangeRateRepository->method('findLatestUpdate')->willReturn(null); // Žádná předchozí aktualizace

        $provider = $this->createMock(ExchangeRateProviderInterface::class);
        $provider->method('getName')->willReturn('PrimaryProvider');
        $provider->method('fetchRates')->willReturn([
            new RateDto('USD', 'EUR', '0.85'),
        ]);

        $service = new RateUpdateService(
            new \ArrayIterator([$provider]),
            $this->entityManager,
            $this->exchangeRateRepository,
            $this->logger
        );

        // Očekáváme persistenci nové entity ExchangeRate
        $this->entityManager->expects($this->once())->method('persist')->with($this->isInstanceOf(ExchangeRate::class));
        $this->entityManager->expects($this->once())->method('flush');

        $result = $service->updateRates();

        $this->assertEquals('PrimaryProvider', $result);
    }

    /**
     * Ověřuje failover logiku: Pokud první poskytovatel selže, služba by měla zkusit druhého.
     * Chyba prvního poskytovatele by měla být zalogována jako warning.
     */
    public function testFailoverToSecondProvider(): void
    {
        $this->exchangeRateRepository->method('findLatestUpdate')->willReturn(null);

        // Primární poskytovatel selže
        $primary = $this->createMock(ExchangeRateProviderInterface::class);
        $primary->method('getName')->willReturn('PrimaryProvider');
        $primary->method('fetchRates')->willThrowException(new \Exception('Connection timeout'));

        // Sekundární poskytovatel uspěje
        $secondary = $this->createMock(ExchangeRateProviderInterface::class);
        $secondary->method('getName')->willReturn('SecondaryProvider');
        $secondary->method('fetchRates')->willReturn([
            new RateDto('USD', 'CZK', '22.50'),
        ]);

        $service = new RateUpdateService(
            new \ArrayIterator([$primary, $secondary]),
            $this->entityManager,
            $this->exchangeRateRepository,
            $this->logger
        );

        // Očekáváme logování chyby primárního poskytovatele
        $this->logger->expects($this->atLeastOnce())->method('warning');

        // Očekáváme uložení dat od sekundárního poskytovatele
        $this->entityManager->expects($this->once())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        $result = $service->updateRates();

        $this->assertEquals('SecondaryProvider', $result);
    }

    /**
     * Ověřuje chování, kdy selžou všichni nakonfigurovaní poskytovatelé.
     * Služba by měla vyhodit výjimku RuntimeException.
     */
    public function testAllProvidersFail(): void
    {
        $this->exchangeRateRepository->method('findLatestUpdate')->willReturn(null);

        $primary = $this->createMock(ExchangeRateProviderInterface::class);
        $primary->method('getName')->willReturn('PrimaryProvider');
        $primary->method('fetchRates')->willThrowException(new \Exception('Error 1'));

        $service = new RateUpdateService(
            new \ArrayIterator([$primary]),
            $this->entityManager,
            $this->exchangeRateRepository,
            $this->logger
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('All exchange rate providers failed');

        $service->updateRates();
    }
}
