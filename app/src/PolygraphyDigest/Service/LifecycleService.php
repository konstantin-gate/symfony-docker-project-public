<?php

declare(strict_types=1);

namespace App\PolygraphyDigest\Service;

use App\PolygraphyDigest\Enum\ArticleStatusEnum;
use App\PolygraphyDigest\Repository\ArticleRepository;
use App\PolygraphyDigest\Service\Search\SearchIndexer;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Služba pro správu životního cyklu článků.
 * Zajišťuje automatickou archivaci po 30 dnech a odstranění po 90 dnech.
 */
class LifecycleService
{
    private const string CACHE_KEY_LAST_RUN = 'polygraphy_lifecycle_last_run';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ArticleRepository $articleRepository,
        private readonly CacheInterface $cache,
        private readonly LoggerInterface $logger,
        private readonly SearchIndexer $searchIndexer,
    ) {
    }

    /**
     * Spustí údržbu databáze a indexu.
     */
    public function runMaintenance(): void
    {
        try {
            if (!$this->shouldRun()) {
                return;
            }

            $this->logger->info('Spouštím údržbu životního cyklu článků.');

            $this->archiveOldArticles();
            $this->deleteExpiredArticles();

            $this->markAsRun();

            $this->logger->info('Údržba životního cyklu článků dokončena.');
        } catch (\Throwable $e) {
            $this->logger->error('Chyba při provádění údržby článků: ' . $e->getMessage(), [
                'exception' => $e,
            ]);
        }
    }

    /**
     * Kontroluje, zda má být údržba spuštěna (maximálně jednou denně).
     *
     * @throws InvalidArgumentException
     */
    private function shouldRun(): bool
    {
        $lastRun = $this->cache->get(self::CACHE_KEY_LAST_RUN, function (ItemInterface $item) {
            return null;
        });

        $today = (new \DateTimeImmutable())->format('Y-m-d');

        return $lastRun !== $today;
    }

    /**
     * Archivuje články starší než 30 dní (nastaví status HIDDEN).
     */
    private function archiveOldArticles(): void
    {
        $thirtyDaysAgo = new \DateTimeImmutable('-30 days');
        $articles = $this->articleRepository->findArticlesToArchive($thirtyDaysAgo);
        $count = \count($articles);

        if ($count > 0) {
            foreach ($articles as $article) {
                $previousStatus = $article->getStatus();
                $article->setStatus(ArticleStatusEnum::HIDDEN);

                try {
                    $this->searchIndexer->indexArticle($article);
                } catch (\Throwable $e) {
                    // Pokud selže indexace, vrátíme původní status, aby nedošlo k desynchronizaci
                    $article->setStatus($previousStatus);
                    $this->logger->error(\sprintf('Chyba při archivaci článku ID %s: %s', $article->getId(), $e->getMessage()));
                }
            }

            $this->entityManager->flush();
            $this->logger->info(\sprintf('Archivováno %d článků.', $count));
        }
    }

    /**
     * Odstraní články starší než 90 dní z DB i Elasticsearch.
     */
    private function deleteExpiredArticles(): void
    {
        $ninetyDaysAgo = new \DateTimeImmutable('-90 days');
        $articles = $this->articleRepository->findArticlesToDelete($ninetyDaysAgo);
        $count = \count($articles);

        if ($count > 0) {
            $i = 0;
            foreach ($articles as $article) {
                try {
                    // Odstranění z Elasticsearch
                    if ($article->getId() !== null) {
                        $this->searchIndexer->removeArticle($article->getId()->toRfc4122());
                    }
                } catch (\Throwable $e) {
                    $this->logger->error(\sprintf('Chyba při mazání článku ID %s z Elasticsearch: %s', $article->getId(), $e->getMessage()));
                    continue; // Nepokračujeme s mazáním z DB, aby se akce opakovala příště
                }

                // Odstranění z DB
                $this->entityManager->remove($article);

                if ((++$i % 50) === 0) {
                    $this->entityManager->flush();
                }
            }

            $this->entityManager->flush();
            $this->logger->info(\sprintf('Odstraněno %d starých článků.', $count));
        }
    }

    /**
     * Označí dnešní běh jako dokončený v mezipaměti.
     *
     * @throws InvalidArgumentException
     */
    private function markAsRun(): void
    {
        $this->cache->delete(self::CACHE_KEY_LAST_RUN);
        $this->cache->get(self::CACHE_KEY_LAST_RUN, function (ItemInterface $item) {
            $item->expiresAfter(new \DateInterval('P1D'));

            return (new \DateTimeImmutable())->format('Y-m-d');
        });
    }
}
