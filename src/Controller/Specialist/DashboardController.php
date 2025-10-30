<?php

namespace App\Controller\Specialist;

use App\Repository\RequestRepository;
use App\Repository\TaskRepository;
use App\Repository\PlanningRepository;
use App\Repository\SpecialistRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/specialist/dashboard')]
class DashboardController extends BaseSpecialistController
{
    public function __construct(
        private RequestRepository $requestRepository,
        private TaskRepository $taskRepository,
        private PlanningRepository $planningRepository,
        private SpecialistRepository $specialistRepository
    ) {
        parent::__construct($specialistRepository);
    }

    #[Route('', name: 'specialist_dashboard', methods: ['GET'])]
    public function dashboard(): JsonResponse
    {
        $specialist = $this->getSpecialist();
        
        // Demandes en attente
        $pendingRequests = $this->requestRepository->findBy(['specialist' => $specialist, 'status' => 'pending']);
        $pendingRequestsData = array_map(fn($request) => $request->toArray(), $pendingRequests);
        
        // Demandes en cours
        $inProgressRequests = $this->requestRepository->findBy(['specialist' => $specialist, 'status' => 'in_progress']);
        $inProgressRequestsData = array_map(fn($request) => $request->toArray(), $inProgressRequests);
        
        // Tâches en attente
        $pendingTasks = $this->taskRepository->findBy(['assignedTo' => $specialist, 'status' => 'pending']);
        $pendingTasksData = array_map(fn($task) => $task->toArray(), $pendingTasks);
        
        // Prochaines interventions (planning)
        $upcomingInterventions = [];
        $students = $specialist->getStudents();
        foreach ($students as $student) {
            $studentPlannings = $student->getPlannings();
            foreach ($studentPlannings as $planning) {
                if ($planning->getDate() >= new \DateTimeImmutable()) {
                    $upcomingInterventions[] = $planning->toArray();
                }
            }
        }
        
        // Trier par date
        usort($upcomingInterventions, function($a, $b) {
            return strtotime($a['date']) - strtotime($b['date']);
        });
        
        // Limiter aux 5 prochaines interventions
        $upcomingInterventions = array_slice($upcomingInterventions, 0, 5);
        
        // Demandes urgentes (priorité haute)
        $urgentRequests = $this->requestRepository->findBy(['specialist' => $specialist, 'priority' => 'high']);
        $urgentRequestsData = array_map(fn($request) => $request->toArray(), $urgentRequests);

        $dashboardData = [
            'specialist' => $specialist->toArray(),
            'pendingRequests' => $pendingRequestsData,
            'inProgressRequests' => $inProgressRequestsData,
            'pendingTasks' => $pendingTasksData,
            'upcomingInterventions' => $upcomingInterventions,
            'urgentRequests' => $urgentRequestsData,
            'stats' => [
                'totalPendingRequests' => count($pendingRequestsData),
                'totalInProgressRequests' => count($inProgressRequestsData),
                'totalPendingTasks' => count($pendingTasksData),
                'totalUpcomingInterventions' => count($upcomingInterventions),
                'totalUrgentRequests' => count($urgentRequestsData)
            ]
        ];

        return $this->successResponse($dashboardData, 'Specialist dashboard retrieved successfully');
    }

    #[Route('/requests/pending', name: 'specialist_dashboard_pending_requests', methods: ['GET'])]
    public function getPendingRequests(): JsonResponse
    {
        $specialist = $this->getSpecialist();
        
        $pendingRequests = $this->requestRepository->findBy(['specialist' => $specialist, 'status' => 'pending']);
        $pendingRequestsData = array_map(fn($request) => $request->toArray(), $pendingRequests);
        
        return $this->successResponse($pendingRequestsData, 'Pending requests retrieved successfully');
    }

    #[Route('/requests/in-progress', name: 'specialist_dashboard_in_progress_requests', methods: ['GET'])]
    public function getInProgressRequests(): JsonResponse
    {
        $specialist = $this->getSpecialist();
        
        $inProgressRequests = $this->requestRepository->findBy(['specialist' => $specialist, 'status' => 'in_progress']);
        $inProgressRequestsData = array_map(fn($request) => $request->toArray(), $inProgressRequests);
        
        return $this->successResponse($inProgressRequestsData, 'In progress requests retrieved successfully');
    }

    #[Route('/interventions/upcoming', name: 'specialist_dashboard_upcoming_interventions', methods: ['GET'])]
    public function getUpcomingInterventions(): JsonResponse
    {
        $specialist = $this->getSpecialist();
        
        $upcomingInterventions = [];
        $students = $specialist->getStudents();
        foreach ($students as $student) {
            $studentPlannings = $student->getPlannings();
            foreach ($studentPlannings as $planning) {
                if ($planning->getDate() >= new \DateTimeImmutable()) {
                    $upcomingInterventions[] = $planning->toArray();
                }
            }
        }
        
        // Trier par date
        usort($upcomingInterventions, function($a, $b) {
            return strtotime($a['date']) - strtotime($b['date']);
        });
        
        return $this->successResponse($upcomingInterventions, 'Upcoming interventions retrieved successfully');
    }

    #[Route('/tasks/pending', name: 'specialist_dashboard_pending_tasks', methods: ['GET'])]
    public function getPendingTasks(): JsonResponse
    {
        $specialist = $this->getSpecialist();
        
        $pendingTasks = $this->taskRepository->findBy(['assignedTo' => $specialist, 'status' => 'pending']);
        $pendingTasksData = array_map(fn($task) => $task->toArray(), $pendingTasks);
        
        return $this->successResponse($pendingTasksData, 'Pending tasks retrieved successfully');
    }

    #[Route('/requests/urgent', name: 'specialist_dashboard_urgent_requests', methods: ['GET'])]
    public function getUrgentRequests(): JsonResponse
    {
        $specialist = $this->getSpecialist();
        
        $urgentRequests = $this->requestRepository->findBy(['specialist' => $specialist, 'priority' => 'high']);
        $urgentRequestsData = array_map(fn($request) => $request->toArray(), $urgentRequests);
        
        return $this->successResponse($urgentRequestsData, 'Urgent requests retrieved successfully');
    }
}
