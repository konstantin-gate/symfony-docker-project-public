<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration to create tables for managing greeting contacts and logging sent greetings.
 */
final class Version20251208192709 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Creates greeting_contact and greeting_log tables.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration creates the greeting_contact and greeting_log tables
        $this->addSql('CREATE TABLE greeting_contact (id UUID NOT NULL, email VARCHAR(180) NOT NULL, language VARCHAR(255) DEFAULT \'cs\' NOT NULL, status VARCHAR(255) DEFAULT \'active\' NOT NULL, unsubscribe_token VARCHAR(64) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_3B8ACF27E7927C74 ON greeting_contact (email)');
        $this->addSql('CREATE TABLE greeting_log (id UUID NOT NULL, sent_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, year INT NOT NULL, contact_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_6CA35E0DE7A1254A ON greeting_log (contact_id)');
        $this->addSql('ALTER TABLE greeting_log ADD CONSTRAINT FK_6CA35E0DE7A1254A FOREIGN KEY (contact_id) REFERENCES greeting_contact (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration reverts the creation of greeting_contact and greeting_log tables
        $this->addSql('ALTER TABLE greeting_log DROP CONSTRAINT FK_6CA35E0DE7A1254A');
        $this->addSql('DROP TABLE greeting_contact');
        $this->addSql('DROP TABLE greeting_log');
    }
}
