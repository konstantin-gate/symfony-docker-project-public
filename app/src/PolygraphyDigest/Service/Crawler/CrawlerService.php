<?php

declare(strict_types=1);

namespace App\PolygraphyDigest\Service\Crawler;

use App\PolygraphyDigest\Entity\Source;
use App\PolygraphyDigest\Repository\ArticleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

/**
 * Služba pro stahování a parsování obsahu ze zdrojů.
 */
readonly class CrawlerService
{
    public function __construct(
        private HttpClientInterface    $httpClient,
        private ParserProvider         $parserProvider,
        private EntityManagerInterface $entityManager,
        private ArticleRepository      $articleRepository,
        private LoggerInterface        $logger,
    ) {
    }

    /**
     * Zpracuje daný zdroj: stáhne obsah, vyparsuje články a uloží nové do databáze.
     *
     * @throws Throwable
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

            $response = $this->httpClient->request('GET', $url);
            $content = $response->getContent();

            $parser = $this->parserProvider->getParser($source->getType());
            $articles = $parser->parse($content, $source);

            $this->logger->info(sprintf('Parsed %d articles from source', count($articles)), [
                'source' => $source->getName(),
            ]);

            $newCount = 0;

            foreach ($articles as $article) {
                // Kontrola duplicity podle externího ID a zdroje
                $existing = $this->articleRepository->findOneBy([
                    'source' => $source,
                    'externalId' => $article->getExternalId(),
                ]);

                if ($existing === null) {
                    $this->entityManager->persist($article);
                    $newCount++;
                }
            }

            if ($newCount > 0) {
                $this->entityManager->flush();
                $this->logger->info(sprintf('Saved %d new articles', $newCount), [
                    'source' => $source->getName(),
                ]);
            }
        } catch (Throwable $e) {
            $this->logger->error('Error during crawling', [
                'source' => $source->getName(),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
