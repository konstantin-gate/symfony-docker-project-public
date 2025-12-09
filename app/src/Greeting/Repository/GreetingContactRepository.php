<?php

declare(strict_types=1);

namespace App\Greeting\Repository;

use App\Greeting\Entity\GreetingContact;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<GreetingContact>
 *
 * @method GreetingContact|null find($id, $lockMode = null, $lockVersion = null)
 * @method GreetingContact|null findOneBy(array<string, mixed> $criteria, array<string, string> $orderBy = null)
 * @method GreetingContact[]    findAll()
 * @method GreetingContact[]    findBy(array<string, mixed> $criteria, array<string, string> $orderBy = null, $limit = null, $offset = null)
 */
class GreetingContactRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GreetingContact::class);
    }

    //    /**
    //     * @return GreetingContact[] Returns an array of GreetingContact objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('g')
    //            ->andWhere('g.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('g.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?GreetingContact
    //    {
    //        return $this->createQueryBuilder('g')
    //            ->andWhere('g.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
