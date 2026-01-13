<?php

declare(strict_types=1);

namespace App\PolygraphyDigest\MessageHandler;

use App\PolygraphyDigest\Entity\Source;
use App\PolygraphyDigest\Message\ProcessSourceMessage;
use App\PolygraphyDigest\Message\TriggerSourceCheckMessage;
use App\PolygraphyDigest\Repository\SourceRepository;
use Cron\CronExpression;
use DateTimeImmutable;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Throwable;

/**
 * Handler pro TriggerSourceCheckMessage.
 * Tento handler je periodicky spouštěn komponentou Scheduler (každou minutu).
 * Jeho úkolem je projít všechny aktivní zdroje dat a na základě jejich individuálního
 * nastavení (cron expression) rozhodnout, zda mají být v daný moment zpracovány.
 */
#[AsMessageHandler]
final readonly class TriggerSourceCheckHandler
{
    /**
     * @param SourceRepository $sourceRepository Repozitář pro přístup k definovaným zdrojům dat.
     * @param MessageBusInterface $messageBus Sběrnice zpráv pro odesílání úloh k asynchronnímu zpracování.
     * @param LoggerInterface $logger Služba pro logování informací o průběhu plánování a chybách.
     */
    public function __construct(
        private SourceRepository $sourceRepository,
        private MessageBusInterface $messageBus,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Hlavní metoda handleru volaná plánovačem.
     * Vyhledá aktivní zdroje, vyhodnotí jejich časový plán a v případě potřeby odešle zprávu do fronty.
     *
     * @param TriggerSourceCheckMessage $message Zpráva od plánovače (slouží pouze jako trigger).
     */
    public function __invoke(TriggerSourceCheckMessage $message): void
    {
        $this->logger->info('Scheduler: Checking sources for updates...');
        
        $sources = $this->sourceRepository->findBy(['active' => true]);
        $now = new DateTimeImmutable();
        $dispatchedCount = 0;

        foreach ($sources as $source) {
            try {
                if ($this->shouldProcess($source, $now)) {
                    $this->logger->info('Scheduler: Source is due for update', [
                        'id' => (string) $source->getId(), 
                        'name' => $source->getName()
                    ]);
                    
                    $this->messageBus->dispatch(new ProcessSourceMessage((string) $source->getId()));
                    $dispatchedCount++;
                }
            } catch (Throwable $e) {
                $this->logger->error('Scheduler: Error checking source schedule', [
                    'sourceId' => (string) $source->getId(),
                    'error' => $e->getMessage()
                ]);
            }
        }

        $this->logger->info('Scheduler: Check complete', ['dispatched' => $dispatchedCount]);
    }

    /**
     * Interní logika pro vyhodnocení, zda má být zdroj právě teď zpracován.
     * Porovnává aktuální čas s časem posledního stažení a definovaným cron řetězcem.
     *
     * @param Source $source Entita zdroje, jejíž plán se vyhodnocuje.
     * @param DateTimeImmutable $now Aktuální časový okamžik pro porovnání.
     * @return bool Vrací true, pokud nastal čas pro spuštění stahování.
     */
    private function shouldProcess(Source $source, DateTimeImmutable $now): bool
    {
        $schedule = $source->getSchedule();
        if (!$schedule) {
            // Pokud není plán, nespouštíme automaticky.
            return false;
        }
        
        $lastScrapedAt = $source->getLastScrapedAt();
        if (!$lastScrapedAt) {
            // Pokud ještě nikdy neběžel, spustíme hned.
            return true;
        }

        try {
            $cron = new CronExpression($schedule);
            // Získáme datum příštího běhu počítané od posledního běhu
            $nextRun = $cron->getNextRunDate($lastScrapedAt);
            
            // Pokud je čas příštího běhu v minulosti (nebo teď), je čas spustit.
            return $nextRun <= $now;
        } catch (Throwable) {
             // Nevalidní cron expression ignorujeme (nebo logujeme jinde)
             return false;
        }
    }
}
