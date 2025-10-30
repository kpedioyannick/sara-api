<?php

namespace App\Controller\Coach;

use App\Entity\Proof;
use App\Entity\Task;
use App\Repository\ProofRepository;
use App\Repository\TaskRepository;
use App\Service\FileStorageService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/coach/proofs')]
class ProofController extends BaseCoachController
{
    public function __construct(
        private ProofRepository $proofRepository,
        private TaskRepository $taskRepository,
        private FileStorageService $fileStorageService,
        private ValidatorInterface $validator
    ) {}

    #[Route('/upload', name: 'coach_proofs_upload', methods: ['POST'])]
    public function uploadProof(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            if (!$data) {
                return $this->errorResponse('Invalid JSON', Response::HTTP_BAD_REQUEST);
            }

            $requiredFields = ['task_id', 'title', 'type'];
            foreach ($requiredFields as $field) {
                if (!isset($data[$field]) || empty($data[$field])) {
                    return $this->errorResponse("Field '{$field}' is required", Response::HTTP_BAD_REQUEST);
                }
            }

            // Récupérer la tâche
            $task = $this->taskRepository->find($data['task_id']);
            if (!$task) {
                return $this->errorResponse('Task not found', Response::HTTP_NOT_FOUND);
            }

            // Vérifier que la tâche appartient au coach
            $coach = $this->getCoach();
            if ($task->getCoach() !== $coach) {
                return $this->errorResponse('Access denied to this task', Response::HTTP_FORBIDDEN);
            }

            // Vérifier que la tâche nécessite des preuves
            if (!$task->isRequiresProof()) {
                return $this->errorResponse('This task does not require proof', Response::HTTP_BAD_REQUEST);
            }

            $proof = new Proof();
            $proof->setTitle($data['title']);
            $proof->setDescription($data['description'] ?? null);
            $proof->setType($data['type']);
            $proof->setTask($task);
            $proof->setSubmittedBy($coach);

            // Gestion des fichiers
            if (isset($data['file_base64']) && !empty($data['file_base64'])) {
                // Upload base64
                $this->handleBase64Upload($proof, $data);
            } elseif (isset($data['file_path']) && !empty($data['file_path'])) {
                // Fichier déjà uploadé
                $proof->setFilePath($data['file_path']);
                $proof->setFileUrl($this->fileStorageService->generateSecureUrl($data['file_path']));
            } elseif (isset($data['content']) && !empty($data['content'])) {
                // Contenu texte
                $proof->setContent($data['content']);
            } else {
                return $this->errorResponse('No proof content provided', Response::HTTP_BAD_REQUEST);
            }

            $errors = $this->validator->validate($proof);
            if (count($errors) > 0) {
                $errorMessages = [];
                foreach ($errors as $error) {
                    $errorMessages[] = $error->getMessage();
                }
                return $this->errorResponse('Validation failed', Response::HTTP_BAD_REQUEST, $errorMessages);
            }

            $this->proofRepository->save($proof, true);

            return $this->successResponse($proof->toArray(), 'Proof uploaded successfully', Response::HTTP_CREATED);

        } catch (\Exception $e) {
            return $this->errorResponse('Proof upload failed: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/{id}', name: 'coach_proofs_show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $proof = $this->proofRepository->find($id);
        
        if (!$proof) {
            return $this->errorResponse('Proof not found', Response::HTTP_NOT_FOUND);
        }

        // Vérifier que la preuve appartient à une tâche du coach
        $coach = $this->getCoach();
        if ($proof->getTask()->getCoach() !== $coach) {
            return $this->errorResponse('Access denied to this proof', Response::HTTP_FORBIDDEN);
        }

        return $this->successResponse($proof->toArray(), 'Proof retrieved successfully');
    }

    #[Route('/{id}', name: 'coach_proofs_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $proof = $this->proofRepository->find($id);
        
        if (!$proof) {
            return $this->errorResponse('Proof not found', Response::HTTP_NOT_FOUND);
        }

        // Vérifier que la preuve appartient à une tâche du coach
        $coach = $this->getCoach();
        if ($proof->getTask()->getCoach() !== $coach) {
            return $this->errorResponse('Access denied to this proof', Response::HTTP_FORBIDDEN);
        }

        // Supprimer le fichier physique s'il existe
        if ($proof->getFilePath()) {
            $this->fileStorageService->deleteFile($proof->getFilePath());
        }

        $this->proofRepository->remove($proof, true);

        return $this->successResponse(null, 'Proof deleted successfully');
    }

    #[Route('/task/{taskId}', name: 'coach_proofs_by_task', methods: ['GET'])]
    public function getByTask(int $taskId): JsonResponse
    {
        $task = $this->taskRepository->find($taskId);
        
        if (!$task) {
            return $this->errorResponse('Task not found', Response::HTTP_NOT_FOUND);
        }

        // Vérifier que la tâche appartient au coach
        $coach = $this->getCoach();
        if ($task->getCoach() !== $coach) {
            return $this->errorResponse('Access denied to this task', Response::HTTP_FORBIDDEN);
        }

        $proofs = $this->proofRepository->findBy(['task' => $task]);
        $data = array_map(fn($proof) => $proof->toArray(), $proofs);

        return $this->successResponse($data, 'Task proofs retrieved successfully');
    }

    private function handleBase64Upload(Proof $proof, array $data): void
    {
        $base64Data = $data['file_base64'];
        $fileName = $data['file_name'] ?? 'proof_' . uniqid();
        $mimeType = $data['mime_type'] ?? 'application/octet-stream';

        // Valider le type de fichier
        if (!$this->fileStorageService->validateFileType($mimeType, $proof->getType())) {
            throw new \Exception('Type de fichier non autorisé pour ce type de preuve');
        }

        // Valider la taille (estimer à partir de la taille base64)
        $fileSize = strlen($base64Data) * 3 / 4; // Approximation
        if (!$this->fileStorageService->validateFileSize($fileSize, $proof->getType())) {
            throw new \Exception('Fichier trop volumineux');
        }

        // Déterminer l'extension
        $extension = $this->getExtensionFromMimeType($mimeType);

        // Sauvegarder le fichier
        $filePath = $this->fileStorageService->saveBase64File($base64Data, $extension);
        
        $proof->setFilePath($filePath);
        $proof->setFileUrl($this->fileStorageService->generateSecureUrl($filePath));
        $proof->setFileName($fileName);
        $proof->setFileSize($fileSize);
        $proof->setMimeType($mimeType);
    }

    private function getExtensionFromMimeType(string $mimeType): string
    {
        $mimeToExt = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'audio/mpeg' => 'mp3',
            'audio/wav' => 'wav',
            'audio/ogg' => 'ogg',
            'video/mp4' => 'mp4',
            'video/avi' => 'avi',
            'application/pdf' => 'pdf',
            'text/plain' => 'txt'
        ];

        return $mimeToExt[$mimeType] ?? 'bin';
    }
}
