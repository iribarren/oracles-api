<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260329164947 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE oracle_categories (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(50) NOT NULL, display_order INT DEFAULT 0 NOT NULL, UNIQUE INDEX UNIQ_39B048A45E237E06 (name), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE oracle_options (id INT AUTO_INCREMENT NOT NULL, value VARCHAR(255) NOT NULL, hint VARCHAR(500) DEFAULT \'\', display_order INT DEFAULT 0 NOT NULL, is_active TINYINT DEFAULT 1 NOT NULL, category_id INT NOT NULL, INDEX IDX_B7EBBF0F12469DE2 (category_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE users (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, UNIQUE INDEX UNIQ_IDENTIFIER_EMAIL (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE oracle_options ADD CONSTRAINT FK_B7EBBF0F12469DE2 FOREIGN KEY (category_id) REFERENCES oracle_categories (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE oracle_options DROP FOREIGN KEY FK_B7EBBF0F12469DE2');
        $this->addSql('DROP TABLE oracle_categories');
        $this->addSql('DROP TABLE oracle_options');
        $this->addSql('DROP TABLE users');
    }
}
