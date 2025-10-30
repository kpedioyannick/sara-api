<?php

namespace App\Controller\Parent;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use App\Repository\TaskRepository;
use App\Repository\PlanningRepository;
use App\Repository\ParentUserRepository;

#[Route('/api/parent/dashboard')]
class DashboardController extends BaseParentController
{
    public function __construct(
        private TaskRepository $taskRepository,
        private PlanningRepository $planningRepository,
        private ParentUserRepository $parentUserRepository
    ) {
        parent::__construct($parentUserRepository);
    }

    #[Route('', name: 'parent_dashboard', methods: ['GET'])]
    public function dashboard(): JsonResponse
    {
        $parent = $this->getParent();
        $family = $parent->getFamily();
        
        if (!$family) {
            return $this->errorResponse('No family found for this parent', 404);
        }

        $students = $family->getStudents();
        $studentsData = [];
        
        foreach ($students as $student) {
            $studentsData[] = [
                'id' => $student->getId(),
                'firstName' => $student->getFirstName(),
                'lastName' => $student->getLastName(),
                'pseudo' => $student->getPseudo(),
                'class' => $student->getClass(),
                'points' => $student->getPoints(),
                'activeObjectives' => $student->getObjectives()->count(),
                'pendingTasks' => $student->getTasks()->count(),
                'stats' => $student->getStats()
            ];
        }

        // Actions en attente (tâches assignées au parent)
        $assignedTasks = $this->taskRepository->findBy(['assignedTo' => $parent, 'status' => 'pending']);
        $assignedTasksData = array_map(fn($task) => $task->toArray(), $assignedTasks);

        // Prochains événements (planning des enfants)
        $upcomingEvents = [];
        foreach ($students as $student) {
            $studentPlannings = $student->getPlannings();
            foreach ($studentPlannings as $planning) {
                if ($planning->getDate() >= new \DateTimeImmutable()) {
                    $upcomingEvents[] = $planning->toArray();
                }
            }
        }
        
        // Trier par date
        usort($upcomingEvents, function($a, $b) {
            return strtotime($a['date']) - strtotime($b['date']);
        });
        
        // Limiter aux 5 prochains événements
        $upcomingEvents = array_slice($upcomingEvents, 0, 5);

        $dashboardData = [
            'parent' => $parent->toArray(),
            'family' => $family->toArray(),
            'students' => $studentsData,
            'totalStudents' => $students->count(),
            'totalActiveObjectives' => array_sum(array_column($studentsData, 'activeObjectives')),
            'totalPendingTasks' => array_sum(array_column($studentsData, 'pendingTasks')),
            'totalPoints' => array_sum(array_column($studentsData, 'points')),
            'pendingActions' => $assignedTasksData,
            'upcomingEvents' => $upcomingEvents
        ];

        return $this->successResponse($dashboardData, 'Parent dashboard retrieved successfully');
    }

    #[Route('/actions', name: 'parent_dashboard_actions', methods: ['GET'])]
    public function getActions(): JsonResponse
    {
        $parent = $this->getParent();
        
        $assignedTasks = $this->taskRepository->findBy(['assignedTo' => $parent, 'status' => 'pending']);
        $assignedTasksData = array_map(fn($task) => $task->toArray(), $assignedTasks);
        
        return $this->successResponse($assignedTasksData, 'Pending actions retrieved successfully');
    }

    #[Route('/upcoming-events', name: 'parent_dashboard_upcoming_events', methods: ['GET'])]
    public function getUpcomingEvents(): JsonResponse
    {
        $parent = $this->getParent();
        $family = $parent->getFamily();
        
        if (!$family) {
            return $this->errorResponse('No family found for this parent', 404);
        }

        $students = $family->getStudents();
        $upcomingEvents = [];
        
        foreach ($students as $student) {
            $studentPlannings = $student->getPlannings();
            foreach ($studentPlannings as $planning) {
                if ($planning->getDate() >= new \DateTimeImmutable()) {
                    $upcomingEvents[] = $planning->toArray();
                }
            }
        }
        
        // Trier par date
        usort($upcomingEvents, function($a, $b) {
            return strtotime($a['date']) - strtotime($b['date']);
        });
        
        return $this->successResponse($upcomingEvents, 'Upcoming events retrieved successfully');
    }
}
