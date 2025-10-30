<?php

namespace App\Controller\Parent;

use App\Entity\Objective;
use App\Entity\Comment;
use App\Repository\ObjectiveRepository;
use App\Repository\StudentRepository;
use App\Repository\CommentRepository;
use App\Repository\ParentUserRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/parent/objectives')]
class ObjectiveController extends BaseParentController
{
    public function __construct(
        private ObjectiveRepository $objectiveRepository,
        private StudentRepository $studentRepository,
        private CommentRepository $commentRepository,
        private ValidatorInterface $validator,
        private ParentUserRepository $parentUserRepository
    ) {
        parent::__construct($parentUserRepository);
    }

    #[Route('', name: 'parent_objectives_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $parent = $this->getParent();
        $family = $parent->getFamily();
        
        if (!$family) {
            return $this->errorResponse('No family found for this parent', 404);
        }

        $studentId = $request->query->get('student_id');
        $status = $request->query->get('status');
        
        $students = $family->getStudents();
        $objectives = [];
        
        foreach ($students as $student) {
            if ($studentId && $student->getId() != $studentId) {
                continue;
            }
            
            $studentObjectives = $student->getObjectives();
            foreach ($studentObjectives as $objective) {
                if ($status && $objective->getStatus() !== $status) {
                    continue;
                }
                $objectives[] = $objective->toArray();
            }
        }

        return $this->successResponse($objectives, 'Objectives retrieved successfully');
    }

    #[Route('', name: 'parent_objectives_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
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

            $requiredFields = ['title', 'description', 'student_id'];
            foreach ($requiredFields as $field) {
                if (!isset($data[$field]) || empty($data[$field])) {
                    return $this->errorResponse("Field '{$field}' is required", Response::HTTP_BAD_REQUEST);
                }
            }

            // Vérifier que l'étudiant appartient à la famille du parent
            $student = $this->studentRepository->find($data['student_id']);
            if (!$student || $student->getFamily() !== $family) {
                return $this->errorResponse('Student not found or access denied', Response::HTTP_FORBIDDEN);
            }

            // Récupérer le coach de la famille
            $coach = $family->getCoach();
            if (!$coach) {
                return $this->errorResponse('No coach assigned to this family', Response::HTTP_BAD_REQUEST);
            }

            $objective = Objective::createForCoach($data, $student, $coach);

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

    #[Route('/{id}', name: 'parent_objectives_show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $parent = $this->getParent();
        $family = $parent->getFamily();
        
        if (!$family) {
            return $this->errorResponse('No family found for this parent', 404);
        }

        $objective = $this->objectiveRepository->find($id);
        
        if (!$objective) {
            return $this->errorResponse('Objective not found', Response::HTTP_NOT_FOUND);
        }

        // Vérifier que l'objectif appartient à un enfant de la famille
        $student = $objective->getStudent();
        if (!$student || $student->getFamily() !== $family) {
            return $this->errorResponse('Access denied to this objective', Response::HTTP_FORBIDDEN);
        }

        return $this->successResponse($objective->toArray(), 'Objective retrieved successfully');
    }

    #[Route('/{id}/comments', name: 'parent_objectives_add_comment', methods: ['POST'])]
    public function addComment(int $id, Request $request): JsonResponse
    {
        try {
            $parent = $this->getParent();
            $family = $parent->getFamily();
            
            if (!$family) {
                return $this->errorResponse('No family found for this parent', 404);
            }

            $objective = $this->objectiveRepository->find($id);
            
            if (!$objective) {
                return $this->errorResponse('Objective not found', Response::HTTP_NOT_FOUND);
            }

            // Vérifier que l'objectif appartient à un enfant de la famille
            $student = $objective->getStudent();
            if (!$student || $student->getFamily() !== $family) {
                return $this->errorResponse('Access denied to this objective', Response::HTTP_FORBIDDEN);
            }

            $data = json_decode($request->getContent(), true);
            
            if (!$data) {
                return $this->errorResponse('Invalid JSON', Response::HTTP_BAD_REQUEST);
            }

            if (!isset($data['content']) || empty($data['content'])) {
                return $this->errorResponse('Content is required', Response::HTTP_BAD_REQUEST);
            }

            $comment = Comment::createForCoach($data, $parent, $objective);
            $this->commentRepository->save($comment, true);

            return $this->successResponse($comment->toArray(), 'Comment added successfully', Response::HTTP_CREATED);

        } catch (\Exception $e) {
            return $this->errorResponse('Comment creation failed: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/{id}/tasks', name: 'parent_objectives_tasks', methods: ['GET'])]
    public function getObjectiveTasks(int $id): JsonResponse
    {
        $parent = $this->getParent();
        $family = $parent->getFamily();
        
        if (!$family) {
            return $this->errorResponse('No family found for this parent', 404);
        }

        $objective = $this->objectiveRepository->find($id);
        
        if (!$objective) {
            return $this->errorResponse('Objective not found', Response::HTTP_NOT_FOUND);
        }

        // Vérifier que l'objectif appartient à un enfant de la famille
        $student = $objective->getStudent();
        if (!$student || $student->getFamily() !== $family) {
            return $this->errorResponse('Access denied to this objective', Response::HTTP_FORBIDDEN);
        }

        $tasks = $objective->getTasks();
        $tasksData = array_map(fn($task) => $task->toArray(), $tasks->toArray());

        return $this->successResponse($tasksData, 'Objective tasks retrieved successfully');
    }
}
