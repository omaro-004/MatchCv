<?php
 
namespace App\Controller;
 
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
 
class PasswordController extends AbstractController
{
    /**
     * Formulaire de demande de réinitialisation de mot de passe.
     * Envoie un lien valable 24h à l'email saisi (règle RM-U04).
     */
    #[Route('/mot-de-passe-oublie', name: 'app_forgot_password', methods: ['GET', 'POST'])]
    public function forgotPassword(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            // TODO : implémenter l'envoi de l'email de réinitialisation
            // via symfonycasts/reset-password-bundle ou un service mailer custom.
            $this->addFlash('success', 'Si votre adresse email est enregistrée, vous recevrez un lien de réinitialisation sous peu.');
            return $this->redirectToRoute('app_login');
        }
 
        return $this->render('forgot_password.html.twig');
    }
 
    /**
     * Formulaire de saisie du nouveau mot de passe via token.
     * Le token est à usage unique et expire après 24h (règle RM-U04).
     */
    #[Route('/reset-password/{token}', name: 'app_reset_password', methods: ['GET', 'POST'])]
    public function resetPassword(Request $request, string $token): Response
    {
        // TODO : valider le token (hash SHA-256, durée, usage unique — règle RM-U04)
        // et appliquer le nouveau mot de passe haché bcrypt (règle RM-U02).
 
        if ($request->isMethod('POST')) {
            $this->addFlash('success', 'Votre mot de passe a été réinitialisé avec succès. Vous pouvez vous connecter.');
            return $this->redirectToRoute('app_login');
        }
 
        return $this->render('reset_password.html.twig', [
            'token' => $token,
        ]);
    }
}