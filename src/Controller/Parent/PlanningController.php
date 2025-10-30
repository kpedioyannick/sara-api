<?php

namespace App\Controller\Parent;

use App\Repository\PlanningRepository;
use App\Repository\ParentUserRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/parent/planning')]
class PlanningController extends BaseParentController
{
    public function __construct(
        private PlanningRepository $planningRepository,
        private ParentUserRepository $parentUserRepository
    ) {
        parent::__construct($parentUserRepository);
    }

    #[Route('', name: 'parent_planning_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $parent = $this->getParent();
        $family = $parent->getFamily();
        
        if (!$family) {
            return $this->errorResponse('No family found for this parent', 404);
        }

        $studentId = $request->query->get('student_id');
        $type = $request->query->get('type');
        $date = $request->query->get('date');
        
        $students = $family->getStudents();
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
                if ($date && $planning->getDate()->format('Y-m-d') !== $date) {
                    continue;
                }
                $plannings[] = $planning->toArray();
            }
        }

        return $this->successResponse($plannings, 'Planning retrieved successfully');
    }

    #[Route('/{id}', name: 'parent_planning_show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $parent = $this->getParent();
        $family = $parent->getFamily();
        
        if (!$family) {
            return $this->errorResponse('No family found for this parent', 404);
        }

        $planning = $this->planningRepository->find($id);
        
        if (!$planning) {
            return $this->errorResponse('Planning event not found', Response::HTTP_NOT_FOUND);
        }

        // Vérifier que l'événement appartient à un enfant de la famille
        $student = $planning->getStudent();
        if (!$student || $student->getFamily() !== $family) {
            return $this->errorResponse('Access denied to this planning event', Response::HTTP_FORBIDDEN);
        }

        return $this->successResponse($planning->toArray(), 'Planning event retrieved successfully');
    }
}
