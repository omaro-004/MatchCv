<?php
 
namespace App\Controller;
 
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
 
class UserController extends AbstractController
{
    /**
     * Page d'accueil : redirige vers le choix d'inscription.
     */
    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function home(): RedirectResponse
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_dashboard_redirect');
        }
        return $this->redirectToRoute('app_inscription_choice');
    }
 
    /**
     * Redirige vers le dashboard selon le rôle de l'utilisateur connecté.
     */
    #[Route('/dashboard/redirect', name: 'app_dashboard_redirect', methods: ['GET'])]
    public function dashboardRedirect(): Response
    {
        $user = $this->getUser();
 
        if ($user === null) {
            return $this->redirectToRoute('app_login');
        }
 
        if (!method_exists($user, 'getRole')) {
            return new Response('Utilisateur invalide.', Response::HTTP_FORBIDDEN);
        }
 
        return match ($user->getRole()) {
            'admin'     => $this->redirectToRoute('app_admin_dashboard'),
            'entreprise'=> $this->redirectToRoute('app_entreprise_dashboard'),
            'candidat'  => $this->redirectToRoute('app_candidat_dashboard'),
            default     => new Response('Rôle utilisateur non pris en charge.', Response::HTTP_FORBIDDEN),
        };
    }
 
    /**
     * Dashboard candidat — ROLE_CANDIDAT requis (règle RM-R01).
     */
    #[Route('/candidat/dashboard', name: 'app_candidat_dashboard', methods: ['GET'])]
    #[IsGranted('ROLE_CANDIDAT')]
    public function candidatDashboard(): Response
    {
        return $this->render('candidat_dashboard.html.twig');
    }
 
    /**
     * Dashboard entreprise — ROLE_ENTREPRISE requis (règle RM-R01).
     */
    #[Route('/entreprise/dashboard', name: 'app_entreprise_dashboard', methods: ['GET'])]
    #[IsGranted('ROLE_ENTREPRISE')]
    public function entrepriseDashboard(): Response
    {
        return $this->render('entreprise_dashboard.html.twig');
    }
 
    /**
     * Dashboard admin — ROLE_ADMIN requis (règle RM-R01).
     */
    #[Route('/admin/dashboard', name: 'app_admin_dashboard', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function adminDashboard(): Response
    {
        // TODO: Create admin/dashboard.html.twig template
        return $this->render('admin_dashboard.html.twig');
    }
}