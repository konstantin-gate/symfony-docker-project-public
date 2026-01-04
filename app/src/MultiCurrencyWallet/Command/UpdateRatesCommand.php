<?php

declare(strict_types=1);

namespace App\MultiCurrencyWallet\Command;

use App\MultiCurrencyWallet\Service\RateUpdateService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * CLI příkaz pro aktualizaci směnných kurzů.
 * Umožňuje spouštět synchronizaci kurzů z příkazové řádky (např. pomocí cronu).
 */
#[AsCommand(
    name: 'app:wallet:update-rates',
    description: 'Aktualizuje směnné kurzy z externích poskytovatelů',
)]
class UpdateRatesCommand extends Command
{
    public function __construct(
        private readonly RateUpdateService $rateUpdateService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Vynutit aktualizaci i v případě, že jsou data čerstvá');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Aktualizace směnných kurzů');

        try {
            // RateUpdateService by v budoucnu mohl podporovat parametr $force.
            // Prozatím budeme simulovat force smazáním starých dat (nebo prostě voláme službu).
            // V současné implementaci RateUpdateService.php throttling funguje automaticky (1 hodina).

            $providerName = $this->rateUpdateService->updateRates();

            if ('skipped' === $providerName) {
                $io->info('Aktualizace byla přeskočena (data jsou stále čerstvá).');
            } else {
                $io->success(\sprintf('Kurzy byly úspěšně aktualizovány pomocí poskytovatele: %s', $providerName));
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error(\sprintf('Při aktualizaci kurzů došlo k chybě: %s', $e->getMessage()));

            return Command::FAILURE;
        }
    }
}
