<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260714112345 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE candidature CHANGE statut statut ENUM(\'en_attente\', \'accepte\', \'refuse\') NOT NULL DEFAULT \'en_attente\'');
        $this->addSql('ALTER TABLE evenement ADD photo VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE offre CHANGE type_contrat type_contrat ENUM(\'stage\', \'emploi\') NOT NULL DEFAULT \'stage\', CHANGE mode_travail mode_travail ENUM(\'sur_site\', \'teletravail\', \'hybride\') NOT NULL DEFAULT \'sur_site\', CHANGE statut statut ENUM(\'active\', \'archivee\') NOT NULL DEFAULT \'active\', CHANGE motif_archivage motif_archivage ENUM(\'duree_terminee\', \'poste_pourvu\', \'autre\') DEFAULT NULL');
        $this->addSql('ALTER TABLE profil_candidat CHANGE type_contrat type_contrat ENUM(\'stage\', \'emploi\', \'les_deux\') NOT NULL DEFAULT \'stage\'');
        $this->addSql('ALTER TABLE user CHANGE role role ENUM(\'candidat\', \'entreprise\', \'admin\') NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE candidature CHANGE statut statut ENUM(\'en_attente\', \'accepte\', \'refuse\') DEFAULT \'en_attente\' NOT NULL');
        $this->addSql('ALTER TABLE evenement DROP photo');
        $this->addSql('ALTER TABLE offre CHANGE type_contrat type_contrat ENUM(\'stage\', \'emploi\') DEFAULT \'stage\' NOT NULL, CHANGE mode_travail mode_travail ENUM(\'sur_site\', \'teletravail\', \'hybride\') DEFAULT \'sur_site\' NOT NULL, CHANGE statut statut ENUM(\'active\', \'archivee\') DEFAULT \'active\' NOT NULL, CHANGE motif_archivage motif_archivage ENUM(\'duree_terminee\', \'poste_pourvu\', \'autre\') DEFAULT NULL');
        $this->addSql('ALTER TABLE profil_candidat CHANGE type_contrat type_contrat ENUM(\'stage\', \'emploi\', \'les_deux\') DEFAULT \'stage\' NOT NULL');
        $this->addSql('ALTER TABLE user CHANGE role role ENUM(\'candidat\', \'entreprise\', \'admin\') NOT NULL');
    }
}
