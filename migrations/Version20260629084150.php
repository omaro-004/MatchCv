<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260629084150 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE profil_candidat CHANGE type_contrat type_contrat ENUM(\'stage\', \'emploi\', \'les_deux\') NOT NULL DEFAULT \'stage\'');
        $this->addSql('DROP INDEX idx_user_face_id_enabled ON user');
        $this->addSql('ALTER TABLE user CHANGE role role ENUM(\'candidat\', \'entreprise\', \'admin\') NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE profil_candidat CHANGE type_contrat type_contrat ENUM(\'stage\', \'emploi\', \'les_deux\') DEFAULT \'stage\' NOT NULL');
        $this->addSql('ALTER TABLE user CHANGE role role ENUM(\'candidat\', \'entreprise\', \'admin\') NOT NULL');
        $this->addSql('CREATE INDEX idx_user_face_id_enabled ON user (face_id_enabled)');
    }
}
