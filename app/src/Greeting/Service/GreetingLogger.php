<?php

declare(strict_types=1);

namespace App\Greeting\Service;

use App\Greeting\Factory\GreetingLogFactory;
use App\Greeting\Repository\GreetingContactRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Služba pro logování odeslaných pozdravů.
 */
readonly class GreetingLogger
{
    public function __construct(
        private GreetingContactRepository $contactRepository,
        private GreetingLogFactory $logFactory,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Zaznamená odeslání pozdravu pro daný e-mail.
     */
    public function logForEmail(string $email): void
    {
        $contact = $this->contactRepository->findOneBy(['email' => $email]);

        if ($contact) {
            $log = $this->logFactory->create($contact);
            $this->entityManager->persist($log);
            $this->entityManager->flush();
        }
    }
}
