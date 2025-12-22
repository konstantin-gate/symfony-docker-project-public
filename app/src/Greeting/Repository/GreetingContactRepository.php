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

    /**
     * Vrátí seznam e-mailů z vstupního pole, které NEEXISTUJÍ v databázi.
     *
     * @param array<string> $emails
     *
     * @return array<string>
     */
    public function findNonExistingEmails(array $emails): array
    {
        if (empty($emails)) {
            return [];
        }

        // Нормализуем входные данные к нижнему регистру для сравнения
        $normalizedInputMap = [];

        foreach ($emails as $email) {
            $normalizedInputMap[mb_strtolower($email)] = $email; // map: lower -> original
        }

        $normalizedEmails = array_keys($normalizedInputMap);

        // Находим те, которые УЖЕ ЕСТЬ в базе (по нижнему регистру)
        $existingEmails = $this->createQueryBuilder('c')
            ->select('LOWER(c.email)')
            ->where('LOWER(c.email) IN (:emails)')
            ->setParameter('emails', $normalizedEmails)
            ->getQuery()
            ->getSingleColumnResult();

        // Удаляем из списка нормализованных входных те, что нашлись
        $existingMap = array_flip($existingEmails); // для быстрого поиска

        $nonExistingOriginals = [];

        foreach ($normalizedInputMap as $lower => $original) {
            if (!isset($existingMap[$lower])) {
                $nonExistingOriginals[] = $original;
            }
        }

        return $nonExistingOriginals;
    }

    /**
     * @param string[] $emails
     *
     * @return GreetingContact[]
     */
    public function findByEmailsCaseInsensitive(array $emails): array
    {
        if (empty($emails)) {
            return [];
        }

        // Use LOWER() for case insensitive search.
        // Also normalize input emails to lowercase to ensure match.
        $normalizedEmails = array_map(static fn (string $email) => mb_strtolower($email), $emails);

        return $this->createQueryBuilder('c')
            ->where('LOWER(c.email) IN (:emails)')
            ->setParameter('emails', $normalizedEmails)
            ->getQuery()
            ->getResult();
    }
}
