<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Recherche un compte à partir d'un identifiant "flou" saisi par
     * l'utilisateur sur la page "mot de passe oublié" : email, numéro de
     * téléphone (candidat ou entreprise), ou nom complet / raison sociale.
     *
     * Ordre de recherche (du plus fiable au moins fiable) :
     *   1. Email exact
     *   2. Numéro de téléphone
     *   3. Nom complet (candidat) / raison sociale (entreprise)
     */
    public function findOneByIdentifier(string $identifiant): ?User
    {
        $identifiant = trim($identifiant);

        if ($identifiant === '') {
            return null;
        }

        // 1. Email (insensible à la casse)
        $user = $this->createQueryBuilder('u')
            ->where('LOWER(u.email) = :email')
            ->setParameter('email', mb_strtolower($identifiant))
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if ($user) {
            return $user;
        }

        // 2. Numéro de téléphone (candidat ou entreprise)
        $user = $this->createQueryBuilder('u')
            ->leftJoin('u.profilCandidat', 'pc')
            ->leftJoin('u.profilEntreprise', 'pe')
            ->where('pc.numTel = :tel')
            ->orWhere('pe.numTel = :tel')
            ->setParameter('tel', $identifiant)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if ($user) {
            return $user;
        }

        // 3. Nom complet / raison sociale (exact, insensible à la casse)
        $user = $this->createQueryBuilder('u')
            ->leftJoin('u.profilCandidat', 'pc')
            ->leftJoin('u.profilEntreprise', 'pe')
            ->where('LOWER(pc.nomComplet) = :nom')
            ->orWhere('LOWER(pe.raisonSociale) = :nom')
            ->setParameter('nom', mb_strtolower($identifiant))
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $user;
    }
    // ================================================================
    //  NOUVEAU — Requêtes globales pour le module Admin
    // ================================================================

    public function countAll(): int
    {
        return (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countByRole(string $role): int
    {
        return (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->andWhere('u.role = :role')
            ->setParameter('role', $role)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countSuspended(): int
    {
        return (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->andWhere('u.compteStatut = :statut')
            ->setParameter('statut', User::STATUT_SUSPENDU)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @param array{role?: string, statut?: string, q?: string} $filters
     * @return User[]
     */
    public function findAllFiltered(array $filters = []): array
    {
        $qb = $this->createQueryBuilder('u')
            ->leftJoin('u.profilCandidat', 'pc')
            ->leftJoin('u.profilEntreprise', 'pe')
            ->addSelect('pc', 'pe')
            ->orderBy('u.dateInscri', 'DESC');

        $role = trim((string) ($filters['role'] ?? ''));
        if ($role !== '') {
            $qb->andWhere('u.role = :role')->setParameter('role', $role);
        }

        $statut = trim((string) ($filters['statut'] ?? ''));
        if ($statut !== '') {
            $qb->andWhere('u.compteStatut = :statut')->setParameter('statut', $statut);
        }

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $qb->andWhere(
                $qb->expr()->orX(
                    'LOWER(u.email) LIKE :q',
                    'LOWER(pc.nomComplet) LIKE :q',
                    'LOWER(pe.raisonSociale) LIKE :q'
                )
            )->setParameter('q', '%' . mb_strtolower($q) . '%');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Évolution des inscriptions par mois (candidats vs entreprises), pour
     * le graphique du dashboard admin.
     *
     * @return array<string, array{candidat: int, entreprise: int}>
     */
    public function countInscriptionsByMonth(int $months = 6): array
    {
        $since = (new \DateTimeImmutable('first day of this month midnight'))
            ->modify('-' . ($months - 1) . ' months');

        $users = $this->createQueryBuilder('u')
            ->andWhere('u.dateInscri >= :since')
            ->andWhere('u.role IN (:roles)')
            ->setParameter('since', $since)
            ->setParameter('roles', ['candidat', 'entreprise'])
            ->getQuery()
            ->getResult();

        $result = [];
        $cursor = $since;
        for ($i = 0; $i < $months; $i++) {
            $result[$cursor->format('Y-m')] = ['candidat' => 0, 'entreprise' => 0];
            $cursor = $cursor->modify('+1 month');
        }

        foreach ($users as $user) {
            $key = $user->getDateInscri()->format('Y-m');
            if (isset($result[$key])) {
                $result[$key][$user->getRole()]++;
            }
        }

        return $result;
    }
}