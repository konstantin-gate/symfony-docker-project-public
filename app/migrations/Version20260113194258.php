<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migrace pro zvětšení délky polí v tabulce polygraphy_article.
 * Tato změna řeší chybu "value too long for type character varying(255)" 
 * u dlouhých titulků a URL adres z RSS kanálů.
 */
final class Version20260113194258 extends AbstractMigration
{
    /**
     * Vrací popis migrace.
     */
    public function getDescription(): string
    {
        return 'Zvětšení délky polí external_id, title a url v tabulce polygraphy_article.';
    }

    /**
     * Provede změny v databázi (zvětšení limitů).
     */
    public function up(Schema $schema): void
    {
        // Úprava typů polí na delší varianty (TEXT a delší VARCHAR)
        $this->addSql('ALTER TABLE polygraphy_article ALTER external_id TYPE VARCHAR(512)');
        $this->addSql('ALTER TABLE polygraphy_article ALTER title TYPE TEXT');
        $this->addSql('ALTER TABLE polygraphy_article ALTER url TYPE VARCHAR(2048)');
    }

    /**
     * Vrátí změny zpět (původní limit 255 znaků).
     */
    public function down(Schema $schema): void
    {
        // Návrat k původním limitům 255 znaků
        $this->addSql('ALTER TABLE polygraphy_article ALTER external_id TYPE VARCHAR(255)');
        $this->addSql('ALTER TABLE polygraphy_article ALTER title TYPE VARCHAR(255)');
        $this->addSql('ALTER TABLE polygraphy_article ALTER url TYPE VARCHAR(255)');
    }
}
