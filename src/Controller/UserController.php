<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class UserController extends AbstractController
{
    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function home(): RedirectResponse
    {
        return $this->redirectToRoute('app_inscription_choice');
    }

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
            'admin' => new RedirectResponse('/admin/dashboard'),
            'entreprise' => new RedirectResponse('/entreprise/dashboard'),
            'candidat' => new RedirectResponse('/candidat/dashboard'),
            default => new Response('Rôle utilisateur non pris en charge.', Response::HTTP_FORBIDDEN),
        };
    }
}
