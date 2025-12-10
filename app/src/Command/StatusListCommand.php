<?php

declare(strict_types=1);

namespace App\Command;

use App\Enum\Status;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:status:list',
    description: 'Displays a list of all possible statuses and their parameters',
)]
class StatusListCommand extends Command
{
    public function __construct(private readonly string $statusEnumClass = Status::class)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Status Reference Guide (Enum Status)');

        $rows = [];

        foreach (($this->statusEnumClass)::cases() as $status) {
            $rows[] = [
                $status->name,                     // Název případu (například Active)
                $status->value,                    // Hodnota v DB (active)
                $status->getTranslationKey(),      // Klíč překladu
                $status->getColor(),               // Barva UI
                $status->isVisible() ? '+' : '-',  // Viditelnost
                $status->isEditable() ? '+' : '-', // Editovatelnost
            ];
        }

        $io->table(
            ['Case Name', 'DB Value', 'Trans Key', 'Color', 'Visible', 'Editable'],
            $rows
        );

        return Command::SUCCESS;
    }
}
