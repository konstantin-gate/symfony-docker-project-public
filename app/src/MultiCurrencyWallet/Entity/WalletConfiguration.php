<?php

declare(strict_types=1);

namespace App\MultiCurrencyWallet\Entity;

use App\MultiCurrencyWallet\Enum\CurrencyEnum;
use App\MultiCurrencyWallet\Repository\WalletConfigurationRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Entita pro ukládání globálního nastavení modulu Multi-Currency Wallet.
 * Obsahuje preference uživatele, jako je hlavní měna a povolení automatických aktualizací.
 */
#[ORM\Entity(repositoryClass: WalletConfigurationRepository::class)]
#[ORM\Table(name: 'wallet_configuration')]
class WalletConfiguration
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $autoUpdateEnabled = true;

    #[ORM\Column(type: 'string', length: 3, enumType: CurrencyEnum::class, options: ['default' => 'CZK'])]
    private CurrencyEnum $mainCurrency = CurrencyEnum::CZK;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function isAutoUpdateEnabled(): bool
    {
        return $this->autoUpdateEnabled;
    }

    public function setAutoUpdateEnabled(bool $autoUpdateEnabled): self
    {
        $this->autoUpdateEnabled = $autoUpdateEnabled;

        return $this;
    }

    public function getMainCurrency(): CurrencyEnum
    {
        return $this->mainCurrency;
    }

    public function setMainCurrency(CurrencyEnum $mainCurrency): self
    {
        $this->mainCurrency = $mainCurrency;

        return $this;
    }
}
