<?php

declare(strict_types=1);

namespace App\PolygraphyDigest\Service\Crawler;

use App\PolygraphyDigest\Entity\Source;
use App\PolygraphyDigest\Repository\ArticleRepository;
use App\PolygraphyDigest\Repository\SourceRepository;
use App\PolygraphyDigest\Service\Search\SearchIndexer;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Služba pro stahování a parsování obsahu ze zdrojů.
 */
readonly class CrawlerService
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private ParserProvider $parserProvider,
        private EntityManagerInterface $entityManager,
        private ArticleRepository $articleRepository,
        private SourceRepository $sourceRepository,
        private SearchIndexer $searchIndexer,
        #[Autowire(service: 'monolog.logger.polygraphy')]
        private LoggerInterface $polygraphyLogger,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Zpracuje daný zdroj: stáhne obsah, vyparsuje články a uloží nové do databáze.
     *
     * @throws \Throwable
     */
    public function processSource(Source $source): void
    {
        $url = $source->getUrl();

        if (!$url) {
            $this->logger->error('Source has no URL', ['sourceId' => $source->getId()]);

            return;
        }

        try {
            $this->logger->info('Fetching content', ['url' => $url, 'source' => $source->getName()]);

            $response = $this->httpClient->request('GET', $url, [
                'verify_peer' => false,
                'verify_host' => false,
            ]);
            $content = $response->getContent();

            $parser = $this->parserProvider->getParser($source->getType());
            $articles = $parser->parse($content, $source);

            $this->logger->info(\sprintf('Parsed %d articles from source', \count($articles)), [
                'source' => $source->getName(),
            ]);

            $newCount = 0;
            $newArticles = [];

            foreach ($articles as $article) {
                // Kontrola duplicity podle externího ID a zdroje
                $existing = $this->articleRepository->findOneBy([
                    'source' => $source,
                    'externalId' => $article->getExternalId(),
                ]);

                if ($existing === null) {
                    $this->entityManager->persist($article);
                    $newArticles[] = $article;
                    ++$newCount;
                }
            }

            if ($newCount > 0) {
                $this->entityManager->flush();

                foreach ($newArticles as $newArticle) {
                    try {
                        $this->searchIndexer->indexArticle($newArticle);
                    } catch (\Throwable $e) {
                        $this->logger->error('Failed to index article', [
                            'article_id' => $newArticle->getId(),
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                $this->logger->info(\sprintf('Saved and indexed %d new articles', $newCount), [
                    'source' => $source->getName(),
                ]);
            }
        } catch (\Throwable $e) {
            $this->logger->error('Error during crawling', [
                'source' => $source->getName(),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Zpracuje všechny zdroje.
     *
     * @return array{processed: int, new_articles: int, errors: array<int, array{source: string, message: string}>}
     */
    public function processAllSources(): array
    {
        $stats = [
            'processed' => 0,
            'new_articles' => 0,
            'errors' => [],
        ];

        $sources = $this->sourceRepository->findAll();

        foreach ($sources as $source) {
            try {
                $this->processSource($source);
                $stats['processed']++;
            } catch (\Throwable $e) {
                $stats['errors'][] = [
                    'source' => $source->getName(),
                    'message' => $e->getMessage(),
                ];
                $this->logger->error('Error processing source manually', [
                    'source' => $source->getName(),
                    'error' => $e->getMessage(),
                ]);
                $this->polygraphyLogger->error('Error processing source manually', [
                    'source' => $source->getName(),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->polygraphyLogger->info('Manual crawl executed', $stats);

        return $stats;
    }
}
