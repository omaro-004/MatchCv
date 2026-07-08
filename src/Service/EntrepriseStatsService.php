<?php

namespace App\Service;

use App\Entity\ProfilEntreprise;
use App\Repository\CandidatureRepository;
use App\Repository\OffreRepository;

/**
 * Centralise le calcul des statistiques du dashboard entreprise.
 * Réutilisé par le contrôleur d'affichage ET les contrôleurs d'export (PDF/Excel)
 * pour garantir que les chiffres affichés et exportés sont toujours identiques.
 */
class EntrepriseStatsService
{
    public function __construct(
        private readonly OffreRepository $offreRepository,
        private readonly CandidatureRepository $candidatureRepository,
    ) {
    }

    public function computeStats(ProfilEntreprise $entreprise): array
    {
        return [
            'offres_actives' => $this->offreRepository->countActiveByEntreprise($entreprise),
            'offres_archivees' => $this->offreRepository->countArchivedByEntreprise($entreprise),
            'candidatures_totales' => $this->candidatureRepository->countByEntreprise($entreprise),
            'score_matching_moyen' => $this->candidatureRepository->averageScoreForEntreprise($entreprise),
            'candidatures_par_statut' => $this->candidatureRepository->countByStatutForEntreprise($entreprise),
            'offres_par_type_contrat' => $this->offreRepository->countByTypeContratForEntreprise($entreprise),
            'offres_par_mode_travail' => $this->offreRepository->countByModeTravailForEntreprise($entreprise),
            'publications_par_mois' => $this->offreRepository->countPublicationsByMonthForEntreprise($entreprise, 6),
            'top_offres' => $this->candidatureRepository->topOffresByEntreprise($entreprise, 5),
            // Module Événements pas encore implémenté (aucune entité Evenement en base) —
            // on affiche honnêtement null plutôt que d'inventer un chiffre.
            'evenements_a_venir' => null,
        ];
    }
}