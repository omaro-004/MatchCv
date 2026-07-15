<?php

namespace App\Repository;

use App\Entity\EvenementApplication;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<EvenementApplication>
 */
class EvenementApplicationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EvenementApplication::class);
    }

    /**
     * Retourne les candidatures d'un candidat triées par date d'inscription.
     *
     * @return EvenementApplication[]
     */
    public function findByCandidat(User $user): array
    {
        return $this->findBy(['candidat' => $user], ['createdAt' => 'DESC']);
    }
}
