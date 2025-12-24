<?php

declare(strict_types=1);

namespace App\Greeting\Repository;

use App\Greeting\Entity\GreetingLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repozitář pro správu entit GreetingLog.
 *
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
     * Získá seznam ID kontaktů, které mají alespoň jeden záznam v logu (byly osloveny).
     *
     * @param string[] $contactIds
     *
     * @return string[] Array of contact IDs that have at least one log entry
     */
    public function getContactIdsWithLogs(array $contactIds): array
    {
        if (empty($contactIds)) {
            return [];
        }

        $results = $this->createQueryBuilder('gl')
            ->select('DISTINCT IDENTITY(gl.contact) as contactId')
            ->where('gl.contact IN (:ids)')
            ->setParameter('ids', $contactIds)
            ->getQuery()
            ->getScalarResult();

        return array_map(static fn (array $row) => (string) $row['contactId'], $results);
    }

    /**
     * Najde ID kontaktů a datum posledního oslovení od zadaného data.
     *
     * @return array<string, string>
     */
    public function findGreetedContactIdsSince(\DateTimeImmutable $since): array
    {
        $map = [];
        $results = $this->createQueryBuilder('gl')
            ->select('IDENTITY(gl.contact) as contactId', 'MAX(gl.sentAt) as lastSentAt')
            ->where('gl.sentAt >= :since')
            ->setParameter('since', $since)
            ->groupBy('contactId')
            ->getQuery()
            ->getScalarResult();

        foreach ($results as $row) {
            /** @var string $id */
            $id = $row['contactId'];
            /** @var string $date */
            $date = $row['lastSentAt'];
            $map[$id] = $date;
        }

        return $map;
    }
}
