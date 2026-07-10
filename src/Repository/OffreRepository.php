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
     * @param array{q?: string, typeContrat?: string, modeTravail?: string, localisation?: string} $filters
     *
     * @return Offre[]
     */
    public function findAllActive(array $filters = []): array
    {
        $qb = $this->createQueryBuilder('o')
            ->leftJoin('o.entreprise', 'e')
            ->addSelect('e')
            ->andWhere('o.statut = :statut')
            ->setParameter('statut', Offre::STATUT_ACTIVE)
            ->orderBy('o.datePublication', 'DESC');

        $query = trim((string) ($filters['q'] ?? ''));
        if ($query !== '') {
            $qb->andWhere(
                $qb->expr()->orX(
                    'LOWER(o.titre) LIKE :query',
                    'LOWER(o.description) LIKE :query',
                    'LOWER(o.localisation) LIKE :query',
                    'LOWER(e.raisonSociale) LIKE :query',
                    'LOWER(e.secteur) LIKE :query'
                )
            )->setParameter('query', '%' . mb_strtolower($query) . '%');
        }

        $typeContrat = trim((string) ($filters['typeContrat'] ?? ''));
        if ($typeContrat !== '') {
            $qb->andWhere('o.typeContrat = :typeContrat')
                ->setParameter('typeContrat', $typeContrat);
        }

        $modeTravail = trim((string) ($filters['modeTravail'] ?? ''));
        if ($modeTravail !== '') {
            $qb->andWhere('o.modeTravail = :modeTravail')
                ->setParameter('modeTravail', $modeTravail);
        }

        $localisation = trim((string) ($filters['localisation'] ?? ''));
        if ($localisation !== '') {
            $qb->andWhere('LOWER(o.localisation) LIKE :localisation')
                ->setParameter('localisation', '%' . mb_strtolower($localisation) . '%');
        }

        return $qb->getQuery()->getResult();
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

    public function countActiveByEntreprise(ProfilEntreprise $entreprise): int
    {
        return (int) $this->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->andWhere('o.entreprise = :entreprise')
            ->andWhere('o.statut = :statut')
            ->setParameter('entreprise', $entreprise)
            ->setParameter('statut', Offre::STATUT_ACTIVE)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countArchivedByEntreprise(ProfilEntreprise $entreprise): int
    {
        return (int) $this->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->andWhere('o.entreprise = :entreprise')
            ->andWhere('o.statut = :statut')
            ->setParameter('entreprise', $entreprise)
            ->setParameter('statut', Offre::STATUT_ARCHIVEE)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return array<string, int> ex: ['stage' => 2, 'emploi' => 3]
     */
    public function countByTypeContratForEntreprise(ProfilEntreprise $entreprise): array
    {
        $rows = $this->createQueryBuilder('o')
            ->select('o.typeContrat AS type, COUNT(o.id) AS total')
            ->andWhere('o.entreprise = :entreprise')
            ->andWhere('o.statut = :statut')
            ->setParameter('entreprise', $entreprise)
            ->setParameter('statut', Offre::STATUT_ACTIVE)
            ->groupBy('o.typeContrat')
            ->getQuery()
            ->getResult();

        $result = array_fill_keys(Offre::TYPES_CONTRAT, 0);
        foreach ($rows as $row) {
            $result[$row['type']] = (int) $row['total'];
        }

        return $result;
    }

    /**
     * @return array<string, int>
     */
    public function countByModeTravailForEntreprise(ProfilEntreprise $entreprise): array
    {
        $rows = $this->createQueryBuilder('o')
            ->select('o.modeTravail AS mode, COUNT(o.id) AS total')
            ->andWhere('o.entreprise = :entreprise')
            ->andWhere('o.statut = :statut')
            ->setParameter('entreprise', $entreprise)
            ->setParameter('statut', Offre::STATUT_ACTIVE)
            ->groupBy('o.modeTravail')
            ->getQuery()
            ->getResult();

        $result = array_fill_keys(Offre::MODES_TRAVAIL, 0);
        foreach ($rows as $row) {
            $result[$row['mode']] = (int) $row['total'];
        }

        return $result;
    }

    /**
     * Nombre d'offres publiées par mois sur les N derniers mois (toutes statuts confondus).
     *
     * @return array<string, int> ex: ['2026-02' => 1, '2026-03' => 4, ...]
     */
    public function countPublicationsByMonthForEntreprise(ProfilEntreprise $entreprise, int $months = 6): array
    {
        $since = (new \DateTimeImmutable('first day of this month midnight'))
            ->modify('-' . ($months - 1) . ' months');

        $offres = $this->createQueryBuilder('o')
            ->andWhere('o.entreprise = :entreprise')
            ->andWhere('o.datePublication >= :since')
            ->setParameter('entreprise', $entreprise)
            ->setParameter('since', $since)
            ->getQuery()
            ->getResult();

        $result = [];
        $cursor = $since;
        for ($i = 0; $i < $months; $i++) {
            $result[$cursor->format('Y-m')] = 0;
            $cursor = $cursor->modify('+1 month');
        }

        foreach ($offres as $offre) {
            $key = $offre->getDatePublication()->format('Y-m');
            if (isset($result[$key])) {
                $result[$key]++;
            }
        }

        return $result;
    }

    // ================================================================
    //  NOUVEAU — Statistiques GLOBALES (toutes entreprises confondues)
    //  utilisées par le dashboard CANDIDAT.
    // ================================================================

    /**
     * Nombre total d'offres actives sur toute la plateforme.
     */
    public function countAllActive(): int
    {
        return (int) $this->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->andWhere('o.statut = :statut')
            ->setParameter('statut', Offre::STATUT_ACTIVE)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Nombre d'offres actives correspondant au type de contrat recherché
     * par le candidat ('les_deux' = pas de filtre).
     */
    public function countActiveMatchingTypeContrat(string $typeContratCandidat): int
    {
        $qb = $this->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->andWhere('o.statut = :statut')
            ->setParameter('statut', Offre::STATUT_ACTIVE);

        if ($typeContratCandidat !== 'les_deux') {
            $qb->andWhere('o.typeContrat = :type')->setParameter('type', $typeContratCandidat);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Nombre d'offres actives publiées durant les N derniers jours
     * (toutes entreprises confondues).
     */
    public function countRecentActive(int $days = 7): int
    {
        $since = (new \DateTimeImmutable())->modify(sprintf('-%d days', $days));

        return (int) $this->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->andWhere('o.statut = :statut')
            ->andWhere('o.datePublication >= :since')
            ->setParameter('statut', Offre::STATUT_ACTIVE)
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Répartition GLOBALE des offres actives par type de contrat.
     *
     * @return array<string, int>
     */
    public function countActiveGroupByTypeContratGlobal(): array
    {
        $rows = $this->createQueryBuilder('o')
            ->select('o.typeContrat AS type, COUNT(o.id) AS total')
            ->andWhere('o.statut = :statut')
            ->setParameter('statut', Offre::STATUT_ACTIVE)
            ->groupBy('o.typeContrat')
            ->getQuery()
            ->getResult();

        $result = array_fill_keys(Offre::TYPES_CONTRAT, 0);
        foreach ($rows as $row) {
            $result[$row['type']] = (int) $row['total'];
        }

        return $result;
    }

    /**
     * Répartition GLOBALE des offres actives par mode de travail.
     *
     * @return array<string, int>
     */
    public function countActiveGroupByModeTravailGlobal(): array
    {
        $rows = $this->createQueryBuilder('o')
            ->select('o.modeTravail AS mode, COUNT(o.id) AS total')
            ->andWhere('o.statut = :statut')
            ->setParameter('statut', Offre::STATUT_ACTIVE)
            ->groupBy('o.modeTravail')
            ->getQuery()
            ->getResult();

        $result = array_fill_keys(Offre::MODES_TRAVAIL, 0);
        foreach ($rows as $row) {
            $result[$row['mode']] = (int) $row['total'];
        }

        return $result;
    }

    /**
     * Offres actives correspondant au type de contrat recherché par le
     * candidat, les plus récentes en premier — base de la liste de
     * recommandations du dashboard candidat.
     *
     * @return Offre[]
     */
    public function findActiveForCandidat(string $typeContratCandidat, int $limit = 20): array
    {
        $qb = $this->createQueryBuilder('o')
            ->andWhere('o.statut = :statut')
            ->setParameter('statut', Offre::STATUT_ACTIVE)
            ->orderBy('o.datePublication', 'DESC')
            ->setMaxResults($limit);

        if ($typeContratCandidat !== 'les_deux') {
            $qb->andWhere('o.typeContrat = :type')->setParameter('type', $typeContratCandidat);
        }

        return $qb->getQuery()->getResult();
    }
}