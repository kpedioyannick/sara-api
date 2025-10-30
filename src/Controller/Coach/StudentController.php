<?php

namespace App\Controller\Coach;

use App\Entity\Student;
use App\Repository\StudentRepository;
use App\Repository\FamilyRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/coach/students')]
class StudentController extends BaseCoachController
{
    public function __construct(
        private StudentRepository $studentRepository,
        private FamilyRepository $familyRepository
    ) {}

    #[Route('', name: 'coach_students_list', methods: ['GET', 'POST'])]
    public function list(Request $request): JsonResponse
    {
        try {
            $coach = $this->getCoach();
            $search = '';
            
            // Récupérer les paramètres selon la méthode HTTP
            if ($request->isMethod('POST')) {
                $data = json_decode($request->getContent(), true);
                if (!$data) {
                    return $this->errorResponse('Invalid JSON', Response::HTTP_BAD_REQUEST);
                }
                $search = $data['search'] ?? '';
                $excludeSpecialistId = $data['exclude_specialist_id'] ?? '';
            } 
            // Utiliser le repository pour la recherche avec exclusion
            $students = $this->studentRepository->findByCoachWithSearch($coach, $search, '', '', '', '', $excludeSpecialistId);
            
            $data = array_map(fn($student) => $student->toArray(), $students);
            
            return $this->successResponse($data, 'Coach students retrieved successfully');

        } catch (\Exception $e) {
            return $this->errorResponse('Student retrieval failed: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

}