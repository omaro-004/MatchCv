<?php

namespace App\Repository;

use App\Entity\AvisEntreprise;
use App\Entity\ProfilCandidat;
use App\Entity\ProfilEntreprise;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AvisEntreprise>
 */
class AvisEntrepriseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AvisEntreprise::class);
    }

    /**
     * @return AvisEntreprise[]
     */
    public function findByEntreprise(ProfilEntreprise $entreprise, int $limit = 20): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.entreprise = :entreprise')
            ->setParameter('entreprise', $entreprise)
            ->orderBy('a.dateAvis', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function averageNoteForEntreprise(ProfilEntreprise $entreprise): ?float
    {
        $avg = $this->createQueryBuilder('a')
            ->select('AVG(a.note) AS moyenne')
            ->andWhere('a.entreprise = :entreprise')
            ->setParameter('entreprise', $entreprise)
            ->getQuery()
            ->getSingleScalarResult();

        return $avg !== null ? round((float) $avg, 1) : null;
    }

    public function countForEntreprise(ProfilEntreprise $entreprise): int
    {
        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->andWhere('a.entreprise = :entreprise')
            ->setParameter('entreprise', $entreprise)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findOneByEntrepriseAndCandidat(ProfilEntreprise $entreprise, ProfilCandidat $candidat): ?AvisEntreprise
    {
        return $this->findOneBy(['entreprise' => $entreprise, 'candidat' => $candidat]);
    }
}