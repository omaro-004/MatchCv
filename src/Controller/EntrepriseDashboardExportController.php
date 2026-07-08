<?php

namespace App\Controller;

use App\Entity\ProfilEntreprise;
use App\Entity\User;
use App\Service\EntrepriseStatsService;
use Dompdf\Dompdf;
use Dompdf\Options;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ENTREPRISE')]
class EntrepriseDashboardExportController extends AbstractController
{
    #[Route('/entreprise/dashboard/export/pdf', name: 'app_entreprise_dashboard_export_pdf', methods: ['GET'])]
    public function exportPdf(EntrepriseStatsService $statsService): Response
    {
        $profil = $this->getProfilEntrepriseOrThrow();
        $stats = $statsService->computeStats($profil);

        $html = $this->renderView('entreprise/dashboard/export_pdf.html.twig', [
            'entreprise' => $profil,
            'stats' => $stats,
            'dateExport' => new \DateTimeImmutable(),
        ]);

        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'Helvetica');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $filename = 'statistiques-matchcv-' . (new \DateTimeImmutable())->format('Y-m-d') . '.pdf';

        return new Response($dompdf->output(), Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    #[Route('/entreprise/dashboard/export/excel', name: 'app_entreprise_dashboard_export_excel', methods: ['GET'])]
    public function exportExcel(EntrepriseStatsService $statsService): StreamedResponse
    {
        $profil = $this->getProfilEntrepriseOrThrow();
        $stats = $statsService->computeStats($profil);

        $spreadsheet = new Spreadsheet();

        // ── Feuille 1 : Résumé ──────────────────────────────────────
        $resume = $spreadsheet->getActiveSheet();
        $resume->setTitle('Résumé');
        $resume->setCellValue('A1', 'Statistiques MatchCV — ' . $profil->getRaisonSociale());
        $resume->mergeCells('A1:B1');
        $resume->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $resume->setCellValue('A3', 'Indicateur');
        $resume->setCellValue('B3', 'Valeur');
        $resume->getStyle('A3:B3')->getFont()->setBold(true);

        $lignes = [
            ['Offres actives', $stats['offres_actives']],
            ['Offres archivées', $stats['offres_archivees']],
            ['Candidatures totales', $stats['candidatures_totales']],
            ['Score moyen de matching', $stats['score_matching_moyen'] !== null ? $stats['score_matching_moyen'] . ' %' : 'N/A'],
        ];
        $row = 4;
        foreach ($lignes as $ligne) {
            $resume->setCellValue('A' . $row, $ligne[0]);
            $resume->setCellValue('B' . $row, $ligne[1]);
            $row++;
        }
        foreach (['A', 'B'] as $col) {
            $resume->getColumnDimension($col)->setAutoSize(true);
        }

        // ── Feuille 2 : Offres (par type / mode de travail) ────────
        $offresSheet = $spreadsheet->createSheet();
        $offresSheet->setTitle('Offres');
        $offresSheet->setCellValue('A1', 'Type de contrat');
        $offresSheet->setCellValue('B1', "Nombre d'offres actives");
        $offresSheet->getStyle('A1:B1')->getFont()->setBold(true);
        $row = 2;
        foreach ($stats['offres_par_type_contrat'] as $type => $total) {
            $offresSheet->setCellValue('A' . $row, ucfirst($type));
            $offresSheet->setCellValue('B' . $row, $total);
            $row++;
        }
        $row += 1;
        $offresSheet->setCellValue('A' . $row, 'Mode de travail');
        $offresSheet->setCellValue('B' . $row, "Nombre d'offres actives");
        $offresSheet->getStyle('A' . $row . ':B' . $row)->getFont()->setBold(true);
        $row++;
        foreach ($stats['offres_par_mode_travail'] as $mode => $total) {
            $offresSheet->setCellValue('A' . $row, ucfirst(str_replace('_', ' ', $mode)));
            $offresSheet->setCellValue('B' . $row, $total);
            $row++;
        }
        foreach (['A', 'B'] as $col) {
            $offresSheet->getColumnDimension($col)->setAutoSize(true);
        }

        // ── Feuille 3 : Candidatures par statut ─────────────────────
        $candidaturesSheet = $spreadsheet->createSheet();
        $candidaturesSheet->setTitle('Candidatures');
        $candidaturesSheet->setCellValue('A1', 'Statut');
        $candidaturesSheet->setCellValue('B1', 'Nombre');
        $candidaturesSheet->getStyle('A1:B1')->getFont()->setBold(true);
        $row = 2;
        foreach ($stats['candidatures_par_statut'] as $statut => $total) {
            $candidaturesSheet->setCellValue('A' . $row, ucfirst(str_replace('_', ' ', $statut)));
            $candidaturesSheet->setCellValue('B' . $row, $total);
            $row++;
        }
        foreach (['A', 'B'] as $col) {
            $candidaturesSheet->getColumnDimension($col)->setAutoSize(true);
        }

        // ── Feuille 4 : Évolution des publications ──────────────────
        $moisSheet = $spreadsheet->createSheet();
        $moisSheet->setTitle('Évolution');
        $moisSheet->setCellValue('A1', 'Mois');
        $moisSheet->setCellValue('B1', 'Offres publiées');
        $moisSheet->getStyle('A1:B1')->getFont()->setBold(true);
        $row = 2;
        foreach ($stats['publications_par_mois'] as $mois => $total) {
            $moisSheet->setCellValue('A' . $row, $mois);
            $moisSheet->setCellValue('B' . $row, $total);
            $row++;
        }
        foreach (['A', 'B'] as $col) {
            $moisSheet->getColumnDimension($col)->setAutoSize(true);
        }

        $spreadsheet->setActiveSheetIndex(0);

        $writer = new Xlsx($spreadsheet);
        $filename = 'statistiques-matchcv-' . (new \DateTimeImmutable())->format('Y-m-d') . '.xlsx';

        $response = new StreamedResponse(function () use ($writer) {
            $writer->save('php://output');
        });
        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
        $response->headers->set('Cache-Control', 'max-age=0');

        return $response;
    }

    private function getProfilEntrepriseOrThrow(): ProfilEntreprise
    {
        /** @var User $user */
        $user = $this->getUser();
        $profil = $user->getProfilEntreprise();

        if (!$profil) {
            throw $this->createNotFoundException('Profil entreprise introuvable.');
        }

        return $profil;
    }
}