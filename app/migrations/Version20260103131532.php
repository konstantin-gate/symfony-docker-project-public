<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migrace pro vytvoření tabulky "wallet_exchange_rate".
 */
final class Version20260103131532 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Vytvoření tabulky wallet_exchange_rate pro ukládání historie směnných kurzů.';
    }

    public function up(Schema $schema): void
    {
        // Tato část migrace vytvoří tabulku wallet_exchange_rate s indexem pro vyhledávání kurzů
        $this->addSql('CREATE TABLE wallet_exchange_rate (id UUID NOT NULL, base_currency VARCHAR(3) NOT NULL, target_currency VARCHAR(3) NOT NULL, rate VARCHAR(64) NOT NULL, fetched_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_rate_lookup ON wallet_exchange_rate (base_currency, target_currency, fetched_at)');
        $this->addSql('COMMENT ON COLUMN wallet_exchange_rate.fetched_at IS \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        // Tato část migrace odstraní tabulku wallet_exchange_rate
        $this->addSql('DROP TABLE wallet_exchange_rate');
    }
}