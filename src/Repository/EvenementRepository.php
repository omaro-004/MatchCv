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

    /** @return Evenement[] */
    public function findAllActive(): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.isAnnule = false')
            ->andWhere('e.isArchive = false')
            ->orderBy('e.debutAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @param array{q?: string, statut?: string} $filters
     *
     * @return Evenement[]
     */
    public function findAllForAdmin(array $filters = []): array
    {
        $now = new \DateTimeImmutable();

        $qb = $this->createQueryBuilder('e')
            ->leftJoin('e.entreprise', 'u')
            ->leftJoin('u.profilEntreprise', 'pe')
            ->addSelect('u', 'pe')
            ->orderBy('e.debutAt', 'DESC');

        $query = trim((string) ($filters['q'] ?? ''));
        if ($query !== '') {
            $qb->andWhere(
                $qb->expr()->orX(
                    'LOWER(e.titre) LIKE :query',
                    'LOWER(e.description) LIKE :query',
                    'LOWER(e.lieu) LIKE :query',
                    'LOWER(u.email) LIKE :query',
                    'LOWER(pe.raisonSociale) LIKE :query'
                )
            )->setParameter('query', '%' . mb_strtolower($query) . '%');
        }

        $statut = trim((string) ($filters['statut'] ?? ''));
        if ($statut === 'active') {
            $qb->andWhere('e.isAnnule = false')
                ->andWhere('e.isArchive = false')
                ->andWhere('e.finAt >= :now')
                ->setParameter('now', $now);
        } elseif ($statut === 'archivee') {
            $qb->andWhere('e.isArchive = true');
        } elseif ($statut === 'annulee') {
            $qb->andWhere('e.isAnnule = true');
        } elseif ($statut === 'terminee') {
            $qb->andWhere('e.isAnnule = false')
                ->andWhere('e.isArchive = false')
                ->andWhere('e.finAt < :now')
                ->setParameter('now', $now);
        }

        return $qb->getQuery()->getResult();
    }
}