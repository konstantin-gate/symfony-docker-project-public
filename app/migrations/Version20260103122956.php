<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migrace pro vytvoření tabulky "wallet_balance".
 */
final class Version20260103122956 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Vytvoření tabulky wallet_balance pro ukládání zůstatků v různých měnách.';
    }

    public function up(Schema $schema): void
    {
        // Tato část migrace vytvoří tabulku wallet_balance s unikátním indexem pro měnu
        $this->addSql('CREATE TABLE wallet_balance (id UUID NOT NULL, currency VARCHAR(3) NOT NULL, amount VARCHAR(64) NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX unique_currency_idx ON wallet_balance (currency)');
    }

    public function down(Schema $schema): void
    {
        // Tato část migrace odstraní tabulku wallet_balance
        $this->addSql('DROP TABLE wallet_balance');
    }
}
