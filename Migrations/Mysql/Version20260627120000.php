<?php

declare(strict_types=1);

namespace Neos\Flow\Persistence\Doctrine\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add the `discoverable` flag to second factors so a passwordless-capable "Passkey"
 * (discoverable/resident credential) can be distinguished from a "Passkey as 2nd factor"
 * (non-discoverable). Existing rows default to non-discoverable.
 */
final class Version20260627120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add discoverable flag to second factor for passkey vs. 2nd-factor distinction';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MySqlPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySqlPlatform,'."
        );

        $this->addSql('ALTER TABLE sandstorm_neostwofactorauthentication_domain_model_secondfactor ADD discoverable TINYINT(1) DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MySqlPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySqlPlatform,'."
        );

        $this->addSql('ALTER TABLE sandstorm_neostwofactorauthentication_domain_model_secondfactor DROP discoverable');
    }
}
