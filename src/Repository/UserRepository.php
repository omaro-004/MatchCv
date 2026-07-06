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
}