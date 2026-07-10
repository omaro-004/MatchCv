<?php

namespace App\Repository;

use App\Entity\Candidature;
use App\Entity\Offre;
use App\Entity\ProfilCandidat;
use App\Entity\ProfilEntreprise;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Candidature>
 */
class CandidatureRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Candidature::class);
    }

    public function countByEntreprise(ProfilEntreprise $entreprise): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->join('c.offre', 'o')
            ->andWhere('o.entreprise = :entreprise')
            ->setParameter('entreprise', $entreprise)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return array<string, int> ex: ['en_attente' => 3, 'accepte' => 1, 'refuse' => 0]
     */
    public function countByStatutForEntreprise(ProfilEntreprise $entreprise): array
    {
        $rows = $this->createQueryBuilder('c')
            ->select('c.statut AS statut, COUNT(c.id) AS total')
            ->join('c.offre', 'o')
            ->andWhere('o.entreprise = :entreprise')
            ->setParameter('entreprise', $entreprise)
            ->groupBy('c.statut')
            ->getQuery()
            ->getResult();

        $result = array_fill_keys(Candidature::STATUTS, 0);
        foreach ($rows as $row) {
            $result[$row['statut']] = (int) $row['total'];
        }

        return $result;
    }

    public function averageScoreForEntreprise(ProfilEntreprise $entreprise): ?float
    {
        $avg = $this->createQueryBuilder('c')
            ->select('AVG(c.scoreMatching) AS moyenne')
            ->join('c.offre', 'o')
            ->andWhere('o.entreprise = :entreprise')
            ->andWhere('c.scoreMatching IS NOT NULL')
            ->setParameter('entreprise', $entreprise)
            ->getQuery()
            ->getSingleScalarResult();

        return $avg !== null ? round((float) $avg, 1) : null;
    }

    /**
     * @return array<int, array{titre: string, total: int}>
     */
    public function topOffresByEntreprise(ProfilEntreprise $entreprise, int $limit = 5): array
    {
        return $this->createQueryBuilder('c')
            ->select('o.titre AS titre, COUNT(c.id) AS total')
            ->join('c.offre', 'o')
            ->andWhere('o.entreprise = :entreprise')
            ->setParameter('entreprise', $entreprise)
            ->groupBy('o.id')
            ->orderBy('total', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    // ── NOUVEAU : logique du flux candidat ↔ offre ────────────────

    public function findOneByOffreAndCandidat(Offre $offre, ProfilCandidat $candidat): ?Candidature
    {
        return $this->findOneBy(['offre' => $offre, 'candidat' => $candidat]);
    }

    /**
     * @return int[] IDs des offres auxquelles ce candidat a déjà postulé.
     */
    public function findOffreIdsCandidatees(ProfilCandidat $candidat): array
    {
        $rows = $this->createQueryBuilder('c')
            ->select('IDENTITY(c.offre) AS offreId')
            ->andWhere('c.candidat = :candidat')
            ->setParameter('candidat', $candidat)
            ->getQuery()
            ->getScalarResult();

        return array_map(static fn ($row) => (int) $row['offreId'], $rows);
    }

    /**
     * @return Candidature[]
     */
    public function findByCandidat(ProfilCandidat $candidat): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.candidat = :candidat')
            ->setParameter('candidat', $candidat)
            ->orderBy('c.dateCandidature', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Candidature[]
     */
    public function findByEntreprise(ProfilEntreprise $entreprise, ?Offre $offre = null): array
    {
        $qb = $this->createQueryBuilder('c')
            ->join('c.offre', 'o')
            ->addSelect('o')
            ->andWhere('o.entreprise = :entreprise')
            ->setParameter('entreprise', $entreprise)
            ->orderBy('c.scoreMatching', 'DESC')
            ->addOrderBy('c.dateCandidature', 'DESC');

        if ($offre !== null) {
            $qb->andWhere('c.offre = :offre')->setParameter('offre', $offre);
        }

        return $qb->getQuery()->getResult();
    }
}