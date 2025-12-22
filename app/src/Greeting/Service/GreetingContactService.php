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
}
