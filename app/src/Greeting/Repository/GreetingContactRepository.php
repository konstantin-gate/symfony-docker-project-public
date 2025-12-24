<?php

declare(strict_types=1);

namespace App\Greeting\Repository;

use App\Enum\Status;
use App\Greeting\Entity\GreetingContact;
use App\Greeting\Enum\GreetingLanguage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repozitář pro správu entit GreetingContact.
 *
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
     * Najde všechny aktivní kontakty seskupené podle jazyka.
     *
     * @return array<string, array<int, GreetingContact>>
     */
    public function findAllActiveGroupedByLanguage(): array
    {
        // Získáme všechny aktivní kontakty seřazené podle e-mailu
        $contacts = $this->findBy(['status' => Status::Active], ['email' => 'ASC']);

        $groupedContacts = [];

        foreach (GreetingLanguage::cases() as $langEnum) {
            $groupedContacts[$langEnum->value] = [];
        }

        foreach ($contacts as $contact) {
            $lang = $contact->getLanguage()->value;
            $groupedContacts[$lang][] = $contact;
        }

        return $groupedContacts;
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

        // Normalizujeme vstupní data na malá písmena pro porovnání
        $normalizedInputMap = [];

        foreach ($emails as $email) {
            $normalizedInputMap[mb_strtolower($email)] = $email; // map: lower -> original
        }

        $normalizedEmails = array_keys($normalizedInputMap);

        // Najdeme ty, které JIŽ EXISTUJÍ v databázi (dle malých písmen)
        $existingEmails = $this->createQueryBuilder('c')
            ->select('LOWER(c.email)')
            ->where('LOWER(c.email) IN (:emails)')
            ->setParameter('emails', $normalizedEmails)
            ->getQuery()
            ->getSingleColumnResult();

        // Odstraníme ze seznamu normalizovaných vstupů ty, které byly nalezeny
        $existingMap = array_flip($existingEmails); // pro rychlé vyhledávání

        $nonExistingOriginals = [];

        foreach ($normalizedInputMap as $lower => $original) {
            if (!isset($existingMap[$lower])) {
                $nonExistingOriginals[] = $original;
            }
        }

        return $nonExistingOriginals;
    }

    /**
     * Najde kontakty podle e-mailů (case-insensitive).
     *
     * @param string[] $emails
     *
     * @return GreetingContact[]
     */
    public function findByEmailsCaseInsensitive(array $emails): array
    {
        if (empty($emails)) {
            return [];
        }

        // Použijeme LOWER() pro case-insensitive vyhledávání.
        // Také normalizujeme vstupní e-maily na malá písmena pro shodu.
        $normalizedEmails = array_map(static fn (string $email) => mb_strtolower($email), $emails);

        return $this->createQueryBuilder('c')
            ->where('LOWER(c.email) IN (:emails)')
            ->setParameter('emails', $normalizedEmails)
            ->getQuery()
            ->getResult();
    }

    /**
     * Najde e-mailové adresy podle seznamu ID.
     *
     * @param string[] $ids
     *
     * @return string[]
     */
    public function findEmailsByIds(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        return $this->createQueryBuilder('c')
            ->select('c.email')
            ->where('c.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getSingleColumnResult();
    }
}
