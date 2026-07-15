<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\CandidatureRepository;
use App\Repository\OffreRepository;
use App\Repository\UserRepository;

/**
 * AdminStatsService
 *
 * Centralise les statistiques GLOBALES de la plateforme pour le dashboard
 * Admin. Même principe que CandidatStatsService / EntrepriseStatsService :
 * aucune donnée fictive, tout est calculé depuis la base.
 */
class AdminStatsService
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly OffreRepository $offreRepository,
        private readonly CandidatureRepository $candidatureRepository,
    ) {
    }

    public function computeStats(): array
    {
        return [
            'utilisateurs_totaux' => $this->userRepository->countAll(),
            'candidats_totaux' => $this->userRepository->countByRole('candidat'),
            'entreprises_totales' => $this->userRepository->countByRole('entreprise'),
            'comptes_suspendus' => $this->userRepository->countSuspended(),

            'offres_actives' => $this->offreRepository->countAllActive(),
            'offres_archivees' => $this->offreRepository->countAllGlobalArchived(),

            'candidatures_totales' => $this->candidatureRepository->countAllGlobal(),
            'candidatures_par_statut' => $this->candidatureRepository->countByStatutGlobal(),
            'score_matching_moyen' => $this->candidatureRepository->averageScoreGlobal(),

            'inscriptions_par_mois' => $this->userRepository->countInscriptionsByMonth(6),

            'offres_par_type_contrat' => $this->offreRepository->countActiveGroupByTypeContratGlobal(),
            'offres_par_mode_travail' => $this->offreRepository->countActiveGroupByModeTravailGlobal(),

            'top_salaires_suspects' => $this->offreRepository->findTopSalairesActifs(5),
        ];
    }
}