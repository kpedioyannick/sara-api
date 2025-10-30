<?php

namespace App\Controller\Parent;

use App\Entity\Task;
use App\Entity\Proof;
use App\Repository\TaskRepository;
use App\Repository\ProofRepository;
use App\Repository\ParentUserRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/parent/tasks')]
class TaskController extends BaseParentController
{
    public function __construct(
        private TaskRepository $taskRepository,
        private ProofRepository $proofRepository,
        private ValidatorInterface $validator,
        private ParentUserRepository $parentUserRepository
    ) {
        parent::__construct($parentUserRepository);
    }

    #[Route('', name: 'parent_tasks_list', methods: ['GET'])]
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
        $tasks = [];
        
        foreach ($students as $student) {
            if ($studentId && $student->getId() != $studentId) {
                continue;
            }
            
            $studentTasks = $student->getTasks();
            foreach ($studentTasks as $task) {
                if ($status && $task->getStatus() !== $status) {
                    continue;
                }
                $tasks[] = $task->toArray();
            }
        }

        return $this->successResponse($tasks, 'Tasks retrieved successfully');
    }

    #[Route('/assigned', name: 'parent_tasks_assigned', methods: ['GET'])]
    public function listAssignedTasks(Request $request): JsonResponse
    {
        $parent = $this->getParent();
        
        $status = $request->query->get('status');
        
        $criteria = ['assignedTo' => $parent];
        if ($status) {
            $criteria['status'] = $status;
        }
        
        $tasks = $this->taskRepository->findBy($criteria);
        $tasksData = array_map(fn($task) => $task->toArray(), $tasks);
        
        return $this->successResponse($tasksData, 'Assigned tasks retrieved successfully');
    }

    #[Route('/{id}', name: 'parent_tasks_show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $parent = $this->getParent();
        $family = $parent->getFamily();
        
        if (!$family) {
            return $this->errorResponse('No family found for this parent', 404);
        }

        $task = $this->taskRepository->find($id);
        
        if (!$task) {
            return $this->errorResponse('Task not found', Response::HTTP_NOT_FOUND);
        }

        // Vérifier que la tâche appartient à un enfant de la famille
        $student = $task->getStudent();
        if (!$student || $student->getFamily() !== $family) {
            return $this->errorResponse('Access denied to this task', Response::HTTP_FORBIDDEN);
        }

        return $this->successResponse($task->toArray(), 'Task retrieved successfully');
    }

    #[Route('/{id}/status', name: 'parent_tasks_update_status', methods: ['PUT'])]
    public function updateStatus(int $id, Request $request): JsonResponse
    {
        try {
            $parent = $this->getParent();
            $family = $parent->getFamily();
            
            if (!$family) {
                return $this->errorResponse('No family found for this parent', 404);
            }

            $task = $this->taskRepository->find($id);
            
            if (!$task) {
                return $this->errorResponse('Task not found', Response::HTTP_NOT_FOUND);
            }

            // Vérifier que la tâche appartient à un enfant de la famille
            $student = $task->getStudent();
            if (!$student || $student->getFamily() !== $family) {
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

    #[Route('/{id}/proofs', name: 'parent_tasks_upload_proof', methods: ['POST'])]
    public function uploadProof(int $id, Request $request): JsonResponse
    {
        try {
            $parent = $this->getParent();
            $family = $parent->getFamily();
            
            if (!$family) {
                return $this->errorResponse('No family found for this parent', 404);
            }

            $task = $this->taskRepository->find($id);
            
            if (!$task) {
                return $this->errorResponse('Task not found', Response::HTTP_NOT_FOUND);
            }

            // Vérifier que la tâche appartient à un enfant de la famille
            $student = $task->getStudent();
            if (!$student || $student->getFamily() !== $family) {
                return $this->errorResponse('Access denied to this task', Response::HTTP_FORBIDDEN);
            }

            $data = json_decode($request->getContent(), true);
            
            if (!$data) {
                return $this->errorResponse('Invalid JSON', Response::HTTP_BAD_REQUEST);
            }

            $requiredFields = ['filename', 'filePath', 'fileType', 'fileSize'];
            foreach ($requiredFields as $field) {
                if (!isset($data[$field])) {
                    return $this->errorResponse("Field '{$field}' is required", Response::HTTP_BAD_REQUEST);
                }
            }

            $proof = Proof::createForCoach($data, $task, $parent);
            
            $errors = $this->validator->validate($proof);
            if (count($errors) > 0) {
                $errorMessages = [];
                foreach ($errors as $error) {
                    $errorMessages[] = $error->getMessage();
                }
                return $this->errorResponse('Validation failed', Response::HTTP_BAD_REQUEST, $errorMessages);
            }

            $this->proofRepository->save($proof, true);

            $response = [
                'proof' => $proof->toArray(),
                'confirmation' => [
                    'message' => 'Proof uploaded successfully',
                    'filename' => $proof->getFilename(),
                    'uploadedAt' => $proof->getCreatedAt()->format('Y-m-d H:i:s'),
                    'fileSize' => $proof->getFileSize(),
                    'fileType' => $proof->getFileType()
                ]
            ];

            return $this->successResponse($response, 'Proof uploaded successfully', Response::HTTP_CREATED);

        } catch (\Exception $e) {
            return $this->errorResponse('Proof upload failed: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
