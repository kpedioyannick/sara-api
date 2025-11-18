<?php

namespace App\Controller;

use App\Entity\Coach;
use App\Entity\ParentUser;
use App\Entity\Specialist;
use App\Entity\Student;
use App\Repository\FamilyRepository;
use App\Repository\ObjectiveRepository;
use App\Repository\RequestRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class DashboardController extends AbstractController
{
    public function __construct(
        private readonly FamilyRepository $familyRepository,
        private readonly ObjectiveRepository $objectiveRepository,
        private readonly RequestRepository $requestRepository
    ) {
    }

    #[Route('/dashboard', name: 'app_dashboard')]
    #[IsGranted('ROLE_USER')]
    public function index(): Response
    {
        $user = $this->getUser();

        // Si pas d'utilisateur, le #[IsGranted] redirigera automatiquement
        // Ne pas faire de redirection manuelle pour éviter les boucles
        if (!$user) {
            // Cette ligne ne devrait jamais être atteinte grâce à #[IsGranted]
            throw new \RuntimeException('Utilisateur non authentifié');
        }

        // Rediriger selon le rôle seulement si c'est un Coach
        // Ne pas rediriger les autres rôles pour éviter les boucles
        if ($user instanceof Coach) {
            return $this->redirectToRoute('admin_dashboard');
        }

        // Par défaut, afficher le dashboard générique
        return $this->render('tailadmin/pages/dashboard.html.twig', [
            'pageTitle' => 'Tableau de bord',
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => $this->generateUrl('app_dashboard')],
            ],
            'user' => $user,
        ]);
    }

    #[Route('/admin/dashboard', name: 'admin_dashboard')]
    #[IsGranted('ROLE_COACH')]
    public function adminDashboard(): Response
    {
        $user = $this->getUser();

        // Si pas d'utilisateur ou pas un Coach, le #[IsGranted] redirigera automatiquement
        // Ne pas faire de redirection manuelle pour éviter les boucles
        if (!$user || !$user instanceof Coach) {
            // Cette ligne ne devrait jamais être atteinte grâce à #[IsGranted]
            throw new \RuntimeException('Accès non autorisé');
        }

        // Statistiques pour le coach connecté
        $stats = [
            'totalFamilies' => $this->familyRepository->countActiveByCoach($user),
            'totalObjectives' => $this->objectiveRepository->countByCoach($user),
            'pendingRequests' => $this->requestRepository->countByCoachAndStatus($user, 'pending'),
            'activeRequests' => $this->requestRepository->countByCoachAndStatus($user, 'in_progress'),
        ];

        return $this->render('tailadmin/pages/dashboard.html.twig', [
            'pageTitle' => 'Tableau de bord - Coach',
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => $this->generateUrl('admin_dashboard')],
            ],
            'user' => $user,
            'stats' => $stats,
            'role' => 'coach',
        ]);
    }
}

