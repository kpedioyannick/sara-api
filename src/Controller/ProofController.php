<?php

namespace App\Controller;

use App\Controller\Trait\CoachTrait;
use App\Entity\Planning;
use App\Entity\Proof;
use App\Entity\Task;
use App\Repository\CoachRepository;
use App\Repository\PlanningRepository;
use App\Repository\ProofRepository;
use App\Repository\TaskRepository;
use App\Service\FileStorageService;
use App\Service\NotificationService;
use App\Service\PermissionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ProofController extends AbstractController
{
    use CoachTrait;

    public function __construct(
        private readonly ProofRepository $proofRepository,
        private readonly TaskRepository $taskRepository,
        private readonly PlanningRepository $planningRepository,
        private readonly CoachRepository $coachRepository,
        private readonly Security $security,
        private readonly EntityManagerInterface $em,
        private readonly FileStorageService $fileStorageService,
        private readonly ValidatorInterface $validator,
        private readonly PermissionService $permissionService,
        private readonly NotificationService $notificationService
    ) {
    }

    #[Route('/admin/tasks/{taskId}/proofs', name: 'admin_task_proofs_list')]
    public function listForTask(int $taskId): Response
    {
        $coach = $this->getCurrentCoach($this->coachRepository, $this->security);
        if (!$coach) {
            return $this->redirectToRoute('app_login');
        }

        $task = $this->taskRepository->find($taskId);
        if (!$task || $task->getObjective()?->getCoach() !== $coach) {
            throw $this->createNotFoundException('Tâche non trouvée');
        }

        $proofs = $task->getProofs()->toArray();
        $proofsData = array_map(fn($proof) => $proof->toArray(), $proofs);

        return $this->render('tailadmin/pages/proofs/list.html.twig', [
            'proofs' => $proofsData,
            'taskId' => $taskId,
            'planningId' => null,
            'task' => $task,
        ]);
    }

    #[Route('/admin/plannings/{planningId}/proofs', name: 'admin_planning_proofs_list')]
    public function listForPlanning(int $planningId): Response
    {
        $coach = $this->getCurrentCoach($this->coachRepository, $this->security);
        if (!$coach) {
            return $this->redirectToRoute('app_login');
        }

        $planning = $this->planningRepository->find($planningId);
        if (!$planning || $planning->getCoach() !== $coach) {
            throw $this->createNotFoundException('Événement non trouvé');
        }

        $proofs = $planning->getProofs()->toArray();
        $proofsData = array_map(fn($proof) => $proof->toArray(), $proofs);

        return $this->render('tailadmin/pages/proofs/list.html.twig', [
            'proofs' => $proofsData,
            'taskId' => null,
            'planningId' => $planningId,
            'planning' => $planning,
        ]);
    }

    #[Route('/admin/tasks/{taskId}/proofs/create', name: 'admin_proofs_create_for_task', methods: ['POST'])]
    public function createForTask(int $taskId, Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['success' => false, 'message' => 'Vous devez être connecté'], 403);
        }

        $task = $this->taskRepository->find($taskId);
        if (!$task) {
            return new JsonResponse(['success' => false, 'message' => 'Tâche non trouvée'], 404);
        }

        // Vérifier que l'utilisateur peut compléter cette tâche
        if (!$this->permissionService->canCompleteTask($user, $task)) {
            return new JsonResponse(['success' => false, 'message' => 'Vous n\'avez pas le droit de compléter cette tâche'], 403);
        }

        // Vérifier que la tâche n'est pas terminée - on ne peut ajouter des preuves que si la tâche est "pending" ou "in_progress"
        if ($task->getStatus() === Task::STATUS_COMPLETED) {
            return new JsonResponse(['success' => false, 'message' => 'Impossible d\'ajouter une preuve à une tâche terminée'], 403);
        }

        $data = json_decode($request->getContent(), true);
        
        $proof = new Proof();
        $proof->setTitle($data['title'] ?? '');
        $proof->setDescription($data['description'] ?? null);
        $proof->setType($data['type'] ?? 'text');
        $proof->setTask($task);
        $proof->setSubmittedBy($user);

        // Gérer la date de soumission (submittedAt)
        // Si un planning est associé à la tâche, utiliser planning.startDate
        $planning = null;
        if (isset($data['planningId'])) {
            $planning = $this->planningRepository->find($data['planningId']);
        }
        
        if ($planning && $planning->getStartDate()) {
            // Utiliser la date de l'événement Planning
            $proof->setSubmittedAt($planning->getStartDate());
            $proof->setPlanning($planning);
        } elseif (isset($data['submittedAt']) && !empty($data['submittedAt'])) {
            // Depuis objectifs : utiliser submittedAt du formulaire
            try {
                $proof->setSubmittedAt(new \DateTimeImmutable($data['submittedAt']));
            } catch (\Exception $e) {
                // En cas d'erreur, utiliser la date du jour
                $proof->setSubmittedAt(new \DateTimeImmutable());
            }
        } else {
            // Par défaut : date du jour
            $proof->setSubmittedAt(new \DateTimeImmutable());
        }
        
        // Gestion du contenu selon le type
        if ($data['type'] === 'text' && isset($data['content'])) {
            $proof->setContent($data['content']);
        } elseif (isset($data['fileBase64']) && isset($data['fileName'])) {
            // Upload de fichier base64
            try {
                $extension = pathinfo($data['fileName'], PATHINFO_EXTENSION);
                $filePath = $this->fileStorageService->saveBase64File($data['fileBase64'], $extension);
                $proof->setFilePath($filePath);
                $proof->setFileName($data['fileName']);
                $proof->setFileUrl($this->fileStorageService->generateSecureUrl($filePath));
                $proof->setFileSize($data['fileSize'] ?? null);
                $proof->setMimeType($data['mimeType'] ?? null);
            } catch (\Exception $e) {
                return new JsonResponse(['success' => false, 'message' => 'Erreur lors de l\'upload: ' . $e->getMessage()], 400);
            }
        }

        // Validation
        $errors = $this->validator->validate($proof);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            return new JsonResponse(['success' => false, 'message' => implode(', ', $errorMessages)], 400);
        }

        $this->em->persist($proof);
        
        // Mettre à jour le statut de la tâche selon son statut actuel
        // Si la tâche est en "pending", elle passe à "in_progress" après soumission de la preuve
        // Les autres changements de statut doivent être faits manuellement par l'utilisateur
        if ($task->getStatus() === Task::STATUS_PENDING) {
            $task->setStatus(Task::STATUS_IN_PROGRESS);
            $task->setUpdatedAt(new \DateTimeImmutable());
        }
        
        $this->em->flush();

        // Notifier le coach qu'une preuve a été soumise
        try {
            $this->notificationService->notifyProofSubmitted($task, $user);
        } catch (\Exception $e) {
            // Log l'erreur mais ne bloque pas la création de la preuve
            error_log('Erreur notification preuve soumise: ' . $e->getMessage());
        }

        return new JsonResponse(['success' => true, 'id' => $proof->getId(), 'message' => 'Preuve créée avec succès']);
    }

    #[Route('/admin/plannings/{planningId}/proofs/create', name: 'admin_proofs_create_for_planning', methods: ['POST'])]
    public function createForPlanning(int $planningId, Request $request): JsonResponse
    {
        $coach = $this->getCurrentCoach($this->coachRepository, $this->security);
        if (!$coach) {
            return new JsonResponse(['success' => false, 'message' => 'Coach non trouvé'], 404);
        }

        $planning = $this->planningRepository->find($planningId);
        if (!$planning || $planning->getCoach() !== $coach) {
            return new JsonResponse(['success' => false, 'message' => 'Événement non trouvé'], 404);
        }

        $data = json_decode($request->getContent(), true);
        
        $proof = new Proof();
        $proof->setTitle($data['title'] ?? '');
        $proof->setDescription($data['description'] ?? null);
        $proof->setType($data['type'] ?? 'text');
        $proof->setPlanning($planning);
        $proof->setSubmittedBy($coach);

        // Gérer la date de soumission (submittedAt)
        // Depuis planning : utiliser la date de l'événement (startDate du planning)
        if ($planning->getStartDate()) {
            $proof->setSubmittedAt($planning->getStartDate());
        } else {
            // Par défaut : date du jour
            $proof->setSubmittedAt(new \DateTimeImmutable());
        }

        // Gestion du contenu selon le type
        if ($data['type'] === 'text' && isset($data['content'])) {
            $proof->setContent($data['content']);
        } elseif (isset($data['fileBase64']) && isset($data['fileName'])) {
            // Upload de fichier base64
            try {
                $extension = pathinfo($data['fileName'], PATHINFO_EXTENSION);
                $filePath = $this->fileStorageService->saveBase64File($data['fileBase64'], $extension);
                $proof->setFilePath($filePath);
                $proof->setFileName($data['fileName']);
                $proof->setFileUrl($this->fileStorageService->generateSecureUrl($filePath));
                $proof->setFileSize($data['fileSize'] ?? null);
                $proof->setMimeType($data['mimeType'] ?? null);
            } catch (\Exception $e) {
                return new JsonResponse(['success' => false, 'message' => 'Erreur lors de l\'upload: ' . $e->getMessage()], 400);
            }
        }

        // Validation
        $errors = $this->validator->validate($proof);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            return new JsonResponse(['success' => false, 'message' => implode(', ', $errorMessages)], 400);
        }

        $this->em->persist($proof);
        $this->em->flush();

        return new JsonResponse(['success' => true, 'id' => $proof->getId(), 'message' => 'Preuve créée avec succès']);
    }

    #[Route('/admin/proofs/{id}/update', name: 'admin_proofs_update', methods: ['POST'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $coach = $this->getCurrentCoach($this->coachRepository, $this->security);
        if (!$coach) {
            return new JsonResponse(['success' => false, 'message' => 'Coach non trouvé'], 404);
        }

        $proof = $this->proofRepository->find($id);
        if (!$proof) {
            return new JsonResponse(['success' => false, 'message' => 'Preuve non trouvée'], 404);
        }

        // Vérifier que la preuve appartient au coach
        $task = $proof->getTask();
        $planning = $proof->getPlanning();
        if (($task && $task->getObjective()?->getCoach() !== $coach) || 
            ($planning && $planning->getCoach() !== $coach)) {
            return new JsonResponse(['success' => false, 'message' => 'Vous n\'avez pas le droit de modifier cette preuve'], 403);
        }

        $data = json_decode($request->getContent(), true);
        
        if (isset($data['title'])) {
            $proof->setTitle($data['title']);
        }
        if (isset($data['description'])) {
            $proof->setDescription($data['description']);
        }
        if (isset($data['type'])) {
            $proof->setType($data['type']);
        }
        if ($data['type'] === 'text' && isset($data['content'])) {
            $proof->setContent($data['content']);
        }
        
        // Gestion du remplacement de fichier
        if (isset($data['fileBase64']) && isset($data['fileName'])) {
            // Supprimer l'ancien fichier si existe
            if ($proof->getFilePath()) {
                $this->fileStorageService->deleteFile($proof->getFilePath());
            }
            
            try {
                $extension = pathinfo($data['fileName'], PATHINFO_EXTENSION);
                $filePath = $this->fileStorageService->saveBase64File($data['fileBase64'], $extension);
                $proof->setFilePath($filePath);
                $proof->setFileName($data['fileName']);
                $proof->setFileUrl($this->fileStorageService->generateSecureUrl($filePath));
                $proof->setFileSize($data['fileSize'] ?? null);
                $proof->setMimeType($data['mimeType'] ?? null);
            } catch (\Exception $e) {
                return new JsonResponse(['success' => false, 'message' => 'Erreur lors de l\'upload: ' . $e->getMessage()], 400);
            }
        }

        $proof->setUpdatedAt(new \DateTimeImmutable());

        // Validation
        $errors = $this->validator->validate($proof);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            return new JsonResponse(['success' => false, 'message' => implode(', ', $errorMessages)], 400);
        }

        $this->em->flush();

        return new JsonResponse(['success' => true, 'message' => 'Preuve modifiée avec succès']);
    }

    #[Route('/admin/proofs/{id}/delete', name: 'admin_proofs_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $coach = $this->getCurrentCoach($this->coachRepository, $this->security);
        if (!$coach) {
            return new JsonResponse(['success' => false, 'message' => 'Coach non trouvé'], 404);
        }

        $proof = $this->proofRepository->find($id);
        if (!$proof) {
            return new JsonResponse(['success' => false, 'message' => 'Preuve non trouvée'], 404);
        }

        // Vérifier que la preuve appartient au coach
        $task = $proof->getTask();
        $planning = $proof->getPlanning();
        if (($task && $task->getObjective()?->getCoach() !== $coach) || 
            ($planning && $planning->getCoach() !== $coach)) {
            return new JsonResponse(['success' => false, 'message' => 'Vous n\'avez pas le droit de supprimer cette preuve'], 403);
        }

        // Supprimer le fichier si existe
        if ($proof->getFilePath()) {
            $this->fileStorageService->deleteFile($proof->getFilePath());
        }

        $this->em->remove($proof);
        $this->em->flush();

        return new JsonResponse(['success' => true, 'message' => 'Preuve supprimée avec succès']);
    }

    #[Route('/api/tasks/{id}/proofs', name: 'api_task_proofs_history', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getProofsHistory(int $id, Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['success' => false, 'message' => 'Vous devez être connecté'], 403);
        }

        $task = $this->taskRepository->find($id);
        if (!$task) {
            return new JsonResponse(['success' => false, 'message' => 'Tâche non trouvée'], 404);
        }

        // Vérifier que l'utilisateur peut voir cette tâche
        if (!$this->permissionService->canViewTask($user, $task)) {
            return new JsonResponse(['success' => false, 'message' => 'Vous n\'avez pas le droit de voir cette tâche'], 403);
        }

        // Récupérer toutes les preuves de la tâche, triées par date de soumission (chronologique)
        $proofs = $this->proofRepository->createQueryBuilder('p')
            ->where('p.task = :task')
            ->setParameter('task', $task)
            ->orderBy('p.submittedAt', 'ASC')
            ->getQuery()
            ->getResult();

        $proofsData = array_map(fn($proof) => $proof->toArray(), $proofs);

        return new JsonResponse([
            'success' => true,
            'proofs' => $proofsData,
            'task' => [
                'id' => $task->getId(),
                'title' => $task->getTitle(),
                'description' => $task->getDescription(),
                'status' => $task->getStatus(),
                'objective' => $task->getObjective() ? [
                    'id' => $task->getObjective()->getId(),
                    'title' => $task->getObjective()->getTitle(),
                ] : null,
            ]
        ]);
    }
}

