<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260402100546 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE refresh_tokens');
        $this->addSql('ALTER TABLE game_sessions DROP FOREIGN KEY `FK_312462357E3C61F9`');
        $this->addSql('DROP INDEX IDX_312462357E3C61F9 ON game_sessions');
        $this->addSql('ALTER TABLE game_sessions ADD game_mode VARCHAR(50) NOT NULL, DROP owner_id');
        $this->addSql('ALTER TABLE users DROP display_name');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE refresh_tokens (id INT AUTO_INCREMENT NOT NULL, refresh_token VARCHAR(128) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_ai_ci`, username VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_ai_ci`, valid DATETIME NOT NULL, UNIQUE INDEX UNIQ_9BACE7E1C74F2195 (refresh_token), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE game_sessions ADD owner_id INT DEFAULT NULL, DROP game_mode');
        $this->addSql('ALTER TABLE game_sessions ADD CONSTRAINT `FK_312462357E3C61F9` FOREIGN KEY (owner_id) REFERENCES users (id) ON UPDATE NO ACTION ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_312462357E3C61F9 ON game_sessions (owner_id)');
        $this->addSql('ALTER TABLE users ADD display_name VARCHAR(100) DEFAULT NULL');
    }
}
