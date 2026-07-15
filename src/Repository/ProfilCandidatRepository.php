<?php

namespace App\Repository;

use App\Entity\ProfilCandidat;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProfilCandidat>
 */
class ProfilCandidatRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProfilCandidat::class);
    }

    /**
     * Candidats dont le type de contrat recherché correspond à celui d'une
     * offre nouvellement publiée ('les_deux' matche toujours). Utilisé par
     * NotificationService::notifierNouvelleOffre() — même logique de
     * correspondance que OffreRepository::countActiveMatchingTypeContrat().
     *
     * @return ProfilCandidat[]
     */
    public function findMatchingTypeContrat(string $typeContratOffre): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.typeContrat = :type OR p.typeContrat = :lesDeux')
            ->setParameter('type', $typeContratOffre)
            ->setParameter('lesDeux', 'les_deux')
            ->getQuery()
            ->getResult();
    }
}