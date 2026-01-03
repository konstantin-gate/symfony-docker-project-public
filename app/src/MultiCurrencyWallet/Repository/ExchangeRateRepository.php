<?php

declare(strict_types=1);

namespace App\MultiCurrencyWallet\Repository;

use App\MultiCurrencyWallet\Entity\ExchangeRate;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use App\MultiCurrencyWallet\Enum\CurrencyEnum;

/**
 * @extends ServiceEntityRepository<ExchangeRate>
 *
 * @method ExchangeRate|null find($id, $lockMode = null, $lockVersion = null)
 * @method ExchangeRate|null findOneBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null)
 * @method ExchangeRate[]    findAll()
 * @method ExchangeRate[]    findBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null, $limit = null, $offset = null)
 */
class ExchangeRateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ExchangeRate::class);
    }

    /**
     * Vyhledá nejnovější směnný kurz pro daný pár měn bez ohledu na směr (Base/Target).
     */
    public function findLatestExchangeRate(CurrencyEnum $currencyA, CurrencyEnum $currencyB): ?ExchangeRate
    {
        return $this->createQueryBuilder('e')
            ->where('(e.baseCurrency = :currencyA AND e.targetCurrency = :currencyB)')
            ->orWhere('(e.baseCurrency = :currencyB AND e.targetCurrency = :currencyA)')
            ->setParameter('currencyA', $currencyA)
            ->setParameter('currencyB', $currencyB)
            ->orderBy('e.fetchedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
