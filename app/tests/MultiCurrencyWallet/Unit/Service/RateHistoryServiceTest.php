<?php

declare(strict_types=1);

namespace App\Tests\MultiCurrencyWallet\Unit\Service;

use App\MultiCurrencyWallet\Enum\CurrencyEnum;
use App\MultiCurrencyWallet\Repository\ExchangeRateRepository;
use App\MultiCurrencyWallet\Service\CurrencyConverter;
use App\MultiCurrencyWallet\Service\RateHistoryService;
use Brick\Money\Money;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Jednotkové testy pro službu RateHistoryService.
 *
 * Ověřuje správnost získávání historických kurzů pomocí CurrencyConverter,
 * který zajišťuje výpočet přímých, inverzních a křížových kurzů.
 */
class RateHistoryServiceTest extends TestCase
{
    /** @var ExchangeRateRepository&MockObject */
    private ExchangeRateRepository $exchangeRateRepository;

    /** @var CurrencyConverter&MockObject */
    private CurrencyConverter $currencyConverter;

    private RateHistoryService $rateHistoryService;

    /**
     * Nastaví prostředí pro testy.
     *
     * Vytvoří mocky repozitáře a konvertoru měn a inicializuje testovanou službu.
     */
    protected function setUp(): void
    {
        $this->exchangeRateRepository = $this->createMock(ExchangeRateRepository::class);
        $this->currencyConverter = $this->createMock(CurrencyConverter::class);
        $this->rateHistoryService = new RateHistoryService(
            $this->exchangeRateRepository,
            $this->currencyConverter,
        );
    }

    /**
     * Ověřuje, že služba vrací prázdné pole, pokud v databázi nejsou žádná data.
     *
     * Testuje chování při prázdné historii kurzů.
     */
    public function testGetHistoryReturnsEmptyArrayWhenNoDatesAvailable(): void
    {
        $this->exchangeRateRepository
            ->method('getAvailableDatesInRange')
            ->willReturn([]);

        $result = $this->rateHistoryService->getHistory(CurrencyEnum::CZK, CurrencyEnum::EUR, 7);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * Ověřuje správné získání historie kurzů pro více dat.
     *
     * Testuje, že služba volá CurrencyConverter pro každé dostupné datum
     * a vrací správně strukturovaný výsledek.
     */
    public function testGetHistoryReturnsCorrectDataForMultipleDates(): void
    {
        $availableDates = ['2026-01-15', '2026-01-16', '2026-01-17'];

        $this->exchangeRateRepository
            ->method('getAvailableDatesInRange')
            ->with(7)
            ->willReturn($availableDates);

        // Mock konverze: 1 CZK -> 0.04 EUR pro každé datum
        $this->currencyConverter
            ->method('convert')
            ->willReturn(Money::of('0.04', CurrencyEnum::EUR->toBrickCurrency()));

        $result = $this->rateHistoryService->getHistory(CurrencyEnum::CZK, CurrencyEnum::EUR, 7);

        $this->assertCount(3, $result);

        // Ověříme strukturu výsledku
        foreach ($result as $item) {
            $this->assertArrayHasKey('date', $item);
            $this->assertArrayHasKey('rate', $item);
            $this->assertEquals('0.04', $item['rate']);
        }

        // Ověříme, že data jsou seřazena vzestupně
        $this->assertEquals('2026-01-15', $result[0]['date']);
        $this->assertEquals('2026-01-16', $result[1]['date']);
        $this->assertEquals('2026-01-17', $result[2]['date']);
    }

    /**
     * Ověřuje, že služba přeskočí data, pro která nelze získat kurz.
     *
     * Testuje odolnost vůči výjimkám z CurrencyConverter.
     */
    public function testGetHistorySkipsDateWhenConversionFails(): void
    {
        $availableDates = ['2026-01-15', '2026-01-16', '2026-01-17'];

        $this->exchangeRateRepository
            ->method('getAvailableDatesInRange')
            ->willReturn($availableDates);

        // Mock: Konverze selže pro 2026-01-16
        $this->currencyConverter
            ->method('convert')
            ->willReturnCallback(function (Money $amount, CurrencyEnum $target, ?\DateTimeInterface $date) {
                $dateString = $date?->format('Y-m-d');
                if ($dateString === '2026-01-16') {
                    throw new \RuntimeException('Kurz nenalezen');
                }

                return Money::of('0.04', CurrencyEnum::EUR->toBrickCurrency());
            });

        $result = $this->rateHistoryService->getHistory(CurrencyEnum::CZK, CurrencyEnum::EUR, 7);

        // Měly by být vráceny pouze 2 záznamy (16. je přeskočen)
        $this->assertCount(2, $result);
        $this->assertEquals('2026-01-15', $result[0]['date']);
        $this->assertEquals('2026-01-17', $result[1]['date']);
    }

    /**
     * Ověřuje správné volání CurrencyConverter s parametrem atDate.
     *
     * Testuje, že služba předává správné datum do konvertoru.
     */
    public function testGetHistoryCallsConverterWithCorrectDate(): void
    {
        $availableDates = ['2026-01-15'];

        $this->exchangeRateRepository
            ->method('getAvailableDatesInRange')
            ->willReturn($availableDates);

        $this->currencyConverter
            ->expects($this->once())
            ->method('convert')
            ->with(
                $this->callback(fn(Money $m) => $m->getAmount()->toFloat() === 1.0),
                CurrencyEnum::EUR,
                $this->callback(fn(\DateTimeInterface $d) => $d->format('Y-m-d') === '2026-01-15'),
            )
            ->willReturn(Money::of('0.04', CurrencyEnum::EUR->toBrickCurrency()));

        $this->rateHistoryService->getHistory(CurrencyEnum::CZK, CurrencyEnum::EUR, 7);
    }

    /**
     * Ověřuje, že služba správně převádí různé měny.
     *
     * Testuje převod CZK -> USD s jiným kurzem.
     */
    public function testGetHistoryWorksWithDifferentCurrencies(): void
    {
        $availableDates = ['2026-01-18'];

        $this->exchangeRateRepository
            ->method('getAvailableDatesInRange')
            ->willReturn($availableDates);

        // 1 CZK = 0.05 USD (zaokrouhleno na 2 desetinná místa)
        $this->currencyConverter
            ->method('convert')
            ->willReturn(Money::of('0.05', CurrencyEnum::USD->toBrickCurrency()));

        $result = $this->rateHistoryService->getHistory(CurrencyEnum::CZK, CurrencyEnum::USD, 7);

        $this->assertCount(1, $result);
        $this->assertEquals('0.05', $result[0]['rate']);
    }
}
