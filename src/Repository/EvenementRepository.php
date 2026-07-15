<?php

namespace App\Repository;

use App\Entity\Evenement;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Evenement>
 */
class EvenementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Evenement::class);
    }

    /**
     * @return Evenement[]
     */
    public function findByEntrepriseOrdered(User $entreprise): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.entreprise = :entreprise')
            ->setParameter('entreprise', $entreprise)
            ->orderBy('e.debutAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** @return Evenement[] */
    public function findActiveByEntreprise(User $entreprise): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.entreprise = :entreprise')
            ->andWhere('e.isAnnule = false')
            ->andWhere('e.isArchive = false')
            ->setParameter('entreprise', $entreprise)
            ->orderBy('e.debutAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** @return Evenement[] */
    public function findArchivedByEntreprise(User $entreprise): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.entreprise = :entreprise')
            ->andWhere('e.isArchive = true')
            ->setParameter('entreprise', $entreprise)
            ->orderBy('e.debutAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** @return Evenement[] */
    public function findCancelledByEntreprise(User $entreprise): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.entreprise = :entreprise')
            ->andWhere('e.isAnnule = true')
            ->setParameter('entreprise', $entreprise)
            ->orderBy('e.debutAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}