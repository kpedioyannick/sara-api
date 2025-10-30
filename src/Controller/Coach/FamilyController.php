<?php

namespace App\Controller\Coach;

use App\Entity\Family;
use App\Entity\Student;
use App\Entity\ParentUser;
use App\Repository\FamilyRepository;
use App\Repository\StudentRepository;
use App\Repository\ParentUserRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/coach/families')]
class FamilyController extends BaseCoachController
{
    public function __construct(
        private FamilyRepository $familyRepository,
        private StudentRepository $studentRepository,
        private ParentUserRepository $parentUserRepository,
        private ValidatorInterface $validator
    ) {}

    #[Route('/list', name: 'coach_families_list', methods: ['GET', 'POST'])]
    public function list(Request $request): JsonResponse
    {
        try {
            $coach = $this->getCoach();
            $data = json_decode($request->getContent(), true);
            $search = $data['search'] ?? '';
            $status = $data['status'] ?? null;
            // Utiliser le repository pour la recherche avec LIKE
            $families = $this->familyRepository->findByCoachWithSearch($coach, $search, $status);
            $data = array_map(fn($family) => $family->toArray(), $families);
            
            return $this->successResponse($data, 'Coach families retrieved successfully');

        } catch (\Exception $e) {
            return $this->errorResponse('Family retrieval failed: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }



    #[Route('', name: 'coach_families_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            if (!$data) {
                return $this->errorResponse('Invalid JSON', Response::HTTP_BAD_REQUEST);
            }

            $requiredFields = ['parent', 'children'];
            foreach ($requiredFields as $field) {
                if (!isset($data[$field])) {
                    return $this->errorResponse("Field '{$field}' is required", Response::HTTP_BAD_REQUEST);
                }
            }

            // Créer la famille
            $family = Family::createForCoach($data, $this->getCoach());
            $this->familyRepository->save($family, true);

            // Créer le parent
            $parent = ParentUser::createForCoach($data['parent'], $family);
            $this->parentUserRepository->save($parent, true);

            // Créer les étudiants
            foreach ($data['children'] as $studentData) {
                $student = Student::createForCoach($studentData, $family);
                $this->studentRepository->save($student, true);
            }

            return $this->successResponse($family->toArray(), 'Family created successfully', Response::HTTP_CREATED);

        } catch (\Exception $e) {
            return $this->errorResponse('Family creation failed: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/{id}', name: 'coach_families_show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $family = $this->familyRepository->find($id);
        
        if (!$family) {
            return $this->errorResponse('Family not found', Response::HTTP_NOT_FOUND);
        }

        // Vérifier que la famille appartient au coach
        $coach = $this->getCoach();
        if ($family->getCoach() !== $coach) {
            return $this->errorResponse('Access denied to this family', Response::HTTP_FORBIDDEN);
        }

        return $this->successResponse($family->toArray(), 'Family retrieved successfully');
    }

    #[Route('/{id}', name: 'coach_families_update', methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        try {
            $family = $this->familyRepository->find($id);
            
            if (!$family) {
                return $this->errorResponse('Family not found', Response::HTTP_NOT_FOUND);
            }

            // Vérifier que la famille appartient au coach
            $coach = $this->getCoach();
            if ($family->getCoach() !== $coach) {
                return $this->errorResponse('Access denied to this family', Response::HTTP_FORBIDDEN);
            }

            $data = json_decode($request->getContent(), true);
            
            if (!$data) {
                return $this->errorResponse('Invalid JSON', Response::HTTP_BAD_REQUEST);
            }

            if (isset($data['isActive'])) {
                $family->setIsActive($data['isActive']);
            }

            $this->familyRepository->save($family, true);

            return $this->successResponse($family->toArray(), 'Family updated successfully');

        } catch (\Exception $e) {
            return $this->errorResponse('Family update failed: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/{id}', name: 'coach_families_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $family = $this->familyRepository->find($id);
        
        if (!$family) {
            return $this->errorResponse('Family not found', Response::HTTP_NOT_FOUND);
        }

        // Vérifier que la famille appartient au coach
        $coach = $this->getCoach();
        if ($family->getCoach() !== $coach) {
            return $this->errorResponse('Access denied to this family', Response::HTTP_FORBIDDEN);
        }

        $this->familyRepository->remove($family, true);

        return $this->successResponse(null, 'Family deleted successfully');
    }

    #[Route('/{id}/children', name: 'coach_families_add_child', methods: ['POST'])]
    public function addChild(int $id, Request $request): JsonResponse
    {
        try {
            $family = $this->familyRepository->find($id);
            
            if (!$family) {
                return $this->errorResponse('Family not found', Response::HTTP_NOT_FOUND);
            }

            // Vérifier que la famille appartient au coach
            $coach = $this->getCoach();
            if ($family->getCoach() !== $coach) {
                return $this->errorResponse('Access denied to this family', Response::HTTP_FORBIDDEN);
            }

            $data = json_decode($request->getContent(), true);
            
            if (!$data) {
                return $this->errorResponse('Invalid JSON', Response::HTTP_BAD_REQUEST);
            }

            $requiredFields = ['pseudo', 'class', 'password'];
            foreach ($requiredFields as $field) {
                if (!isset($data[$field]) || empty($data[$field])) {
                    return $this->errorResponse("Field '{$field}' is required", Response::HTTP_BAD_REQUEST);
                }
            }

            $student = Student::createForCoach($data, $family);
            
            $this->studentRepository->save($student, true);

            return $this->successResponse($student->toArray(), 'Child added successfully', Response::HTTP_CREATED);

        } catch (\Exception $e) {
            return $this->errorResponse('Child addition failed: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/{id}/activate', name: 'coach_families_activate', methods: ['PUT'])]
    public function activate(int $id): JsonResponse
    {
        try {
            $family = $this->familyRepository->find($id);
            
            if (!$family) {
                return $this->errorResponse('Family not found', Response::HTTP_NOT_FOUND);
            }

            // Vérifier que la famille appartient au coach
            $coach = $this->getCoach();
            if ($family->getCoach() !== $coach) {
                return $this->errorResponse('Access denied to this family', Response::HTTP_FORBIDDEN);
            }

            $family->setIsActive(true);
            $this->familyRepository->save($family, true);

            return $this->successResponse([
                'id' => $family->getId(),
                'isActive' => $family->isActive(),
                'familyIdentifier' => $family->getFamilyIdentifier()
            ], 'Family activated successfully');

        } catch (\Exception $e) {
            return $this->errorResponse('Family activation failed: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/{id}/deactivate', name: 'coach_families_deactivate', methods: ['PUT'])]
    public function deactivate(int $id): JsonResponse
    {
        try {
            $family = $this->familyRepository->find($id);
            
            if (!$family) {
                return $this->errorResponse('Family not found', Response::HTTP_NOT_FOUND);
            }

            // Vérifier que la famille appartient au coach
            $coach = $this->getCoach();
            if ($family->getCoach() !== $coach) {
                return $this->errorResponse('Access denied to this family', Response::HTTP_FORBIDDEN);
            }

            $family->setIsActive(false);
            $this->familyRepository->save($family, true);

            return $this->successResponse([
                'id' => $family->getId(),
                'isActive' => $family->isActive(),
                'familyIdentifier' => $family->getFamilyIdentifier()
            ], 'Family deactivated successfully');

        } catch (\Exception $e) {
            return $this->errorResponse('Family deactivation failed: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/{id}/toggle', name: 'coach_families_toggle', methods: ['PUT'])]
    public function toggle(int $id): JsonResponse
    {
        try {
            $family = $this->familyRepository->find($id);
            
            if (!$family) {
                return $this->errorResponse('Family not found', Response::HTTP_NOT_FOUND);
            }

            // Vérifier que la famille appartient au coach
            $coach = $this->getCoach();
            if ($family->getCoach() !== $coach) {
                return $this->errorResponse('Access denied to this family', Response::HTTP_FORBIDDEN);
            }

            $family->setIsActive(!$family->isActive());
            $this->familyRepository->save($family, true);

            $action = $family->isActive() ? 'activated' : 'deactivated';

            return $this->successResponse([
                'id' => $family->getId(),
                'isActive' => $family->isActive(),
                'familyIdentifier' => $family->getFamilyIdentifier()
            ], "Family {$action} successfully");

        } catch (\Exception $e) {
            return $this->errorResponse('Family toggle failed: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/{family_id}/children/{child_id}/activate', name: 'coach_families_children_activate', methods: ['PUT'])]
    public function activateChild(int $family_id, int $child_id): JsonResponse
    {
        try {
            $family = $this->familyRepository->find($family_id);
            
            if (!$family) {
                return $this->errorResponse('Family not found', Response::HTTP_NOT_FOUND);
            }

            // Vérifier que la famille appartient au coach
            $coach = $this->getCoach();
            if ($family->getCoach() !== $coach) {
                return $this->errorResponse('Access denied to this family', Response::HTTP_FORBIDDEN);
            }

            $student = $this->studentRepository->find($child_id);
            
            if (!$student) {
                return $this->errorResponse('Child not found', Response::HTTP_NOT_FOUND);
            }

            // Vérifier que l'enfant appartient à cette famille
            if ($student->getFamily() !== $family) {
                return $this->errorResponse('Child does not belong to this family', Response::HTTP_FORBIDDEN);
            }

            $student->setIsActive(true);
            $this->studentRepository->save($student, true);

            return $this->successResponse([
                'id' => $student->getId(),
                'isActive' => $student->isActive(),
                'pseudo' => $student->getPseudo(),
                'email' => $student->getEmail()
            ], 'Child activated successfully');

        } catch (\Exception $e) {
            return $this->errorResponse('Child activation failed: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/{family_id}/children/{child_id}/deactivate', name: 'coach_families_children_deactivate', methods: ['PUT'])]
    public function deactivateChild(int $family_id, int $child_id): JsonResponse
    {
        try {
            $family = $this->familyRepository->find($family_id);
            
            if (!$family) {
                return $this->errorResponse('Family not found', Response::HTTP_NOT_FOUND);
            }

            // Vérifier que la famille appartient au coach
            $coach = $this->getCoach();
            if ($family->getCoach() !== $coach) {
                return $this->errorResponse('Access denied to this family', Response::HTTP_FORBIDDEN);
            }

            $student = $this->studentRepository->find($child_id);
            
            if (!$student) {
                return $this->errorResponse('Child not found', Response::HTTP_NOT_FOUND);
            }

            // Vérifier que l'enfant appartient à cette famille
            if ($student->getFamily() !== $family) {
                return $this->errorResponse('Child does not belong to this family', Response::HTTP_FORBIDDEN);
            }

            $student->setIsActive(false);
            $this->studentRepository->save($student, true);

            return $this->successResponse([
                'id' => $student->getId(),
                'isActive' => $student->isActive(),
                'pseudo' => $student->getPseudo(),
                'email' => $student->getEmail()
            ], 'Child deactivated successfully');

        } catch (\Exception $e) {
            return $this->errorResponse('Child deactivation failed: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/{family_id}/children/{child_id}/toggle', name: 'coach_families_children_toggle', methods: ['PUT'])]
    public function toggleChild(int $family_id, int $child_id): JsonResponse
    {
        try {
            $family = $this->familyRepository->find($family_id);
            
            if (!$family) {
                return $this->errorResponse('Family not found', Response::HTTP_NOT_FOUND);
            }

            // Vérifier que la famille appartient au coach
            $coach = $this->getCoach();
            if ($family->getCoach() !== $coach) {
                return $this->errorResponse('Access denied to this family', Response::HTTP_FORBIDDEN);
            }

            $student = $this->studentRepository->find($child_id);
            
            if (!$student) {
                return $this->errorResponse('Child not found', Response::HTTP_NOT_FOUND);
            }

            // Vérifier que l'enfant appartient à cette famille
            if ($student->getFamily() !== $family) {
                return $this->errorResponse('Child does not belong to this family', Response::HTTP_FORBIDDEN);
            }

            $student->setIsActive(!$student->isActive());
            $this->studentRepository->save($student, true);

            $action = $student->isActive() ? 'activated' : 'deactivated';

            return $this->successResponse([
                'id' => $student->getId(),
                'isActive' => $student->isActive(),
                'pseudo' => $student->getPseudo(),
                'email' => $student->getEmail()
            ], "Child {$action} successfully");

        } catch (\Exception $e) {
            return $this->errorResponse('Child toggle failed: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}