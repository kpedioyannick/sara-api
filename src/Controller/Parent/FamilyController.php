<?php

namespace App\Controller\Parent;

use App\Entity\Student;
use App\Repository\StudentRepository;
use App\Repository\ParentUserRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/parent/family')]
class FamilyController extends BaseParentController
{
    public function __construct(
        private StudentRepository $studentRepository,
        private ValidatorInterface $validator,
        private ParentUserRepository $parentUserRepository
    ) {
        parent::__construct($parentUserRepository);
    }

    #[Route('/children', name: 'parent_children_list', methods: ['GET'])]
    public function listChildren(): JsonResponse
    {
        $parent = $this->getParent();
        $family = $parent->getFamily();
        
        if (!$family) {
            return $this->errorResponse('No family found for this parent', 404);
        }

        $students = $family->getStudents();
        $studentsData = array_map(fn($student) => $student->toArray(), $students->toArray());

        return $this->successResponse($studentsData, 'Children retrieved successfully');
    }

    #[Route('/children', name: 'parent_children_create', methods: ['POST'])]
    public function createChild(Request $request): JsonResponse
    {
        try {
            $parent = $this->getParent();
            $family = $parent->getFamily();
            
            if (!$family) {
                return $this->errorResponse('No family found for this parent', 404);
            }

            $data = json_decode($request->getContent(), true);
            
            if (!$data) {
                return $this->errorResponse('Invalid JSON', Response::HTTP_BAD_REQUEST);
            }

            $requiredFields = ['email', 'firstName', 'lastName', 'pseudo', 'class', 'password', 'confirmPassword'];
            foreach ($requiredFields as $field) {
                if (!isset($data[$field]) || empty($data[$field])) {
                    return $this->errorResponse("Field '{$field}' is required", Response::HTTP_BAD_REQUEST);
                }
            }

            // Vérifier que les mots de passe correspondent
            if ($data['password'] !== $data['confirmPassword']) {
                return $this->errorResponse('Passwords do not match', Response::HTTP_BAD_REQUEST);
            }

            $student = Student::createForCoach($data, $family);
            
            $errors = $this->validator->validate($student);
            if (count($errors) > 0) {
                $errorMessages = [];
                foreach ($errors as $error) {
                    $errorMessages[] = $error->getMessage();
                }
                return $this->errorResponse('Validation failed', Response::HTTP_BAD_REQUEST, $errorMessages);
            }

            $this->studentRepository->save($student, true);

            return $this->successResponse($student->toArray(), 'Child created successfully', Response::HTTP_CREATED);

        } catch (\Exception $e) {
            return $this->errorResponse('Child creation failed: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/children/{id}', name: 'parent_children_show', methods: ['GET'])]
    public function showChild(int $id): JsonResponse
    {
        $parent = $this->getParent();
        $family = $parent->getFamily();
        
        if (!$family) {
            return $this->errorResponse('No family found for this parent', 404);
        }

        $student = $this->studentRepository->find($id);
        
        if (!$student) {
            return $this->errorResponse('Child not found', Response::HTTP_NOT_FOUND);
        }

        // Vérifier que l'enfant appartient à la famille du parent
        if ($student->getFamily() !== $family) {
            return $this->errorResponse('Access denied to this child', Response::HTTP_FORBIDDEN);
        }

        return $this->successResponse($student->toArray(), 'Child retrieved successfully');
    }

    #[Route('/children/{id}', name: 'parent_children_update', methods: ['PUT'])]
    public function updateChild(int $id, Request $request): JsonResponse
    {
        try {
            $parent = $this->getParent();
            $family = $parent->getFamily();
            
            if (!$family) {
                return $this->errorResponse('No family found for this parent', 404);
            }

            $student = $this->studentRepository->find($id);
            
            if (!$student) {
                return $this->errorResponse('Child not found', Response::HTTP_NOT_FOUND);
            }

            // Vérifier que l'enfant appartient à la famille du parent
            if ($student->getFamily() !== $family) {
                return $this->errorResponse('Access denied to this child', Response::HTTP_FORBIDDEN);
            }

            $data = json_decode($request->getContent(), true);
            
            if (!$data) {
                return $this->errorResponse('Invalid JSON', Response::HTTP_BAD_REQUEST);
            }

            if (isset($data['firstName'])) {
                $student->setFirstName($data['firstName']);
            }
            if (isset($data['lastName'])) {
                $student->setLastName($data['lastName']);
            }
            if (isset($data['pseudo'])) {
                $student->setPseudo($data['pseudo']);
            }
            if (isset($data['class'])) {
                $student->setClass($data['class']);
            }
            if (isset($data['email'])) {
                $student->setEmail($data['email']);
            }

            $errors = $this->validator->validate($student);
            if (count($errors) > 0) {
                $errorMessages = [];
                foreach ($errors as $error) {
                    $errorMessages[] = $error->getMessage();
                }
                return $this->errorResponse('Validation failed', Response::HTTP_BAD_REQUEST, $errorMessages);
            }

            $this->studentRepository->save($student, true);

            return $this->successResponse($student->toArray(), 'Child updated successfully');

        } catch (\Exception $e) {
            return $this->errorResponse('Child update failed: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/children/{id}', name: 'parent_children_delete', methods: ['DELETE'])]
    public function deleteChild(int $id): JsonResponse
    {
        $parent = $this->getParent();
        $family = $parent->getFamily();
        
        if (!$family) {
            return $this->errorResponse('No family found for this parent', 404);
        }

        $student = $this->studentRepository->find($id);
        
        if (!$student) {
            return $this->errorResponse('Child not found', Response::HTTP_NOT_FOUND);
        }

        // Vérifier que l'enfant appartient à la famille du parent
        if ($student->getFamily() !== $family) {
            return $this->errorResponse('Access denied to this child', Response::HTTP_FORBIDDEN);
        }

        $this->studentRepository->remove($student, true);

        return $this->successResponse(null, 'Child deleted successfully');
    }

    #[Route('/profile', name: 'parent_family_profile', methods: ['GET'])]
    public function familyProfile(): JsonResponse
    {
        $parent = $this->getParent();
        $family = $parent->getFamily();
        
        if (!$family) {
            return $this->errorResponse('No family found for this parent', 404);
        }

        $familyData = $family->toArray();
        $familyData['parent'] = $parent->toArray();
        $familyData['children'] = array_map(fn($student) => $student->toArray(), $family->getStudents()->toArray());

        return $this->successResponse($familyData, 'Family profile retrieved successfully');
    }

    #[Route('/profile', name: 'parent_family_update', methods: ['PUT'])]
    public function updateFamilyProfile(Request $request): JsonResponse
    {
        try {
            $parent = $this->getParent();
            $family = $parent->getFamily();
            
            if (!$family) {
                return $this->errorResponse('No family found for this parent', 404);
            }

            $data = json_decode($request->getContent(), true);
            
            if (!$data) {
                return $this->errorResponse('Invalid JSON', Response::HTTP_BAD_REQUEST);
            }

            if (isset($data['familyIdentifier'])) {
                $family->setFamilyIdentifier($data['familyIdentifier']);
            }

            $this->familyRepository->save($family, true);

            return $this->successResponse($family->toArray(), 'Family profile updated successfully');

        } catch (\Exception $e) {
            return $this->errorResponse('Family profile update failed: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/classes', name: 'parent_family_classes', methods: ['GET'])]
    public function getAvailableClasses(): JsonResponse
    {
        $classes = [
            'CP', 'CE1', 'CE2', 'CM1', 'CM2',
            '6ème', '5ème', '4ème', '3ème',
            '2nde', '1ère', 'Terminale'
        ];

        return $this->successResponse($classes, 'Available classes retrieved successfully');
    }
}
