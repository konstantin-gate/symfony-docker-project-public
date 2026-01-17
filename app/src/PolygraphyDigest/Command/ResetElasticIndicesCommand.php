<?php

declare(strict_types=1);

namespace App\PolygraphyDigest\Command;

use App\PolygraphyDigest\Service\Search\ElasticsearchClientInterface;
use App\PolygraphyDigest\Service\Search\IndexInitializer;
use Elastic\Elasticsearch\Exception\ClientResponseException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'polygraphy:search:reset',
    description: 'Smaže a znovu vytvoří indexy pro Polygraphy Digest.',
)]
class ResetElasticIndicesCommand extends Command
{
    public function __construct(
        private readonly ElasticsearchClientInterface $client,
        private readonly IndexInitializer $indexInitializer,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('force', 'f', InputOption::VALUE_NONE, 'Vynutit smazání bez potvrzení');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $force = $input->getOption('force');

        if (!$force && !$io->confirm('Opravdu chcete smazat všechny indexy a data v nich? Tato akce je nevratná!', false)) {
            $io->note('Akce zrušena.');

            return Command::SUCCESS;
        }

        try {
            $io->section('Resetování indexů Elasticsearch');

            // 1. Smazat existující indexy
            $indices = ['polygraphy_articles', 'polygraphy_products'];

            foreach ($indices as $index) {
                try {
                    $this->client->indices()->delete(['index' => $index]);
                    $io->success("Index $index byl smazán.");
                } catch (ClientResponseException $e) {
                    if ($e->getResponse()->getStatusCode() === 404) {
                        $io->note("Index $index neexistuje, přeskakuji mazání.");
                    } else {
                        throw $e;
                    }
                }
            }

            // 2. Vytvořit nové indexy
            $io->text('Vytvářím nové indexy...');
            $this->indexInitializer->initializeArticlesIndex();
            $this->indexInitializer->initializeProductsIndex();

            $io->success('Indexy byly úspěšně resetovány a znovu vytvořeny.');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Chyba: ' . $e->getMessage());

            return Command::FAILURE;
        }
    }
}
