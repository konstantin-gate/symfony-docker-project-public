<?php

declare(strict_types=1);

namespace App\Tests\MultiCurrencyWallet\Unit\Enum;

use App\MultiCurrencyWallet\Enum\CurrencyEnum;
use Brick\Money\Exception\UnknownCurrencyException;
use PHPUnit\Framework\TestCase;

/**
 * Jednotkové testy pro CurrencyEnum.
 *
 * Tato třída ověřuje metadata měn (symboly, ikony, desetinná místa) a převod na objekty brick/money.
 */
class CurrencyEnumTest extends TestCase
{
    /**
     * Testuje správnost vracených symbolů pro jednotlivé měny.
     */
    public function testGetSymbol(): void
    {
        $this->assertSame('$', CurrencyEnum::USD->getSymbol());
        $this->assertSame('€', CurrencyEnum::EUR->getSymbol());
        $this->assertSame('Kč', CurrencyEnum::CZK->getSymbol());
        $this->assertSame('₽', CurrencyEnum::RUB->getSymbol());
        $this->assertSame('₿', CurrencyEnum::BTC->getSymbol());
        $this->assertSame('¥', CurrencyEnum::JPY->getSymbol());
    }

    /**
     * Testuje správnost vracených ikon (emoji) pro jednotlivé měny.
     */
    public function testGetIcon(): void
    {
        $this->assertSame('🇺🇸', CurrencyEnum::USD->getIcon());
        $this->assertSame('🇪🇺', CurrencyEnum::EUR->getIcon());
        $this->assertSame('🇨🇿', CurrencyEnum::CZK->getIcon());
        $this->assertSame('🇷🇺', CurrencyEnum::RUB->getIcon());
        $this->assertSame('₿', CurrencyEnum::BTC->getIcon());
        $this->assertSame('🇯🇵', CurrencyEnum::JPY->getIcon());
    }

    /**
     * Testuje správnost počtu desetinných míst pro různé typy měn (fiat vs krypto).
     */
    public function testGetDecimals(): void
    {
        $this->assertSame(2, CurrencyEnum::USD->getDecimals());
        $this->assertSame(2, CurrencyEnum::CZK->getDecimals());
        $this->assertSame(0, CurrencyEnum::JPY->getDecimals());
        $this->assertSame(8, CurrencyEnum::BTC->getDecimals());
    }

    /**
     * Testuje generování překladových klíčů pro UI.
     */
    public function testGetTranslationKey(): void
    {
        $this->assertSame('card.usd', CurrencyEnum::USD->getTranslationKey());
        $this->assertSame('card.btc', CurrencyEnum::BTC->getTranslationKey());
    }

    /**
     * Testuje převod standardní ISO měny na objekt brick/money.
     *
     * @throws UnknownCurrencyException
     */
    public function testToBrickCurrencyIso(): void
    {
        $usd = CurrencyEnum::USD->toBrickCurrency();
        $this->assertSame('USD', $usd->getCurrencyCode());
    }

    /**
     * Testuje převod Bitcoinu (ne-ISO měna) na objekt brick/money s vlastní konfigurací.
     *
     * @throws UnknownCurrencyException
     */
    public function testToBrickCurrencyBtc(): void
    {
        $btc = CurrencyEnum::BTC->toBrickCurrency();
        $this->assertSame('BTC', $btc->getCurrencyCode());
        $this->assertSame(8, $btc->getDefaultFractionDigits());
    }
}
