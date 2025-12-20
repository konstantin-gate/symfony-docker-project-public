<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\EmailService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

/**
 * Toto je pomocný příkaz pro kontrolu:
 * docker compose exec php bin/console greeting:test-email test@example.com
 *
 * Po provedení zkontrolujte soubor v app/var/mails/:
 * 1. Bude vytvořen soubor .eml s jedinečným časovým razítkem.
 * 2. Uvnitř bude správný MIME (HTML + Text).
 *
 * Jak zkontrolovat (Test)
 * docker compose exec php bin/console greeting:test-email test@example.com --env=test
 *
 * Dopis nebude vytvořen, nedojde k žádným chybám.
 */
#[AsCommand(
    name: 'greeting:test-email',
    description: 'Sends a test email to verify configuration',
)]
class TestEmailCommand extends Command
{
    public function __construct(
        private readonly EmailService $emailService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('to', InputArgument::REQUIRED, 'Recipient email');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $to = $input->getArgument('to');
        $output->writeln("Sending email to $to...");

        try {
            $this->emailService->send(
                to: $to,
                subject: 'Test Email from CLI',
                template: 'email/greeting.html.twig',
                context: [
                    'subject' => 'Test Subject',
                    'body' => 'This is a test body from CLI.',
                ]
            );
            $output->writeln('Email sent successfully.');

            return Command::SUCCESS;
        } catch (\Exception|TransportExceptionInterface $e) {
            $output->writeln('Error: ' . $e->getMessage());

            return Command::FAILURE;
        }
    }
}
