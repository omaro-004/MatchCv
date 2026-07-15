<?php

namespace App\Repository;

use App\Entity\PasswordResetEmailCode;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PasswordResetEmailCode>
 */
class PasswordResetEmailCodeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PasswordResetEmailCode::class);
    }

    /**
     * Dernier code non utilisé pour cet utilisateur (le plus récent).
     */
    public function findLatestValidForUser(User $user): ?PasswordResetEmailCode
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.user = :user')
            ->andWhere('c.used = :notUsed')
            ->setParameter('user', $user)
            ->setParameter('notUsed', false)
            ->orderBy('c.requestedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Invalide tous les anciens codes non utilisés avant d'en générer un
     * nouveau (évite d'avoir plusieurs codes valides simultanément).
     */
    public function invalidateAllForUser(User $user): void
    {
        $this->createQueryBuilder('c')
            ->update()
            ->set('c.used', ':used')
            ->where('c.user = :user')
            ->andWhere('c.used = :notUsed')
            ->setParameter('used', true)
            ->setParameter('notUsed', false)
            ->setParameter('user', $user)
            ->getQuery()
            ->execute();
    }
}