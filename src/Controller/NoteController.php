<?php

namespace App\Controller;

use App\Entity\Note;
use App\Entity\NoteImage;
use App\Entity\Student;
use App\Enum\NoteType;
use App\Repository\NoteRepository;
use App\Repository\StudentRepository;
use App\Service\FileStorageService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class NoteController extends AbstractController
{
    public function __construct(
        private readonly NoteRepository $noteRepository,
        private readonly StudentRepository $studentRepository,
        private readonly EntityManagerInterface $em,
        private readonly FileStorageService $fileStorageService,
        private readonly ValidatorInterface $validator
    ) {
    }

    #[Route('/admin/students/{studentId}/notes', name: 'admin_students_notes_list', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function list(int $studentId): JsonResponse
    {
        $student = $this->studentRepository->find($studentId);
        if (!$student) {
            return new JsonResponse(['success' => false, 'message' => 'Élève non trouvé'], 404);
        }

        $notes = $this->noteRepository->findBy(['student' => $student], ['createdAt' => 'DESC']);

        return new JsonResponse([
            'success' => true,
            'notes' => array_map(fn($note) => $note->toArray(), $notes)
        ]);
    }

    #[Route('/admin/students/{studentId}/notes', name: 'admin_students_notes_create', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function create(int $studentId, Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['success' => false, 'message' => 'Vous devez être connecté'], 403);
        }

        $student = $this->studentRepository->find($studentId);
        if (!$student) {
            return new JsonResponse(['success' => false, 'message' => 'Élève non trouvé'], 404);
        }

        $data = json_decode($request->getContent(), true);
        if (!$data) {
            return new JsonResponse(['success' => false, 'message' => 'Données invalides'], 400);
        }

        // Validation des champs requis
        if (empty($data['type']) || empty($data['text'])) {
            return new JsonResponse(['success' => false, 'message' => 'Le type et le texte sont requis'], 400);
        }

        // Vérifier que le type est valide
        try {
            $noteType = NoteType::from($data['type']);
        } catch (\ValueError $e) {
            return new JsonResponse(['success' => false, 'message' => 'Type de note invalide'], 400);
        }

        // Créer la note
        $note = new Note();
        $note->setType($noteType);
        $note->setText($data['text']);
        $note->setStudent($student);
        $note->setCreatedBy($user);
        $note->setUpdatedAt(new \DateTimeImmutable());

        // Gérer les images uploadées (base64)
        if (isset($data['images']) && is_array($data['images'])) {
            foreach ($data['images'] as $index => $imageData) {
                if (isset($imageData['fileBase64']) && isset($imageData['fileName'])) {
                    try {
                        $extension = pathinfo($imageData['fileName'], PATHINFO_EXTENSION) ?: 'jpg';
                        $filePath = $this->fileStorageService->saveBase64File(
                            $imageData['fileBase64'],
                            $extension,
                            'notes'
                        );

                        $noteImage = new NoteImage();
                        $noteImage->setFilePath($filePath);
                        $noteImage->setCaption($imageData['caption'] ?? null);
                        $noteImage->setSortOrder($index);
                        $noteImage->setNote($note);
                        $this->em->persist($noteImage);
                    } catch (\Exception $e) {
                        // Continue avec les autres images même si une échoue
                        continue;
                    }
                }
            }
        }

        // Validation
        $errors = $this->validator->validate($note);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            return new JsonResponse(['success' => false, 'message' => implode(', ', $errorMessages)], 400);
        }

        $this->em->persist($note);
        $this->em->flush();

        return new JsonResponse([
            'success' => true,
            'message' => 'Note créée avec succès',
            'note' => $note->toArray()
        ]);
    }

    #[Route('/admin/notes/{id}', name: 'admin_notes_update', methods: ['PUT', 'PATCH'])]
    #[IsGranted('ROLE_USER')]
    public function update(int $id, Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['success' => false, 'message' => 'Vous devez être connecté'], 403);
        }

        $note = $this->noteRepository->find($id);
        if (!$note) {
            return new JsonResponse(['success' => false, 'message' => 'Note non trouvée'], 404);
        }

        // Vérifier les permissions (seul le créateur peut modifier)
        if ($note->getCreatedBy() !== $user) {
            return new JsonResponse(['success' => false, 'message' => 'Vous n\'avez pas le droit de modifier cette note'], 403);
        }

        $data = json_decode($request->getContent(), true);
        if (!$data) {
            return new JsonResponse(['success' => false, 'message' => 'Données invalides'], 400);
        }

        // Mettre à jour le type si fourni
        if (isset($data['type'])) {
            try {
                $noteType = NoteType::from($data['type']);
                $note->setType($noteType);
            } catch (\ValueError $e) {
                return new JsonResponse(['success' => false, 'message' => 'Type de note invalide'], 400);
            }
        }

        // Mettre à jour le texte si fourni
        if (isset($data['text'])) {
            $note->setText($data['text']);
        }

        $note->setUpdatedAt(new \DateTimeImmutable());

        // Gérer les nouvelles images uploadées (base64)
        if (isset($data['images']) && is_array($data['images'])) {
            foreach ($data['images'] as $index => $imageData) {
                if (isset($imageData['fileBase64']) && isset($imageData['fileName'])) {
                    try {
                        $extension = pathinfo($imageData['fileName'], PATHINFO_EXTENSION) ?: 'jpg';
                        $filePath = $this->fileStorageService->saveBase64File(
                            $imageData['fileBase64'],
                            $extension,
                            'notes'
                        );

                        $noteImage = new NoteImage();
                        $noteImage->setFilePath($filePath);
                        $noteImage->setCaption($imageData['caption'] ?? null);
                        $noteImage->setSortOrder($note->getImages()->count() + $index);
                        $noteImage->setNote($note);
                        $this->em->persist($noteImage);
                    } catch (\Exception $e) {
                        // Continue avec les autres images même si une échoue
                        continue;
                    }
                }
            }
        }

        // Supprimer les images si demandé
        if (isset($data['deletedImageIds']) && is_array($data['deletedImageIds'])) {
            foreach ($data['deletedImageIds'] as $imageId) {
                $image = $this->em->getRepository(NoteImage::class)->find($imageId);
                if ($image && $image->getNote() === $note) {
                    $this->em->remove($image);
                }
            }
        }

        // Validation
        $errors = $this->validator->validate($note);
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
            'message' => 'Note mise à jour avec succès',
            'note' => $note->toArray()
        ]);
    }

    #[Route('/admin/notes/{id}', name: 'admin_notes_delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_USER')]
    public function delete(int $id): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['success' => false, 'message' => 'Vous devez être connecté'], 403);
        }

        $note = $this->noteRepository->find($id);
        if (!$note) {
            return new JsonResponse(['success' => false, 'message' => 'Note non trouvée'], 404);
        }

        // Vérifier les permissions (seul le créateur peut supprimer)
        if ($note->getCreatedBy() !== $user) {
            return new JsonResponse(['success' => false, 'message' => 'Vous n\'avez pas le droit de supprimer cette note'], 403);
        }

        $this->em->remove($note);
        $this->em->flush();

        return new JsonResponse([
            'success' => true,
            'message' => 'Note supprimée avec succès'
        ]);
    }
}
