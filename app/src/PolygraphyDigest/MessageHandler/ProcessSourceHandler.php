<?php

declare(strict_types=1);

namespace App\PolygraphyDigest\MessageHandler;

use App\PolygraphyDigest\Message\ProcessSourceMessage;
use App\PolygraphyDigest\Repository\SourceRepository;
use App\PolygraphyDigest\Service\Crawler\CrawlerService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Handler pro zpracování zpráv typu ProcessSourceMessage.
 * Zajišťuje orchestraci procesu stahování dat z konkrétního zdroje.
 */
#[AsMessageHandler]
final readonly class ProcessSourceHandler
{
    /**
     * @param SourceRepository       $sourceRepository repozitář pro přístup k entitám zdrojů
     * @param CrawlerService         $crawlerService   služba pro samotné stahování a parsování obsahu
     * @param EntityManagerInterface $entityManager    správce entit pro ukládání změn
     * @param LoggerInterface        $logger           služba pro logování průběhu a chyb
     */
    public function __construct(
        private SourceRepository $sourceRepository,
        private CrawlerService $crawlerService,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Hlavní metoda handleru, která je volána při přijetí zprávy z fronty.
     * 1. Vyhledá zdroj v databázi.
     * 2. Spustí proces stahování přes CrawlerService.
     * 3. Aktualizuje čas posledního stažení u zdroje.
     *
     * @param ProcessSourceMessage $message zpráva obsahující ID zdroje ke zpracování
     *
     * @throws \DateMalformedStringException pokud dojde k chybě při vytváření DateTimeImmutable
     * @throws \Throwable                    pokud dojde k jakékoli chybě během zpracování, je vyhozena dál pro případný retry v Messengeru
     */
    public function __invoke(ProcessSourceMessage $message): void
    {
        $source = $this->sourceRepository->find($message->sourceId);

        if ($source === null) {
            $this->logger->error('Source not found for processing', ['sourceId' => $message->sourceId]);

            return;
        }

        try {
            $this->logger->info('Processing source', ['source' => $source->getName(), 'id' => $source->getId()]);

            $this->crawlerService->processSource($source);

            // Aktualizace času posledního stažení
            $source->setLastScrapedAt(new \DateTimeImmutable());
            $this->entityManager->flush();

            $this->logger->info('Source processed successfully', ['sourceId' => $source->getId()]);
        } catch (\Throwable $e) {
            $this->logger->error('Error processing source', [
                'sourceId' => $source->getId(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e; // Znovu vyhodit výjimku, aby Messenger mohl zajistit opakování nebo přesun do failure transportu
        }
    }
}
