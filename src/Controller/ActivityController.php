<?php

namespace App\Controller;

use App\Controller\Trait\CoachTrait;
use App\Entity\Activity;
use App\Entity\ActivityCategory;
use App\Entity\Comment;
use App\Entity\ActivityImage;
use App\Entity\Coach;
use App\Entity\ParentUser;
use App\Entity\Specialist;
use App\Repository\ActivityCategoryRepository;
use App\Repository\CommentRepository;
use App\Repository\ActivityRepository;
use App\Repository\CoachRepository;
use App\Repository\ParentUserRepository;
use App\Repository\SpecialistRepository;
use App\Service\FileStorageService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Psr\Log\LoggerInterface;

class ActivityController extends AbstractController
{
    use CoachTrait;

    public function __construct(
        private readonly ActivityRepository $activityRepository,
        private readonly ActivityCategoryRepository $categoryRepository,
        private readonly CommentRepository $commentRepository,
        private readonly CoachRepository $coachRepository,
        private readonly ParentUserRepository $parentRepository,
        private readonly SpecialistRepository $specialistRepository,
        private readonly Security $security,
        private readonly EntityManagerInterface $em,
        private readonly ValidatorInterface $validator,
        private readonly FileStorageService $fileStorageService,
        private readonly LoggerInterface $logger
    ) {
    }

    #[Route('/admin/activities', name: 'admin_activities_list')]
    #[IsGranted('ROLE_COACH')]
    public function list(Request $request): Response
    {
        $coach = $this->getCurrentCoach($this->coachRepository, $this->security);
        
        if (!$coach) {
            throw $this->createNotFoundException('Aucun coach trouvé');
        }

        // Récupération des paramètres de filtrage
        $filters = [
            'createdBy' => $coach,
            'categoryId' => $request->query->getInt('categoryId', 0) ?: null,
            'ageRange' => $request->query->get('ageRange'),
            'type' => $request->query->get('type'),
            'workedPoint' => $request->query->get('workedPoint'),
            'search' => $request->query->get('search'),
        ];

        // Récupération des activités avec filtrage
        $activities = $this->activityRepository->findWithFilters($filters);

        // Conversion en tableau pour le template
        $activitiesData = array_map(fn($activity) => $activity->toArray(), $activities);

        // Récupérer toutes les catégories pour les filtres
        $categories = $this->categoryRepository->findActiveOrdered();
        $categoriesData = array_map(fn($cat) => $cat->toArray(), $categories);

        return $this->render('tailadmin/pages/activities/list.html.twig', [
            'pageTitle' => 'Liste des Activités | TailAdmin',
            'pageName' => 'activities',
            'activities' => $activitiesData,
            'categories' => $categoriesData,
            'filters' => $filters,
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => $this->generateUrl('admin_dashboard')],
            ],
        ]);
    }

    #[Route('/admin/activities/create', name: 'admin_activities_create', methods: ['POST'])]
    #[IsGranted('ROLE_COACH')]
    public function create(Request $request): JsonResponse
    {
        try {
            $coach = $this->getCurrentCoach($this->coachRepository, $this->security);
            if (!$coach) {
                return new JsonResponse(['success' => false, 'message' => 'Coach non trouvé'], 404);
            }

            $data = json_decode($request->getContent(), true);
            
            // Validation des champs requis
            if (empty($data['description'])) {
                return new JsonResponse(['success' => false, 'message' => 'La description est requise'], 400);
            }
            if (empty($data['duration'])) {
                return new JsonResponse(['success' => false, 'message' => 'La durée est requise'], 400);
            }
            if (empty($data['ageRange'])) {
                return new JsonResponse(['success' => false, 'message' => 'La tranche d\'âge est requise'], 400);
            }
            if (empty($data['type'])) {
                return new JsonResponse(['success' => false, 'message' => 'Le type est requis'], 400);
            }
            if (empty($data['categoryId'])) {
                return new JsonResponse(['success' => false, 'message' => 'La catégorie est requise'], 400);
            }

            // Récupérer la catégorie
            $category = $this->categoryRepository->find($data['categoryId']);
            if (!$category) {
                return new JsonResponse(['success' => false, 'message' => 'Catégorie non trouvée'], 400);
            }

            // Créer l'activité
            $activity = Activity::create([
                'description' => $data['description'],
                'duration' => $data['duration'],
                'ageRange' => $data['ageRange'],
                'type' => $data['type'],
                'objectives' => $data['objectives'] ?? [],
                'workedPoints' => $data['workedPoints'] ?? [],
            ], $coach, $category);

            // Validation
            $errors = $this->validator->validate($activity);
            if (count($errors) > 0) {
                $errorMessages = [];
                foreach ($errors as $error) {
                    $errorMessages[] = $error->getMessage();
                }
                return new JsonResponse(['success' => false, 'message' => implode(', ', $errorMessages)], 400);
            }

            $this->em->persist($activity);

            // Gérer les images uploadées
            if (isset($data['images']) && is_array($data['images'])) {
                foreach ($data['images'] as $index => $imageData) {
                    if (isset($imageData['filePath'])) {
                        $activityImage = new ActivityImage();
                        $activityImage->setFilePath($imageData['filePath']);
                        $activityImage->setCaption($imageData['caption'] ?? null);
                        $activityImage->setSortOrder($index);
                        $activityImage->setActivity($activity);
                        $this->em->persist($activityImage);
                    }
                }
            }

            $this->em->flush();

            return new JsonResponse([
                'success' => true, 
                'id' => $activity->getId(), 
                'message' => 'Activité créée avec succès'
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de la création d\'activité', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return new JsonResponse([
                'success' => false,
                'message' => 'Erreur serveur: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/admin/activities/{id}', name: 'admin_activities_detail', requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_COACH')]
    public function detail(int $id): Response
    {
        $coach = $this->getCurrentCoach($this->coachRepository, $this->security);
        
        if (!$coach) {
            throw $this->createNotFoundException('Aucun coach trouvé');
        }

        $activity = $this->activityRepository->find($id);
        
        if (!$activity) {
            throw $this->createNotFoundException('Activité non trouvée');
        }

        // Vérifier l'accès (seulement les activités créées par le coach)
        if ($activity->getCreatedBy() !== $coach) {
            throw $this->createAccessDeniedException('Accès refusé à cette activité');
        }

        // Récupérer les images et commentaires
        $images = $activity->getImages()->toArray();
        $imagesData = array_map(fn($img) => $img->toArray(), $images);

        $comments = $this->commentRepository->findByActivity($activity->getId());
        $commentsData = array_map(fn($comment) => $comment->toArray(), $comments);

        // Récupérer toutes les catégories pour le formulaire d'édition
        $categories = $this->categoryRepository->findActiveOrdered();
        $categoriesData = array_map(fn($cat) => $cat->toArray(), $categories);

        return $this->render('tailadmin/pages/activities/detail.html.twig', [
            'pageTitle' => 'Détail de l\'Activité | TailAdmin',
            'pageName' => 'activities',
            'activity' => $activity->toArray(),
            'images' => $imagesData,
            'comments' => $commentsData,
            'categories' => $categoriesData,
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => $this->generateUrl('admin_dashboard')],
                ['label' => 'Activités', 'url' => $this->generateUrl('admin_activities_list')],
            ],
        ]);
    }

    #[Route('/admin/activities/{id}/update', name: 'admin_activities_update', methods: ['POST'])]
    #[IsGranted('ROLE_COACH')]
    public function update(int $id, Request $request): JsonResponse
    {
        try {
            $coach = $this->getCurrentCoach($this->coachRepository, $this->security);
            if (!$coach) {
                return new JsonResponse(['success' => false, 'message' => 'Coach non trouvé'], 404);
            }

            $activity = $this->activityRepository->find($id);
            if (!$activity || $activity->getCreatedBy() !== $coach) {
                return new JsonResponse(['success' => false, 'message' => 'Activité non trouvée'], 404);
            }

            $data = json_decode($request->getContent(), true);

            // Mettre à jour les champs
            if (isset($data['description'])) {
                $activity->setDescription($data['description']);
            }
            if (isset($data['duration'])) {
                $activity->setDuration($data['duration']);
            }
            if (isset($data['ageRange'])) {
                $activity->setAgeRange($data['ageRange']);
            }
            if (isset($data['type'])) {
                $activity->setType($data['type']);
            }
            if (isset($data['objectives'])) {
                $activity->setObjectives($data['objectives']);
            }
            if (isset($data['workedPoints'])) {
                $activity->setWorkedPoints($data['workedPoints']);
            }

            // Mettre à jour la catégorie si nécessaire
            if (isset($data['categoryId'])) {
                $category = $this->categoryRepository->find($data['categoryId']);
                if ($category) {
                    $activity->setCategory($category);
                }
            }

            $activity->setUpdatedAt(new \DateTimeImmutable());

            // Validation
            $errors = $this->validator->validate($activity);
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
                'message' => 'Activité mise à jour avec succès'
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de la mise à jour d\'activité', [
                'activity_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return new JsonResponse([
                'success' => false,
                'message' => 'Erreur serveur: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/admin/activities/{id}/delete', name: 'admin_activities_delete', methods: ['POST'])]
    #[IsGranted('ROLE_COACH')]
    public function delete(int $id): JsonResponse
    {
        try {
            $coach = $this->getCurrentCoach($this->coachRepository, $this->security);
            if (!$coach) {
                return new JsonResponse(['success' => false, 'message' => 'Coach non trouvé'], 404);
            }

            $activity = $this->activityRepository->find($id);
            if (!$activity || $activity->getCreatedBy() !== $coach) {
                return new JsonResponse(['success' => false, 'message' => 'Activité non trouvée'], 404);
            }

            // Supprimer les images associées
            foreach ($activity->getImages() as $image) {
                $this->fileStorageService->deleteFile($image->getFilePath());
            }

            $this->em->remove($activity);
            $this->em->flush();

            return new JsonResponse([
                'success' => true,
                'message' => 'Activité supprimée avec succès'
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de la suppression d\'activité', [
                'activity_id' => $id,
                'error' => $e->getMessage()
            ]);
            return new JsonResponse([
                'success' => false,
                'message' => 'Erreur serveur: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/admin/activities/{id}/comment', name: 'admin_activities_comment', methods: ['POST'])]
    #[IsGranted('ROLE_COACH')]
    public function addComment(int $id, Request $request): JsonResponse
    {
        try {
            $coach = $this->getCurrentCoach($this->coachRepository, $this->security);
            if (!$coach) {
                return new JsonResponse(['success' => false, 'message' => 'Coach non trouvé'], 404);
            }

            $activity = $this->activityRepository->find($id);
            if (!$activity || $activity->getCreatedBy() !== $coach) {
                return new JsonResponse(['success' => false, 'message' => 'Activité non trouvée'], 404);
            }

            $data = json_decode($request->getContent(), true);

            if (empty($data['content'])) {
                return new JsonResponse(['success' => false, 'message' => 'Le contenu du commentaire est requis'], 400);
            }

            $comment = Comment::createForUser([
                'content' => $data['content'],
            ], $coach, null, $activity);

            $this->em->persist($comment);
            $this->em->flush();

            return new JsonResponse([
                'success' => true,
                'comment' => $comment->toArray(),
                'message' => 'Commentaire ajouté avec succès'
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de l\'ajout de commentaire', [
                'activity_id' => $id,
                'error' => $e->getMessage()
            ]);
            return new JsonResponse([
                'success' => false,
                'message' => 'Erreur serveur: ' . $e->getMessage()
            ], 500);
        }
    }


    #[Route('/admin/activities/{id}/upload-image', name: 'admin_activities_upload_image', methods: ['POST'])]
    #[IsGranted('ROLE_COACH')]
    public function uploadImage(int $id, Request $request): JsonResponse
    {
        try {
            $coach = $this->getCurrentCoach($this->coachRepository, $this->security);
            if (!$coach) {
                return new JsonResponse(['success' => false, 'message' => 'Coach non trouvé'], 404);
            }

            $activity = $this->activityRepository->find($id);
            if (!$activity || $activity->getCreatedBy() !== $coach) {
                return new JsonResponse(['success' => false, 'message' => 'Activité non trouvée'], 404);
            }

            $file = $request->files->get('image');
            if (!$file) {
                return new JsonResponse(['success' => false, 'message' => 'Aucun fichier fourni'], 400);
            }

            $filePath = $this->fileStorageService->uploadFile($file, 'activities');
            $caption = $request->request->get('caption', '');

            $activityImage = new ActivityImage();
            $activityImage->setFilePath($filePath);
            $activityImage->setCaption($caption);
            $activityImage->setSortOrder($activity->getImages()->count());
            $activityImage->setActivity($activity);

            $this->em->persist($activityImage);
            $this->em->flush();

            return new JsonResponse([
                'success' => true,
                'image' => $activityImage->toArray(),
                'message' => 'Image ajoutée avec succès'
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de l\'upload d\'image', [
                'activity_id' => $id,
                'error' => $e->getMessage()
            ]);
            return new JsonResponse([
                'success' => false,
                'message' => 'Erreur serveur: ' . $e->getMessage()
            ], 500);
        }
    }
}

