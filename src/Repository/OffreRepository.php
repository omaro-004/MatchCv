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
    /**
     * Liste des offres actives, toutes entreprises confondues, pour la navigation candidat.
     * Filtres optionnels : q (titre/description), typeContrat, modeTravail, localisation.
     *
     * @param array{q?: string, typeContrat?: string, modeTravail?: string, localisation?: string} $filters
     * @return Offre[]
     */
    public function findAllActive(array $filters = []): array
    {
        $qb = $this->createQueryBuilder('o')
            ->join('o.entreprise', 'e')
            ->addSelect('e')
            ->andWhere('o.statut = :statut')
            ->setParameter('statut', Offre::STATUT_ACTIVE)
            ->orderBy('o.datePublication', 'DESC');

        if (!empty($filters['q'])) {
            $qb->andWhere('o.titre LIKE :q OR o.description LIKE :q OR e.raisonSociale LIKE :q')
                ->setParameter('q', '%' . $filters['q'] . '%');
        }

        if (!empty($filters['typeContrat']) && in_array($filters['typeContrat'], Offre::TYPES_CONTRAT, true)) {
            $qb->andWhere('o.typeContrat = :typeContrat')->setParameter('typeContrat', $filters['typeContrat']);
        }

        if (!empty($filters['modeTravail']) && in_array($filters['modeTravail'], Offre::MODES_TRAVAIL, true)) {
            $qb->andWhere('o.modeTravail = :modeTravail')->setParameter('modeTravail', $filters['modeTravail']);
        }

        if (!empty($filters['localisation'])) {
            $qb->andWhere('o.localisation LIKE :loc')->setParameter('loc', '%' . $filters['localisation'] . '%');
        }

        return $qb->getQuery()->getResult();
    }

}