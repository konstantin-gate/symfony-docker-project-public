<?php

declare(strict_types=1);

namespace App\Tests\MultiCurrencyWallet\Unit\Entity;

use App\MultiCurrencyWallet\Entity\WalletConfiguration;
use App\MultiCurrencyWallet\Enum\CurrencyEnum;
use PHPUnit\Framework\TestCase;

class WalletConfigurationTest extends TestCase
{
    public function testDefaultValues(): void
    {
        $config = new WalletConfiguration();

        // Default based on property definition
        $this->assertTrue($config->isAutoUpdateEnabled());
        $this->assertSame(CurrencyEnum::CZK, $config->getMainCurrency());
    }

    public function testSetMainCurrency(): void
    {
        $config = new WalletConfiguration();
        $config->setMainCurrency(CurrencyEnum::USD);

        $this->assertSame(CurrencyEnum::USD, $config->getMainCurrency());
    }

    public function testSetAutoUpdateEnabled(): void
    {
        $config = new WalletConfiguration();
        $config->setAutoUpdateEnabled(false);

        $this->assertFalse($config->isAutoUpdateEnabled());
    }
}
