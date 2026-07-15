<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260715120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create evenement_application table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE evenement_application (id INT AUTO_INCREMENT NOT NULL, evenement_id INT NOT NULL, candidat_id INT NOT NULL, message LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, INDEX IDX_EA_EVENEMENT (evenement_id), INDEX IDX_EA_CANDIDAT (candidat_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE evenement_application ADD CONSTRAINT FK_EA_EVENEMENT FOREIGN KEY (evenement_id) REFERENCES evenement (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE evenement_application ADD CONSTRAINT FK_EA_CANDIDAT FOREIGN KEY (candidat_id) REFERENCES user (id_user) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE evenement_application DROP FOREIGN KEY FK_EA_EVENEMENT');
        $this->addSql('ALTER TABLE evenement_application DROP FOREIGN KEY FK_EA_CANDIDAT');
        $this->addSql('DROP TABLE evenement_application');
    }
}
