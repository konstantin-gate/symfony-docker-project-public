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
    description: 'Выводит список всех возможных статусов и их параметры',
)]
class StatusListCommand extends Command
{
    public function __construct(private string $statusEnumClass = Status::class)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Справочник статусов (Enum Status)');

        $rows = [];

        foreach (($this->statusEnumClass)::cases() as $status) {
            $rows[] = [
                $status->name,                      // Имя кейса (например, Active)
                $status->value,                     // Значение в БД (active)
                $status->getTranslationKey(),       // Ключ перевода
                $status->getColor(),                // Цвет UI
                $status->isVisible() ? '+' : '-',  // Видимость
                $status->isEditable() ? '+' : '-', // Редактируемость
            ];
        }

        $io->table(
            ['Case Name', 'DB Value', 'Trans Key', 'Color', 'Visible', 'Editable'],
            $rows
        );

        return Command::SUCCESS;
    }
}
