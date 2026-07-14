<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260701092750 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE offre (id_offre INT AUTO_INCREMENT NOT NULL, titre VARCHAR(255) NOT NULL, type_offre VARCHAR(30) NOT NULL, description LONGTEXT NOT NULL, competences LONGTEXT DEFAULT NULL, mode_travail VARCHAR(40) NOT NULL, localisation VARCHAR(255) NOT NULL, duree VARCHAR(100) DEFAULT NULL, date_debut DATE DEFAULT NULL, salaire VARCHAR(100) DEFAULT NULL, niveau_requis VARCHAR(100) DEFAULT NULL, niveau_etudes VARCHAR(100) DEFAULT NULL, experience VARCHAR(100) DEFAULT NULL, langues VARCHAR(255) DEFAULT NULL, nombre_postes INT DEFAULT 1 NOT NULL, secteur VARCHAR(150) DEFAULT NULL, avantages LONGTEXT DEFAULT NULL, date_limite DATE DEFAULT NULL, candidatures_count INT DEFAULT 0 NOT NULL, statut VARCHAR(20) DEFAULT \'active\' NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, id_user INT NOT NULL, INDEX IDX_AF86866F6B3CA4B (id_user), PRIMARY KEY (id_offre)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE offre ADD CONSTRAINT FK_AF86866F6B3CA4B FOREIGN KEY (id_user) REFERENCES user (id_user) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE profil_candidat CHANGE type_contrat type_contrat ENUM(\'stage\', \'emploi\', \'les_deux\') NOT NULL DEFAULT \'stage\'');
        $this->addSql('ALTER TABLE user CHANGE role role ENUM(\'candidat\', \'entreprise\', \'admin\') NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE offre DROP FOREIGN KEY FK_AF86866F6B3CA4B');
        $this->addSql('DROP TABLE offre');
        $this->addSql('ALTER TABLE profil_candidat CHANGE type_contrat type_contrat ENUM(\'stage\', \'emploi\', \'les_deux\') DEFAULT \'stage\' NOT NULL');
        $this->addSql('ALTER TABLE user CHANGE role role ENUM(\'candidat\', \'entreprise\', \'admin\') NOT NULL');
    }
}
