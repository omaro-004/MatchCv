<?php

namespace App\Repository;

use App\Entity\PasswordResetToken;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PasswordResetToken>
 */
class PasswordResetTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PasswordResetToken::class);
    }

    /**
     * Retourne le token correspondant au hash SHA-256 s'il est encore valide
     * (non utilisé, non expiré). Le token brut n'est JAMAIS stocké en base.
     */
    public function findValidByHash(string $tokenHash): ?PasswordResetToken
    {
        $token = $this->findOneBy(['tokenHash' => $tokenHash]);

        if ($token === null || !$token->isValid()) {
            return null;
        }

        return $token;
    }

    /**
     * Invalide tous les anciens tokens non utilisés d'un utilisateur avant
     * d'en générer un nouveau (évite d'avoir plusieurs liens valides en même temps).
     */
    public function invalidateAllForUser(User $user): void
    {
        $this->createQueryBuilder('t')
            ->update()
            ->set('t.used', ':used')
            ->where('t.user = :user')
            ->andWhere('t.used = :notUsed')
            ->setParameter('used', true)
            ->setParameter('notUsed', false)
            ->setParameter('user', $user)
            ->getQuery()
            ->execute();
    }
}