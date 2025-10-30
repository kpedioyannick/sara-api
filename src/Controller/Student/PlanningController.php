<?php

namespace App\Controller\Student;

use App\Repository\PlanningRepository;
use App\Repository\StudentRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/student/planning')]
class PlanningController extends BaseStudentController
{
    public function __construct(
        private PlanningRepository $planningRepository,
        private StudentRepository $studentRepository
    ) {
        parent::__construct($studentRepository);
    }

    #[Route('', name: 'student_planning_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $student = $this->getStudent();
        
        $type = $request->query->get('type');
        $date = $request->query->get('date');
        
        $plannings = $student->getPlannings();
        
        if ($type) {
            $plannings = $plannings->filter(fn($planning) => $planning->getType() === $type);
        }
        
        if ($date) {
            $plannings = $plannings->filter(fn($planning) => $planning->getDate()->format('Y-m-d') === $date);
        }
        
        $planningsData = array_map(fn($planning) => $planning->toArray(), $plannings->toArray());

        return $this->successResponse($planningsData, 'Planning retrieved successfully');
    }

    #[Route('/{id}', name: 'student_planning_show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $student = $this->getStudent();
        
        $planning = $this->planningRepository->find($id);
        
        if (!$planning) {
            return $this->errorResponse('Planning event not found', Response::HTTP_NOT_FOUND);
        }

        // Vérifier que l'événement appartient à l'étudiant
        if ($planning->getStudent() !== $student) {
            return $this->errorResponse('Access denied to this planning event', Response::HTTP_FORBIDDEN);
        }

        return $this->successResponse($planning->toArray(), 'Planning event retrieved successfully');
    }
}
