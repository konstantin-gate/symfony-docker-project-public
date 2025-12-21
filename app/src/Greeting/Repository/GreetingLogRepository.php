<?php

declare(strict_types=1);

namespace App\Greeting\Repository;

use App\Greeting\Entity\GreetingLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<GreetingLog>
 *
 * @method GreetingLog|null find($id, $lockMode = null, $lockVersion = null)
 * @method GreetingLog|null findOneBy(array<string, mixed> $criteria, array<string, string> $orderBy = null)
 * @method GreetingLog[]    findAll()
 * @method GreetingLog[]    findBy(array<string, mixed> $criteria, array<string, string> $orderBy = null, $limit = null, $offset = null)
 */
class GreetingLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GreetingLog::class);
    }

    /**
     * @return string[]
     */
    public function findGreetedContactIdsSince(\DateTimeImmutable $since): array
    {
        $qb = $this->createQueryBuilder('gl');

        $results = $qb->select('IDENTITY(gl.contact) as contactId')
            ->where('gl.sentAt >= :since')
            ->setParameter('since', $since)
            ->distinct()
            ->getQuery()
            ->getScalarResult(); // Returns array of ['contactId' => 'uuid-string']

        // Flatten the array to return just UUID strings
        return array_column($results, 'contactId');
    }
}
