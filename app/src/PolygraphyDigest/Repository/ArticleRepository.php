<?php

declare(strict_types=1);

namespace App\PolygraphyDigest\Repository;

use App\PolygraphyDigest\Entity\Article;
use App\PolygraphyDigest\Enum\ArticleStatusEnum;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Article>
 *
 * @method Article|null find($id, $lockMode = null, $lockVersion = null)
 * @method Article|null findOneBy(array $criteria, array $orderBy = null)
 * @method Article[]    findAll()
 * @method Article[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ArticleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Article::class);
    }

    /**
     * Najde články starší než zadané datum, které ještě nejsou skryté.
     *
     * @return Article[]
     */
    public function findArticlesToArchive(\DateTimeImmutable $olderThan): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.publishedAt < :date')
            ->andWhere('a.status != :status')
            ->setParameter('date', $olderThan)
            ->setParameter('status', ArticleStatusEnum::HIDDEN)
            ->getQuery()
            ->getResult();
    }

    /**
     * Najde články starší než zadané datum (pro smazání).
     *
     * @return Article[]
     */
    public function findArticlesToDelete(\DateTimeImmutable $olderThan): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.publishedAt < :date')
            ->setParameter('date', $olderThan)
            ->getQuery()
            ->getResult();
    }
}
