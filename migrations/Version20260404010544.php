<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260404010544 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE game_sessions ADD owner_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE game_sessions ADD CONSTRAINT FK_312462357E3C61F9 FOREIGN KEY (owner_id) REFERENCES users (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_312462357E3C61F9 ON game_sessions (owner_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE game_sessions DROP FOREIGN KEY FK_312462357E3C61F9');
        $this->addSql('DROP INDEX IDX_312462357E3C61F9 ON game_sessions');
        $this->addSql('ALTER TABLE game_sessions DROP owner_id');
    }
}
