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
        // Získáme všechny existující e-maily z databáze, které jsou ve vstupním poli
        $existingEmails = $this->createQueryBuilder('c')
            ->select('c.email')
            ->where('c.email IN (:emails)')
            ->setParameter('emails', $emails)
            ->getQuery()
            ->getSingleColumnResult();

        // Vrátíme e-maily, které jsou ve vstupním poli, ale nejsou v databázi
        return array_diff($emails, $existingEmails);
    }
}
