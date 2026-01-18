<?php

declare(strict_types=1);

namespace App\Tests\PolygraphyDigest\Command;

use App\PolygraphyDigest\Command\ReindexArticlesCommand;
use App\PolygraphyDigest\Entity\Article;
use App\PolygraphyDigest\Repository\ArticleRepository;
use App\PolygraphyDigest\Service\Search\SearchIndexer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Uid\Uuid;

/**
 * Bezpečnostní testy pro příkaz ReindexArticlesCommand.
 * Zaměřuje se na bezpečné zacházení s daty a logování.
 */
class ReindexArticlesCommandSecurityTest extends TestCase
{
    /**
     * Testuje odolnost proti "injection" útokům v datech článku.
     * Ověřuje, že příkaz pasivně předá data (SQL, XSS payloady) indexeru a nesnaží se je interpretovat.
     *
     * @return void
     */
    public function testMaliciousInputHandling(): void
    {
        // Simulujeme článek s "nebezpečným" obsahem
        $maliciousTitle = "'; DROP TABLE articles; --";
        $maliciousContent = "<script>alert('XSS')</script>";
        
        $article = $this->createMock(Article::class);
        $article->method('getId')->willReturn(Uuid::v4());
        // Mockujeme metody, které by mohly být volány (i když příkaz volá jen indexer)
        $article->method('getTitle')->willReturn($maliciousTitle);
        $article->method('getContent')->willReturn($maliciousContent);

        $articleRepository = $this->createMock(ArticleRepository::class);
        $articleRepository->method('findAll')->willReturn([$article]);

        $searchIndexer = $this->createMock(SearchIndexer::class);
        // Očekáváme, že indexer obdrží objekt s těmito daty beze změny ze strany příkazu
        $searchIndexer->expects($this->once())
            ->method('indexArticle')
            ->with($article);

        $command = new ReindexArticlesCommand($articleRepository, $searchIndexer);
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

        $this->assertSame(0, $commandTester->getStatusCode());
    }

    /**
     * Testuje, že v chybových výstupech (logy) nejsou odhalena citlivá data.
     * Ověřuje, že při chybě se vypíše pouze ID a chybová hláška, nikoliv obsah článku.
     *
     * @return void
     */
    public function testSensitiveDataIsNotExposedInLogs(): void
    {
        $sensitiveContent = "Toto je přísně tajný obsah článku.";
        $articleId = Uuid::v4();
        
        $article = $this->createMock(Article::class);
        $article->method('getId')->willReturn($articleId);
        $article->method('getContent')->willReturn($sensitiveContent);

        $articleRepository = $this->createMock(ArticleRepository::class);
        $articleRepository->method('findAll')->willReturn([$article]);

        $searchIndexer = $this->createMock(SearchIndexer::class);
        // Vyvoláme chybu při indexaci
        $searchIndexer->expects($this->once())
            ->method('indexArticle')
            ->willThrowException(new \RuntimeException('Obecná chyba indexace'));

        $command = new ReindexArticlesCommand($articleRepository, $searchIndexer);
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

        $output = $commandTester->getDisplay();
        
        // Ověříme, že výstup obsahuje ID a chybu
        $this->assertStringContainsString((string)$articleId, $output);
        $this->assertStringContainsString('Obecná chyba indexace', $output);
        
        // Ověříme, že výstup NEOBSAHUJE citlivý obsah článku
        $this->assertStringNotContainsString($sensitiveContent, $output);
    }

    /**
     * Testuje, že příkaz je odolný vůči chybám v datech, které by mohly způsobit pád (např. null ID).
     * Ověřuje robustnost příkazu.
     *
     * @return void
     */
    public function testMalformedDataRobustness(): void
    {
        $article = $this->createMock(Article::class);
        // Simulujeme situaci, kdy getId vrátí null (např. poškozená entita)
        $article->method('getId')->willReturn(null);

        $articleRepository = $this->createMock(ArticleRepository::class);
        $articleRepository->method('findAll')->willReturn([$article]);

        $searchIndexer = $this->createMock(SearchIndexer::class);
        $searchIndexer->expects($this->once())
            ->method('indexArticle')
            ->willThrowException(new \InvalidArgumentException('ID is required'));

        $command = new ReindexArticlesCommand($articleRepository, $searchIndexer);
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

        $output = $commandTester->getDisplay();
        
        // Příkaz by měl i tak skončit úspěšně (odchytit výjimku)
        $this->assertSame(0, $commandTester->getStatusCode());
        // A zalogovat chybu (i když ID je prázdné)
        $this->assertStringContainsString('Chyba při indexaci', $output);
    }
}
