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
     * @param string[] $emails
     */
    public function saveContacts(array $emails, GreetingLanguage $language = GreetingLanguage::Russian): int
    {
        if (empty($emails)) {
            return 0;
        }

        // Нормализация и реализация уникальности входных данных
        // Мы используем ключи массива для дедупликации, сохраняя оригинальное написание (хотя для сохранения все равно будет lower)
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

        // Получаем список только тех email, которых еще нет в базе
        // Метод репозитория теперь корректно работает с регистром
        $emailsToCreate = $this->repository->findNonExistingEmails($uniqueEmails);

        if (empty($emailsToCreate)) {
            return 0;
        }

        $now = new \DateTimeImmutable();

        foreach ($emailsToCreate as $email) {
            // Factory создает сущность, и там внутри setEmail сделает strtolower
            $contact = $this->factory->create($email, $language, $now);
            $this->entityManager->persist($contact);
        }

        $this->entityManager->flush();

        return \count($emailsToCreate);
    }

    public function delete(GreetingContact $contact): void
    {
        if ($contact->getStatus() === Status::Deleted) {
            throw new ContactAlreadyDeletedException('dashboard.delete_error_already_deleted');
        }

        $contact->setStatus(Status::Deleted);
        $this->entityManager->flush();
        $this->logger->info('Greeting contact deleted: {email}', ['email' => $contact->getEmail()]);
    }

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
