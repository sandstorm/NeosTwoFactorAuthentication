<?php

declare(strict_types=1);

namespace Neos\Flow\Persistence\Doctrine\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * User management: tokens behind mailed links (forgot / initial password, email verification)
 * and the forgot-password throttle.
 */
final class Version20261021120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add user action token and throttle entry tables for the user management';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MySqlPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySqlPlatform,'."
        );

        $this->addSql('CREATE TABLE sandstorm_neostwofactorauthentication_useractiontoken (persistence_object_identifier VARCHAR(40) NOT NULL, account VARCHAR(40) DEFAULT NULL, tokenhash VARCHAR(64) NOT NULL, action VARCHAR(40) NOT NULL, emailatissue VARCHAR(255) NOT NULL, createdat DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', expiresat DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', consumedat DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX UNIQ_3AEAD79D45FBC61E (tokenhash), INDEX IDX_3AEAD79D7D3656A4 (account), PRIMARY KEY(persistence_object_identifier)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE sandstorm_neostwofactorauthentication_useractiontoken ADD CONSTRAINT FK_3AEAD79D7D3656A4 FOREIGN KEY (account) REFERENCES neos_flow_security_account (persistence_object_identifier) ON DELETE CASCADE');
        $this->addSql('CREATE TABLE sandstorm_neostwofactorauthentication_throttleentry (persistence_object_identifier VARCHAR(40) NOT NULL, bucket VARCHAR(64) NOT NULL, createdat DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX bucket_createdat (bucket, createdat), PRIMARY KEY(persistence_object_identifier)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MySqlPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySqlPlatform,'."
        );

        $this->addSql('DROP TABLE sandstorm_neostwofactorauthentication_useractiontoken');
        $this->addSql('DROP TABLE sandstorm_neostwofactorauthentication_throttleentry');
    }
}
