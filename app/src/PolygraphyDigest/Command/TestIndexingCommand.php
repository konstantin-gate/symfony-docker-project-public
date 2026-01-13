<?php

declare(strict_types=1);

namespace App\PolygraphyDigest\Command;

use App\PolygraphyDigest\Entity\Article;
use App\PolygraphyDigest\Entity\Source;
use App\PolygraphyDigest\Enum\ArticleStatusEnum;
use App\PolygraphyDigest\Service\Search\SearchIndexer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Uid\Uuid;

#[AsCommand(
    name: 'polygraphy:search:test-index',
    description: 'Vytvoří testovací dokument a zaindexuje jej do Elasticsearch.',
)]
class TestIndexingCommand extends Command
{
    public function __construct(
        private readonly SearchIndexer $searchIndexer,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $io->section('Testovací indexace do Elasticsearch');

            // Vytvoření testovacích dat (in-memory)
            $source = new Source();
            $source->setName('Testovací Zdroj');

            $article = new Article();
            $article->setTitle('Testovací článek o moderní polygrafii');
            $article->setSummary('Tento článek slouží k ověření funkčnosti indexace do Elasticsearch.');
            $article->setContent('<p>Toto je <strong>testovací obsah</strong> článku. Obsahuje HTML tagy, které by měly být odstraněny.</p>');
            $article->setUrl('https://example.com/test-article-' . bin2hex(random_bytes(4)));
            $article->setPublishedAt(new \DateTimeImmutable());
            $article->setStatus(ArticleStatusEnum::PROCESSED);
            $article->setSource($source);

            // Nastavení UUID pomocí reflexe (protože ID generuje Doctrine)
            $reflection = new \ReflectionClass($article);
            $property = $reflection->getProperty('id');
            $property->setValue($article, Uuid::v4());

            $io->text(\sprintf('Indexuji článek: "%s" (ID: %s)', $article->getTitle(), $article->getId()));

            $this->searchIndexer->indexArticle($article);

            $io->success('Článek byl úspěšně odeslán do Elasticsearch.');
            $io->info('Můžete ověřit existenci dokumentu v indexu polygraphy_articles.');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Chyba při testovací indexaci: ' . $e->getMessage());

            return Command::FAILURE;
        }
    }
}
