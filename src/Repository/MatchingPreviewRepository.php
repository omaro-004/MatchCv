<?php

namespace App\Repository;

use App\Entity\MatchingPreview;
use App\Entity\Offre;
use App\Entity\ProfilCandidat;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MatchingPreview>
 */
class MatchingPreviewRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MatchingPreview::class);
    }

    public function findOneByCandidatAndOffre(ProfilCandidat $candidat, Offre $offre): ?MatchingPreview
    {
        return $this->findOneBy(['candidat' => $candidat, 'offre' => $offre]);
    }

    /**
     * Invalide (supprime) tout le cache de previews d'un candidat — utilisé
     * quand son profil (compétences, expériences...) est modifié manuellement,
     * pour ne jamais afficher un score basé sur des données obsolètes.
     */
    public function deleteAllForCandidat(ProfilCandidat $candidat): void
    {
        $this->createQueryBuilder('p')
            ->delete()
            ->where('p.candidat = :candidat')
            ->setParameter('candidat', $candidat)
            ->getQuery()
            ->execute();
    }
}