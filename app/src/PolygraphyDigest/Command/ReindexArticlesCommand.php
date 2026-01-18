<?php

declare(strict_types=1);

namespace App\PolygraphyDigest\Command;

use App\PolygraphyDigest\Repository\ArticleRepository;
use App\PolygraphyDigest\Service\Search\SearchIndexer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Příkaz pro hromadnou reindexaci všech článků z relační databáze do Elasticsearch.
 * Slouží k synchronizaci dat, například po vyčištění indexu nebo změně mappingu.
 */
#[AsCommand(
    name: 'polygraphy:search:reindex',
    description: 'Reindexuje všechny články z databáze do Elasticsearch',
)]
class ReindexArticlesCommand extends Command
{
    /**
     * @param ArticleRepository $articleRepository Repozitář pro získání článků z DB
     * @param SearchIndexer     $searchIndexer     Služba pro indexaci do Elasticsearch
     */
    public function __construct(
        private readonly ArticleRepository $articleRepository,
        private readonly SearchIndexer $searchIndexer,
    ) {
        parent::__construct();
    }

    /**
     * Hlavní metoda pro spuštění příkazu.
     * Načte všechny články a postupně je odesílá do Elasticsearch s vizualizací průběhu.
     *
     * @param InputInterface  $input  Vstupní rozhraní konzole
     * @param OutputInterface $output Výstupní rozhraní konzole
     *
     * @return int Návratový kód příkazu (0 pro úspěch)
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $articles = $this->articleRepository->findAll();
        } catch (\Throwable $e) {
            $io->error(\sprintf('Chyba při načítání článků z databáze: %s', $e->getMessage()));
            return Command::FAILURE;
        }

        $count = \count($articles);

        $io->title(\sprintf('Startuji reindexaci %d článků', $count));
        $io->progressStart($count);

        foreach ($articles as $article) {
            try {
                $this->searchIndexer->indexArticle($article);
                $io->progressAdvance();
            } catch (\Throwable $e) {
                $io->error(\sprintf('Chyba při indexaci článku ID %s: %s', $article->getId(), $e->getMessage()));
            }
        }

        $io->progressFinish();
        $io->success('Reindexace dokončena.');

        return Command::SUCCESS;
    }
}
