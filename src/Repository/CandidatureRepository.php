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
     * @return Candidature[]
     */
    public function findByEntreprise(ProfilEntreprise $entreprise, ?Offre $offre = null): array
    {
        $queryBuilder = $this->createQueryBuilder('c')
            ->join('c.offre', 'o')
            ->join('c.candidat', 'p')
            ->addSelect('o', 'p')
            ->andWhere('o.entreprise = :entreprise')
            ->setParameter('entreprise', $entreprise)
            ->orderBy('c.dateCandidature', 'DESC');

        if ($offre !== null) {
            $queryBuilder
                ->andWhere('c.offre = :offre')
                ->setParameter('offre', $offre);
        }

        return $queryBuilder
            ->getQuery()
            ->getResult();
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

    // ================================================================
    //  NOUVEAU — Statistiques côté CANDIDAT, utilisées par le dashboard
    //  candidat (CandidatStatsService).
    // ================================================================

    public function countByCandidat(ProfilCandidat $candidat): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.candidat = :candidat')
            ->setParameter('candidat', $candidat)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return array<string, int> ex: ['en_attente' => 2, 'accepte' => 1, 'refuse' => 0]
     */
    public function countByStatutForCandidat(ProfilCandidat $candidat): array
    {
        $rows = $this->createQueryBuilder('c')
            ->select('c.statut AS statut, COUNT(c.id) AS total')
            ->andWhere('c.candidat = :candidat')
            ->setParameter('candidat', $candidat)
            ->groupBy('c.statut')
            ->getQuery()
            ->getResult();

        $result = array_fill_keys(Candidature::STATUTS, 0);
        foreach ($rows as $row) {
            $result[$row['statut']] = (int) $row['total'];
        }

        return $result;
    }

    /**
     * Score de matching moyen obtenu par CE candidat sur l'ensemble de
     * ses candidatures déjà scorées par l'IA.
     */
    public function averageScoreForCandidat(ProfilCandidat $candidat): ?float
    {
        $avg = $this->createQueryBuilder('c')
            ->select('AVG(c.scoreMatching) AS moyenne')
            ->andWhere('c.candidat = :candidat')
            ->andWhere('c.scoreMatching IS NOT NULL')
            ->setParameter('candidat', $candidat)
            ->getQuery()
            ->getSingleScalarResult();

        return $avg !== null ? round((float) $avg, 1) : null;
    }

    /**
     * @return Candidature[]
     */
    public function findRecentByCandidat(ProfilCandidat $candidat, int $limit = 5): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.candidat = :candidat')
            ->setParameter('candidat', $candidat)
            ->orderBy('c.dateCandidature', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return int[]
     */
    public function findOffreIdsCandidatees(ProfilCandidat $candidat): array
    {
        $rows = $this->createQueryBuilder('c')
            ->select('IDENTITY(c.offre) AS offre_id')
            ->andWhere('c.candidat = :candidat')
            ->setParameter('candidat', $candidat)
            ->getQuery()
            ->getScalarResult();

        return array_map(static fn(array $row): int => (int) $row['offre_id'], $rows);
    }

    public function findOneByOffreAndCandidat(
        
        \App\Entity\Offre $offre,
        ProfilCandidat $candidat
    ): ?Candidature {
        return $this->createQueryBuilder('c')
            ->andWhere('c.offre = :offre')
            ->andWhere('c.candidat = :candidat')
            ->setParameter('offre', $offre)
            ->setParameter('candidat', $candidat)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
    // ================================================================
    //  NOUVEAU — Requêtes globales pour le module Admin
    // ================================================================

    public function countAllGlobal(): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function averageScoreGlobal(): ?float
    {
        $avg = $this->createQueryBuilder('c')
            ->select('AVG(c.scoreMatching) AS moyenne')
            ->andWhere('c.scoreMatching IS NOT NULL')
            ->getQuery()
            ->getSingleScalarResult();

        return $avg !== null ? round((float) $avg, 1) : null;
    }

    /**
     * @return array<string, int>
     */
    public function countByStatutGlobal(): array
    {
        $rows = $this->createQueryBuilder('c')
            ->select('c.statut AS statut, COUNT(c.id) AS total')
            ->groupBy('c.statut')
            ->getQuery()
            ->getResult();

        $result = array_fill_keys(Candidature::STATUTS, 0);
        foreach ($rows as $row) {
            $result[$row['statut']] = (int) $row['total'];
        }

        return $result;
    }

    /**
     * @param array{statut?: string, q?: string} $filters
     * @return Candidature[]
     */
    public function findAllForAdmin(array $filters = []): array
    {
        $qb = $this->createQueryBuilder('c')
            ->join('c.offre', 'o')
            ->join('c.candidat', 'p')
            ->addSelect('o', 'p')
            ->orderBy('c.dateCandidature', 'DESC');

        $statut = trim((string) ($filters['statut'] ?? ''));
        if ($statut !== '') {
            $qb->andWhere('c.statut = :statut')->setParameter('statut', $statut);
        }

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $qb->andWhere(
                $qb->expr()->orX(
                    'LOWER(p.nomComplet) LIKE :q',
                    'LOWER(o.titre) LIKE :q'
                )
            )->setParameter('q', '%' . mb_strtolower($q) . '%');
        }

        return $qb->getQuery()->getResult();
    }
}