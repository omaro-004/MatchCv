<?php

namespace App\Repository;

use App\Entity\Offre;
use App\Entity\ProfilEntreprise;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Offre>
 */
class OffreRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Offre::class);
    }

    /**
     * @return Offre[]
     */
    public function findActiveByEntreprise(ProfilEntreprise $entreprise): array
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.entreprise = :entreprise')
            ->andWhere('o.statut = :statut')
            ->setParameter('entreprise', $entreprise)
            ->setParameter('statut', Offre::STATUT_ACTIVE)
            ->orderBy('o.datePublication', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Offre[]
     */
    public function findArchivedByEntreprise(ProfilEntreprise $entreprise): array
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.entreprise = :entreprise')
            ->andWhere('o.statut = :statut')
            ->setParameter('entreprise', $entreprise)
            ->setParameter('statut', Offre::STATUT_ARCHIVEE)
            ->orderBy('o.dateArchivage', 'DESC')
            ->getQuery()
            ->getResult();
    }
}