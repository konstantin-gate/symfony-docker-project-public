<?php

declare(strict_types=1);

namespace App\Tests\MultiCurrencyWallet\Unit\Entity;

use App\MultiCurrencyWallet\Entity\Balance;
use App\MultiCurrencyWallet\Enum\CurrencyEnum;
use Brick\Money\Exception\UnknownCurrencyException;
use Brick\Money\Money;
use PHPUnit\Framework\TestCase;

/**
 * Jednotkové testy pro entitu Balance.
 *
 * Tato třída ověřuje správné chování entity Balance, která reprezentuje zůstatek v peněžence,
 * včetně inicializace, integrace s knihovnou brick/money a validace měn.
 */
class BalanceTest extends TestCase
{
    /**
     * Testuje, zda konstruktor správně nastavuje počáteční hodnoty.
     */
    public function testConstructorSetsValues(): void
    {
        $balance = new Balance(CurrencyEnum::USD, '100.50');

        $this->assertSame(CurrencyEnum::USD, $balance->getCurrency());
        $this->assertSame('100.50', $balance->getAmount());
        $this->assertSame(0, $balance->getDisplayOrder()); // Kontrola výchozí hodnoty
    }

    /**
     * Testuje, zda metoda getMoney vrací správně nakonfigurovaný objekt Money.
     *
     * @throws UnknownCurrencyException
     */
    public function testGetMoneyReturnsCorrectObject(): void
    {
        $balance = new Balance(CurrencyEnum::EUR, '50.00');
        $money = $balance->getMoney();

        $this->assertSame('50.00', (string) $money->getAmount());
        $this->assertSame('EUR', $money->getCurrency()->getCurrencyCode());
    }

    /**
     * Testuje, zda metoda setMoney správně aktualizuje částku v entitě.
     *
     * @throws UnknownCurrencyException
     */
    public function testSetMoneyUpdatesAmount(): void
    {
        $balance = new Balance(CurrencyEnum::CZK, '100');
        $newMoney = Money::of('200', 'CZK');

        $balance->setMoney($newMoney);

        // Knihovna brick/money automaticky normalizuje částku na odpovídající počet desetinných míst
        $this->assertSame('200.00', $balance->getAmount());
    }

    /**
     * Testuje, že metoda setMoney vyhodí výjimku, pokud se měna objektu Money neshoduje s měnou entity.
     *
     * @throws UnknownCurrencyException
     */
    public function testSetMoneyThrowsExceptionOnCurrencyMismatch(): void
    {
        $balance = new Balance(CurrencyEnum::USD, '100');
        $wrongMoney = Money::of('100', 'EUR');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Měna EUR neodpovídá měně účtu USD');

        $balance->setMoney($wrongMoney);
    }

    /**
     * Testuje správné nastavení a získání pořadí zobrazení.
     */
    public function testSetDisplayOrder(): void
    {
        $balance = new Balance(CurrencyEnum::USD);
        $balance->setDisplayOrder(5);

        $this->assertSame(5, $balance->getDisplayOrder());
    }

    /**
     * Testuje chování při záporné částce.
     * I když aktuální implementace záporné částky v entitě neblokuje (není tam validace),
     * je důležité ověřit, jak se k nim chová integrace s brick/money.
     */
    public function testNegativeAmount(): void
    {
        $balance = new Balance(CurrencyEnum::USD, '-10.00');

        $this->assertSame('-10.00', $balance->getAmount());
        $this->assertSame('-10.00', (string) $balance->getMoney()->getAmount());
    }

    /**
     * Testuje přesnost pro velmi velké částky (např. 1 bilion JPY nebo RUB).
     * Ověřuje, že uložení jako řetězec nezpůsobuje ztrátu přesnosti.
     */
    public function testLargeNumbersPrecision(): void
    {
        $largeAmount = '1000000000000.12345678'; // 1 bilion + 8 desetinných míst
        $balance = new Balance(CurrencyEnum::BTC, $largeAmount);

        $this->assertSame($largeAmount, $balance->getAmount());
        $this->assertSame($largeAmount, (string) $balance->getMoney()->getAmount());
    }
}
