<?php

declare(strict_types=1);

namespace Neos\Flow\Persistence\Doctrine\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Widen the `secret` column to TEXT so it can hold serialized WebAuthn credentials.
 */
final class Version20260519120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Widen second factor secret column to TEXT for WebAuthn credential storage';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MySqlPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySqlPlatform,'."
        );

        $this->addSql('ALTER TABLE sandstorm_neostwofactorauthentication_domain_model_secondfactor MODIFY secret TEXT NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MySqlPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySqlPlatform,'."
        );

        $this->addSql('ALTER TABLE sandstorm_neostwofactorauthentication_domain_model_secondfactor MODIFY secret VARCHAR(255) NOT NULL');
    }
}
