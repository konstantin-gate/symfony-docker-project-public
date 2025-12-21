<?php

declare(strict_types=1);

namespace App\Greeting\Service;

use App\Greeting\Enum\GreetingLanguage;
use App\Greeting\Factory\GreetingContactFactory;
use App\Greeting\Repository\GreetingContactRepository;
use Doctrine\ORM\EntityManagerInterface;

readonly class GreetingContactService
{
    public function __construct(
        private GreetingContactRepository $repository,
        private GreetingContactFactory $factory,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param string[] $emails
     */
    public function saveContacts(array $emails, GreetingLanguage $language = GreetingLanguage::Russian): int
    {
        if (empty($emails)) {
            return 0;
        }

        // Нормализация и реализация уникальности входных данных
        $uniqueEmails = array_unique(array_filter(array_map(
            static fn (string $email) => mb_strtolower(trim($email)),
            $emails
        )));

        if (empty($uniqueEmails)) {
            return 0;
        }

        // Поиск существующих в базе
        $existingContacts = $this->repository->findBy(['email' => $uniqueEmails]);
        $existingEmails = array_map(
            static fn ($contact) => mb_strtolower((string) $contact->getEmail()),
            $existingContacts
        );

        $emailsToCreate = array_diff($uniqueEmails, $existingEmails);

        if (empty($emailsToCreate)) {
            return 0;
        }

        $now = new \DateTimeImmutable();

        foreach ($emailsToCreate as $email) {
            $contact = $this->factory->create($email, $language, $now);
            $this->entityManager->persist($contact);
        }

        $this->entityManager->flush();

        return \count($emailsToCreate);
    }
}
