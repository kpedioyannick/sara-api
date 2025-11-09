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

        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        // Rediriger selon le rôle
        if ($user instanceof Coach) {
            return $this->redirectToRoute('admin_dashboard');
        }
        // Pour les autres rôles, afficher le dashboard générique (pas de redirection pour éviter les boucles)

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

        if (!$user instanceof Coach) {
            return $this->redirectToRoute('app_login');
        }

        // Statistiques pour le coach
        $stats = [
            'totalFamilies' => $this->familyRepository->count(['isActive' => true]),
            'totalObjectives' => $this->objectiveRepository->count([]),
            'pendingRequests' => $this->requestRepository->count(['status' => 'pending']),
            'activeRequests' => $this->requestRepository->count(['status' => 'in_progress']),
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

