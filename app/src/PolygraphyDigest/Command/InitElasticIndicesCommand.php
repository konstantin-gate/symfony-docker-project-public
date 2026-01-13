<?php

declare(strict_types=1);

namespace App\PolygraphyDigest\Command;

use App\PolygraphyDigest\Service\Search\IndexInitializer;
use Elastic\Elasticsearch\Exception\ClientResponseException;
use Elastic\Elasticsearch\Exception\MissingParameterException;
use Elastic\Elasticsearch\Exception\ServerResponseException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'polygraphy:search:init',
    description: 'Inicializuje indexy Elasticsearch pro Polygraphy Digest modul.',
)]
class InitElasticIndicesCommand extends Command
{
    public function __construct(
        private readonly IndexInitializer $indexInitializer,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $io->section('Inicializace indexů Elasticsearch');

            $io->text('Vytvářím index polygraphy_articles...');
            $this->indexInitializer->initializeArticlesIndex();
            $io->success('Index polygraphy_articles byl úspěšně vytvořen (nebo již existoval).');

            $io->text('Vytvářím index polygraphy_products...');
            $this->indexInitializer->initializeProductsIndex();
            $io->success('Index polygraphy_products byl úspěšně vytvořen (nebo již existoval).');

            return Command::SUCCESS;
        } catch (ClientResponseException|ServerResponseException|MissingParameterException $e) {
            $io->error('Chyba při komunikaci s Elasticsearch: ' . $e->getMessage());

            return Command::FAILURE;
        } catch (\Exception $e) {
            $io->error('Neočekávaná chyba: ' . $e->getMessage());

            return Command::FAILURE;
        }
    }
}
