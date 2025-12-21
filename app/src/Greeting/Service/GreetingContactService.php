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
        private GreetingContactRepository $greetingContactRepository,
        private GreetingContactFactory $greetingContactFactory,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param string[] $emails
     */
    public function importContacts(array $emails, GreetingLanguage $language, \DateTimeImmutable $registrationDate): int
    {
        if (empty($emails)) {
            return 0;
        }

        // Filter out existing emails to avoid duplicates
        $newEmails = $this->greetingContactRepository->findNonExistingEmails($emails);

        if (empty($newEmails)) {
            return 0;
        }

        foreach ($newEmails as $email) {
            $contact = $this->greetingContactFactory->create($email, $language, $registrationDate);
            $this->entityManager->persist($contact);
        }

        $this->entityManager->flush();

        return \count($newEmails);
    }
}
