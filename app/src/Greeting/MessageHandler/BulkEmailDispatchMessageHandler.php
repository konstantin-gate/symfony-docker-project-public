<?php

declare(strict_types=1);

namespace App\Greeting\MessageHandler;

use App\DTO\EmailRequest;
use App\Greeting\Message\BulkEmailDispatchMessage;
use App\Greeting\Repository\GreetingContactRepository;
use App\Service\EmailSequenceService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\ExceptionInterface;

#[AsMessageHandler]
readonly class BulkEmailDispatchMessageHandler
{
    private const int CHUNK_SIZE = 100;

    public function __construct(
        private GreetingContactRepository $greetingContactRepository,
        private EmailSequenceService $emailSequenceService,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @throws ExceptionInterface
     */
    public function __invoke(BulkEmailDispatchMessage $message): void
    {
        $contactIds = $message->contactIds;
        $total = \count($contactIds);
        $subject = $message->subject;
        $body = $message->body;

        if ($total === 0) {
            return;
        }

        $this->logger->info('Starting bulk email dispatch for {count} contacts.', ['count' => $total]);

        $chunks = array_chunk($contactIds, self::CHUNK_SIZE);
        $processedCount = 0;

        foreach ($chunks as $chunkIndex => $chunkIds) {
            // Optimization: Fetch only email strings, avoiding full entity hydration.
            $emails = $this->greetingContactRepository->findEmailsByIds($chunkIds);

            $emailRequests = [];

            foreach ($emails as $email) {
                if ($email === '') {
                    continue;
                }

                $emailRequests[] = new EmailRequest(
                    to: $email,
                    subject: $subject,
                    template: 'email/greeting.html.twig',
                    context: ['subject' => $subject, 'body' => $body]
                );
            }

            if (!empty($emailRequests)) {
                $this->emailSequenceService->sendSequence($emailRequests);
            }

            // Clear EntityManager to free up memory (even if we used scalar hydration,
            // it's good practice in long-running workers for other potential accumulations).
            $this->entityManager->clear();

            $processedCount += \count($emails);
            $this->logger->info('Processed chunk {index}/{totalChunks}. Total sent so far: {processed}', [
                'index' => $chunkIndex + 1,
                'totalChunks' => \count($chunks),
                'processed' => $processedCount,
            ]);
        }

        $this->logger->info('Bulk email dispatch completed.');
    }
}
