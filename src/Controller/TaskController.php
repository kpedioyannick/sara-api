<?php

namespace App\Controller;

use App\Controller\Trait\CoachTrait;
use App\Entity\Objective;
use App\Entity\Task;
use App\Enum\TaskType;
use App\Repository\ActivityRepository;
use App\Repository\CoachRepository;
use App\Repository\ObjectiveRepository;
use App\Repository\ParentUserRepository;
use App\Repository\PathRepository;
use App\Repository\SpecialistRepository;
use App\Repository\StudentRepository;
use App\Repository\TaskRepository;
use App\Service\TaskPlanningService;
use App\Service\NotificationService;
use App\Service\PermissionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
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
        private readonly ValidatorInterface $validator,
        private readonly TaskPlanningService $taskPlanningService,
        private readonly PermissionService $permissionService,
        private readonly NotificationService $notificationService,
        private readonly ActivityRepository $activityRepository,
        private readonly PathRepository $pathRepository
    ) {
    }

    #[Route('/admin/objectives/{objectiveId}/tasks/create', name: 'admin_tasks_create', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function create(int $objectiveId, Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['success' => false, 'message' => 'Vous devez être connecté'], 403);
        }

        $objective = $this->objectiveRepository->find($objectiveId);
        if (!$objective) {
            return new JsonResponse(['success' => false, 'message' => 'Objectif non trouvé'], 404);
        }

        // Vérifier les permissions d'accès à l'objectif
        if (!$this->permissionService->canViewObjective($user, $objective)) {
            return new JsonResponse(['success' => false, 'message' => 'Vous n\'avez pas accès à cet objectif'], 403);
        }

        // Vérifier les permissions de modification de tâches
        if (!$this->permissionService->canModifyTask($user, null, $objective)) {
            return new JsonResponse(['success' => false, 'message' => 'Vous n\'avez pas le droit de créer des tâches'], 403);
        }

        // Pour les coaches, vérifier qu'ils sont bien le coach de l'objectif
        $coach = null;
        if ($user->isCoach()) {
            $coach = $this->getCurrentCoach($this->coachRepository, $this->security);
            if (!$coach || $objective->getCoach() !== $coach) {
                return new JsonResponse(['success' => false, 'message' => 'Vous n\'avez pas le droit de créer des tâches pour cet objectif'], 403);
            }
        } else {
            // Pour les autres rôles, vérifier qu'ils peuvent modifier l'objectif
            if (!$this->permissionService->canModifyObjective($user, $objective)) {
                return new JsonResponse(['success' => false, 'message' => 'Vous n\'avez pas le droit de créer des tâches pour cet objectif'], 403);
            }
        }

        // Vérifier si on peut créer des tâches pour cet objectif (statut)
        if (!$objective->canModifyTasks()) {
            return new JsonResponse([
                'success' => false, 
                'message' => 'Impossible de créer une tâche. ' . $objective->getStatusMessage()
            ], 403);
        }

        // Utiliser le coach de l'objectif pour la création
        $coach = $objective->getCoach();
        if (!$coach) {
            return new JsonResponse(['success' => false, 'message' => 'Coach non trouvé'], 404);
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
            'created_at' => $data['createdAt'] ?? null,
        ], $objective, $assignedTo, $assignedType);

        // Définir le type de tâche
        if (isset($data['type'])) {
            try {
                $taskType = TaskType::from($data['type']);
                $task->setType($taskType);
            } catch (\ValueError $e) {
                // Type invalide, utiliser la valeur par défaut
                $task->setType(TaskType::TASK);
            }
        }

        // Lier l'activité si fournie (pour type activity_task)
        if (isset($data['activityId']) && !empty($data['activityId'])) {
            $activity = $this->activityRepository->find($data['activityId']);
            if ($activity) {
                $task->setActivity($activity);
            }
        }

        // Lier l'activité scolaire (Path) si fournie (pour type school_activity_task)
        if (isset($data['pathId']) && !empty($data['pathId'])) {
            $path = $this->pathRepository->find($data['pathId']);
            if ($path) {
                $task->setPath($path);
            }
        }

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

        // Générer les événements Planning à partir de la tâche
        try {
            $this->taskPlanningService->generatePlanningFromTask($task);
            $this->em->flush();
        } catch (\Exception $e) {
            // Logger l'erreur mais ne pas faire échouer la création de la tâche
            // Les événements Planning peuvent être régénérés plus tard
        }

        // Notifier l'utilisateur assigné si ce n'est pas le coach
        try {
            if ($assignedType !== 'coach') {
                $this->notificationService->notifyNewTaskAssigned($task);
            }
        } catch (\Exception $e) {
            error_log('Erreur notification nouvelle tâche: ' . $e->getMessage());
        }

        return new JsonResponse(['success' => true, 'id' => $task->getId(), 'message' => 'Tâche créée avec succès']);
    }

    #[Route('/admin/tasks/{id}/update', name: 'admin_tasks_update', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function update(int $id, Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['success' => false, 'message' => 'Vous devez être connecté'], 403);
        }

        $task = $this->taskRepository->find($id);
        if (!$task) {
            return new JsonResponse(['success' => false, 'message' => 'Tâche non trouvée'], 404);
        }

        // Vérifier les permissions d'accès à la tâche
        if (!$this->permissionService->canViewTask($user, $task)) {
            return new JsonResponse(['success' => false, 'message' => 'Vous n\'avez pas accès à cette tâche'], 403);
        }

        // Vérifier les permissions de modification
        if (!$this->permissionService->canModifyTask($user, $task)) {
            return new JsonResponse(['success' => false, 'message' => 'Vous n\'avez pas le droit de modifier cette tâche'], 403);
        }

        $objective = $task->getObjective();
        if (!$objective) {
            return new JsonResponse(['success' => false, 'message' => 'Objectif non trouvé'], 404);
        }

        // Pour les coaches, vérifier qu'ils sont bien le coach de l'objectif
        $coach = null;
        if ($user->isCoach()) {
            $coach = $this->getCurrentCoach($this->coachRepository, $this->security);
            if (!$coach || $task->getCoach() !== $coach || $objective->getCoach() !== $coach) {
                return new JsonResponse(['success' => false, 'message' => 'Vous n\'avez pas le droit de modifier cette tâche'], 403);
            }
        }

        // Vérifier si on peut modifier des tâches pour cet objectif (statut)
        if (!$objective->canModifyTasks()) {
            return new JsonResponse([
                'success' => false, 
                'message' => 'Impossible de modifier cette tâche. ' . $objective->getStatusMessage()
            ], 403);
        }

        $data = json_decode($request->getContent(), true);
        if (isset($data['title'])) $task->setTitle($data['title']);
        if (isset($data['description'])) $task->setDescription($data['description']);
        // Seul le coach peut changer le statut d'une tâche
        if (isset($data['status'])) {
            if (!$user->isCoach()) {
                return new JsonResponse(['success' => false, 'message' => 'Seul le coach peut changer le statut d\'une tâche'], 403);
            }
            $task->setStatus($data['status']);
        }
        if (isset($data['frequency'])) $task->setFrequency($data['frequency']);
        // Mettre à jour requiresProof si fourni, sinon garder la valeur actuelle
        if (isset($data['requiresProof'])) {
            $task->setRequiresProof((bool)$data['requiresProof']);
        }
        // Mettre à jour le type de tâche
        if (isset($data['type'])) {
            try {
                $taskType = TaskType::from($data['type']);
                $task->setType($taskType);
            } catch (\ValueError $e) {
                // Type invalide, ignorer
            }
        }
        // Mettre à jour les dates si fournies
        if (isset($data['createdAt']) && $data['createdAt']) {
            try {
                $task->setCreatedAt(new \DateTimeImmutable($data['createdAt']));
            } catch (\Exception $e) {
                // Ignorer les erreurs de format de date
            }
        }
        if (isset($data['dueDate']) && $data['dueDate']) {
            try {
                $task->setDueDate(new \DateTimeImmutable($data['dueDate']));
            } catch (\Exception $e) {
                // Ignorer les erreurs de format de date
            }
        }
        if (isset($data['proofType'])) $task->setProofType($data['proofType']);
        
        // Mise à jour de l'activité liée (pour type activity_task)
        if (isset($data['activityId'])) {
            if (empty($data['activityId'])) {
                $task->setActivity(null);
            } else {
                $activity = $this->activityRepository->find($data['activityId']);
                if ($activity) {
                    $task->setActivity($activity);
                }
            }
        }

        // Mise à jour de l'activité scolaire liée (Path) (pour type school_activity_task)
        if (isset($data['pathId'])) {
            if (empty($data['pathId'])) {
                $task->setPath(null);
            } else {
                $path = $this->pathRepository->find($data['pathId']);
                if ($path) {
                    $task->setPath($path);
                }
            }
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

        // Mettre à jour les événements Planning
        try {
            $this->taskPlanningService->updatePlanningForTask($task);
        } catch (\Exception $e) {
            // Logger l'erreur mais ne pas faire échouer la mise à jour
        }

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

        // Vérifier que l'objectif peut être modifié (pas encore validé par le coach)
        $objective = $task->getObjective();
        if (!$objective) {
            return new JsonResponse(['success' => false, 'message' => 'Objectif non trouvé'], 404);
        }

        // Vérifier que l'objectif appartient au coach
        if ($objective->getCoach() !== $coach) {
            return new JsonResponse(['success' => false, 'message' => 'Vous n\'avez pas le droit de supprimer cette tâche'], 403);
        }

        // Vérifier si on peut modifier des tâches pour cet objectif (statut)
        // La suppression n'est possible que si l'objectif n'est pas encore validé
        if (!$objective->canModifyTasks()) {
            return new JsonResponse([
                'success' => false, 
                'message' => 'Impossible de supprimer cette tâche. ' . $objective->getStatusMessage()
            ], 403);
        }

        // Vérifier que la tâche n'est pas terminée
        // Les tâches terminées ne peuvent pas être supprimées
        if ($task->getStatus() === Task::STATUS_COMPLETED) {
            return new JsonResponse([
                'success' => false, 
                'message' => 'Impossible de supprimer une tâche terminée.'
            ], 403);
        }

        // Supprimer les événements Planning associés
        try {
            $this->taskPlanningService->removePlanningForTask($task);
        } catch (\Exception $e) {
            // Logger l'erreur mais continuer la suppression
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
        
        // Sauvegarder l'ancien statut pour détecter le changement
        $oldStatus = $task->getStatus();
        
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

        // Notifier si la tâche vient d'être validée (passée à completed)
        try {
            if ($oldStatus !== Task::STATUS_COMPLETED && $task->getStatus() === Task::STATUS_COMPLETED) {
                $this->notificationService->notifyTaskValidated($task);
            }
        } catch (\Exception $e) {
            error_log('Erreur notification tâche validée: ' . $e->getMessage());
        }

        // Mettre à jour les événements Planning si la fréquence a changé
        try {
            $this->taskPlanningService->updatePlanningForTask($task);
        } catch (\Exception $e) {
            // Logger l'erreur mais ne pas faire échouer la mise à jour
        }

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


