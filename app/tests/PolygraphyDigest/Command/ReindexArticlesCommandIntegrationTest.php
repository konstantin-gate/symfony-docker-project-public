<?php

declare(strict_types=1);

namespace App\Tests\PolygraphyDigest\Command;

use App\PolygraphyDigest\Command\ReindexArticlesCommand;
use App\PolygraphyDigest\Entity\Article;
use App\PolygraphyDigest\Entity\Source;
use App\PolygraphyDigest\Enum\SourceTypeEnum;
use App\PolygraphyDigest\Repository\ArticleRepository;
use App\PolygraphyDigest\Service\Search\SearchIndexer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Integrační testy pro příkaz ReindexArticlesCommand.
 * Tyto testy ověřují správnou spolupráci příkazu s reálnou databází a infrastrukturou Symfony.
 * Na rozdíl od unit testů zde používáme skutečný repozitář a EntityManager.
 */
class ReindexArticlesCommandIntegrationTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private ArticleRepository $articleRepository;

    /**
     * Příprava testovacího prostředí.
     * Tato metoda se spouští před každým testem. Bootuje kernel a získává potřebné služby.
     * Zároveň zajišťuje vyčištění databáze, aby testy nebyly ovlivněny předchozími běhy.
     */
    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->articleRepository = $container->get(ArticleRepository::class);

        // Vyčištění databáze před testem (pořadí je důležité kvůli FK)
        $this->truncateTable(Article::class);
        $this->truncateTable(Source::class);
    }

    /**
     * Ověřuje, že příkaz správně zpracuje situaci s prázdnou databází.
     * Testuje, zda reálný repozitář vrátí prázdné pole a příkaz proběhne bez chyb.
     */
    public function testExecuteWithEmptyDatabase(): void
    {
        // Mockujeme Indexer, protože testujeme integraci s DB, ne s Elasticsearch
        $searchIndexer = $this->createMock(SearchIndexer::class);
        $searchIndexer->expects($this->never())->method('indexArticle');

        $command = new ReindexArticlesCommand($this->articleRepository, $searchIndexer);
        $commandTester = new CommandTester($command);

        $exitCode = $commandTester->execute([]);

        $this->assertSame(0, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Startuji reindexaci 0 článků', $output);
    }

    /**
     * Ověřuje, že příkaz správně načte reálná data z databáze a předá je indexeru.
     * V tomto testu vytvoříme skutečnou entitu v databázi a ověříme, že ji příkaz najde.
     */
    public function testExecuteWithRealData(): void
    {
        // 1. Příprava dat v reálné DB
        // Nejprve musíme vytvořit Source (zdroj), protože Article na něj má FK
        $source = new Source();
        $source->setName('Testovací zdroj');
        $source->setUrl('https://example.com/rss');
        $source->setType(SourceTypeEnum::RSS);
        $this->entityManager->persist($source);

        $article = new Article();
        // ID se generuje automaticky (UUID)
        $article->setTitle('Integrační test titulek');
        $article->setUrl('https://example.com/article/1'); // URL musí být unikátní
        $article->setContent('Obsah pro integrační test databáze.');
        $article->setSource($source); // Nastavení povinné vazby
        $article->setPublishedAt(new \DateTimeImmutable());

        $this->entityManager->persist($article);
        $this->entityManager->flush();

        // Získáme ID pro kontrolu (po flush už je nastaveno)
        $articleId = $article->getId();
        $this->assertNotNull($articleId);

        // 2. Příprava Mock Indexeru
        $searchIndexer = $this->createMock(SearchIndexer::class);
        $searchIndexer->expects($this->once())
            ->method('indexArticle')
            ->with($this->callback(function (Article $indexedArticle) use ($articleId) {
                // Ověříme, že indexovaná entita má stejné ID jako ta v DB
                return $indexedArticle->getId()->equals($articleId) &&
                       $indexedArticle->getTitle() === 'Integrační test titulek';
            }));

        // 3. Spuštění příkazu
        $command = new ReindexArticlesCommand($this->articleRepository, $searchIndexer);
        $commandTester = new CommandTester($command);

        $exitCode = $commandTester->execute([]);

        $this->assertSame(0, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Startuji reindexaci 1 článků', $output);
        $this->assertStringContainsString('Reindexace dokončena.', $output);
    }

    /**
     * Pomocná metoda pro vyčištění tabulky.
     * Používá hrubou sílu (DELETE FROM) pro zajištění čistého stavu.
     * Přidáno 'CASCADE' pro PostgreSQL, aby se smazaly závislosti.
     */
    private function truncateTable(string $className): void
    {
        $connection = $this->entityManager->getConnection();
        $platform = $connection->getDatabasePlatform();
        
        $metadata = $this->entityManager->getClassMetadata($className);
        $tableName = $metadata->getTableName();

        // Pro PostgreSQL přidáme CASCADE, pokud je to potřeba (parametr true v getTruncateTableSQL obvykle stačí)
        $sql = $platform->getTruncateTableSQL($tableName, true);
        
        $connection->executeStatement($sql);
    }
}