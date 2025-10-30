<?php

namespace App\Controller\Coach;

use App\Entity\Task;
use App\Entity\Proof;
use App\Entity\TaskHistory;
use App\Repository\TaskRepository;
use App\Repository\StudentRepository;
use App\Repository\ParentUserRepository;
use App\Repository\SpecialistRepository;
use App\Repository\ObjectiveRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/coach/tasks', name: 'coach_tasks_')]
class TaskController extends BaseCoachController
{
    public function __construct(
        private TaskRepository $taskRepository,
        private StudentRepository $studentRepository,
        private ParentUserRepository $parentUserRepository,
        private SpecialistRepository $specialistRepository,
        private ObjectiveRepository $objectiveRepository,
        private ValidatorInterface $validator
    ) {}

    #[Route('', name: 'list', methods: ['POST'])]
    public function list(Request $request): JsonResponse
    {
        try {
            $coach = $this->getCoach();
            
            $data = json_decode($request->getContent(), true);
            $objectiveId = $data['objective_id'] ?? null;
            $studentId = $data['student_id'] ?? null;
            $status = $data['status'] ?? null;
            $search = $data['search'] ?? null;

            // Si recherche par texte, utiliser la méthode de recherche
            if ($search) {
                $tasks = $this->taskRepository->findByCoachWithSearch($coach, $search, $objectiveId, $studentId, $status);
            } else {
                // Sinon, utiliser la méthode standard
                $criteria = ['coach' => $coach];
                if ($objectiveId) {
                    $objective = $this->objectiveRepository->find($objectiveId);
                    if ($objective) {
                        $criteria['objective'] = $objective;
                    }
                }
                if ($studentId) {
                    $student = $this->studentRepository->find($studentId);
                    if ($student) {
                        $criteria['student'] = $student;
                    }
                }
                if ($status) {
                    $criteria['status'] = $status;
                }
                $tasks = $this->taskRepository->findBy($criteria);
            }
            
            $data = [];
            foreach ($tasks as $task) {
                $data[] = $task->toArray();
            }

            return $this->successResponse($data, 'Coach tasks retrieved successfully');

        } catch (\Exception $e) {
            return $this->errorResponse('Task retrieval failed: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/create', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            if (!$data) {
                return $this->errorResponse('Invalid JSON', Response::HTTP_BAD_REQUEST);
            }

            $requiredFields = ['title', 'description', 'objective_id'];
            foreach ($requiredFields as $field) {
                if (!isset($data[$field]) || empty($data[$field])) {
                    return $this->errorResponse("Field '{$field}' is required", Response::HTTP_BAD_REQUEST);
                }
            }

            $objective = $this->objectiveRepository->find($data['objective_id']);
            if (!$objective) {
                return $this->errorResponse('Objective not found', Response::HTTP_NOT_FOUND);
            }

            // Vérifier que l'objectif appartient au coach
            $coach = $this->getCoach();
            if ($objective->getCoach() !== $coach) {
                return $this->errorResponse('Access denied to this objective', Response::HTTP_FORBIDDEN);
            }

            // Déterminer l'assigné
            $assignedTo = null;
            $assignedType = $data['assigned_type'] ?? 'coach'; // coach, student, parent, specialist
            
            switch ($assignedType) {
                case 'student':
                    if (isset($data['assigned_id'])) {
                        $assignedTo = $this->studentRepository->find($data['assigned_id']);
                        if (!$assignedTo) {
                            return $this->errorResponse('Student not found', Response::HTTP_NOT_FOUND);
                        }
                    }
                    break;
                case 'parent':
                    if (isset($data['assigned_id'])) {
                        $assignedTo = $this->parentUserRepository->find($data['assigned_id']);
                        if (!$assignedTo) {
                            return $this->errorResponse('Parent not found', Response::HTTP_NOT_FOUND);
                        }
                    }
                    break;
                case 'specialist':
                    if (isset($data['assigned_id'])) {
                        $assignedTo = $this->specialistRepository->find($data['assigned_id']);
                        if (!$assignedTo) {
                            return $this->errorResponse('Specialist not found', Response::HTTP_NOT_FOUND);
                        }
                    }
                    break;
                default:
                    $assignedTo = $coach;
                    break;
            }

            $task = Task::createForCoach($data, $objective, $assignedTo, $assignedType);

            $errors = $this->validator->validate($task);
            if (count($errors) > 0) {
                $errorMessages = [];
                foreach ($errors as $error) {
                    $errorMessages[] = $error->getMessage();
                }
                return $this->errorResponse('Validation failed', Response::HTTP_BAD_REQUEST, $errorMessages);
            }

            $this->taskRepository->save($task, true);

            return $this->successResponse($task->toArray(), 'Task created successfully', Response::HTTP_CREATED);

        } catch (\Exception $e) {
            return $this->errorResponse('Task creation failed: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/show', name: 'show', methods: ['POST'])]
    public function show(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $id = $data['id'] ?? null;
        
        if (!$id) {
            return $this->errorResponse('Task ID is required', Response::HTTP_BAD_REQUEST);
        }
        
        $task = $this->taskRepository->find($id);
        
        if (!$task) {
            return $this->errorResponse('Task not found', Response::HTTP_NOT_FOUND);
        }

        // Vérifier que la tâche appartient au coach
        $coach = $this->getCoach();
        if ($task->getCoach() !== $coach) {
            return $this->errorResponse('Access denied to this task', Response::HTTP_FORBIDDEN);
        }

        return $this->successResponse($task->toArray(), 'Task retrieved successfully');
    }

    #[Route('/update', name: 'update', methods: ['POST'])]
    public function update(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            $id = $data['id'] ?? null;
            
            if (!$id) {
                return $this->errorResponse('Task ID is required', Response::HTTP_BAD_REQUEST);
            }
            
            $task = $this->taskRepository->find($id);
            
            if (!$task) {
                return $this->errorResponse('Task not found', Response::HTTP_NOT_FOUND);
            }

            // Vérifier que la tâche appartient au coach
            $coach = $this->getCoach();
            if ($task->getCoach() !== $coach) {
                return $this->errorResponse('Access denied to this task', Response::HTTP_FORBIDDEN);
            }
            
            if (!$data) {
                return $this->errorResponse('Invalid JSON', Response::HTTP_BAD_REQUEST);
            }

            // Mettre à jour les champs modifiables
            if (isset($data['title'])) {
                $task->setTitle($data['title']);
            }
            if (isset($data['description'])) {
                $task->setDescription($data['description']);
            }
            if (isset($data['status'])) {
                $task->setStatus($data['status']);
            }
            if (isset($data['frequency'])) {
                $task->setFrequency($data['frequency']);
            }
            if (isset($data['requires_proof'])) {
                $task->setRequiresProof($data['requires_proof']);
            }
            if (isset($data['proof_type'])) {
                $task->setProofType($data['proof_type']);
            }
            if (isset($data['due_date'])) {
                $task->setDueDate(new \DateTimeImmutable($data['due_date']));
            }

            $errors = $this->validator->validate($task);
            if (count($errors) > 0) {
                $errorMessages = [];
                foreach ($errors as $error) {
                    $errorMessages[] = $error->getMessage();
                }
                return $this->errorResponse('Validation failed', Response::HTTP_BAD_REQUEST, $errorMessages);
            }

            $this->taskRepository->save($task, true);

            return $this->successResponse($task->toArray(), 'Task updated successfully');

        } catch (\Exception $e) {
            return $this->errorResponse('Task update failed: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/delete', name: 'delete', methods: ['POST'])]
    public function delete(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $id = $data['id'] ?? null;
        
        if (!$id) {
            return $this->errorResponse('Task ID is required', Response::HTTP_BAD_REQUEST);
        }
        
        $task = $this->taskRepository->find($id);
        
        if (!$task) {
            return $this->errorResponse('Task not found', Response::HTTP_NOT_FOUND);
        }

        // Vérifier que la tâche appartient au coach
        $coach = $this->getCoach();
        if ($task->getCoach() !== $coach) {
            return $this->errorResponse('Access denied to this task', Response::HTTP_FORBIDDEN);
        }

        $this->taskRepository->remove($task, true);

        return $this->successResponse(null, 'Task deleted successfully');
    }

    #[Route('/proofs', name: 'proofs', methods: ['POST'])]
    public function getProofs(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            $taskId = $data['task_id'] ?? null;
            
            if (!$taskId) {
                return $this->errorResponse('Task ID is required', Response::HTTP_BAD_REQUEST);
            }
            
            $task = $this->taskRepository->find($taskId);
            
            if (!$task) {
                return $this->errorResponse('Task not found', Response::HTTP_NOT_FOUND);
            }

            // Vérifier que la tâche appartient au coach
            $coach = $this->getCoach();
            if ($task->getCoach() !== $coach) {
                return $this->errorResponse('Access denied to this task', Response::HTTP_FORBIDDEN);
            }

            $proofs = $task->getProofs();
            $data = [];
            foreach ($proofs as $proof) {
                $data[] = $proof->toArray();
            }

            return $this->successResponse($data, 'Task proofs retrieved successfully');

        } catch (\Exception $e) {
            return $this->errorResponse('Proofs retrieval failed: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/history', name: 'history', methods: ['POST'])]
    public function getHistory(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            $taskId = $data['task_id'] ?? null;
            
            if (!$taskId) {
                return $this->errorResponse('Task ID is required', Response::HTTP_BAD_REQUEST);
            }
            
            $task = $this->taskRepository->find($taskId);
            
            if (!$task) {
                return $this->errorResponse('Task not found', Response::HTTP_NOT_FOUND);
            }

            // Vérifier que la tâche appartient au coach
            $coach = $this->getCoach();
            if ($task->getCoach() !== $coach) {
                return $this->errorResponse('Access denied to this task', Response::HTTP_FORBIDDEN);
            }

            $history = $task->getTaskHistories();
            $data = [];
            foreach ($history as $historyItem) {
                $data[] = $historyItem->toArray();
            }

            return $this->successResponse($data, 'Task history retrieved successfully');

        } catch (\Exception $e) {
            return $this->errorResponse('History retrieval failed: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/add-history', name: 'add_history', methods: ['POST'])]
    public function addHistory(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            $taskId = $data['task_id'] ?? null;
            $progress = $data['progress'] ?? null;
            $notes = $data['notes'] ?? null;
            
            if (!$taskId || $progress === null) {
                return $this->errorResponse('Task ID and progress are required', Response::HTTP_BAD_REQUEST);
            }
            
            $task = $this->taskRepository->find($taskId);
            
            if (!$task) {
                return $this->errorResponse('Task not found', Response::HTTP_NOT_FOUND);
            }

            // Vérifier que la tâche appartient au coach
            $coach = $this->getCoach();
            if ($task->getCoach() !== $coach) {
                return $this->errorResponse('Access denied to this task', Response::HTTP_FORBIDDEN);
            }

            // Créer un historique
            $historyItem = TaskHistory::createForCoach([
                'progress' => $progress,
                'notes' => $notes
            ], $coach);
            
            $historyItem->setTask($task);

            $errors = $this->validator->validate($historyItem);
            if (count($errors) > 0) {
                $errorMessages = [];
                foreach ($errors as $error) {
                    $errorMessages[] = $error->getMessage();
                }
                return $this->errorResponse('Validation failed', Response::HTTP_BAD_REQUEST, $errorMessages);
            }

            $this->taskRepository->getEntityManager()->persist($historyItem);
            $this->taskRepository->getEntityManager()->flush();

            return $this->successResponse($historyItem->toArray(), 'Task history added successfully', Response::HTTP_CREATED);

        } catch (\Exception $e) {
            return $this->errorResponse('History creation failed: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}