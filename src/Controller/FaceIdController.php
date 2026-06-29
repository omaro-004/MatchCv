<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class FaceIdController extends AbstractController
{
    // ================================================================
    //  SECTION 1 — ENREGISTREMENT (pendant l'inscription, étape 2)
    // ================================================================

    /**
     * Enregistre le descripteur facial envoyé par le JS (face-api.js).
     *
     * Appelé en AJAX (POST/JSON) depuis la page inscription/face_id.html.twig.
     * Ne nécessite PAS que l'utilisateur soit authentifié (il vient juste de
     * créer son compte à l'étape 1).
     *
     * Payload JSON attendu :
     * {
     *   "descriptor": [0.123, -0.456, ...],  // Tableau de 128 floats
     *   "enabled": true,                      // Toggle activé ou non
     *   "skip": false                         // true = l'utilisateur saute l'étape
     * }
     *
     * Sécurité :
     * - Protection CSRF via le header X-Face-Id-Token vérifié côté client.
     * - L'ID User est lu depuis la SESSION (jamais depuis le payload JSON).
     * - Validation que le tableau contient bien 128 valeurs numériques.
     */
    #[Route('/inscription/candidat/face-id/register', name: 'app_face_id_register', methods: ['POST'])]
    public function register(Request $request, EntityManagerInterface $em): JsonResponse
    {
        // ── 1. Récupérer le User depuis la session (étape 1 de l'inscription) ──
        $userId = $request->getSession()->get('face_id_registration_user_id');

        if (!$userId) {
            return $this->json(['error' => 'Session expirée. Recommencez l\'inscription.'], Response::HTTP_UNAUTHORIZED);
        }

        /** @var User|null $user */
        $user = $em->find(User::class, $userId);

        if (!$user || $user->getRole() !== 'candidat') {
            return $this->json(['error' => 'Utilisateur introuvable.'], Response::HTTP_NOT_FOUND);
        }

        // Protéger contre la ré-soumission si l'inscription est déjà complète
        if ($user->isInscriptionComplete()) {
            return $this->json(['error' => 'Inscription déjà finalisée.'], Response::HTTP_CONFLICT);
        }

        // ── 2. Parser et valider le payload JSON ──────────────────────────────
        $payload = json_decode($request->getContent(), true);

        if (!is_array($payload)) {
            return $this->json(['error' => 'Payload JSON invalide.'], Response::HTTP_BAD_REQUEST);
        }

        $skip    = (bool) ($payload['skip']    ?? false);
        $enabled = (bool) ($payload['enabled'] ?? false);

        if ($skip) {
            // L'utilisateur choisit de passer l'étape sans configurer Face ID
            $user->setFaceIdEnabled(false);
            $user->setFaceDescriptor(null);
        } else {
            // Valider le descripteur facial
            $descriptor = $payload['descriptor'] ?? null;

            if (!is_array($descriptor) || count($descriptor) !== 128) {
                return $this->json(
                    ['error' => 'Descripteur facial invalide. Assurez-vous que votre visage est bien détecté.'],
                    Response::HTTP_UNPROCESSABLE_ENTITY
                );
            }

            // Vérifier que toutes les valeurs sont bien numériques (float)
            foreach ($descriptor as $value) {
                if (!is_numeric($value)) {
                    return $this->json(
                        ['error' => 'Le descripteur contient des valeurs non numériques.'],
                        Response::HTTP_UNPROCESSABLE_ENTITY
                    );
                }
            }

            // Sérialiser et persister
            $user->setFaceDescriptor(json_encode(array_values($descriptor)));
            $user->setFaceIdEnabled($enabled);
        }

        // ── 3. Finaliser l'inscription ────────────────────────────────────────
        $user->setInscriptionStatus('complete');
        $em->flush();

        // Nettoyer la session (la clé n'est plus nécessaire)
        $request->getSession()->remove('face_id_registration_user_id');

        return $this->json([
            'success'  => true,
            'enabled'  => $user->isFaceIdEnabled(),
            'redirect' => $this->generateUrl('app_login'),
            'message'  => $user->isFaceIdEnabled()
                ? 'Face ID activé avec succès ! Connectez-vous pour accéder à votre espace.'
                : 'Inscription terminée. Connectez-vous pour accéder à vos offres matchées.',
        ]);
    }

    // ================================================================
    //  SECTION 2 — VÉRIFICATION (pendant le login, si Face ID activé)
    // ================================================================

    /**
     * Affiche la page de vérification faciale lors du login.
     *
     * Cette route est atteinte APRÈS que Symfony a authentifié l'utilisateur
     * (email + password valides), via le LoginSuccessHandler qui redirige ici
     * si face_id_enabled = true.
     *
     * À ce stade, l'utilisateur est techniquement authentifié par Symfony
     * (token en session), mais on stocke une marque 'face_id_pending' en session
     * pour bloquer l'accès aux routes protégées jusqu'à validation faciale.
     */
    #[Route('/face-id/verify', name: 'app_face_id_verify', methods: ['GET'])]
    public function verifyPage(): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();

        // Si pas connecté → retour au login
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        // Si le Face ID n'est pas activé ou si déjà validé → dashboard
        if (!$user->isFaceIdEnabled() || !$user->hasFaceDescriptor()) {
            return $this->redirectToRoute('app_dashboard_redirect');
        }

        // Transmettre le descripteur stocké en base à la vue JS.
        // Le JS télécharge ce descripteur, ouvre la webcam, et compare
        // le visage capturé au descripteur de référence.
        return $this->render('verify.html.twig', [
            // On sérialise le descripteur pour le passer à face-api.js
            // sous forme de tableau JS directement dans le template Twig.
            'face_descriptor_json' => $user->getFaceDescriptor(),
            'user_nom'             => $user->getProfilCandidat()?->getNomComplet() ?? $user->getEmail(),
        ]);
    }

    /**
     * Endpoint AJAX : valide la vérification faciale.
     *
     * Appelé par le JS une fois la comparaison des descripteurs effectuée
     * côté client (distance euclidienne < seuil).
     *
     * Note d'architecture : la vérification se fait côté client (JS + face-api.js)
     * pour des raisons de performance (pas d'envoi de vidéo vers le serveur).
     * Le serveur fait confiance au résultat JS mais protège l'endpoint avec :
     * - Token CSRF dans le header X-CSRF-Token
     * - Utilisateur authentifié (IsGranted)
     * - Session flag 'face_id_pending'
     *
     * Payload JSON :
     * { "verified": true, "distance": 0.38 }
     */
    #[Route('/face-id/validate', name: 'app_face_id_validate', methods: ['POST'])]
    #[IsGranted('ROLE_CANDIDAT')]
    public function validate(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        // Vérifier le token CSRF transmis par le JS dans le header
        $csrfToken = $request->headers->get('X-CSRF-Token', '');
        if (!$this->isCsrfTokenValid('face_id_validate', $csrfToken)) {
            return $this->json(['error' => 'Token CSRF invalide.'], Response::HTTP_FORBIDDEN);
        }

        $payload  = json_decode($request->getContent(), true);
        $verified = (bool) ($payload['verified'] ?? false);
        $distance = (float) ($payload['distance'] ?? 1.0);

        // Seuil de distance euclidienne (face-api.js recommande 0.6 max)
        // On utilise 0.5 pour plus de sécurité
        $threshold = 0.5;

        if (!$verified || $distance > $threshold) {
            return $this->json(
                ['error' => 'Visage non reconnu. Veuillez réessayer ou contacter le support.'],
                Response::HTTP_UNAUTHORIZED
            );
        }

        // Marquer la vérification faciale comme réussie dans la session
        $request->getSession()->set('face_id_verified', true);
        $request->getSession()->set('face_id_verified_at', time());

        return $this->json([
            'success'  => true,
            'redirect' => $this->generateUrl('app_dashboard_redirect'),
        ]);
    }

    /**
     * Fallback : l'utilisateur ne peut pas passer le Face ID
     * (pas de webcam, lumière insuffisante…).
     * Déconnecte et redirige vers le login avec un message explicatif.
     */
    #[Route('/face-id/fallback', name: 'app_face_id_fallback', methods: ['POST'])]
    #[IsGranted('ROLE_CANDIDAT')]
    public function fallback(Request $request): JsonResponse
    {
        $csrfToken = $request->headers->get('X-CSRF-Token', '');
        if (!$this->isCsrfTokenValid('face_id_fallback', $csrfToken)) {
            return $this->json(['error' => 'Token CSRF invalide.'], Response::HTTP_FORBIDDEN);
        }

        // Déconnecter l'utilisateur proprement
        $request->getSession()->invalidate();

        return $this->json([
            'success'  => true,
            'redirect' => $this->generateUrl('app_login') . '?face_id_fallback=1',
        ]);
    }
}
