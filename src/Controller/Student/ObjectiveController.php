<?php

namespace App\Controller\Student;

use App\Entity\Objective;
use App\Entity\Comment;
use App\Repository\ObjectiveRepository;
use App\Repository\CommentRepository;
use App\Repository\StudentRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/student/objectives')]
class ObjectiveController extends BaseStudentController
{
    public function __construct(
        private ObjectiveRepository $objectiveRepository,
        private CommentRepository $commentRepository,
        private ValidatorInterface $validator,
        private StudentRepository $studentRepository
    ) {
        parent::__construct($studentRepository);
    }

    #[Route('', name: 'student_objectives_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $student = $this->getStudent();
        
        $status = $request->query->get('status');
        
        $objectives = $student->getObjectives();
        
        if ($status) {
            $objectives = $objectives->filter(fn($obj) => $obj->getStatus() === $status);
        }
        
        $objectivesData = array_map(fn($objective) => $objective->toArray(), $objectives->toArray());

        return $this->successResponse($objectivesData, 'Objectives retrieved successfully');
    }

    #[Route('/{id}', name: 'student_objectives_show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $student = $this->getStudent();
        
        $objective = $this->objectiveRepository->find($id);
        
        if (!$objective) {
            return $this->errorResponse('Objective not found', Response::HTTP_NOT_FOUND);
        }

        // Vérifier que l'objectif appartient à l'étudiant
        if ($objective->getStudent() !== $student) {
            return $this->errorResponse('Access denied to this objective', Response::HTTP_FORBIDDEN);
        }

        return $this->successResponse($objective->toArray(), 'Objective retrieved successfully');
    }

    #[Route('/{id}/tasks', name: 'student_objectives_tasks', methods: ['GET'])]
    public function getObjectiveTasks(int $id): JsonResponse
    {
        $student = $this->getStudent();
        
        $objective = $this->objectiveRepository->find($id);
        
        if (!$objective) {
            return $this->errorResponse('Objective not found', Response::HTTP_NOT_FOUND);
        }

        // Vérifier que l'objectif appartient à l'étudiant
        if ($objective->getStudent() !== $student) {
            return $this->errorResponse('Access denied to this objective', Response::HTTP_FORBIDDEN);
        }

        $tasks = $objective->getTasks();
        $tasksData = array_map(fn($task) => $task->toArray(), $tasks->toArray());

        return $this->successResponse($tasksData, 'Objective tasks retrieved successfully');
    }

    #[Route('/{id}/progress', name: 'student_objectives_progress', methods: ['GET'])]
    public function getObjectiveProgress(int $id): JsonResponse
    {
        $student = $this->getStudent();
        
        $objective = $this->objectiveRepository->find($id);
        
        if (!$objective) {
            return $this->errorResponse('Objective not found', Response::HTTP_NOT_FOUND);
        }

        // Vérifier que l'objectif appartient à l'étudiant
        if ($objective->getStudent() !== $student) {
            return $this->errorResponse('Access denied to this objective', Response::HTTP_FORBIDDEN);
        }

        $tasks = $objective->getTasks();
        $totalTasks = $tasks->count();
        $completedTasks = $tasks->filter(fn($task) => $task->getStatus() === 'completed')->count();
        $progressPercentage = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100, 2) : 0;

        $progressData = [
            'objective' => $objective->toArray(),
            'totalTasks' => $totalTasks,
            'completedTasks' => $completedTasks,
            'progressPercentage' => $progressPercentage,
            'status' => $objective->getStatus()
        ];

        return $this->successResponse($progressData, 'Objective progress retrieved successfully');
    }

    #[Route('/{id}/comments', name: 'student_objectives_add_comment', methods: ['POST'])]
    public function addComment(int $id, Request $request): JsonResponse
    {
        try {
            $student = $this->getStudent();
            
            $objective = $this->objectiveRepository->find($id);
            
            if (!$objective) {
                return $this->errorResponse('Objective not found', Response::HTTP_NOT_FOUND);
            }

            // Vérifier que l'objectif appartient à l'étudiant
            if ($objective->getStudent() !== $student) {
                return $this->errorResponse('Access denied to this objective', Response::HTTP_FORBIDDEN);
            }

            $data = json_decode($request->getContent(), true);
            
            if (!$data) {
                return $this->errorResponse('Invalid JSON', Response::HTTP_BAD_REQUEST);
            }

            if (!isset($data['content']) || empty($data['content'])) {
                return $this->errorResponse('Content is required', Response::HTTP_BAD_REQUEST);
            }

            $comment = Comment::createForCoach($data, $student, $objective);
            $this->commentRepository->save($comment, true);

            return $this->successResponse($comment->toArray(), 'Comment added successfully', Response::HTTP_CREATED);

        } catch (\Exception $e) {
            return $this->errorResponse('Comment creation failed: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
