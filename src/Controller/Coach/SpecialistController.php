<?php

namespace App\Controller\Coach;

use App\Entity\Specialist;
use App\Repository\SpecialistRepository;
use App\Repository\StudentRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/coach/specialists')]
class SpecialistController extends BaseCoachController
{
    public function __construct(
        private SpecialistRepository $specialistRepository,
        private StudentRepository $studentRepository,
        private ValidatorInterface $validator
    ) {}

    #[Route('', name: 'coach_specialists_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        try {
            $search = '';
            $specialization = '';
            $status = '';
            
            // Récupérer les paramètres GET
            $search = $request->query->get('search', '');
            $specialization = $request->query->get('specialization', '');
            $status = $request->query->get('status', '');
            
            // Utiliser le repository pour la recherche avec LIKE
            $specialists = $this->specialistRepository->findByWithSearch($search, $specialization, $status);
            $data = array_map(fn($specialist) => $specialist->toArray(), $specialists);
            
            return $this->successResponse($data, 'Coach specialists retrieved successfully');

        } catch (\Exception $e) {
            return $this->errorResponse('Specialist retrieval failed: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/search', name: 'coach_specialists_search', methods: ['POST'])]
    public function search(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            if (!$data) {
                return $this->errorResponse('Invalid JSON', Response::HTTP_BAD_REQUEST);
            }
            
            $search = $data['search'] ?? '';
            $specialization = $data['specialization'] ?? '';
            $status = $data['status'] ?? '';
            
            // Utiliser le repository pour la recherche avec LIKE
            $specialists = $this->specialistRepository->findByWithSearch($search, $specialization, $status);
            $data = array_map(fn($specialist) => $specialist->toArray(), $specialists);
            
            return $this->successResponse($data, 'Coach specialists search completed successfully');

        } catch (\Exception $e) {
            return $this->errorResponse('Specialist search failed: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('', name: 'coach_specialists_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            if (!$data) {
                return $this->errorResponse('Invalid JSON', Response::HTTP_BAD_REQUEST);
            }

            $requiredFields = ['email', 'firstName', 'lastName', 'specializations'];
            foreach ($requiredFields as $field) {
                if (!isset($data[$field]) || empty($data[$field])) {
                    return $this->errorResponse("Field '{$field}' is required", Response::HTTP_BAD_REQUEST);
                }
            }
            
            // Générer un mot de passe par défaut si non fourni
            if (!isset($data['password']) || empty($data['password'])) {
                $data['password'] = 'defaultPassword123';
            }

            // Vérifier si le spécialiste existe déjà
            if ($this->specialistRepository->findOneBy(['email' => $data['email']])) {
                return $this->errorResponse('Specialist already exists', Response::HTTP_CONFLICT);
            }

            $specialist = Specialist::createForCoach($data);

            $errors = $this->validator->validate($specialist);
            if (count($errors) > 0) {
                $errorMessages = [];
                foreach ($errors as $error) {
                    $errorMessages[] = $error->getMessage();
                }
                return $this->errorResponse('Validation failed', Response::HTTP_BAD_REQUEST, $errorMessages);
            }

            $this->specialistRepository->save($specialist, true);

            return $this->successResponse($specialist->toArray(), 'Specialist created successfully', Response::HTTP_CREATED);

        } catch (\Exception $e) {
            return $this->errorResponse('Specialist creation failed: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/{id}', name: 'coach_specialists_show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $specialist = $this->specialistRepository->find($id);
        
        if (!$specialist) {
            return $this->errorResponse('Specialist not found', Response::HTTP_NOT_FOUND);
        }

        return $this->successResponse($specialist->toArray(), 'Specialist retrieved successfully');
    }

    #[Route('/{id}', name: 'coach_specialists_update', methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        try {
            $specialist = $this->specialistRepository->find($id);
            
            if (!$specialist) {
                return $this->errorResponse('Specialist not found', Response::HTTP_NOT_FOUND);
            }

            $data = json_decode($request->getContent(), true);
            
            if (!$data) {
                return $this->errorResponse('Invalid JSON', Response::HTTP_BAD_REQUEST);
            }

            if (isset($data['firstName'])) {
                $specialist->setFirstName($data['firstName']);
            }
            if (isset($data['lastName'])) {
                $specialist->setLastName($data['lastName']);
            }
            if (isset($data['specializations'])) {
                $specialist->setSpecializations($data['specializations']);
            }
            if (isset($data['isActive'])) {
                $specialist->setIsActive($data['isActive']);
            }

            $errors = $this->validator->validate($specialist);
            if (count($errors) > 0) {
                $errorMessages = [];
                foreach ($errors as $error) {
                    $errorMessages[] = $error->getMessage();
                }
                return $this->errorResponse('Validation failed', Response::HTTP_BAD_REQUEST, $errorMessages);
            }

            $this->specialistRepository->save($specialist, true);

            return $this->successResponse($specialist->toArray(), 'Specialist updated successfully');

        } catch (\Exception $e) {
            return $this->errorResponse('Specialist update failed: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/{id}', name: 'coach_specialists_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $specialist = $this->specialistRepository->find($id);
        
        if (!$specialist) {
            return $this->errorResponse('Specialist not found', Response::HTTP_NOT_FOUND);
        }

        $this->specialistRepository->remove($specialist, true);

        return $this->successResponse(null, 'Specialist deleted successfully');
    }

    #[Route('/{id}/toggle-status', name: 'coach_specialists_toggle_status', methods: ['POST'])]
    public function toggleStatus(int $id): JsonResponse
    {
        try {
            $specialist = $this->specialistRepository->find($id);
            
            if (!$specialist) {
                return $this->errorResponse('Specialist not found', Response::HTTP_NOT_FOUND);
            }

            $specialist->setIsActive(!$specialist->isActive());
            $this->specialistRepository->save($specialist, true);

            return $this->successResponse($specialist->toArray(), 'Specialist status toggled successfully');

        } catch (\Exception $e) {
            return $this->errorResponse('Specialist status toggle failed: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/{id}/students', name: 'coach_specialists_students', methods: ['GET'])]
    public function getStudents(int $id): JsonResponse
    {
        $specialist = $this->specialistRepository->find($id);
        
        if (!$specialist) {
            return $this->errorResponse('Specialist not found', Response::HTTP_NOT_FOUND);
        }

        $students = $specialist->getStudents()->toArray();
        $data = array_map(fn($student) => $student->toArray(), $students);

        return $this->successResponse($data, 'Specialist students retrieved successfully');
    }

    #[Route('/{id}/assign-student', name: 'coach_specialists_assign_student', methods: ['POST'])]
    public function assignStudent(int $id, Request $request): JsonResponse
    {
        try {
            $specialist = $this->specialistRepository->find($id);
            
            if (!$specialist) {
                return $this->errorResponse('Specialist not found', Response::HTTP_NOT_FOUND);
            }

            $data = json_decode($request->getContent(), true);
            
            if (!$data || !isset($data['student_id'])) {
                return $this->errorResponse('student_id field is required', Response::HTTP_BAD_REQUEST);
            }

            $student = $this->studentRepository->find($data['student_id']);
            if (!$student) {
                return $this->errorResponse('Student not found', Response::HTTP_NOT_FOUND);
            }

            // Vérifier que l'étudiant appartient à une famille du coach
            $coach = $this->getCoach();
            if ($student->getFamily()->getCoach() !== $coach) {
                return $this->errorResponse('Access denied to this student', Response::HTTP_FORBIDDEN);
            }

            $specialist->addStudent($student);
            $this->specialistRepository->save($specialist, true);

            return $this->successResponse($specialist->toArray(), 'Student assigned to specialist successfully');

        } catch (\Exception $e) {
            return $this->errorResponse('Student assignment failed: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/{id}/remove-student', name: 'coach_specialists_remove_student', methods: ['POST'])]
    public function removeStudent(int $id, Request $request): JsonResponse
    {
        try {
            $specialist = $this->specialistRepository->find($id);
            
            if (!$specialist) {
                return $this->errorResponse('Specialist not found', Response::HTTP_NOT_FOUND);
            }

            $data = json_decode($request->getContent(), true);
            
            if (!$data || !isset($data['student_id'])) {
                return $this->errorResponse('student_id field is required', Response::HTTP_BAD_REQUEST);
            }

            $student = $this->studentRepository->find($data['student_id']);
            if (!$student) {
                return $this->errorResponse('Student not found', Response::HTTP_NOT_FOUND);
            }

            $specialist->removeStudent($student);
            $this->specialistRepository->save($specialist, true);

            return $this->successResponse($specialist->toArray(), 'Student removed from specialist successfully');

        } catch (\Exception $e) {
            return $this->errorResponse('Student removal failed: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
