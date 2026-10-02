<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261002091304 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Invitation des locataires dans leur espace';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tenant ADD invited_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE tenant ADD invitation_token_hash VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE tenant ADD invitation_expires_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX uniq_tenant_invitation_token ON tenant (invitation_token_hash)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_tenant_invitation_token');
        $this->addSql('ALTER TABLE tenant DROP invited_at');
        $this->addSql('ALTER TABLE tenant DROP invitation_token_hash');
        $this->addSql('ALTER TABLE tenant DROP invitation_expires_at');
    }
}
