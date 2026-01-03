<?php

namespace App\MultiCurrencyWallet\Repository;

use App\MultiCurrencyWallet\Entity\Balance;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Balance>
 *
 * @method Balance|null find($id, $lockMode = null, $lockVersion = null)
 * @method Balance|null findOneBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null)
 * @method Balance[]    findAll()
 * @method Balance[]    findBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null, $limit = null, $offset = null)
 */
class BalanceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Balance::class);
    }

    // Zde mohou být přidány vlastní metody pro vyhledávání, např. podle měny.
}
