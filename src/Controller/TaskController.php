<?php

namespace App\Controller;

use App\Controller\Trait\CoachTrait;
use App\Entity\Objective;
use App\Entity\Task;
use App\Repository\CoachRepository;
use App\Repository\ObjectiveRepository;
use App\Repository\ParentUserRepository;
use App\Repository\SpecialistRepository;
use App\Repository\StudentRepository;
use App\Repository\TaskRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class TaskController extends AbstractController
{
    use CoachTrait;

    public function __construct(
        private readonly TaskRepository $taskRepository,
        private readonly CoachRepository $coachRepository,
        private readonly Security $security,
        private readonly EntityManagerInterface $em,
        private readonly ObjectiveRepository $objectiveRepository,
        private readonly StudentRepository $studentRepository,
        private readonly ParentUserRepository $parentRepository,
        private readonly SpecialistRepository $specialistRepository,
        private readonly ValidatorInterface $validator
    ) {
    }

    #[Route('/admin/objectives/{objectiveId}/tasks/create', name: 'admin_tasks_create', methods: ['POST'])]
    public function create(int $objectiveId, Request $request): JsonResponse
    {
        $coach = $this->getCurrentCoach($this->coachRepository, $this->security);
        if (!$coach) {
            return new JsonResponse(['success' => false, 'message' => 'Coach non trouvé'], 404);
        }

        $objective = $this->objectiveRepository->find($objectiveId);
        if (!$objective || $objective->getCoach() !== $coach) {
            return new JsonResponse(['success' => false, 'message' => 'Objectif non trouvé'], 404);
        }

        $data = json_decode($request->getContent(), true);
        $assignedType = $data['assignedType'] ?? 'coach';
        $assignedTo = $coach;
        
        if ($assignedType === 'student' && isset($data['studentId'])) {
            $assignedTo = $this->studentRepository->find($data['studentId']);
        } elseif ($assignedType === 'parent' && isset($data['parentId'])) {
            $assignedTo = $this->parentRepository->find($data['parentId']);
        } elseif ($assignedType === 'specialist' && isset($data['specialistId'])) {
            $assignedTo = $this->specialistRepository->find($data['specialistId']);
        }
        
        $task = Task::createForCoach([
            'title' => $data['title'] ?? '',
            'description' => $data['description'] ?? '',
            'status' => $data['status'] ?? 'pending',
            'frequency' => $data['frequency'] ?? 'none',
            'requires_proof' => isset($data['requiresProof']) ? (bool)$data['requiresProof'] : true, // Par défaut, toutes les tâches nécessitent des preuves
            'proof_type' => $data['proofType'] ?? null,
            'due_date' => $data['dueDate'] ?? null,
        ], $objective, $assignedTo, $assignedType);

        // Validation
        $errors = $this->validator->validate($task);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            return new JsonResponse(['success' => false, 'message' => implode(', ', $errorMessages)], 400);
        }

        $this->em->persist($task);
        $this->em->flush();

        return new JsonResponse(['success' => true, 'id' => $task->getId(), 'message' => 'Tâche créée avec succès']);
    }

    #[Route('/admin/tasks/{id}/update', name: 'admin_tasks_update', methods: ['POST'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $coach = $this->getCurrentCoach($this->coachRepository, $this->security);
        if (!$coach) {
            return new JsonResponse(['success' => false, 'message' => 'Coach non trouvé'], 404);
        }

        $task = $this->taskRepository->find($id);
        if (!$task || $task->getCoach() !== $coach) {
            return new JsonResponse(['success' => false, 'message' => 'Tâche non trouvée'], 404);
        }

        $data = json_decode($request->getContent(), true);
        if (isset($data['title'])) $task->setTitle($data['title']);
        if (isset($data['description'])) $task->setDescription($data['description']);
        if (isset($data['status'])) $task->setStatus($data['status']);
        if (isset($data['frequency'])) $task->setFrequency($data['frequency']);
        // Mettre à jour requiresProof si fourni, sinon garder la valeur actuelle
        if (isset($data['requiresProof'])) {
            $task->setRequiresProof((bool)$data['requiresProof']);
        }
        if (isset($data['proofType'])) $task->setProofType($data['proofType']);
        if (isset($data['dueDate'])) {
            $task->setDueDate(new \DateTimeImmutable($data['dueDate']));
        }
        
        // Mise à jour de l'assignation
        if (isset($data['assignedType'])) {
            $task->setAssignedType($data['assignedType']);
            $task->setStudent(null);
            $task->setParent(null);
            $task->setSpecialist(null);
            
            if ($data['assignedType'] === 'student' && isset($data['studentId'])) {
                $student = $this->studentRepository->find($data['studentId']);
                if ($student) $task->setStudent($student);
            } elseif ($data['assignedType'] === 'parent' && isset($data['parentId'])) {
                $parent = $this->parentRepository->find($data['parentId']);
                if ($parent) $task->setParent($parent);
            } elseif ($data['assignedType'] === 'specialist' && isset($data['specialistId'])) {
                $specialist = $this->specialistRepository->find($data['specialistId']);
                if ($specialist) $task->setSpecialist($specialist);
            } elseif ($data['assignedType'] === 'coach') {
                // S'assurer que le coach est bien assigné
                $task->setCoach($coach);
            }
        }
        
        $task->setUpdatedAt(new \DateTimeImmutable());

        // Validation
        $errors = $this->validator->validate($task);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            return new JsonResponse(['success' => false, 'message' => implode(', ', $errorMessages)], 400);
        }

        $this->em->flush();

        return new JsonResponse(['success' => true, 'message' => 'Tâche modifiée avec succès']);
    }

    #[Route('/admin/tasks/{id}/delete', name: 'admin_tasks_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $coach = $this->getCurrentCoach($this->coachRepository, $this->security);
        if (!$coach) {
            return new JsonResponse(['success' => false, 'message' => 'Coach non trouvé'], 404);
        }

        $task = $this->taskRepository->find($id);
        if (!$task || $task->getCoach() !== $coach) {
            return new JsonResponse(['success' => false, 'message' => 'Tâche non trouvée'], 404);
        }

        $this->em->remove($task);
        $this->em->flush();

        return new JsonResponse(['success' => true, 'message' => 'Tâche supprimée avec succès']);
    }

    #[Route('/admin/tasks/{id}/configure', name: 'admin_tasks_configure', methods: ['POST'])]
    public function configure(int $id, Request $request): JsonResponse
    {
        $coach = $this->getCurrentCoach($this->coachRepository, $this->security);
        if (!$coach) {
            return new JsonResponse(['success' => false, 'message' => 'Coach non trouvé'], 404);
        }

        $task = $this->taskRepository->find($id);
        if (!$task || $task->getCoach() !== $coach) {
            return new JsonResponse(['success' => false, 'message' => 'Tâche non trouvée'], 404);
        }

        $data = json_decode($request->getContent(), true);
        
        // Mettre à jour le statut
        if (isset($data['status']) && in_array($data['status'], [Task::STATUS_PENDING, Task::STATUS_IN_PROGRESS, Task::STATUS_COMPLETED])) {
            $task->setStatus($data['status']);
        }
        
        // Mettre à jour la demande de preuves
        if (isset($data['requiresProof'])) {
            $task->setRequiresProof((bool)$data['requiresProof']);
        }
        
        // Mettre à jour la temporalité
        if (isset($data['frequency'])) {
            $validFrequencies = array_keys(Task::FREQUENCIES);
            if (in_array($data['frequency'], $validFrequencies)) {
                $task->setFrequency($data['frequency']);
            }
        }
        
        $task->setUpdatedAt(new \DateTimeImmutable());

        // Validation
        $errors = $this->validator->validate($task);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            return new JsonResponse(['success' => false, 'message' => implode(', ', $errorMessages)], 400);
        }

        $this->em->flush();

        return new JsonResponse([
            'success' => true, 
            'message' => 'Configuration de la tâche mise à jour avec succès',
            'task' => $task->toTemplateArray()
        ]);
    }

    #[Route('/admin/tasks/{id}', name: 'admin_tasks_get', methods: ['GET'])]
    public function get(int $id): JsonResponse
    {
        $coach = $this->getCurrentCoach($this->coachRepository, $this->security);
        if (!$coach) {
            return new JsonResponse(['success' => false, 'message' => 'Coach non trouvé'], 404);
        }

        $task = $this->taskRepository->find($id);
        if (!$task || $task->getCoach() !== $coach) {
            return new JsonResponse(['success' => false, 'message' => 'Tâche non trouvée'], 404);
        }

        return new JsonResponse([
            'success' => true,
            'task' => [
                'id' => $task->getId(),
                'title' => $task->getTitle(),
                'status' => $task->getStatus(),
                'frequency' => $task->getFrequency(),
                'requiresProof' => $task->isRequiresProof(),
            ]
        ]);
    }
}


