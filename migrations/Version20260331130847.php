<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260331130847 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE games (id VARCHAR(36) NOT NULL, fen VARCHAR(120) NOT NULL, turn VARCHAR(5) NOT NULL, status VARCHAR(16) NOT NULL, result VARCHAR(16) NOT NULL, ai_color VARCHAR(5) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_games_status ON games (status)');
        $this->addSql('CREATE INDEX idx_games_created_at ON games (created_at)');
        $this->addSql('CREATE TABLE moves (id VARCHAR(36) NOT NULL, ply INTEGER NOT NULL, uci VARCHAR(5) NOT NULL, promotion VARCHAR(1) DEFAULT NULL, san VARCHAR(20) DEFAULT NULL, fen_after VARCHAR(120) NOT NULL, is_check BOOLEAN NOT NULL, is_checkmate BOOLEAN NOT NULL, created_at DATETIME NOT NULL, game_id VARCHAR(36) NOT NULL, PRIMARY KEY (id), CONSTRAINT FK_453F0832E48FD905 FOREIGN KEY (game_id) REFERENCES games (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX idx_moves_game_id ON moves (game_id)');
        $this->addSql('CREATE INDEX idx_moves_created_at ON moves (created_at)');
        $this->addSql('CREATE INDEX idx_moves_ply ON moves (ply)');
        $this->addSql('CREATE TABLE messenger_messages (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, body CLOB NOT NULL, headers CLOB NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL)');
        $this->addSql('CREATE INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 ON messenger_messages (queue_name, available_at, delivered_at, id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE moves');
        $this->addSql('DROP TABLE games');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
