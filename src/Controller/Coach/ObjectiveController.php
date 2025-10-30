<?php

namespace App\Controller\Coach;

use App\Entity\Objective;
use App\Entity\Comment;
use App\Repository\ObjectiveRepository;
use App\Repository\StudentRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/coach/objectives')]
class ObjectiveController extends BaseCoachController
{
    public function __construct(
        private ObjectiveRepository $objectiveRepository,
        private StudentRepository $studentRepository,
        private ValidatorInterface $validator
    ) {}

    #[Route('', name: 'coach_objectives_list', methods: ['POST'])]
    public function list(Request $request): JsonResponse
    {
        $coach = $this->getCoach();
        
        $data = json_decode($request->getContent(), true);
        $studentId = $data['student_id'] ?? null;
        $familyId = $data['family_id'] ?? null;
        $status = $data['status'] ?? null;
        $search = $data['search'] ?? null;

        // Si recherche par texte, utiliser la méthode de recherche
        if ($search) {
            $objectives = $this->objectiveRepository->findByCoachWithSearch($coach, $search, $studentId, $familyId, $status);
        } else {
            // Sinon, utiliser la méthode standard
            $criteria = ['coach' => $coach];
            if ($studentId) {
                $student = $this->studentRepository->find($studentId);
                if ($student) {
                    $criteria['student'] = $student;
                }
            }
            if ($status) {
                $criteria['status'] = $status;
            }
            $objectives = $this->objectiveRepository->findBy($criteria);
        }
        
        $data = [];
        foreach ($objectives as $objective) {
            $data[] = $objective->toArray();
        }

        return $this->successResponse($data, 'Coach objectives retrieved successfully');
    }

    #[Route('/create', name: 'coach_objectives_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            if (!$data) {
                return $this->errorResponse('Invalid JSON', Response::HTTP_BAD_REQUEST);
            }

            $requiredFields = ['title', 'description', 'student_id'];
            foreach ($requiredFields as $field) {
                if (!isset($data[$field]) || empty($data[$field])) {
                    return $this->errorResponse("Field '{$field}' is required", Response::HTTP_BAD_REQUEST);
                }
            }

            $student = $this->studentRepository->find($data['student_id']);
            if (!$student) {
                return $this->errorResponse('Student not found', Response::HTTP_NOT_FOUND);
            }

            $objective = Objective::createForCoach($data, $student, $this->getCoach());

            $errors = $this->validator->validate($objective);
            if (count($errors) > 0) {
                $errorMessages = [];
                foreach ($errors as $error) {
                    $errorMessages[] = $error->getMessage();
                }
                return $this->errorResponse('Validation failed', Response::HTTP_BAD_REQUEST, $errorMessages);
            }

            $this->objectiveRepository->save($objective, true);

            return $this->successResponse($objective->toArray(), 'Objective created successfully', Response::HTTP_CREATED);

        } catch (\Exception $e) {
            return $this->errorResponse('Objective creation failed: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/show', name: 'coach_objectives_show', methods: ['POST'])]
    public function show(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $id = $data['id'] ?? null;
        
        if (!$id) {
            return $this->errorResponse('Objective ID is required', Response::HTTP_BAD_REQUEST);
        }
        
        $objective = $this->objectiveRepository->find($id);
        
        if (!$objective) {
            return $this->errorResponse('Objective not found', Response::HTTP_NOT_FOUND);
        }

        // Vérifier que l'objectif appartient au coach
        $coach = $this->getCoach();
        if ($objective->getCoach() !== $coach) {
            return $this->errorResponse('Access denied to this objective', Response::HTTP_FORBIDDEN);
        }

        return $this->successResponse($objective->toArray(), 'Objective retrieved successfully');
    }

    #[Route('/update', name: 'coach_objectives_update', methods: ['POST'])]
    public function update(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            $id = $data['id'] ?? null;
            
            if (!$id) {
                return $this->errorResponse('Objective ID is required', Response::HTTP_BAD_REQUEST);
            }
            
            $objective = $this->objectiveRepository->find($id);
            
            if (!$objective) {
                return $this->errorResponse('Objective not found', Response::HTTP_NOT_FOUND);
            }

            // Vérifier que l'objectif appartient au coach
            $coach = $this->getCoach();
            if ($objective->getCoach() !== $coach) {
                return $this->errorResponse('Access denied to this objective', Response::HTTP_FORBIDDEN);
            }
            
            if (!$data) {
                return $this->errorResponse('Invalid JSON', Response::HTTP_BAD_REQUEST);
            }

            if (isset($data['title'])) {
                $objective->setTitle($data['title']);
            }
            if (isset($data['description'])) {
                $objective->setDescription($data['description']);
            }
            if (isset($data['status'])) {
                $objective->setStatus($data['status']);
            }
            if (isset($data['priority'])) {
                $objective->setPriority($data['priority']);
            }
            if (isset($data['category'])) {
                $objective->setCategory($data['category']);
            }
            if (isset($data['target_date'])) {
                $objective->setTargetDate(new \DateTime($data['target_date']));
            }

            $errors = $this->validator->validate($objective);
            if (count($errors) > 0) {
                $errorMessages = [];
                foreach ($errors as $error) {
                    $errorMessages[] = $error->getMessage();
                }
                return $this->errorResponse('Validation failed', Response::HTTP_BAD_REQUEST, $errorMessages);
            }

            $this->objectiveRepository->save($objective, true);

            return $this->successResponse($objective->toArray(), 'Objective updated successfully');

        } catch (\Exception $e) {
            return $this->errorResponse('Objective update failed: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/delete', name: 'coach_objectives_delete', methods: ['POST'])]
    public function delete(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $id = $data['id'] ?? null;
        
        if (!$id) {
            return $this->errorResponse('Objective ID is required', Response::HTTP_BAD_REQUEST);
        }
        
        $objective = $this->objectiveRepository->find($id);
        
        if (!$objective) {
            return $this->errorResponse('Objective not found', Response::HTTP_NOT_FOUND);
        }

        // Vérifier que l'objectif appartient au coach
        $coach = $this->getCoach();
        if ($objective->getCoach() !== $coach) {
            return $this->errorResponse('Access denied to this objective', Response::HTTP_FORBIDDEN);
        }

        $this->objectiveRepository->remove($objective, true);

        return $this->successResponse(null, 'Objective deleted successfully');
    }

    #[Route('/add-comment', name: 'coach_objectives_add_comment', methods: ['POST'])]
    public function addComment(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            $objectiveId = $data['objective_id'] ?? null;
            $comment = $data['comment'] ?? null;
            
            if (!$objectiveId || !$comment) {
                return $this->errorResponse('Objective ID and comment are required', Response::HTTP_BAD_REQUEST);
            }
            
            $objective = $this->objectiveRepository->find($objectiveId);
            
            if (!$objective) {
                return $this->errorResponse('Objective not found', Response::HTTP_NOT_FOUND);
            }

            // Vérifier que l'objectif appartient au coach
            $coach = $this->getCoach();
            if ($objective->getCoach() !== $coach) {
                return $this->errorResponse('Access denied to this objective', Response::HTTP_FORBIDDEN);
            }

            // Créer un commentaire
            $commentEntity = Comment::createForCoach([
                'content' => $comment
            ], $coach, $objective);

            $errors = $this->validator->validate($commentEntity);
            if (count($errors) > 0) {
                $errorMessages = [];
                foreach ($errors as $error) {
                    $errorMessages[] = $error->getMessage();
                }
                return $this->errorResponse('Validation failed', Response::HTTP_BAD_REQUEST, $errorMessages);
            }

            $this->objectiveRepository->getEntityManager()->persist($commentEntity);
            $this->objectiveRepository->getEntityManager()->flush();

            return $this->successResponse($commentEntity->toArray(), 'Comment added successfully', Response::HTTP_CREATED);

        } catch (\Exception $e) {
            return $this->errorResponse('Comment creation failed: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/comments', name: 'coach_objectives_comments', methods: ['POST'])]
    public function getComments(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            $objectiveId = $data['objective_id'] ?? null;
            
            if (!$objectiveId) {
                return $this->errorResponse('Objective ID is required', Response::HTTP_BAD_REQUEST);
            }
            
            $objective = $this->objectiveRepository->find($objectiveId);
            
            if (!$objective) {
                return $this->errorResponse('Objective not found', Response::HTTP_NOT_FOUND);
            }

            // Vérifier que l'objectif appartient au coach
            $coach = $this->getCoach();
            if ($objective->getCoach() !== $coach) {
                return $this->errorResponse('Access denied to this objective', Response::HTTP_FORBIDDEN);
            }

            $comments = $objective->getComments();
            $data = [];
            foreach ($comments as $comment) {
                $data[] = $comment->toArray();
            }

            return $this->successResponse($data, 'Comments retrieved successfully');

        } catch (\Exception $e) {
            return $this->errorResponse('Comments retrieval failed: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}