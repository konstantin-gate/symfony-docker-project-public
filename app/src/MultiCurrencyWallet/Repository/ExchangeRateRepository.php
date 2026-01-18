<?php

declare(strict_types=1);

namespace App\MultiCurrencyWallet\Repository;

use App\MultiCurrencyWallet\Entity\ExchangeRate;
use App\MultiCurrencyWallet\Enum\CurrencyEnum;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

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

    /**
     * Najde záznam s nejnovějším datem stažení.
     */
    public function findLatestUpdate(): ?ExchangeRate
    {
        return $this->createQueryBuilder('e')
            ->orderBy('e.fetchedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Vrátí seznam všech unikátních dnů, ve kterých proběhla aktualizace kurzů.
     * Využívá vlastní DQL funkci PG_DATE, která v PostgreSQL provádí přetypování (CAST)
     * timestampu na DATE, což umožňuje získat unikátní kalendářní dny přímo v SQL dotazu.
     *
     * @return array<string> Seznam unikátních dat ve formátu Y-m-d
     */
    public function getAvailableUpdateDates(): array
    {
        $results = $this->createQueryBuilder('e')
            ->select('DISTINCT PG_DATE(e.fetchedAt) as date')
            ->orderBy('date', 'DESC')
            ->getQuery()
            ->getScalarResult();

        return array_column($results, 'date');
    }

    /**
     * Vyhledá směnný kurz pro daný pár měn k určitému datu (nejstarší známý v rámci daného dne).
     */
    public function findExchangeRateAtDate(CurrencyEnum $currencyA, CurrencyEnum $currencyB, \DateTimeInterface $date): ?ExchangeRate
    {
        // Definujeme časový rozsah pro daný den
        $startOfDay = \DateTimeImmutable::createFromInterface($date)->setTime(0, 0, 0);
        $endOfDay = \DateTimeImmutable::createFromInterface($date)->setTime(23, 59, 59);

        return $this->createQueryBuilder('e')
            ->where('((e.baseCurrency = :currencyA AND e.targetCurrency = :currencyB) OR (e.baseCurrency = :currencyB AND e.targetCurrency = :currencyA))')
            ->andWhere('e.fetchedAt >= :start')
            ->andWhere('e.fetchedAt <= :end')
            ->setParameter('currencyA', $currencyA)
            ->setParameter('currencyB', $currencyB)
            ->setParameter('start', $startOfDay)
            ->setParameter('end', $endOfDay)
            ->orderBy('e.fetchedAt', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Vrátí seznam unikátních dat s kurzovými záznamy za posledních N dní.
     *
     * Metoda slouží k získání dostupných dnů pro historickou analýzu.
     * Nevrací samotné kurzy — ty se počítají pomocí RateHistoryService,
     * který využívá CurrencyConverter pro správný výpočet křížových kurzů.
     *
     * @param int $days Počet dní historie (výchozí 30)
     *
     * @return array<string> Seznam unikátních dat ve formátu Y-m-d, seřazených vzestupně
     */
    public function getAvailableDatesInRange(int $days = 30): array
    {
        $startDate = (new \DateTimeImmutable())->modify("-{$days} days")->setTime(0, 0, 0);

        $results = $this->createQueryBuilder('e')
            ->select('DISTINCT PG_DATE(e.fetchedAt) as date')
            ->where('e.fetchedAt >= :start')
            ->setParameter('start', $startDate)
            ->orderBy('date', 'ASC')
            ->getQuery()
            ->getScalarResult();

        return array_column($results, 'date');
    }
}
