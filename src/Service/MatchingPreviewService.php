<?php

namespace App\Service;

use App\Entity\MatchingPreview;
use App\Entity\Offre;
use App\Entity\ProfilCandidat;
use App\Repository\MatchingPreviewRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * MatchingPreviewService
 *
 * Fournit, pour une liste d'offres, un score IA "aperçu" (avant candidature)
 * en réutilisant EXACTEMENT le même moteur que MatchingService (donc le même
 * algorithme / prompt que celui utilisé au dépôt réel d'une candidature).
 *
 * Les résultats sont mis en cache en base (MatchingPreview) pour éviter de
 * ré-appeler l'API Groq à chaque chargement du dashboard candidat. Le nombre
 * de NOUVEAUX calculs IA déclenchés par chargement de page est plafonné
 * (self::DEFAULT_MAX_COMPUTATIONS) pour ne pas dégrader le temps de réponse.
 *
 * Principe "no fabricated data" respecté : une offre pas encore analysée
 * renvoie score = null (jamais un chiffre inventé), et sera recalculée à un
 * prochain chargement de page tant que le plafond n'est pas dépassé.
 */
class MatchingPreviewService
{
    /** Durée de fraîcheur maximale d'un preview avant recalcul forcé. */
    private const MAX_AGE_DAYS = 7;

    public function __construct(
        private readonly MatchingService $matchingService,
        private readonly MatchingPreviewRepository $previewRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param Offre[] $offres
     *
     * @return array<int, array{offre: Offre, score: ?int, competences_matchees: string[], competences_manquantes: string[]}>
     */
    public function getPreviewsForOffres(ProfilCandidat $candidat, array $offres, int $maxComputations = 8): array
    {
        $results = [];
        $computationsUsed = 0;

        foreach ($offres as $offre) {
            $preview = $this->previewRepository->findOneByCandidatAndOffre($candidat, $offre);

            if ($preview !== null && $this->isPreviewValid($preview, $candidat, $offre)) {
                $results[] = [
                    'offre' => $offre,
                    'score' => $preview->getScore(),
                    'competences_matchees' => $preview->getCompetencesMatcheesArray(),
                    'competences_manquantes' => $preview->getCompetencesManquantesArray(),
                ];
                continue;
            }

            if ($computationsUsed >= $maxComputations) {
                // Plafond atteint pour cette requête : on n'invente pas de score,
                // cette offre sera analysée lors d'un prochain chargement.
                $results[] = [
                    'offre' => $offre,
                    'score' => null,
                    'competences_matchees' => [],
                    'competences_manquantes' => [],
                ];
                continue;
            }

            $computationsUsed++;
            $computed = $this->matchingService->computeScore($candidat, $offre);

            if ($preview === null) {
                $preview = new MatchingPreview();
                $preview->setCandidat($candidat);
                $preview->setOffre($offre);
            }

            $preview->setScore($computed['score']);
            $preview->setCompetencesMatcheesArray($computed['competences_matchees']);
            $preview->setCompetencesManquantesArray($computed['competences_manquantes']);
            $preview->setComputedAt(new \DateTimeImmutable());

            $this->entityManager->persist($preview);

            $results[] = [
                'offre' => $offre,
                'score' => $computed['score'],
                'competences_matchees' => $computed['competences_matchees'],
                'competences_manquantes' => $computed['competences_manquantes'],
            ];
        }

        if ($computationsUsed > 0) {
            $this->entityManager->flush();
        }

        return $results;
    }

    /**
     * Utilisé par OffreCandidatController::postuler() : si un aperçu valide
     * existe déjà pour ce couple candidat/offre, on le réutilise TEL QUEL au
     * lieu de relancer un appel IA — garantissant que le score affiché en
     * recommandation est identique au score final de la candidature.
     */
    public function findValidPreview(ProfilCandidat $candidat, Offre $offre): ?MatchingPreview
    {
        $preview = $this->previewRepository->findOneByCandidatAndOffre($candidat, $offre);

        if ($preview !== null && $this->isPreviewValid($preview, $candidat, $offre)) {
            return $preview;
        }

        return null;
    }

    private function isPreviewValid(MatchingPreview $preview, ProfilCandidat $candidat, Offre $offre): bool
    {
        $computedAt = $preview->getComputedAt();

        if ($computedAt === null) {
            return false;
        }

        // Le CV/profil a été ré-analysé par l'IA depuis ce calcul → obsolète.
        if ($candidat->getCvAiParsedAt() !== null && $computedAt < $candidat->getCvAiParsedAt()) {
            return false;
        }

        // L'offre a été modifiée depuis ce calcul → obsolète.
        if ($offre->getUpdatedAt() !== null && $computedAt < $offre->getUpdatedAt()) {
            return false;
        }

        // Fraîcheur maximale dépassée.
        $maxAge = (new \DateTimeImmutable())->modify('-' . self::MAX_AGE_DAYS . ' days');
        if ($computedAt < $maxAge) {
            return false;
        }

        return true;
    }
}