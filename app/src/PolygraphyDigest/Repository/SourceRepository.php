<?php

declare(strict_types=1);

namespace App\PolygraphyDigest\Repository;

use App\PolygraphyDigest\Entity\Source;
use DateMalformedStringException;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\NoResultException;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Source>
 *
 * @method Source|null find($id, $lockMode = null, $lockVersion = null)
 * @method Source|null findOneBy(array $criteria, array $orderBy = null)
 * @method Source[]    findAll()
 * @method Source[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SourceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Source::class);
    }

    /**
     * Najde datum posledního úspěšného stažení (scrape) ze všech aktivních zdrojů.
     * Pokud je zadán název zdroje, hledá pouze pro tento zdroj.
     *
     * @throws DateMalformedStringException
     */
    public function findLatestScrapedAt(?string $sourceName = null): ?\DateTimeImmutable
    {
        $qb = $this->createQueryBuilder('s')
            ->select('MAX(s.lastScrapedAt)')
            ->where('s.active = :active')
            ->setParameter('active', true);

        if ($sourceName) {
            $qb->andWhere('s.name = :name')
                ->setParameter('name', $sourceName);
        }

        try {
            $result = $qb->getQuery()->getSingleScalarResult();
        } catch (NoResultException) {
            return null;
        }

        if ($result === null) {
            return null;
        }

        return $result instanceof \DateTimeImmutable ? $result : new \DateTimeImmutable((string) $result);
    }
}
