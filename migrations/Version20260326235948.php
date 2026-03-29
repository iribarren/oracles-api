<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260326235948 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE attributes (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(255) NOT NULL, base_value INT NOT NULL, background INT NOT NULL, background_title VARCHAR(255) DEFAULT NULL, support INT NOT NULL, support_title VARCHAR(255) DEFAULT NULL, game_session_id BINARY(16) NOT NULL, INDEX IDX_319B9E708FE32B32 (game_session_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE books (id INT AUTO_INCREMENT NOT NULL, phase VARCHAR(255) NOT NULL, color VARCHAR(100) NOT NULL, color_hint VARCHAR(255) NOT NULL, binding VARCHAR(100) NOT NULL, binding_hint VARCHAR(255) NOT NULL, smell VARCHAR(100) NOT NULL, smell_hint VARCHAR(255) NOT NULL, interior VARCHAR(255) NOT NULL, game_session_id BINARY(16) NOT NULL, INDEX IDX_4A1B2A928FE32B32 (game_session_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE game_sessions (id BINARY(16) NOT NULL, character_name VARCHAR(255) DEFAULT NULL, character_description LONGTEXT DEFAULT NULL, genre VARCHAR(100) DEFAULT NULL, epoch VARCHAR(100) DEFAULT NULL, current_phase VARCHAR(255) NOT NULL, overcome_score INT NOT NULL, support_used TINYINT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE journal_entries (id INT AUTO_INCREMENT NOT NULL, phase VARCHAR(255) NOT NULL, content LONGTEXT NOT NULL, created_at DATETIME NOT NULL, game_session_id BINARY(16) NOT NULL, book_id INT DEFAULT NULL, INDEX IDX_FD43FD208FE32B32 (game_session_id), INDEX IDX_FD43FD2016A2B381 (book_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE roll_results (id INT AUTO_INCREMENT NOT NULL, phase VARCHAR(255) NOT NULL, action_number INT DEFAULT NULL, action_die INT NOT NULL, challenge_die_1 INT NOT NULL, challenge_die_2 INT NOT NULL, modifier INT NOT NULL, action_score INT NOT NULL, outcome VARCHAR(255) NOT NULL, attribute_type VARCHAR(255) DEFAULT NULL, game_session_id BINARY(16) NOT NULL, INDEX IDX_8FCDF7B48FE32B32 (game_session_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0 (queue_name), INDEX IDX_75EA56E0E3BD61CE (available_at), INDEX IDX_75EA56E016BA31DB (delivered_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE attributes ADD CONSTRAINT FK_319B9E708FE32B32 FOREIGN KEY (game_session_id) REFERENCES game_sessions (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE books ADD CONSTRAINT FK_4A1B2A928FE32B32 FOREIGN KEY (game_session_id) REFERENCES game_sessions (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE journal_entries ADD CONSTRAINT FK_FD43FD208FE32B32 FOREIGN KEY (game_session_id) REFERENCES game_sessions (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE journal_entries ADD CONSTRAINT FK_FD43FD2016A2B381 FOREIGN KEY (book_id) REFERENCES books (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE roll_results ADD CONSTRAINT FK_8FCDF7B48FE32B32 FOREIGN KEY (game_session_id) REFERENCES game_sessions (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE attributes DROP FOREIGN KEY FK_319B9E708FE32B32');
        $this->addSql('ALTER TABLE books DROP FOREIGN KEY FK_4A1B2A928FE32B32');
        $this->addSql('ALTER TABLE journal_entries DROP FOREIGN KEY FK_FD43FD208FE32B32');
        $this->addSql('ALTER TABLE journal_entries DROP FOREIGN KEY FK_FD43FD2016A2B381');
        $this->addSql('ALTER TABLE roll_results DROP FOREIGN KEY FK_8FCDF7B48FE32B32');
        $this->addSql('DROP TABLE attributes');
        $this->addSql('DROP TABLE books');
        $this->addSql('DROP TABLE game_sessions');
        $this->addSql('DROP TABLE journal_entries');
        $this->addSql('DROP TABLE roll_results');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
