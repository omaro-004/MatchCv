<?php
 
namespace App\Controller;
 
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
 
class ConnexionController extends AbstractController
{
    #[Route('/connexion', name: 'app_login', methods: ['GET', 'POST'])]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        // Redirige si déjà connecté
        if ($this->getUser()) {
            return $this->redirectToRoute('app_dashboard_redirect');
        }
 
        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();
 
        return $this->render('connexion.html.twig', [
            'last_username' => $lastUsername,
            'error'         => $error,
        ]);
    }
 
    #[Route('/deconnexion', name: 'app_logout', methods: ['GET'])]
    public function logout(): void
    {
        // Intercepté par le firewall Symfony — ne pas modifier.
        throw new \LogicException('Cette méthode est interceptée par le pare-feu Symfony Security.');
    }
}