<?php

declare(strict_types=1);

namespace App\Command;

use App\DTO\EmailRequest;
use App\Service\EmailSequenceService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\Exception\ExceptionInterface;

/**
 * Příkaz pro testování sekvenčního odesílání e-mailů s časovou prodlevou.
 */
#[AsCommand(
    name: 'greeting:test-sequence-email',
    description: 'Sends a sequence of test emails to verify delay',
)]
class TestSequenceEmailCommand extends Command
{
    public function __construct(
        private readonly EmailSequenceService $emailSequenceService,
    ) {
        parent::__construct();
    }

    /**
     * Vytvoří a odešle sekvenci testovacích e-mailů do fronty.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Testing Email Sequence');

        $requests = [
            new EmailRequest(
                'test1@example.com',
                'Sequence 1',
                'email/greeting.html.twig',
                ['subject' => 'Seq 1', 'body' => 'Body 1']
            ),
            new EmailRequest(
                'test2@example.com',
                'Sequence 2',
                'email/greeting.html.twig',
                ['subject' => 'Seq 2', 'body' => 'Body 2']
            ),
            new EmailRequest(
                'test3@example.com',
                'Sequence 3',
                'email/greeting.html.twig',
                ['subject' => 'Seq 3', 'body' => 'Body 3']
            ),
        ];

        $io->text('Dispatching 3 emails to queue...');
        $startTime = microtime(true);

        try {
            $this->emailSequenceService->sendSequence($requests);

            $duration = microtime(true) - $startTime;
            $io->success(\sprintf('Messages dispatched in %.2f seconds. Start worker to process them.', $duration));

            return Command::SUCCESS;
        } catch (\Exception|ExceptionInterface $e) {
            $io->error('Error: ' . $e->getMessage());

            return Command::FAILURE;
        }
    }
}
