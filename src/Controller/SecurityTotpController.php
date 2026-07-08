<?php

namespace App\Controller;

use App\Entity\User;
use App\Security\TotpService;
use Doctrine\ORM\EntityManagerInterface;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * SecurityTotpController
 *
 * Gère l'onglet "Sécurité" du dashboard candidat : activation/désactivation
 * de la récupération de mot de passe via application d'authentification
 * (Google Authenticator, Authy, etc.).
 */
#[IsGranted('ROLE_CANDIDAT')]
class SecurityTotpController extends AbstractController
{
    #[Route('/candidat/securite/totp', name: 'app_candidat_securite_totp', methods: ['GET'])]
    public function index(): Response
    {
        return $this->redirectToRoute('app_candidat_securite');
    }

    /**
     * Génère un nouveau secret TOTP (stocké temporairement en session, pas
     * encore en base) et renvoie le QR code à scanner.
     */
    #[Route('/candidat/securite/totp/generer', name: 'app_totp_generate', methods: ['POST'])]
    public function generate(Request $request, TotpService $totpService): JsonResponse
    {
        if (!$this->isCsrfTokenValid('totp_generate', $request->headers->get('X-CSRF-Token', ''))) {
            return $this->json(['error' => 'Token CSRF invalide.'], Response::HTTP_FORBIDDEN);
        }

        /** @var User $user */
        $user = $this->getUser();

        $secret = $totpService->generateSecret();
        $request->getSession()->set('totp_pending_secret', $secret);

        $uri = $totpService->getProvisioningUri($secret, $user->getEmail());

        $qrCode = new QrCode($uri, size: 240, margin: 8);

        $dataUri = (new PngWriter())->write($qrCode)->getDataUri();

        return $this->json([
            'qr'     => $dataUri,
            'secret' => $secret, // affiché en solution de secours pour saisie manuelle
        ]);
    }

    /**
     * Vérifie le premier code saisi par l'utilisateur après scan du QR code.
     * Si valide, le secret est définitivement enregistré sur le compte.
     */
    #[Route('/candidat/securite/totp/confirmer', name: 'app_totp_confirm', methods: ['POST'])]
    public function confirm(Request $request, TotpService $totpService, EntityManagerInterface $em): JsonResponse
    {
        if (!$this->isCsrfTokenValid('totp_confirm', $request->headers->get('X-CSRF-Token', ''))) {
            return $this->json(['error' => 'Token CSRF invalide.'], Response::HTTP_FORBIDDEN);
        }

        $secret = $request->getSession()->get('totp_pending_secret');

        if (!$secret) {
            return $this->json(['error' => 'Aucune configuration en cours. Recommencez.'], Response::HTTP_BAD_REQUEST);
        }

        $payload = json_decode($request->getContent(), true);
        $code = (string) ($payload['code'] ?? '');

        if (!$totpService->verify($secret, $code)) {
            return $this->json(['error' => 'Code invalide. Vérifiez l\'heure de votre téléphone et réessayez.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        /** @var User $user */
        $user = $this->getUser();
        $user->setTotpSecret($secret);
        $user->setTotpEnabled(true);
        $em->flush();

        $request->getSession()->remove('totp_pending_secret');

        return $this->json(['success' => true]);
    }

    #[Route('/candidat/securite/totp/desactiver', name: 'app_totp_disable', methods: ['POST'])]
    public function disable(Request $request, EntityManagerInterface $em): JsonResponse
    {
        if (!$this->isCsrfTokenValid('totp_disable', $request->headers->get('X-CSRF-Token', ''))) {
            return $this->json(['error' => 'Token CSRF invalide.'], Response::HTTP_FORBIDDEN);
        }

        /** @var User $user */
        $user = $this->getUser();
        $user->setTotpEnabled(false);
        $user->setTotpSecret(null);
        $em->flush();

        return $this->json(['success' => true]);
    }
}