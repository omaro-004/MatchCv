<?php

namespace App\Twig;

use App\Entity\User;
use App\Repository\CandidatureRepository;
use App\Repository\OffreRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * CandidatSidebarExtension
 *
 * Fournit aux templates deux fonctions Twig permettant d'afficher des
 * compteurs RÉELS dans la sidebar candidat (remplace les badges "24" et
 * "7" auparavant codés en dur dans base.html.twig).
 */
class CandidatSidebarExtension extends AbstractExtension
{
    public function __construct(
        private readonly Security $security,
        private readonly OffreRepository $offreRepository,
        private readonly CandidatureRepository $candidatureRepository,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('candidat_offres_disponibles_count', [$this, 'countOffresDisponibles']),
            new TwigFunction('candidat_candidatures_count', [$this, 'countCandidatures']),
        ];
    }

    public function countOffresDisponibles(): int
    {
        $user = $this->security->getUser();

        if (!$user instanceof User || !$user->isCandidat() || !$user->getProfilCandidat()) {
            return 0;
        }

        return $this->offreRepository->countActiveMatchingTypeContrat(
            $user->getProfilCandidat()->getTypeContrat()
        );
    }

    public function countCandidatures(): int
    {
        $user = $this->security->getUser();

        if (!$user instanceof User || !$user->isCandidat() || !$user->getProfilCandidat()) {
            return 0;
        }

        return $this->candidatureRepository->countByCandidat($user->getProfilCandidat());
    }
}