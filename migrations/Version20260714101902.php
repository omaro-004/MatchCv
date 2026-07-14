<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260714101902 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE evenement (id INT AUTO_INCREMENT NOT NULL, titre VARCHAR(255) NOT NULL, description LONGTEXT NOT NULL, is_online TINYINT NOT NULL, lieu VARCHAR(255) DEFAULT NULL, debut_at DATETIME NOT NULL, fin_at DATETIME NOT NULL, capacite INT DEFAULT NULL, is_annule TINYINT NOT NULL, note_annulation LONGTEXT DEFAULT NULL, is_archive TINYINT NOT NULL, created_at DATETIME NOT NULL, id_entreprise INT NOT NULL, INDEX IDX_B26681EA8937AB7 (id_entreprise), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE evenement ADD CONSTRAINT FK_B26681EA8937AB7 FOREIGN KEY (id_entreprise) REFERENCES user (id_user) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE avis_entreprise DROP FOREIGN KEY `fk_avis_candidat`');
        $this->addSql('ALTER TABLE avis_entreprise CHANGE note note INT NOT NULL, CHANGE commentaire commentaire LONGTEXT DEFAULT NULL');
        $this->addSql('DROP INDEX fk_avis_candidat ON avis_entreprise');
        $this->addSql('CREATE INDEX IDX_E1DC5D593A6E58E4 ON avis_entreprise (id_candidat)');
        $this->addSql('ALTER TABLE avis_entreprise ADD CONSTRAINT `fk_avis_candidat` FOREIGN KEY (id_candidat) REFERENCES profil_candidat (id_profil) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE candidature DROP FOREIGN KEY `fk_candidature_candidat`');
        $this->addSql('ALTER TABLE candidature DROP FOREIGN KEY `fk_candidature_offre`');
        $this->addSql('ALTER TABLE candidature CHANGE lettre_motivation lettre_motivation LONGTEXT DEFAULT NULL, CHANGE statut statut ENUM(\'en_attente\', \'accepte\', \'refuse\') NOT NULL DEFAULT \'en_attente\', CHANGE score_matching score_matching DOUBLE PRECISION DEFAULT NULL, CHANGE competences_matchees competences_matchees LONGTEXT DEFAULT NULL, CHANGE competences_manquantes competences_manquantes LONGTEXT DEFAULT NULL');
        $this->addSql('DROP INDEX idx_candidature_offre ON candidature');
        $this->addSql('CREATE INDEX IDX_E33BD3B84103C75F ON candidature (id_offre)');
        $this->addSql('DROP INDEX idx_candidature_candidat ON candidature');
        $this->addSql('CREATE INDEX IDX_E33BD3B83A6E58E4 ON candidature (id_candidat)');
        $this->addSql('ALTER TABLE candidature ADD CONSTRAINT `fk_candidature_candidat` FOREIGN KEY (id_candidat) REFERENCES profil_candidat (id_profil) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE candidature ADD CONSTRAINT `fk_candidature_offre` FOREIGN KEY (id_offre) REFERENCES offre (id_offre) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE matching_preview DROP FOREIGN KEY `fk_matching_preview_offre`');
        $this->addSql('ALTER TABLE matching_preview CHANGE competences_matchees competences_matchees LONGTEXT DEFAULT NULL, CHANGE competences_manquantes competences_manquantes LONGTEXT DEFAULT NULL');
        $this->addSql('DROP INDEX fk_matching_preview_offre ON matching_preview');
        $this->addSql('CREATE INDEX IDX_3D8BEB8F4103C75F ON matching_preview (id_offre)');
        $this->addSql('ALTER TABLE matching_preview ADD CONSTRAINT `fk_matching_preview_offre` FOREIGN KEY (id_offre) REFERENCES offre (id_offre) ON DELETE CASCADE');
        $this->addSql('DROP INDEX idx_offre_statut ON offre');
        $this->addSql('ALTER TABLE offre DROP FOREIGN KEY `fk_offre_entreprise`');
        $this->addSql('ALTER TABLE offre CHANGE type_contrat type_contrat ENUM(\'stage\', \'emploi\') NOT NULL DEFAULT \'stage\', CHANGE mode_travail mode_travail ENUM(\'sur_site\', \'teletravail\', \'hybride\') NOT NULL DEFAULT \'sur_site\', CHANGE statut statut ENUM(\'active\', \'archivee\') NOT NULL DEFAULT \'active\', CHANGE motif_archivage motif_archivage ENUM(\'duree_terminee\', \'poste_pourvu\', \'autre\') DEFAULT NULL, CHANGE motif_archivage_details motif_archivage_details LONGTEXT DEFAULT NULL');
        $this->addSql('DROP INDEX idx_offre_entreprise ON offre');
        $this->addSql('CREATE INDEX IDX_AF86866FA8937AB7 ON offre (id_entreprise)');
        $this->addSql('ALTER TABLE offre ADD CONSTRAINT `fk_offre_entreprise` FOREIGN KEY (id_entreprise) REFERENCES profil_entreprise (id_profil) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE password_reset_token DROP FOREIGN KEY `fk_password_reset_token_user`');
        $this->addSql('DROP INDEX uniq_token_hash ON password_reset_token');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_6B7BA4B6B3BC57DA ON password_reset_token (token_hash)');
        $this->addSql('DROP INDEX idx_prt_user ON password_reset_token');
        $this->addSql('CREATE INDEX IDX_6B7BA4B66B3CA4B ON password_reset_token (id_user)');
        $this->addSql('ALTER TABLE password_reset_token ADD CONSTRAINT `fk_password_reset_token_user` FOREIGN KEY (id_user) REFERENCES user (id_user) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE profil_candidat CHANGE type_contrat type_contrat ENUM(\'stage\', \'emploi\', \'les_deux\') NOT NULL DEFAULT \'stage\', CHANGE annees_experience annees_experience INT DEFAULT NULL, CHANGE langues_parlees langues_parlees LONGTEXT DEFAULT NULL, CHANGE competences_techniques competences_techniques LONGTEXT DEFAULT NULL, CHANGE formations formations LONGTEXT DEFAULT NULL, CHANGE experiences_professionnelles experiences_professionnelles LONGTEXT DEFAULT NULL, CHANGE projets_academiques projets_academiques LONGTEXT DEFAULT NULL, CHANGE soft_skills soft_skills LONGTEXT DEFAULT NULL, CHANGE resume_ia resume_ia LONGTEXT DEFAULT NULL, CHANGE cv_ai_parsed_at cv_ai_parsed_at DATETIME DEFAULT NULL');
        $this->addSql('DROP INDEX UNIQ_OAUTH_PROVIDER_ID ON user');
        $this->addSql('ALTER TABLE user CHANGE role role ENUM(\'candidat\', \'entreprise\', \'admin\') NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE evenement DROP FOREIGN KEY FK_B26681EA8937AB7');
        $this->addSql('DROP TABLE evenement');
        $this->addSql('ALTER TABLE avis_entreprise DROP FOREIGN KEY FK_E1DC5D593A6E58E4');
        $this->addSql('ALTER TABLE avis_entreprise CHANGE note note TINYINT NOT NULL, CHANGE commentaire commentaire TEXT DEFAULT NULL');
        $this->addSql('DROP INDEX idx_e1dc5d593a6e58e4 ON avis_entreprise');
        $this->addSql('CREATE INDEX fk_avis_candidat ON avis_entreprise (id_candidat)');
        $this->addSql('ALTER TABLE avis_entreprise ADD CONSTRAINT FK_E1DC5D593A6E58E4 FOREIGN KEY (id_candidat) REFERENCES profil_candidat (id_profil) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE candidature DROP FOREIGN KEY FK_E33BD3B84103C75F');
        $this->addSql('ALTER TABLE candidature DROP FOREIGN KEY FK_E33BD3B83A6E58E4');
        $this->addSql('ALTER TABLE candidature CHANGE lettre_motivation lettre_motivation TEXT DEFAULT NULL, CHANGE statut statut ENUM(\'en_attente\', \'accepte\', \'refuse\') DEFAULT \'en_attente\' NOT NULL, CHANGE score_matching score_matching FLOAT DEFAULT NULL, CHANGE competences_matchees competences_matchees TEXT DEFAULT NULL, CHANGE competences_manquantes competences_manquantes TEXT DEFAULT NULL');
        $this->addSql('DROP INDEX idx_e33bd3b84103c75f ON candidature');
        $this->addSql('CREATE INDEX idx_candidature_offre ON candidature (id_offre)');
        $this->addSql('DROP INDEX idx_e33bd3b83a6e58e4 ON candidature');
        $this->addSql('CREATE INDEX idx_candidature_candidat ON candidature (id_candidat)');
        $this->addSql('ALTER TABLE candidature ADD CONSTRAINT FK_E33BD3B84103C75F FOREIGN KEY (id_offre) REFERENCES offre (id_offre) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE candidature ADD CONSTRAINT FK_E33BD3B83A6E58E4 FOREIGN KEY (id_candidat) REFERENCES profil_candidat (id_profil) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE matching_preview DROP FOREIGN KEY FK_3D8BEB8F4103C75F');
        $this->addSql('ALTER TABLE matching_preview CHANGE competences_matchees competences_matchees TEXT DEFAULT NULL, CHANGE competences_manquantes competences_manquantes TEXT DEFAULT NULL');
        $this->addSql('DROP INDEX idx_3d8beb8f4103c75f ON matching_preview');
        $this->addSql('CREATE INDEX fk_matching_preview_offre ON matching_preview (id_offre)');
        $this->addSql('ALTER TABLE matching_preview ADD CONSTRAINT FK_3D8BEB8F4103C75F FOREIGN KEY (id_offre) REFERENCES offre (id_offre) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE offre DROP FOREIGN KEY FK_AF86866FA8937AB7');
        $this->addSql('ALTER TABLE offre CHANGE type_contrat type_contrat ENUM(\'stage\', \'emploi\') DEFAULT \'stage\' NOT NULL, CHANGE mode_travail mode_travail ENUM(\'sur_site\', \'teletravail\', \'hybride\') DEFAULT \'sur_site\' NOT NULL, CHANGE statut statut ENUM(\'active\', \'archivee\') DEFAULT \'active\' NOT NULL, CHANGE motif_archivage motif_archivage ENUM(\'duree_terminee\', \'poste_pourvu\', \'autre\') DEFAULT NULL, CHANGE motif_archivage_details motif_archivage_details TEXT DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_offre_statut ON offre (statut)');
        $this->addSql('DROP INDEX idx_af86866fa8937ab7 ON offre');
        $this->addSql('CREATE INDEX idx_offre_entreprise ON offre (id_entreprise)');
        $this->addSql('ALTER TABLE offre ADD CONSTRAINT FK_AF86866FA8937AB7 FOREIGN KEY (id_entreprise) REFERENCES profil_entreprise (id_profil) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE password_reset_token DROP FOREIGN KEY FK_6B7BA4B66B3CA4B');
        $this->addSql('DROP INDEX uniq_6b7ba4b6b3bc57da ON password_reset_token');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_TOKEN_HASH ON password_reset_token (token_hash)');
        $this->addSql('DROP INDEX idx_6b7ba4b66b3ca4b ON password_reset_token');
        $this->addSql('CREATE INDEX IDX_PRT_USER ON password_reset_token (id_user)');
        $this->addSql('ALTER TABLE password_reset_token ADD CONSTRAINT FK_6B7BA4B66B3CA4B FOREIGN KEY (id_user) REFERENCES user (id_user) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE profil_candidat CHANGE type_contrat type_contrat ENUM(\'stage\', \'emploi\', \'les_deux\') DEFAULT \'stage\' NOT NULL, CHANGE annees_experience annees_experience INT DEFAULT NULL COMMENT \'Nombre d’années d’expérience déduit par l’IA depuis le CV\', CHANGE langues_parlees langues_parlees LONGTEXT DEFAULT NULL COMMENT \'JSON — langues humaines parlées, ex: ["Français (natif)","Anglais (courant)"]\', CHANGE competences_techniques competences_techniques LONGTEXT DEFAULT NULL COMMENT \'JSON — langages / frameworks / outils détectés dans le CV\', CHANGE formations formations LONGTEXT DEFAULT NULL COMMENT \'JSON — diplômes / cursus académiques détectés dans le CV\', CHANGE experiences_professionnelles experiences_professionnelles LONGTEXT DEFAULT NULL COMMENT \'JSON — intitulés de poste / entreprises détectés dans le CV\', CHANGE projets_academiques projets_academiques LONGTEXT DEFAULT NULL COMMENT \'JSON — projets académiques/personnels détectés dans le CV (intitulé + courte description)\', CHANGE soft_skills soft_skills LONGTEXT DEFAULT NULL COMMENT \'JSON — compétences comportementales (soft skills) détectées dans le CV\', CHANGE resume_ia resume_ia LONGTEXT DEFAULT NULL COMMENT \'Résumé professionnel synthétique généré par l’IA\', CHANGE cv_ai_parsed_at cv_ai_parsed_at DATETIME DEFAULT NULL COMMENT \'Horodatage du dernier parsing IA réussi\'');
        $this->addSql('ALTER TABLE user CHANGE role role ENUM(\'candidat\', \'entreprise\', \'admin\') NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_OAUTH_PROVIDER_ID ON user (oauth_provider, oauth_id)');
    }
}
