<?php

namespace App\Controller\Specialist;

use App\Entity\Task;
use App\Entity\Comment;
use App\Repository\TaskRepository;
use App\Repository\CommentRepository;
use App\Repository\SpecialistRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/specialist/tasks')]
class TaskController extends BaseSpecialistController
{
    public function __construct(
        private TaskRepository $taskRepository,
        private CommentRepository $commentRepository,
        private ValidatorInterface $validator,
        private SpecialistRepository $specialistRepository
    ) {
        parent::__construct($specialistRepository);
    }

    #[Route('', name: 'specialist_tasks_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $specialist = $this->getSpecialist();
        $students = $specialist->getStudents();
        
        $studentId = $request->query->get('student_id');
        $familyId = $request->query->get('family_id');
        $status = $request->query->get('status');
        $dueDate = $request->query->get('due_date');
        
        $tasks = [];
        
        foreach ($students as $student) {
            if ($studentId && $student->getId() != $studentId) {
                continue;
            }
            
            if ($familyId && $student->getFamily()->getId() != $familyId) {
                continue;
            }
            
            $studentTasks = $student->getTasks();
            foreach ($studentTasks as $task) {
                if ($status && $task->getStatus() !== $status) {
                    continue;
                }
                if ($dueDate && $task->getDueDate() && $task->getDueDate()->format('Y-m-d') !== $dueDate) {
                    continue;
                }
                $tasks[] = $task->toArray();
            }
        }

        return $this->successResponse($tasks, 'Tasks retrieved successfully');
    }

    #[Route('/assigned', name: 'specialist_tasks_assigned', methods: ['GET'])]
    public function listAssignedTasks(Request $request): JsonResponse
    {
        $specialist = $this->getSpecialist();
        
        $status = $request->query->get('status');
        $dueDate = $request->query->get('due_date');
        
        $criteria = ['assignedTo' => $specialist];
        if ($status) {
            $criteria['status'] = $status;
        }
        
        $tasks = $this->taskRepository->findBy($criteria);
        
        // Filtrer par date d'échéance si fournie
        if ($dueDate) {
            $tasks = array_filter($tasks, function($task) use ($dueDate) {
                return $task->getDueDate() && $task->getDueDate()->format('Y-m-d') === $dueDate;
            });
        }
        
        $tasksData = array_map(fn($task) => $task->toArray(), $tasks);
        
        return $this->successResponse($tasksData, 'Assigned tasks retrieved successfully');
    }

    #[Route('/{id}', name: 'specialist_tasks_show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $specialist = $this->getSpecialist();
        $students = $specialist->getStudents();
        
        $task = $this->taskRepository->find($id);
        
        if (!$task) {
            return $this->errorResponse('Task not found', Response::HTTP_NOT_FOUND);
        }

        // Vérifier que la tâche appartient à un étudiant assigné au spécialiste ou est assignée au spécialiste
        $student = $task->getStudent();
        $isAssignedToSpecialist = $task->getAssignedTo() === $specialist;
        $isStudentAssigned = $student && $students->contains($student);
        
        if (!$isAssignedToSpecialist && !$isStudentAssigned) {
            return $this->errorResponse('Access denied to this task', Response::HTTP_FORBIDDEN);
        }

        return $this->successResponse($task->toArray(), 'Task retrieved successfully');
    }

    #[Route('/{id}/status', name: 'specialist_tasks_update_status', methods: ['PUT'])]
    public function updateStatus(int $id, Request $request): JsonResponse
    {
        try {
            $specialist = $this->getSpecialist();
            $students = $specialist->getStudents();
            
            $task = $this->taskRepository->find($id);
            
            if (!$task) {
                return $this->errorResponse('Task not found', Response::HTTP_NOT_FOUND);
            }

            // Vérifier que la tâche appartient à un étudiant assigné au spécialiste ou est assignée au spécialiste
            $student = $task->getStudent();
            $isAssignedToSpecialist = $task->getAssignedTo() === $specialist;
            $isStudentAssigned = $student && $students->contains($student);
            
            if (!$isAssignedToSpecialist && !$isStudentAssigned) {
                return $this->errorResponse('Access denied to this task', Response::HTTP_FORBIDDEN);
            }

            $data = json_decode($request->getContent(), true);
            
            if (!$data) {
                return $this->errorResponse('Invalid JSON', Response::HTTP_BAD_REQUEST);
            }

            if (!isset($data['status'])) {
                return $this->errorResponse('Status is required', Response::HTTP_BAD_REQUEST);
            }

            $task->setStatus($data['status']);

            $errors = $this->validator->validate($task);
            if (count($errors) > 0) {
                $errorMessages = [];
                foreach ($errors as $error) {
                    $errorMessages[] = $error->getMessage();
                }
                return $this->errorResponse('Validation failed', Response::HTTP_BAD_REQUEST, $errorMessages);
            }

            $this->taskRepository->save($task, true);

            return $this->successResponse($task->toArray(), 'Task status updated successfully');

        } catch (\Exception $e) {
            return $this->errorResponse('Task status update failed: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/{id}/comments', name: 'specialist_tasks_add_comment', methods: ['POST'])]
    public function addComment(int $id, Request $request): JsonResponse
    {
        try {
            $specialist = $this->getSpecialist();
            $students = $specialist->getStudents();
            
            $task = $this->taskRepository->find($id);
            
            if (!$task) {
                return $this->errorResponse('Task not found', Response::HTTP_NOT_FOUND);
            }

            // Vérifier que la tâche appartient à un étudiant assigné au spécialiste ou est assignée au spécialiste
            $student = $task->getStudent();
            $isAssignedToSpecialist = $task->getAssignedTo() === $specialist;
            $isStudentAssigned = $student && $students->contains($student);
            
            if (!$isAssignedToSpecialist && !$isStudentAssigned) {
                return $this->errorResponse('Access denied to this task', Response::HTTP_FORBIDDEN);
            }

            $data = json_decode($request->getContent(), true);
            
            if (!$data) {
                return $this->errorResponse('Invalid JSON', Response::HTTP_BAD_REQUEST);
            }

            if (!isset($data['content']) || empty($data['content'])) {
                return $this->errorResponse('Content is required', Response::HTTP_BAD_REQUEST);
            }

            $comment = Comment::createForCoach($data, $specialist, null, null);
            $this->commentRepository->save($comment, true);

            return $this->successResponse($comment->toArray(), 'Comment added successfully', Response::HTTP_CREATED);

        } catch (\Exception $e) {
            return $this->errorResponse('Comment creation failed: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/history/{studentId}', name: 'specialist_tasks_history', methods: ['GET'])]
    public function getTaskHistory(int $studentId): JsonResponse
    {
        $specialist = $this->getSpecialist();
        $students = $specialist->getStudents();
        
        // Vérifier que l'étudiant est assigné au spécialiste
        $student = null;
        foreach ($students as $s) {
            if ($s->getId() === $studentId) {
                $student = $s;
                break;
            }
        }
        
        if (!$student) {
            return $this->errorResponse('Student not found or access denied', Response::HTTP_FORBIDDEN);
        }

        $tasks = $student->getTasks();
        $completedTasks = array_filter($tasks->toArray(), fn($task) => $task->getStatus() === 'completed');
        $tasksData = array_map(fn($task) => $task->toArray(), $completedTasks);

        return $this->successResponse($tasksData, 'Task history retrieved successfully');
    }
}
