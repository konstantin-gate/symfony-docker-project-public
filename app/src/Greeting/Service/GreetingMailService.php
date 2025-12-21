<?php

declare(strict_types=1);

namespace App\Greeting\Service;

use App\DTO\EmailRequest;
use App\Greeting\Repository\GreetingContactRepository;
use App\Service\EmailSequenceService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Exception\ExceptionInterface;

readonly class GreetingMailService
{
    public function __construct(
        private GreetingContactRepository $greetingContactRepository,
        private EmailSequenceService $emailSequenceService,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @param string[] $contactIds
     *
     * @throws ExceptionInterface
     */
    public function sendGreetings(array $contactIds, string $subject, string $body): int
    {
        if (empty($contactIds)) {
            return 0;
        }

        $selectedContacts = $this->greetingContactRepository->findBy(['id' => $contactIds]);
        $emailRequests = [];

        foreach ($selectedContacts as $contact) {
            /** @var string $email */
            $email = $contact->getEmail();
            $emailRequests[] = new EmailRequest(
                to: $email,
                subject: $subject,
                template: 'email/greeting.html.twig',
                context: ['subject' => $subject, 'body' => $body]
            );
        }

        $this->emailSequenceService->sendSequence($emailRequests);
        $count = \count($selectedContacts);

        $this->logger->info('Queued {count} greeting emails with subject "{subject}"', [
            'count' => $count,
            'subject' => $subject,
        ]);

        return $count;
    }
}
