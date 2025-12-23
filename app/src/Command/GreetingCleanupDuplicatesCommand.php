<?php

declare(strict_types=1);

namespace App\Command;

use App\Greeting\Entity\GreetingContact;
use App\Greeting\Repository\GreetingContactRepository;
use App\Greeting\Repository\GreetingLogRepository;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'greeting:cleanup-duplicates',
    description: 'Finds and removes duplicate email contacts, keeping the one with history or the oldest one.',
)]
class GreetingCleanupDuplicatesCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly GreetingContactRepository $greetingContactRepository,
        private readonly GreetingLogRepository $greetingLogRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('force', null, InputOption::VALUE_NONE, 'Execute the cleanup (default is dry-run)')
        ;
    }

    /**
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $isForce = $input->getOption('force');
        $isDryRun = !$isForce;

        if ($isDryRun) {
            $io->note('Running in DRY-RUN mode. No changes will be made.');
        } else {
            $io->warning('Running in FORCE mode. Changes WILL be made to the database.');
        }

        // Step 1: Find duplicate groups
        $duplicateEmails = $this->findDuplicateEmails();

        if (empty($duplicateEmails)) {
            $io->success('No duplicate emails found.');

            // Even if no duplicates, we should normalize existing emails if needed (optional, but good practice per requirement)
            // But the requirement says: "У 'Победителя' нормализуем email...".
            // Let's strictly follow duplicates cleanup first.
            return Command::SUCCESS;
        }

        $io->section(\sprintf('Found %d emails with duplicates', \count($duplicateEmails)));

        $deletedCount = 0;
        $normalizedCount = 0;

        foreach ($duplicateEmails as $email) {
            $this->entityManager->beginTransaction();

            try {
                $this->processDuplicateGroup($email, $io, $isDryRun, $deletedCount, $normalizedCount);
                $this->entityManager->commit();
            } catch (\Exception $e) {
                $this->entityManager->rollback();
                $io->error(\sprintf('Error processing email "%s": %s', $email, $e->getMessage()));
            }
        }

        if ($isDryRun) {
            $io->note(\sprintf(
                'DRY RUN SUMMARY: Would delete %d contacts and normalize %d emails.',
                $deletedCount,
                $normalizedCount
            ));
        } else {
            $io->success(\sprintf(
                'CLEANUP COMPLETE: Deleted %d contacts and normalized %d emails.',
                $deletedCount,
                $normalizedCount
            ));
        }

        return Command::SUCCESS;
    }

    /**
     * @return string[]
     *
     * @throws Exception
     */
    private function findDuplicateEmails(): array
    {
        $connection = $this->entityManager->getConnection();
        $sql = '
            SELECT LOWER(email) as e, COUNT(*) as c
            FROM greeting_contact
            GROUP BY LOWER(email)
            HAVING COUNT(*) > 1
        ';

        $results = $connection->executeQuery($sql)->fetchAllAssociative();

        return array_map(static fn (array $row) => (string) $row['e'], $results);
    }

    private function processDuplicateGroup(
        string $email,
        SymfonyStyle $io,
        bool $isDryRun,
        int &$deletedCount,
        int &$normalizedCount,
    ): void {
        // Step 2: Find all contacts for this email (case-insensitive)
        $contacts = $this->greetingContactRepository->findByEmailsCaseInsensitive([$email]);

        if (\count($contacts) < 2) {
            return;
        }

        $io->text(\sprintf('Processing duplicates for: %s (Found %d)', $email, \count($contacts)));

        // Optimization: Preload log existence for all contacts in this group
        $contactIds = array_map(static fn (GreetingContact $c) => (string) $c->getId(), $contacts);
        $idsWithLogs = array_flip($this->greetingLogRepository->getContactIdsWithLogs($contactIds));

        // Step 3: Select Winner and Losers
        // Sort contacts to find the best candidate to keep
        usort($contacts, static function (GreetingContact $a, GreetingContact $b) use ($idsWithLogs) {
            $hasLogsA = isset($idsWithLogs[(string) $a->getId()]);
            $hasLogsB = isset($idsWithLogs[(string) $b->getId()]);

            // Priority 1: Has logs
            if ($hasLogsA && !$hasLogsB) {
                return -1; // A is better
            }

            if (!$hasLogsA && $hasLogsB) {
                return 1; // B is better
            }

            // Priority 2: Oldest (earlier createdAt is better)
            return $a->getCreatedAt() <=> $b->getCreatedAt();
        });

        // The first element after sort is the "Winner" (best one to keep)
        $winner = array_shift($contacts);
        $losers = $contacts;

        // Step 4: Action
        foreach ($losers as $loser) {
            $io->text(\sprintf(' - Deleting: ID %s, Email "%s", Created %s',
                $loser->getId(),
                $loser->getEmail(),
                $loser->getCreatedAt()->format('Y-m-d H:i:s')
            ));

            if (!$isDryRun) {
                $this->entityManager->remove($loser);
            }
            ++$deletedCount;
        }

        // Normalize winner email if needed
        if ($winner->getEmail() !== mb_strtolower((string) $winner->getEmail())) {
            $io->text(\sprintf(' - Normalizing winner: "%s" -> "%s"', $winner->getEmail(), mb_strtolower((string) $winner->getEmail())));

            if (!$isDryRun) {
                $winner->setEmail(mb_strtolower((string) $winner->getEmail()));
                $this->entityManager->persist($winner);
            }
            ++$normalizedCount;
        } else {
            $io->text(\sprintf(' - Keeping winner: ID %s (already normalized)', $winner->getId()));
        }

        if (!$isDryRun) {
            $this->entityManager->flush();
        }
    }
}
