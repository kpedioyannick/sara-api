<?php

namespace App\Controller;

use App\Controller\Trait\CoachTrait;
use App\Entity\Activity;
use App\Entity\ActivityCategory;
use App\Entity\Comment;
use App\Entity\CommentImage;
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
use App\Service\PermissionService;
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
        private readonly LoggerInterface $logger,
        private readonly PermissionService $permissionService
    ) {
    }

    #[Route('/admin/activities', name: 'admin_activities_list')]
    #[IsGranted('ROLE_USER')]
    public function list(Request $request): Response
    {
        $user = $this->getUser();
        if (!$user) {
            throw $this->createAccessDeniedException('Vous devez être connecté');
        }

        // Récupération des paramètres de filtrage
        $filters = [
            'createdBy' => null, // Sera défini selon le rôle
            'categoryId' => $request->query->getInt('categoryId', 0) ?: null,
            'ageRange' => $request->query->get('ageRange'),
            'type' => $request->query->get('type'),
            'workedPoint' => $request->query->get('workedPoint'),
            'search' => $request->query->get('search'),
        ];

        // Tous les rôles (élève, spécialiste, coach et parent) peuvent voir toutes les activités
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

    #[Route('/admin/activities/new', name: 'admin_activities_new')]
    #[IsGranted('ROLE_USER')]
    public function new(): Response
    {
        $user = $this->getUser();
        if (!$user) {
            throw $this->createAccessDeniedException('Vous devez être connecté');
        }

        if (!$user->isCoach() && !$user->isSpecialist()) {
            throw $this->createAccessDeniedException('Vous n\'avez pas le droit de créer des activités');
        }

        return $this->render('tailadmin/pages/activities/create.html.twig', [
            'pageTitle' => 'Nouvelle Activité | TailAdmin',
            'pageName' => 'activities',
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => $this->generateUrl('admin_dashboard')],
                ['label' => 'Activités', 'url' => $this->generateUrl('admin_activities_list')],
            ],
        ]);
    }

    #[Route('/admin/activities/create', name: 'admin_activities_create', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function create(Request $request): JsonResponse
    {
        try {
            $user = $this->getUser();
            if (!$user) {
                return new JsonResponse(['success' => false, 'message' => 'Vous devez être connecté'], 403);
            }

            // Seuls les coaches et spécialistes peuvent créer des activités
            $createdBy = null;
            if ($user->isCoach()) {
                $coach = $this->getCurrentCoach($this->coachRepository, $this->security);
                if (!$coach) {
                    return new JsonResponse(['success' => false, 'message' => 'Coach non trouvé'], 404);
                }
                $createdBy = $coach;
            } elseif ($user->isSpecialist()) {
                $createdBy = $user;
            } else {
                return new JsonResponse(['success' => false, 'message' => 'Vous n\'avez pas le droit de créer des activités'], 403);
            }

            $data = json_decode($request->getContent(), true);
            
            // Validation des champs requis
            if (empty($data['title'])) {
                return new JsonResponse(['success' => false, 'message' => 'Le titre est requis'], 400);
            }
            if (empty($data['description'])) {
                return new JsonResponse(['success' => false, 'message' => 'La description est requise'], 400);
            }
            if (empty($data['duration'])) {
                return new JsonResponse(['success' => false, 'message' => 'La durée est requise'], 400);
            }
            if (empty($data['ageRange'])) {
                return new JsonResponse(['success' => false, 'message' => 'La tranche d\'âge est requise'], 400);
            }
            $category = null;
            if (!empty($data['categoryId'])) {
                $category = $this->categoryRepository->find($data['categoryId']);
                if (!$category) {
                    return new JsonResponse(['success' => false, 'message' => 'Catégorie non trouvée'], 400);
                }
            }

            // Créer l'activité
            $activity = Activity::create([
                'title' => $data['title'],
                'description' => $data['description'],
                'duration' => $data['duration'],
                'ageRange' => $data['ageRange'],
                'type' => $data['type'] ?? Activity::TYPE_INDIVIDUAL,
                'objectives' => $data['objectives'] ?? [],
                'workedPoints' => $data['workedPoints'] ?? [],
                'links' => $data['links'] ?? [],
            ], $createdBy, $category);

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

            // Gérer les images uploadées (base64)
            if (isset($data['images']) && is_array($data['images'])) {
                foreach ($data['images'] as $index => $imageData) {
                    if (isset($imageData['fileBase64']) && isset($imageData['fileName'])) {
                        try {
                            $extension = pathinfo($imageData['fileName'], PATHINFO_EXTENSION) ?: 'jpg';
                            $filePath = $this->fileStorageService->saveBase64File(
                                $imageData['fileBase64'],
                                $extension,
                                'activities'
                            );
                            
                            $activityImage = new ActivityImage();
                            $activityImage->setFilePath($filePath);
                            $activityImage->setCaption($imageData['caption'] ?? null);
                            $activityImage->setSortOrder($index);
                            $activityImage->setActivity($activity);
                            $this->em->persist($activityImage);
                        } catch (\Exception $e) {
                            $this->logger->error('Erreur lors de l\'upload d\'image d\'activité', [
                                'error' => $e->getMessage(),
                                'index' => $index
                            ]);
                            // Continue avec les autres images même si une échoue
                        }
                    } elseif (isset($imageData['filePath'])) {
                        // Ancien format (filePath direct)
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
    #[IsGranted('ROLE_USER')]
    public function detail(int $id): Response
    {
        $user = $this->getUser();
        if (!$user) {
            throw $this->createAccessDeniedException('Vous devez être connecté');
        }

        $activity = $this->activityRepository->find($id);
        
        if (!$activity) {
            throw $this->createNotFoundException('Activité non trouvée');
        }

        // Vérifier les permissions d'accès
        if (!$this->permissionService->canViewActivity($user, $activity)) {
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

    #[Route('/admin/activities/{id}/edit', name: 'admin_activities_edit', requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_USER')]
    public function edit(int $id): Response
    {
        $user = $this->getUser();
        if (!$user) {
            throw $this->createAccessDeniedException('Vous devez être connecté');
        }

        $activity = $this->activityRepository->find($id);
        
        if (!$activity) {
            throw $this->createNotFoundException('Activité non trouvée');
        }

        // Vérifier les permissions de modification
        if (!$this->permissionService->canModifyActivity($user, $activity)) {
            throw $this->createAccessDeniedException('Vous n\'avez pas le droit de modifier cette activité');
        }

        // Vérifier si on peut modifier cette activité (statut)
        if (!$activity->canModify()) {
            $this->addFlash('error', 'Impossible de modifier cette activité. ' . $activity->getStatusMessage());
            return $this->redirectToRoute('admin_activities_detail', ['id' => $id]);
        }

        // Récupérer toutes les catégories pour le formulaire d'édition
        $categories = $this->categoryRepository->findActiveOrdered();
        $categoriesData = array_map(fn($cat) => $cat->toArray(), $categories);

        return $this->render('tailadmin/pages/activities/edit.html.twig', [
            'pageTitle' => 'Modifier l\'Activité | TailAdmin',
            'pageName' => 'activities',
            'activity' => $activity->toArray(),
            'categories' => $categoriesData,
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => $this->generateUrl('admin_dashboard')],
                ['label' => 'Activités', 'url' => $this->generateUrl('admin_activities_list')],
                ['label' => 'Détail', 'url' => $this->generateUrl('admin_activities_detail', ['id' => $id])],
            ],
        ]);
    }

    #[Route('/admin/activities/{id}/update', name: 'admin_activities_update', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function update(int $id, Request $request): JsonResponse
    {
        try {
            $user = $this->getUser();
            if (!$user) {
                return new JsonResponse(['success' => false, 'message' => 'Vous devez être connecté'], 403);
            }

            $activity = $this->activityRepository->find($id);
            if (!$activity) {
                return new JsonResponse(['success' => false, 'message' => 'Activité non trouvée'], 404);
            }

            // Vérifier les permissions de modification
            if (!$this->permissionService->canModifyActivity($user, $activity)) {
                return new JsonResponse(['success' => false, 'message' => 'Vous n\'avez pas le droit de modifier cette activité'], 403);
            }

            // Vérifier si on peut modifier cette activité (statut)
            if (!$activity->canModify()) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Impossible de modifier cette activité. ' . $activity->getStatusMessage()
                ], 403);
            }

            $data = json_decode($request->getContent(), true);

            // Mettre à jour les champs
            if (isset($data['title'])) {
                $activity->setTitle($data['title']);
            }
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

            // Seuls le coach et le spécialiste peuvent changer le statut d'une activité (toutes les activités)
            if (isset($data['status'])) {
                if ($user->isCoach() || $user->isSpecialist()) {
                    // Le coach et le spécialiste peuvent changer le statut de toutes les activités
                    // Pas de vérification supplémentaire nécessaire, canModifyActivity gère déjà les permissions
                } else {
                    return new JsonResponse(['success' => false, 'message' => 'Seul le coach ou le spécialiste peut changer le statut d\'une activité'], 403);
                }
                // Valider que le statut est valide
                if (!in_array($data['status'], array_keys(Activity::STATUSES))) {
                    return new JsonResponse(['success' => false, 'message' => 'Statut invalide'], 400);
                }
                $activity->setStatus($data['status']);
            }

            // Mettre à jour la catégorie si nécessaire
            if (array_key_exists('categoryId', $data)) {
                if ($data['categoryId']) {
                    $category = $this->categoryRepository->find($data['categoryId']);
                    if ($category) {
                        $activity->setCategory($category);
                    }
                } else {
                    $activity->setCategory(null);
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

            // Si c'est une requête AJAX, retourner JSON
            if ($request->isXmlHttpRequest() || $request->headers->get('Content-Type') === 'application/json') {
                return new JsonResponse([
                    'success' => true,
                    'message' => 'Activité mise à jour avec succès'
                ]);
            }

            // Sinon, rediriger vers la page de détail
            $this->addFlash('success', 'Activité mise à jour avec succès');
            return $this->redirectToRoute('admin_activities_detail', ['id' => $id]);
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
    #[IsGranted('ROLE_USER')]
    public function delete(int $id): JsonResponse
    {
        try {
            $user = $this->getUser();
            if (!$user) {
                return new JsonResponse(['success' => false, 'message' => 'Vous devez être connecté'], 403);
            }

            $activity = $this->activityRepository->find($id);
            if (!$activity) {
                return new JsonResponse(['success' => false, 'message' => 'Activité non trouvée'], 404);
            }

            // Vérifier les permissions de modification
            if (!$this->permissionService->canModifyActivity($user, $activity)) {
                return new JsonResponse(['success' => false, 'message' => 'Vous n\'avez pas le droit de supprimer cette activité'], 403);
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
    #[IsGranted('ROLE_USER')]
    public function addComment(int $id, Request $request): JsonResponse
    {
        try {
            $user = $this->getUser();
            if (!$user) {
                return new JsonResponse(['success' => false, 'message' => 'Vous devez être connecté'], 403);
            }

            // Seuls les coaches et spécialistes peuvent ajouter des commentaires
            if (!$user->isCoach() && !$user->isSpecialist()) {
                return new JsonResponse(['success' => false, 'message' => 'Vous n\'avez pas le droit d\'ajouter un commentaire'], 403);
            }

            $activity = $this->activityRepository->find($id);
            if (!$activity) {
                return new JsonResponse(['success' => false, 'message' => 'Activité non trouvée'], 404);
            }

            // Vérifier les permissions
            if (!$this->permissionService->canModifyActivity($user, $activity)) {
                return new JsonResponse(['success' => false, 'message' => 'Vous n\'avez pas le droit d\'ajouter un commentaire à cette activité'], 403);
            }

            // Récupérer le coach ou spécialiste pour créer le commentaire
            $commentUser = null;
            if ($user->isCoach()) {
                $commentUser = $this->getCurrentCoach($this->coachRepository, $this->security);
                if (!$commentUser) {
                    return new JsonResponse(['success' => false, 'message' => 'Coach non trouvé'], 404);
                }
            } elseif ($user->isSpecialist()) {
                $commentUser = $user;
            }

            // Gérer les données JSON ou FormData
            $content = null;
            $images = [];
            
            if ($request->headers->get('Content-Type') && str_contains($request->headers->get('Content-Type'), 'application/json')) {
                $data = json_decode($request->getContent(), true);
                $content = $data['content'] ?? null;
            } else {
                // FormData
                $content = $request->request->get('content');
                $imageFiles = $request->files->get('images', []);
                if (!is_array($imageFiles)) {
                    $imageFiles = $imageFiles ? [$imageFiles] : [];
                }
                $images = $imageFiles;
            }

            if (empty($content)) {
                return new JsonResponse(['success' => false, 'message' => 'Le contenu du commentaire est requis'], 400);
            }

            $comment = Comment::createForUser([
                'content' => $content,
            ], $commentUser, null, $activity);

            // Gérer les images uploadées
            if (!empty($images)) {
                foreach ($images as $index => $imageFile) {
                    if ($imageFile && $imageFile->isValid()) {
                        try {
                            $filePath = $this->fileStorageService->uploadFile($imageFile, 'comments');
                            $commentImage = new CommentImage();
                            $commentImage->setFilePath($filePath);
                            $commentImage->setSortOrder($index);
                            $commentImage->setComment($comment);
                            $this->em->persist($commentImage);
                        } catch (\Exception $e) {
                            $this->logger->error('Erreur lors de l\'upload d\'image de commentaire', [
                                'error' => $e->getMessage()
                            ]);
                        }
                    }
                }
            }

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
    #[IsGranted('ROLE_USER')]
    public function uploadImage(int $id, Request $request): JsonResponse
    {
        try {
            $user = $this->getUser();
            if (!$user) {
                return new JsonResponse(['success' => false, 'message' => 'Vous devez être connecté'], 403);
            }

            // Seuls les coaches et spécialistes peuvent uploader des images
            if (!$user->isCoach() && !$user->isSpecialist()) {
                return new JsonResponse(['success' => false, 'message' => 'Vous n\'avez pas le droit d\'uploader une image'], 403);
            }

            $activity = $this->activityRepository->find($id);
            if (!$activity) {
                return new JsonResponse(['success' => false, 'message' => 'Activité non trouvée'], 404);
            }

            // Vérifier les permissions
            if (!$this->permissionService->canModifyActivity($user, $activity)) {
                return new JsonResponse(['success' => false, 'message' => 'Vous n\'avez pas le droit d\'uploader une image pour cette activité'], 403);
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

