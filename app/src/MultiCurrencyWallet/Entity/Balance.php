<?php

declare(strict_types=1);

namespace App\MultiCurrencyWallet\Entity;

use App\MultiCurrencyWallet\Enum\CurrencyEnum;
use App\MultiCurrencyWallet\Repository\BalanceRepository;
use Brick\Money\Exception\UnknownCurrencyException;
use Brick\Money\Money;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: BalanceRepository::class)]
#[ORM\Table(name: 'wallet_balance')]
#[ORM\UniqueConstraint(name: 'unique_currency_idx', columns: ['currency'])]
class Balance
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    private ?Uuid $id = null;

    #[ORM\Column(type: 'string', length: 3, enumType: CurrencyEnum::class)]
    private CurrencyEnum $currency;

    /**
     * Uložení částky jako řetězec pro zachování přesnosti BigDecimal.
     * Délka 64 znaků je dostatečná pro jakoukoli myslitelnou finanční hodnotu.
     */
    #[ORM\Column(type: 'string', length: 64)]
    private string $amount;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $displayOrder = 0;

    public function __construct(CurrencyEnum $currency, string $amount = '0')
    {
        $this->currency = $currency;
        $this->amount = $amount;
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getCurrency(): CurrencyEnum
    {
        return $this->currency;
    }

    public function getDisplayOrder(): int
    {
        return $this->displayOrder;
    }

    public function setDisplayOrder(int $displayOrder): self
    {
        $this->displayOrder = $displayOrder;

        return $this;
    }

    /**
     * Získání objektu Money pro manipulaci s částkou.
     *
     * @throws UnknownCurrencyException
     */
    public function getMoney(): Money
    {
        return Money::of($this->amount, $this->currency->value);
    }

    /**
     * Nastavení částky pomocí objektu Money.
     * Měna objektu Money musí odpovídat měně této entity.
     */
    public function setMoney(Money $money): self
    {
        if ($money->getCurrency()->getCurrencyCode() !== $this->currency->value) {
            throw new \InvalidArgumentException(\sprintf('Měna %s neodpovídá měně účtu %s', $money->getCurrency()->getCurrencyCode(), $this->currency->value));
        }

        $this->amount = (string) $money->getAmount();

        return $this;
    }

    public function getAmount(): string
    {
        return $this->amount;
    }
}
