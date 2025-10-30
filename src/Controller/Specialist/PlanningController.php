<?php

namespace App\Controller\Specialist;

use App\Repository\PlanningRepository;
use App\Repository\SpecialistRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/specialist/planning')]
class PlanningController extends BaseSpecialistController
{
    public function __construct(
        private PlanningRepository $planningRepository,
        private SpecialistRepository $specialistRepository
    ) {
        parent::__construct($specialistRepository);
    }

    #[Route('', name: 'specialist_planning_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $specialist = $this->getSpecialist();
        $students = $specialist->getStudents();
        
        $studentId = $request->query->get('student_id');
        $type = $request->query->get('type');
        
        $plannings = [];
        
        foreach ($students as $student) {
            if ($studentId && $student->getId() != $studentId) {
                continue;
            }
            
            $studentPlannings = $student->getPlannings();
            foreach ($studentPlannings as $planning) {
                if ($type && $planning->getType() !== $type) {
                    continue;
                }
                $plannings[] = $planning->toArray();
            }
        }

        return $this->successResponse($plannings, 'Planning retrieved successfully');
    }

    #[Route('/{id}', name: 'specialist_planning_show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $specialist = $this->getSpecialist();
        $students = $specialist->getStudents();
        
        $planning = $this->planningRepository->find($id);
        
        if (!$planning) {
            return $this->errorResponse('Planning event not found', Response::HTTP_NOT_FOUND);
        }

        // Vérifier que l'événement appartient à un étudiant assigné au spécialiste
        $student = $planning->getStudent();
        if (!$student || !$students->contains($student)) {
            return $this->errorResponse('Access denied to this planning event', Response::HTTP_FORBIDDEN);
        }

        return $this->successResponse($planning->toArray(), 'Planning event retrieved successfully');
    }
}
