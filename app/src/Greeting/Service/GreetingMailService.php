<?php

declare(strict_types=1);

namespace App\Greeting\Service;

use App\DTO\EmailRequest;
use App\Greeting\Repository\GreetingContactRepository;
use App\Service\EmailSequenceService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Uid\Uuid;

readonly class GreetingMailService
{
    public function __construct(
        private GreetingContactRepository $greetingContactRepository,
        private EmailSequenceService $emailSequenceService,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @param string[]|Uuid[] $contactIds
     *
     * @throws ExceptionInterface
     */
    public function sendGreetings(array $contactIds, string $subject, string $body): int
    {
        if (empty($contactIds)) {
            return 0;
        }

        $successCount = 0;

        foreach ($contactIds as $id) {
            $contact = $this->greetingContactRepository->find($id);

            if (!$contact) {
                $this->logger->error('Contact with ID {id} not found, skipping.', ['id' => $id]);
                continue;
            }

            $email = (string) $contact->getEmail();

            if ($email === '') {
                $this->logger->error('Contact with ID {id} has no email address, skipping.', ['id' => $id]);
                continue;
            }

            try {
                $emailRequest = new EmailRequest(
                    to: $email,
                    subject: $subject,
                    template: 'email/greeting.html.twig',
                    context: ['subject' => $subject, 'body' => $body]
                );

                $this->emailSequenceService->sendSequence([$emailRequest]);
                ++$successCount;
            } catch (\Exception $e) {
                $this->logger->error('Failed to queue greeting for contact {id}: {error}', [
                    'id' => $id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($successCount > 0) {
            $this->logger->info('Queued {count} greeting emails with subject "{subject}"', [
                'count' => $successCount,
                'subject' => $subject,
            ]);
        }

        return $successCount;
    }
}
