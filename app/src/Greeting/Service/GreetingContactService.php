<?php

declare(strict_types=1);

namespace App\Greeting\Service;

use App\Enum\Status;
use App\Greeting\Entity\GreetingContact;
use App\Greeting\Enum\GreetingLanguage;
use App\Greeting\Exception\ContactAlreadyDeletedException;
use App\Greeting\Exception\ContactAlreadyInactiveException;
use App\Greeting\Factory\GreetingContactFactory;
use App\Greeting\Repository\GreetingContactRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Služba pro správu kontaktů (vytváření, mazání, deaktivace).
 */
readonly class GreetingContactService
{
    public function __construct(
        private GreetingContactRepository $repository,
        private GreetingContactFactory $factory,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Uloží seznam e-mailů jako nové kontakty.
     *
     * @param string[] $emails
     */
    public function saveContacts(array $emails, GreetingLanguage $language = GreetingLanguage::Russian): int
    {
        if (empty($emails)) {
            return 0;
        }

        // Normalizace a zajištění unikátnosti vstupních dat
        // Používáme klíče pole pro deduplikaci, zachováváme originální zápis (ačkoliv pro uložení bude stejně použito malé písmo)
        $uniqueEmailsMap = [];

        foreach ($emails as $email) {
            $cleaned = trim($email);

            if ($cleaned === '') {
                continue;
            }

            $lower = mb_strtolower($cleaned);

            if (!isset($uniqueEmailsMap[$lower])) {
                $uniqueEmailsMap[$lower] = $cleaned;
            }
        }

        if (empty($uniqueEmailsMap)) {
            return 0;
        }

        $uniqueEmails = array_values($uniqueEmailsMap);

        // Získáme seznam pouze těch e-mailů, které ještě nejsou v databázi
        // Metoda repozitáře nyní korektně pracuje s velikostí písmen
        $emailsToCreate = $this->repository->findNonExistingEmails($uniqueEmails);

        if (empty($emailsToCreate)) {
            return 0;
        }

        $now = new \DateTimeImmutable();

        foreach ($emailsToCreate as $email) {
            // Factory vytvoří entitu a uvnitř setEmail provede převod na malá písmena
            $contact = $this->factory->create($email, $language, $now);
            $this->entityManager->persist($contact);
        }

        $this->entityManager->flush();

        return \count($emailsToCreate);
    }

    /**
     * Označí kontakt jako smazaný (soft-delete).
     */
    public function delete(GreetingContact $contact): void
    {
        if ($contact->getStatus() === Status::Deleted) {
            throw new ContactAlreadyDeletedException('dashboard.delete_error_already_deleted');
        }

        $contact->setStatus(Status::Deleted);
        $this->entityManager->flush();
        $this->logger->info('Greeting contact deleted: {email}', ['email' => $contact->getEmail()]);
    }

    /**
     * Deaktivuje kontakt (např. při odhlášení).
     */
    public function deactivate(GreetingContact $contact): void
    {
        if ($contact->getStatus() === Status::Inactive || $contact->getStatus() === Status::Deleted) {
            throw new ContactAlreadyInactiveException('dashboard.deactivate_error_already_inactive');
        }

        $contact->setStatus(Status::Inactive);
        $this->entityManager->flush();
        $this->logger->info('Greeting contact deactivated: {email}', ['email' => $contact->getEmail()]);
    }
}
