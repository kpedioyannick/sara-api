<?php

namespace App\Controller\Student;

use App\Repository\TaskRepository;
use App\Repository\PlanningRepository;
use App\Repository\RequestRepository;
use App\Repository\StudentRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/student/dashboard')]
class DashboardController extends BaseStudentController
{
    public function __construct(
        private TaskRepository $taskRepository,
        private PlanningRepository $planningRepository,
        private RequestRepository $requestRepository,
        private StudentRepository $studentRepository
    ) {
        parent::__construct($studentRepository);
    }

    #[Route('', name: 'student_dashboard', methods: ['GET'])]
    public function dashboard(): JsonResponse
    {
        $student = $this->getStudent();
        
        // Objectifs en cours
        $activeObjectives = $student->getObjectives()->filter(fn($obj) => $obj->getStatus() === 'in_progress');
        $activeObjectivesData = array_map(fn($objective) => $objective->toArray(), $activeObjectives->toArray());
        
        // Points actuels et progression
        $currentPoints = $student->getPoints();
        $totalObjectives = $student->getObjectives()->count();
        $completedObjectives = $student->getObjectives()->filter(fn($obj) => $obj->getStatus() === 'completed')->count();
        $progressPercentage = $totalObjectives > 0 ? round(($completedObjectives / $totalObjectives) * 100, 2) : 0;
        
        // Prochains devoirs et événements
        $upcomingTasks = $this->taskRepository->findBy(['assignedTo' => $student, 'status' => 'pending']);
        $upcomingTasksData = array_map(fn($task) => $task->toArray(), $upcomingTasks);
        
        // Prochains événements du planning
        $upcomingEvents = [];
        $studentPlannings = $student->getPlannings();
        foreach ($studentPlannings as $planning) {
            if ($planning->getDate() >= new \DateTimeImmutable()) {
                $upcomingEvents[] = $planning->toArray();
            }
        }
        
        // Trier par date
        usort($upcomingEvents, function($a, $b) {
            return strtotime($a['date']) - strtotime($b['date']);
        });
        
        // Limiter aux 5 prochains événements
        $upcomingEvents = array_slice($upcomingEvents, 0, 5);
        
        // Actions à compléter aujourd'hui
        $today = new \DateTimeImmutable();
        $todayTasks = $this->taskRepository->findBy(['assignedTo' => $student, 'status' => 'pending']);
        $todayTasks = array_filter($todayTasks, function($task) use ($today) {
            return $task->getDueDate() && $task->getDueDate()->format('Y-m-d') === $today->format('Y-m-d');
        });
        $todayTasksData = array_map(fn($task) => $task->toArray(), $todayTasks);
        
        // Demandes récentes
        $recentRequests = $this->requestRepository->findBy(['student' => $student], ['createdAt' => 'DESC'], 5);
        $recentRequestsData = array_map(fn($request) => $request->toArray(), $recentRequests);

        $dashboardData = [
            'student' => $student->toArray(),
            'activeObjectives' => $activeObjectivesData,
            'currentPoints' => $currentPoints,
            'progressPercentage' => $progressPercentage,
            'upcomingTasks' => $upcomingTasksData,
            'upcomingEvents' => $upcomingEvents,
            'todayTasks' => $todayTasksData,
            'recentRequests' => $recentRequestsData,
            'stats' => [
                'totalObjectives' => $totalObjectives,
                'completedObjectives' => $completedObjectives,
                'activeObjectives' => count($activeObjectivesData),
                'pendingTasks' => count($upcomingTasksData),
                'todayTasks' => count($todayTasksData),
                'upcomingEvents' => count($upcomingEvents)
            ]
        ];

        return $this->successResponse($dashboardData, 'Student dashboard retrieved successfully');
    }

    #[Route('/objectives/active', name: 'student_dashboard_active_objectives', methods: ['GET'])]
    public function getActiveObjectives(): JsonResponse
    {
        $student = $this->getStudent();
        
        $activeObjectives = $student->getObjectives()->filter(fn($obj) => $obj->getStatus() === 'in_progress');
        $activeObjectivesData = array_map(fn($objective) => $objective->toArray(), $activeObjectives->toArray());
        
        return $this->successResponse($activeObjectivesData, 'Active objectives retrieved successfully');
    }

    #[Route('/points', name: 'student_dashboard_points', methods: ['GET'])]
    public function getPoints(): JsonResponse
    {
        $student = $this->getStudent();
        
        $pointsData = [
            'currentPoints' => $student->getPoints(),
            'totalObjectives' => $student->getObjectives()->count(),
            'completedObjectives' => $student->getObjectives()->filter(fn($obj) => $obj->getStatus() === 'completed')->count(),
            'progressPercentage' => $student->getObjectives()->count() > 0 ? 
                round(($student->getObjectives()->filter(fn($obj) => $obj->getStatus() === 'completed')->count() / $student->getObjectives()->count()) * 100, 2) : 0
        ];
        
        return $this->successResponse($pointsData, 'Points and progress retrieved successfully');
    }

    #[Route('/upcoming', name: 'student_dashboard_upcoming', methods: ['GET'])]
    public function getUpcoming(): JsonResponse
    {
        $student = $this->getStudent();
        
        // Prochains devoirs
        $upcomingTasks = $this->taskRepository->findBy(['assignedTo' => $student, 'status' => 'pending']);
        $upcomingTasksData = array_map(fn($task) => $task->toArray(), $upcomingTasks);
        
        // Prochains événements
        $upcomingEvents = [];
        $studentPlannings = $student->getPlannings();
        foreach ($studentPlannings as $planning) {
            if ($planning->getDate() >= new \DateTimeImmutable()) {
                $upcomingEvents[] = $planning->toArray();
            }
        }
        
        // Trier par date
        usort($upcomingEvents, function($a, $b) {
            return strtotime($a['date']) - strtotime($b['date']);
        });
        
        $upcomingData = [
            'tasks' => $upcomingTasksData,
            'events' => $upcomingEvents
        ];
        
        return $this->successResponse($upcomingData, 'Upcoming tasks and events retrieved successfully');
    }

    #[Route('/today', name: 'student_dashboard_today', methods: ['GET'])]
    public function getTodayTasks(): JsonResponse
    {
        $student = $this->getStudent();
        
        $today = new \DateTimeImmutable();
        $todayTasks = $this->taskRepository->findBy(['assignedTo' => $student, 'status' => 'pending']);
        $todayTasks = array_filter($todayTasks, function($task) use ($today) {
            return $task->getDueDate() && $task->getDueDate()->format('Y-m-d') === $today->format('Y-m-d');
        });
        $todayTasksData = array_map(fn($task) => $task->toArray(), $todayTasks);
        
        return $this->successResponse($todayTasksData, 'Today tasks retrieved successfully');
    }
}
