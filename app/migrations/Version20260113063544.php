<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migrace pro inicializaci databázového schématu modulu PolygraphyDigest.
 * Tato migrace vytváří základní strukturu pro ukládání zdrojů dat, článků a produktů.
 */
final class Version20260113063544 extends AbstractMigration
{
    /**
     * Vrací popis migrace, který vysvětluje její účel.
     */
    public function getDescription(): string
    {
        return 'Inicializace schématu pro modul PolygraphyDigest (zdroje, články, produkty).';
    }

    /**
     * Aplikuje změny na databázové schéma: vytváří tabulky polygraphy_source, polygraphy_article,
     * polygraphy_product, definuje jejich sloupce, indexy a cizí klíče.
     */
    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE polygraphy_article (id UUID NOT NULL, external_id VARCHAR(255) DEFAULT NULL, title VARCHAR(255) NOT NULL, url VARCHAR(255) NOT NULL, content TEXT DEFAULT NULL, summary TEXT DEFAULT NULL, published_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, fetched_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, status VARCHAR(255) NOT NULL, source_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_6543398F47645AE ON polygraphy_article (url)');
        $this->addSql('CREATE INDEX IDX_6543398953C1C61 ON polygraphy_article (source_id)');
        $this->addSql('CREATE TABLE polygraphy_product (id UUID NOT NULL, name VARCHAR(255) NOT NULL, price NUMERIC(20, 6) DEFAULT NULL, currency VARCHAR(3) DEFAULT NULL, attributes JSON DEFAULT NULL, raw_data JSON DEFAULT NULL, article_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_D72439537294869C ON polygraphy_product (article_id)');
        $this->addSql('CREATE TABLE polygraphy_source (id UUID NOT NULL, name VARCHAR(255) NOT NULL, url VARCHAR(255) NOT NULL, type VARCHAR(255) NOT NULL, active BOOLEAN NOT NULL, schedule VARCHAR(255) DEFAULT NULL, last_scraped_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_EA9194F5F47645AE ON polygraphy_source (url)');
        $this->addSql('ALTER TABLE polygraphy_article ADD CONSTRAINT FK_6543398953C1C61 FOREIGN KEY (source_id) REFERENCES polygraphy_source (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE polygraphy_product ADD CONSTRAINT FK_D72439537294869C FOREIGN KEY (article_id) REFERENCES polygraphy_article (id)');
    }

    /**
     * Vrátí změny provedené metodou up() zpět: odstraní tabulky a veškerá omezení (cizí klíče) modulu PolygraphyDigest.
     */
    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE polygraphy_article DROP CONSTRAINT FK_6543398953C1C61');
        $this->addSql('ALTER TABLE polygraphy_product DROP CONSTRAINT FK_D72439537294869C');
        $this->addSql('DROP TABLE polygraphy_article');
        $this->addSql('DROP TABLE polygraphy_product');
        $this->addSql('DROP TABLE polygraphy_source');
    }
}
