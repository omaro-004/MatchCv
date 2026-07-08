<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\ChangePasswordType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * SecurityCandidatController
 *
 * Onglet "Sécurité" du dashboard candidat. Deux fonctionnalités :
 *
 *  1. Changement de mot de passe (utilisateur déjà connecté) :
 *     exige mot de passe actuel + nouveau + confirmation.
 *     Différent du flux PasswordController (mot de passe oublié / token),
 *     qui reste inchangé et sert quand l'utilisateur n'est PAS connecté.
 *
 *  2. Activation / désactivation du Face ID depuis les paramètres :
 *     - Activation : capture webcam (même logique que
 *       FaceIdController::register() lors de l'inscription), puis
 *       enregistrement du descripteur + passage à faceIdEnabled = true.
 *     - Désactivation : faceIdEnabled = false, descripteur effacé
 *       (une réactivation future exigera une nouvelle capture).
 *
 * Toutes ces routes sont sous /candidat, donc déjà protégées par
 * `{ path: ^/candidat, roles: ROLE_CANDIDAT }` dans security.yaml —
 * aucune modification de security.yaml n'est nécessaire.
 */
#[IsGranted('ROLE_CANDIDAT')]
class SecurityCandidatController extends AbstractController
{
    #[Route('/candidat/securite', name: 'app_candidat_securite', methods: ['GET'])]
    public function index(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $form = $this->createForm(ChangePasswordType::class);

        return $this->render('candidat/securite.html.twig', [
            'form' => $form,
            'face_id_enabled' => $user->isFaceIdEnabled(),
            'totp_enabled' => $user->isTotpEnabled(),
        ]);
    }

    /**
     * Traitement du formulaire de changement de mot de passe.
     */
    #[Route('/candidat/securite/mot-de-passe', name: 'app_candidat_change_password', methods: ['POST'])]
    public function changePassword(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        $form = $this->createForm(ChangePasswordType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $currentPassword = (string) $form->get('currentPassword')->getData();
            $newPassword = (string) $form->get('newPassword')->getData();

            if (!$passwordHasher->isPasswordValid($user, $currentPassword)) {
                $this->addFlash('error', 'Le mot de passe actuel est incorrect.');

                return $this->redirectToRoute('app_candidat_securite');
            }

            $user->setPassword($passwordHasher->hashPassword($user, $newPassword));
            $entityManager->flush();

            $this->addFlash('success', 'Votre mot de passe a été modifié avec succès.');

            return $this->redirectToRoute('app_candidat_securite');
        }

        // Formulaire invalide (ex: mots de passe différents, trop court...)
        return $this->render('candidat/securite.html.twig', [
            'form' => $form,
            'face_id_enabled' => $user->isFaceIdEnabled(),
            'totp_enabled' => $user->isTotpEnabled(),
        ]);
    }

    /**
     * Active le Face ID depuis les paramètres de sécurité.
     * Appelé en AJAX après capture webcam réussie côté client (face-api.js).
     *
     * Payload JSON attendu : { "descriptor": [0.123, ...] } (128 floats)
     */
    #[Route('/candidat/securite/face-id/activer', name: 'app_candidat_face_id_enable', methods: ['POST'])]
    public function enableFaceId(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $csrfToken = $request->headers->get('X-CSRF-Token', '');
        if (!$this->isCsrfTokenValid('face_id_settings', $csrfToken)) {
            return $this->json(['error' => 'Token CSRF invalide.'], Response::HTTP_FORBIDDEN);
        }

        $payload = json_decode($request->getContent(), true);
        $descriptor = is_array($payload) ? ($payload['descriptor'] ?? null) : null;

        if (!is_array($descriptor) || count($descriptor) !== 128) {
            return $this->json(
                ['error' => 'Descripteur facial invalide. Assurez-vous que votre visage est bien détecté.'],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        foreach ($descriptor as $value) {
            if (!is_numeric($value)) {
                return $this->json(
                    ['error' => 'Le descripteur contient des valeurs non numériques.'],
                    Response::HTTP_UNPROCESSABLE_ENTITY
                );
            }
        }

        $user->setFaceDescriptor(json_encode(array_values($descriptor)));
        $user->setFaceIdEnabled(true);
        $entityManager->flush();

        // On marque la session comme déjà vérifiée pour ne pas forcer une
        // re-vérification immédiate juste après l'activation (l'utilisateur
        // vient de prouver son identité en capturant son visage à l'instant).
        // La vérification sera de nouveau exigée à la PROCHAINE connexion
        // (LoginSuccessHandler réinitialise systématiquement ce flag).
        $request->getSession()->set('face_id_verified', true);
        $request->getSession()->set('face_id_verified_at', time());

        return $this->json([
            'success' => true,
            'message' => 'Face ID activé avec succès. Il sera requis à chaque prochaine connexion.',
        ]);
    }

    /**
     * Désactive le Face ID. Le descripteur est effacé : une réactivation
     * future nécessitera une nouvelle capture (pas de réutilisation d'un
     * ancien descripteur potentiellement obsolète).
     */
    #[Route('/candidat/securite/face-id/desactiver', name: 'app_candidat_face_id_disable', methods: ['POST'])]
    public function disableFaceId(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $csrfToken = $request->headers->get('X-CSRF-Token', '');
        if (!$this->isCsrfTokenValid('face_id_settings', $csrfToken)) {
            return $this->json(['error' => 'Token CSRF invalide.'], Response::HTTP_FORBIDDEN);
        }

        $user->setFaceIdEnabled(false);
        $user->setFaceDescriptor(null);
        $entityManager->flush();

        $request->getSession()->remove('face_id_verified');
        $request->getSession()->remove('face_id_verified_at');

        return $this->json([
            'success' => true,
            'message' => 'Face ID désactivé. Vous vous connecterez désormais uniquement avec votre mot de passe.',
        ]);
    }
}