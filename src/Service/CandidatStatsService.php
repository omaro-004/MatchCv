<?php

namespace App\Service;

use App\Entity\ProfilCandidat;
use App\Entity\User;
use App\Repository\CandidatureRepository;
use App\Repository\OffreRepository;

/**
 * CandidatStatsService
 *
 * Centralise le calcul des statistiques RÉELLES du dashboard candidat.
 * Aucune donnée inventée : tant qu'un candidat n'a pas encore de
 * candidature, les statistiques associées affichent honnêtement 0 / null.
 *
 * IMPORTANT (changement) : le "score de compatibilité" affiché sur les
 * offres recommandées n'est PLUS calculé par un recoupement local de
 * mots-clés. Il est désormais calculé par MatchingPreviewService, qui
 * réutilise EXACTEMENT le même moteur IA (App\Service\MatchingService) que
 * celui utilisé au moment du dépôt réel d'une candidature. Le score affiché
 * ici est donc garanti identique au score final si le candidat postule
 * peu après (le cache est réutilisé par OffreCandidatController::postuler()).
 */
class CandidatStatsService
{
    /**
     * Nombre maximum de NOUVEAUX calculs IA (appels Groq) déclenchés par
     * chargement du dashboard, pour ne pas dégrader le temps de réponse.
     * Les offres au-delà de ce plafond affichent "Analyse en cours" et
     * seront calculées aux prochains chargements de page.
     */
    private const MAX_PREVIEW_COMPUTATIONS_PER_LOAD = 8;

    public function __construct(
        private readonly OffreRepository $offreRepository,
        private readonly CandidatureRepository $candidatureRepository,
        private readonly MatchingPreviewService $matchingPreviewService,
    ) {
    }

    public function computeStats(User $user): array
    {
        $profil = $user->getProfilCandidat();

        if (!$profil) {
            return $this->emptyStats();
        }

        $typeContrat = $profil->getTypeContrat();
        $candidaturesParStatut = $this->candidatureRepository->countByStatutForCandidat($profil);

        return [
            'profil_completion' => $this->computeProfilCompletion($profil),
            'cv_uploade' => $profil->hasCv(),
            'offres_disponibles' => $this->offreRepository->countActiveMatchingTypeContrat($typeContrat),
            'offres_nouvelles_semaine' => $this->offreRepository->countRecentActive(7),
            'candidatures_totales' => array_sum($candidaturesParStatut),
            'candidatures_par_statut' => $candidaturesParStatut,
            'score_matching_moyen' => $this->candidatureRepository->averageScoreForCandidat($profil),
            'offres_par_type_contrat' => $this->offreRepository->countActiveGroupByTypeContratGlobal(),
            'offres_par_mode_travail' => $this->offreRepository->countActiveGroupByModeTravailGlobal(),
            'offres_recommandees' => $this->buildRecommandations($profil, $typeContrat),
            'candidatures_recentes' => $this->candidatureRepository->findRecentByCandidat($profil, 5),
        ];
    }

    /**
     * @return array<int, array{offre: \App\Entity\Offre, score: ?int, competences_matchees: string[], competences_manquantes: string[]}>
     */
    private function buildRecommandations(ProfilCandidat $profil, string $typeContrat): array
    {
        $offres = $this->offreRepository->findActiveForCandidat($typeContrat, 20);

        $recommandations = $this->matchingPreviewService->getPreviewsForOffres(
            $profil,
            $offres,
            self::MAX_PREVIEW_COMPUTATIONS_PER_LOAD
        );

        usort($recommandations, static function (array $a, array $b): int {
            $scoreA = $a['score'] ?? -1;
            $scoreB = $b['score'] ?? -1;

            if ($scoreA === $scoreB) {
                return $b['offre']->getDatePublication() <=> $a['offre']->getDatePublication();
            }

            return $scoreB <=> $scoreA;
        });

        return array_slice($recommandations, 0, 6);
    }

    /**
     * @return array{pourcentage: int, elements: array<int, array{label: string, complete: bool}>}
     */
    private function computeProfilCompletion(ProfilCandidat $profil): array
    {
        $checks = [
            'Photo de profil' => $profil->getPhoto() !== null,
            'CV téléchargé' => $profil->hasCv(),
            'Bio renseignée' => $profil->getBio() !== null && trim($profil->getBio()) !== '',
            'Localisation renseignée' => $profil->getLocalisation() !== null && trim($profil->getLocalisation()) !== '',
            'Numéro de téléphone' => $profil->getNumTel() !== null && trim($profil->getNumTel()) !== '',
            'Compétences techniques' => count($profil->getCompetencesTechniquesArray()) > 0,
            'Formations renseignées' => count($profil->getFormationsArray()) > 0,
            'Expériences ou projets' => count($profil->getExperiencesProfessionnellesArray()) > 0
                || count($profil->getProjetsAcademiquesArray()) > 0,
        ];

        $total = count($checks);
        $completed = count(array_filter($checks));

        $elements = [];
        foreach ($checks as $label => $isComplete) {
            $elements[] = ['label' => $label, 'complete' => $isComplete];
        }

        return [
            'pourcentage' => $total > 0 ? (int) round(($completed / $total) * 100) : 0,
            'elements' => $elements,
        ];
    }

    private function emptyStats(): array
    {
        return [
            'profil_completion' => ['pourcentage' => 0, 'elements' => []],
            'cv_uploade' => false,
            'offres_disponibles' => 0,
            'offres_nouvelles_semaine' => 0,
            'candidatures_totales' => 0,
            'candidatures_par_statut' => [],
            'score_matching_moyen' => null,
            'offres_par_type_contrat' => [],
            'offres_par_mode_travail' => [],
            'offres_recommandees' => [],
            'candidatures_recentes' => [],
        ];
    }
}