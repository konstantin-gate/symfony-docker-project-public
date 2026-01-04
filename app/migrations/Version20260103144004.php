<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migrace přidávající sloupec display_order do tabulky wallet_balance.
 * Slouží k definování pořadí zobrazení měn v uživatelském rozhraní.
 */
final class Version20260103144004 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Přidání sloupce display_order do tabulky wallet_balance pro definování pořadí zobrazení měn v uživatelském rozhraní.';
    }

    public function up(Schema $schema): void
    {
        // Přidání sloupce "display_order" typu INT s výchozí hodnotou 0.
        // Tento sloupec slouží k určení pořadí, v jakém se mají měny zobrazovat na frontendu.
        $this->addSql('ALTER TABLE wallet_balance ADD display_order INT DEFAULT 0 NOT NULL');

        // Úprava komentáře sloupce "fetched_at" (odstranění DC2Type hintu, pokud není potřeba nebo pro sjednocení).
        $this->addSql('COMMENT ON COLUMN wallet_exchange_rate.fetched_at IS \'\'');
    }

    public function down(Schema $schema): void
    {
        // Odstranění sloupce "display_order" při návratu migrace.
        $this->addSql('ALTER TABLE wallet_balance DROP display_order');

        // Obnovení původního komentáře sloupce "fetched_at".
        $this->addSql('COMMENT ON COLUMN wallet_exchange_rate.fetched_at IS \'(DC2Type:datetime_immutable)\'');
    }
}
