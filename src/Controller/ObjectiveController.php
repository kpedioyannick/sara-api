<?php

namespace App\Controller;

use App\Controller\Trait\CoachTrait;
use App\Entity\Comment;
use App\Entity\Objective;
use App\Entity\Student;
use App\Form\ObjectiveType as ObjectiveFormType;
use App\Repository\ActivityRepository;
use App\Repository\CommentRepository;
use App\Repository\CoachRepository;
use App\Repository\FamilyRepository;
use App\Repository\ObjectiveRepository;
use App\Repository\PathRepository;
use App\Repository\SpecialistRepository;
use App\Repository\StudentRepository;
use App\Repository\TaskRepository;
use App\Service\SmartObjectiveService;
use App\Service\NotificationService;
use App\Service\PermissionService;
use App\Entity\Task;
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

class ObjectiveController extends AbstractController
{
    use CoachTrait;

    public function __construct(
        private readonly ObjectiveRepository $objectiveRepository,
        private readonly CoachRepository $coachRepository,
        private readonly Security $security,
        private readonly EntityManagerInterface $em,
        private readonly StudentRepository $studentRepository,
        private readonly FamilyRepository $familyRepository,
        private readonly SpecialistRepository $specialistRepository,
        private readonly ValidatorInterface $validator,
        private readonly CommentRepository $commentRepository,
        private readonly SmartObjectiveService $smartObjectiveService,
        private readonly TaskRepository $taskRepository,
        private readonly LoggerInterface $logger,
        private readonly PermissionService $permissionService,
        private readonly NotificationService $notificationService,
        private readonly ActivityRepository $activityRepository,
        private readonly PathRepository $pathRepository
    ) {
    }

    #[Route('/admin/objectives', name: 'admin_objectives_list')]
    #[IsGranted('ROLE_USER')]
    public function list(Request $request): Response
    {
        $user = $this->getUser();
        if (!$user) {
            throw $this->createAccessDeniedException('Vous devez être connecté');
        }

        // Récupération des paramètres de filtrage
        $search = $request->query->get('search', '');
        $status = $request->query->get('status');
        $studentId = $request->query->get('student');
        $profileType = $request->query->get('profileType'); // 'parent', 'specialist', 'student', ou null
        $selectedIds = $request->query->get('selectedIds', ''); // IDs séparés par des virgules

        // Récupération des objectifs selon le rôle de l'utilisateur
        if ($user->isCoach()) {
            // Si l'utilisateur est un coach, utiliser directement l'utilisateur connecté
            $coach = $user instanceof \App\Entity\Coach ? $user : $this->getCurrentCoach($this->coachRepository, $this->security);
            if (!$coach) {
                throw $this->createAccessDeniedException('Vous devez être un coach pour accéder à cette page');
            }
            $objectives = $this->objectiveRepository->findByCoachWithSearch(
                $coach,
                $search ?: null,
                $status ?: null
            );
        } else {
            // Pour les autres rôles, utiliser PermissionService
            $objectives = $this->permissionService->getAccessibleObjectives($user);
            // Filtrer par recherche si nécessaire
            if ($search) {
                $objectives = array_filter($objectives, function($objective) use ($search) {
                    return stripos($objective->getTitle(), $search) !== false 
                        || stripos($objective->getDescription(), $search) !== false
                        || stripos($objective->getStudent()->getPseudo(), $search) !== false;
                });
            }
            // Filtrer par statut si nécessaire
            if ($status) {
                $objectives = array_filter($objectives, function($objective) use ($status) {
                    return $objective->getStatus() === $status;
                });
            }
            // Trier par date de création décroissante
            usort($objectives, function($a, $b) {
                $dateA = $a->getCreatedAt();
                $dateB = $b->getCreatedAt();
                if ($dateA === null && $dateB === null) return 0;
                if ($dateA === null) return 1;
                if ($dateB === null) return -1;
                return $dateB <=> $dateA; // Ordre décroissant
            });
        }
        
        // Filtrer par élève si spécifié
        if ($studentId) {
            $objectives = array_filter($objectives, function($objective) use ($studentId) {
                return $objective->getStudent() && $objective->getStudent()->getId() == (int)$studentId;
            });
        }
        
        // Appliquer les filtres par profil si spécifiés (pour les coaches et les parents)
        if (($user->isCoach() || $user->isParent()) && $profileType && $selectedIds) {
            $ids = array_filter(array_map('intval', explode(',', $selectedIds)));
            if (!empty($ids)) {
                $filteredObjectives = [];
                foreach ($objectives as $objective) {
                    $shouldInclude = false;
                    
                    if ($profileType === 'parent') {
                        // Filtrer par parent (objectifs des enfants du parent)
                        $student = $objective->getStudent();
                        if ($student && $student->getFamily() && $student->getFamily()->getParent()) {
                            if (in_array($student->getFamily()->getParent()->getId(), $ids)) {
                                $shouldInclude = true;
                            }
                        }
                    } elseif ($profileType === 'specialist') {
                        // Filtrer par spécialiste (objectifs où le spécialiste est assigné à au moins une tâche)
                        foreach ($objective->getTasks() as $task) {
                            if ($task->getSpecialist() && in_array($task->getSpecialist()->getId(), $ids)) {
                                $shouldInclude = true;
                                break;
                            }
                        }
                    } elseif ($profileType === 'student') {
                        // Filtrer par élève
                        if ($objective->getStudent() && in_array($objective->getStudent()->getId(), $ids)) {
                            $shouldInclude = true;
                        }
                    }
                    
                    if ($shouldInclude) {
                        $filteredObjectives[] = $objective;
                    }
                }
                $objectives = $filteredObjectives;
            }
        }

        // Trier les objectifs par date de création décroissante (pour les coaches aussi, au cas où)
        if ($user->isCoach()) {
            usort($objectives, function($a, $b) {
                $dateA = $a->getCreatedAt();
                $dateB = $b->getCreatedAt();
                if ($dateA === null && $dateB === null) return 0;
                if ($dateA === null) return 1;
                if ($dateB === null) return -1;
                return $dateB <=> $dateA; // Ordre décroissant
            });
        }

        // Conversion en tableau pour le template avec commentaires
        $objectivesData = array_map(function($objective) {
            $data = $objective->toTemplateArray();
            // Ajouter les commentaires formatés
            $data['comments'] = array_map(fn($comment) => $comment->toArray(), $objective->getComments()->toArray());
            return $data;
        }, $objectives);
        
        // Récupérer les étudiants, parents et spécialistes selon le rôle
        $students = [];
        $parentsData = [];
        $specialistsData = [];
        $coachesData = [];

        if ($user->isCoach()) {
            $coach = $this->getCurrentCoach($this->coachRepository, $this->security);
            $students = $this->studentRepository->findByCoach($coach);
            $families = $this->familyRepository->findByCoachWithSearch($coach);
            foreach ($families as $family) {
                $parent = $family->getParent();
                if ($parent) {
                    $parentsData[] = [
                        'id' => $parent->getId(),
                        'firstName' => $parent->getFirstName(),
                        'lastName' => $parent->getLastName(),
                    ];
                }
            }
            $specialists = $this->specialistRepository->findAll();
            $specialistsData = array_map(fn($s) => [
                'id' => $s->getId(),
                'firstName' => $s->getFirstName(),
                'lastName' => $s->getLastName(),
            ], $specialists);
            $coachesData = [[
                'id' => $coach->getId(),
                'firstName' => $coach->getFirstName(),
                'lastName' => $coach->getLastName(),
            ]];
        } else {
            // Pour les autres rôles, utiliser PermissionService
            $students = $this->permissionService->getAccessibleStudents($user);
        }

        $studentsData = array_map(function($student) {
            $family = $student->getFamily();
            $familyIdentifier = $family ? $family->getFamilyIdentifier() : '';
            $parentLastName = $family && $family->getParent() ? $family->getParent()->getLastName() : '';
            return [
                'id' => $student->getId(),
                'firstName' => $student->getFirstName(),
                'lastName' => $student->getLastName(),
                'pseudo' => $student->getPseudo(),
                'class' => $student->getClass(),
                'familyIdentifier' => $familyIdentifier,
                'parentLastName' => $parentLastName,
            ];
        }, $students);

        // Vérifier si l'utilisateur peut utiliser l'IA (seul le coach peut créer des tâches)
        $canUseAI = $this->permissionService->canUseAI($user);

        return $this->render('tailadmin/pages/objectives/list.html.twig', [
            'pageTitle' => 'Objectifs',
            'pageName' => 'objectives',
            'objectives' => $objectivesData,
            'students' => $studentsData,
            'parents' => $parentsData,
            'specialists' => $specialistsData,
            'coaches' => $coachesData,
            'profileType' => $profileType,
            'selectedIds' => $selectedIds,
            'canUseAI' => $canUseAI,
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => $this->generateUrl('admin_dashboard')],
            ],
        ]);
    }

    #[Route('/admin/objectives/create', name: 'admin_objectives_create', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function create(Request $request): JsonResponse
    {
        try {
            $user = $this->getUser();
            if (!$user) {
                return new JsonResponse(['success' => false, 'message' => 'Vous devez être connecté'], 403);
            }

            $data = json_decode($request->getContent(), true);
            
            // Validation des champs requis
            if (empty($data['description'])) {
                return new JsonResponse(['success' => false, 'message' => 'La description est requise'], 400);
            }
            if (empty($data['studentId'])) {
                return new JsonResponse(['success' => false, 'message' => 'L\'élève est requis'], 400);
            }
            // Le champ category n'est plus obligatoire, on utilise 'general' par défaut
            if (empty($data['category'])) {
                $data['category'] = 'general';
            }

            // Récupérer l'élève
            $student = $this->studentRepository->find($data['studentId']);
            if (!$student) {
                return new JsonResponse(['success' => false, 'message' => 'Élève non trouvé'], 400);
            }

            // Vérifier les permissions de création
            if (!$this->permissionService->canCreateObjective($user, $student)) {
                return new JsonResponse(['success' => false, 'message' => 'Vous n\'avez pas le droit de créer un objectif pour cet élève'], 403);
            }

            // Récupérer le coach (soit l'utilisateur connecté s'il est coach, soit le coach de la famille de l'élève)
            $coach = null;
            if ($user instanceof \App\Entity\Coach) {
                $coach = $user;
            } else {
                $coach = $this->getCurrentCoach($this->coachRepository, $this->security);
                if (!$coach) {
                    // Si pas de coach trouvé, récupérer le coach de la famille de l'élève
                    $family = $student->getFamily();
                    if ($family && $family->getCoach()) {
                        $coach = $family->getCoach();
                    } else {
                        return new JsonResponse(['success' => false, 'message' => 'Coach non trouvé'], 404);
                    }
                }
            }

            // Créer l'objectif avec les données fournies
            $objective = new Objective();
            $objective->setCoach($coach);
            $objective->setStudent($student);
            
            // Sauvegarder la description originale
            $descriptionOrigin = $data['description'];
            $objective->setDescriptionOrigin($descriptionOrigin);
            $objective->setDescription($descriptionOrigin);
            
            $objective->setCategory($data['category'] ?? 'general');
            if (isset($data['categoryTags']) && is_array($data['categoryTags'])) {
                $objective->setCategoryTags($data['categoryTags']);
            }
            $objective->setStatus(Objective::STATUS_MODIFICATION);
            $objective->setProgress(0);
            
            // Utiliser le titre fourni ou créer un titre simple
            if (isset($data['title']) && !empty($data['title'])) {
                $objective->setTitle($data['title']);
            } else {
                $objective->setTitle('Objectif - ' . substr($data['description'], 0, 50));
            }

            // Date limite par défaut : date de création + 3 semaines
            // Note: createdAt sera défini dans le constructeur, on utilise la date actuelle
            $createdAt = new \DateTimeImmutable();
            $deadline = $createdAt->modify('+3 weeks');
            $objective->setDeadline($deadline);

            // Validation
            $errors = $this->validator->validate($objective);
            if (count($errors) > 0) {
                $errorMessages = [];
                foreach ($errors as $error) {
                    $errorMessages[] = $error->getMessage();
                }
                return new JsonResponse(['success' => false, 'message' => implode(', ', $errorMessages)], 400);
            }

            $this->em->persist($objective);
            $this->em->flush();

            // Notifier le coach si l'objectif a été créé par un parent/élève
            try {
                $this->notificationService->notifyObjectiveCreated($objective, $user);
            } catch (\Exception $e) {
                error_log('Erreur notification objectif créé: ' . $e->getMessage());
            }

            // Créer les tâches sélectionnées par l'utilisateur
            $tasksCount = 0;
            if (isset($data['selectedTasks']) && is_array($data['selectedTasks']) && !empty($data['selectedTasks'])) {
                foreach ($data['selectedTasks'] as $taskData) {
                    // Valider la fréquence
                    $frequency = $taskData['frequency'] ?? 'none';
                    $validFrequencies = [
                        Task::FREQUENCY_NONE,
                        Task::FREQUENCY_DAILY,
                        Task::FREQUENCY_WEEKLY,
                        Task::FREQUENCY_MONTHLY
                    ];
                    if (!in_array($frequency, $validFrequencies)) {
                        $frequency = Task::FREQUENCY_NONE; // Fallback
                    }
                    
                    $task = new Task();
                    $task->setObjective($objective);
                    $task->setCoach($coach);
                    $task->setTitle($taskData['title'] ?? '');
                    $task->setDescription($taskData['description'] ?? '');
                    $task->setStatus('pending');
                    $task->setFrequency($frequency);
                    $task->setRequiresProof($taskData['requiresProof'] ?? true);
                    $task->setProofType($taskData['proofType'] ?? '');
                    $task->setAssignedType('coach');
                    
                    $this->em->persist($task);
                    $tasksCount++;
                }
                $this->em->flush();
            }

            // Rediriger vers la page détail de l'objectif créé
            return new JsonResponse([
                'success' => true, 
                'id' => $objective->getId(),
                'redirect' => '/admin/objectives/' . $objective->getId(),
                'message' => $tasksCount > 0 
                    ? 'Objectif créé avec succès avec ' . $tasksCount . ' tâche(s)'
                    : 'Objectif créé avec succès'
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Erreur non capturée lors de la création d\'objectif', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return new JsonResponse([
                'success' => false,
                'message' => 'Erreur serveur: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/admin/objectives/{id}', name: 'admin_objectives_detail', requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_USER')]
    public function detail(int $id): Response
    {
        $user = $this->getUser();
        if (!$user) {
            throw $this->createAccessDeniedException('Vous devez être connecté');
        }

        $objective = $this->objectiveRepository->find($id);
        
        if (!$objective) {
            throw $this->createNotFoundException('Objectif non trouvé');
        }

        // Vérifier les permissions d'accès
        if (!$this->permissionService->canViewObjective($user, $objective)) {
            throw $this->createAccessDeniedException('Vous n\'avez pas accès à cet objectif');
        }

        // Pour les coaches, vérifier qu'ils sont bien le coach de l'objectif
        $coach = null;
        if ($user->isCoach()) {
            $coach = $this->getCurrentCoach($this->coachRepository, $this->security);
            if (!$coach || $objective->getCoach() !== $coach) {
                throw $this->createAccessDeniedException('Vous n\'avez pas accès à cet objectif');
            }
        }

        // Organiser les tâches par statut
        $tasks = $objective->getTasks()->toArray();
        $tasksByStatus = [
            'pending' => [],
            'in_progress' => [],
            'completed' => [],
        ];

        foreach ($tasks as $task) {
            $status = $task->getStatus() ?? 'pending';
            if (!isset($tasksByStatus[$status])) {
                $tasksByStatus['pending'][] = $task;
            } else {
                $tasksByStatus[$status][] = $task;
            }
        }

        // Convertir en tableaux pour le template avec les preuves
        $tasksData = [];
        foreach ($tasksByStatus as $status => $statusTasks) {
            $tasksData[$status] = array_map(function($task) {
                $taskArray = $task->toTemplateArray();
                // Ajouter les preuves (historique) pour chaque tâche
                $proofs = $task->getProofs()->toArray();
                $taskArray['proofs'] = array_map(fn($proof) => $proof->toArray(), $proofs);
                $taskArray['requiresProof'] = $task->isRequiresProof() ?? true; // Par défaut true selon les règles
                $taskArray['frequency'] = $task->getFrequency() ?? 'none';
                return $taskArray;
            }, $statusTasks);
        }

        // Récupérer les commentaires avec les entités complètes pour l'affichage
        $comments = $objective->getComments()->toArray();
        $commentsData = array_map(fn($comment) => $comment->toArray(), $comments);

        // Récupérer le parent de l'élève si disponible
        $studentParent = null;
        $student = $objective->getStudent();
        if ($student && $student->getFamily() && $student->getFamily()->getParent()) {
            $parent = $student->getFamily()->getParent();
            $studentParent = [
                'id' => $parent->getId(),
                'firstName' => $parent->getFirstName(),
                'lastName' => $parent->getLastName(),
            ];
        }

        // Données de l'objectif
        $objectiveData = [
            'id' => $objective->getId(),
            'title' => $objective->getTitle(),
            'description' => $objective->getDescription(),
            'descriptionOrigin' => $objective->getDescriptionOrigin(),
            'category' => $objective->getCategory(),
            'status' => $objective->getStatus(),
            'statusLabel' => $objective->getStatusLabel(),
            'statusMessage' => $objective->getStatusMessage(),
            'canModifyTasks' => $objective->canModifyTasks(),
            'progress' => $objective->getProgress(),
            'deadline' => $objective->getDeadline()?->format('Y-m-d'),
            'createdAt' => $objective->getCreatedAt()?->format('Y-m-d'),
            'updatedAt' => $objective->getUpdatedAt()?->format('d/m/Y H:i'),
            'student' => [
                'id' => $objective->getStudent()->getId(),
                'firstName' => $objective->getStudent()->getFirstName(),
                'lastName' => $objective->getStudent()->getLastName(),
                'pseudo' => $objective->getStudent()->getPseudo(),
            ],
            'parent' => $studentParent,
        ];

        // Initialiser les variables pour les parents et spécialistes
        $parentsData = [];
        $specialistsData = [];

        // Récupérer les étudiants, parents et spécialistes pour les sélecteurs d'affectation
        if ($coach) {
            $students = $this->studentRepository->findByCoach($coach);
            $families = $this->familyRepository->findByCoachWithSearch($coach);
            foreach ($families as $family) {
                $parent = $family->getParent();
                if ($parent) {
                    $parentsData[] = [
                        'id' => $parent->getId(),
                        'firstName' => $parent->getFirstName(),
                        'lastName' => $parent->getLastName(),
                    ];
                }
            }
            $specialists = $this->specialistRepository->findAll();
            $specialistsData = array_map(fn($s) => [
                'id' => $s->getId(),
                'firstName' => $s->getFirstName(),
                'lastName' => $s->getLastName(),
            ], $specialists);
        } else {
            // Pour les autres rôles, utiliser PermissionService
            $students = $this->permissionService->getAccessibleStudents($user);
        }

        $studentsData = array_map(function($student) {
            return [
                'id' => $student->getId(),
                'firstName' => $student->getFirstName(),
                'lastName' => $student->getLastName(),
                'pseudo' => $student->getPseudo(),
            ];
        }, $students);

        // Déterminer le type d'utilisateur
        $currentUser = $this->getUser();
        $userType = 'coach'; // Par défaut
        if ($currentUser instanceof \App\Entity\ParentUser) {
            $userType = 'parent';
        } elseif ($currentUser instanceof \App\Entity\Student) {
            $userType = 'student';
        } elseif ($currentUser instanceof \App\Entity\Specialist) {
            $userType = 'specialist';
        } elseif ($currentUser instanceof \App\Entity\Coach) {
            $userType = 'coach';
        }

        // Vérifier si l'utilisateur peut utiliser l'IA
        $canUseAI = $this->permissionService->canUseAI($currentUser);

        // Récupérer toutes les activités pour le sélecteur
        $activities = $this->activityRepository->findAll();
        $regularActivities = [];
        
        foreach ($activities as $activity) {
            $regularActivities[] = [
                'id' => $activity->getId(),
                'title' => $activity->getTitle(),
                'description' => $activity->getDescription() ?? '',
            ];
        }
        
        // Récupérer tous les Paths (activités scolaires) pour le sélecteur
        $paths = $this->pathRepository->findAll();
        $schoolActivities = [];
        
        foreach ($paths as $path) {
            $schoolActivities[] = [
                'id' => $path->getId(),
                'title' => $path->getTitle(),
                'description' => $path->getDescription() ?? '',
            ];
        }

        return $this->render('tailadmin/pages/objectives/detail.html.twig', [
            'pageTitle' => 'Détail de l\'Objectif ',
            'pageName' => 'objectives-detail',
            'objective' => $objectiveData,
            'tasks' => $tasksData,
            'comments' => $commentsData,
            'userType' => $userType,
            'canUseAI' => $canUseAI,
            'students' => $studentsData,
            'parents' => $parentsData,
            'specialists' => $specialistsData,
            'regularActivities' => $regularActivities,
            'schoolActivities' => $schoolActivities,
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => $this->generateUrl('admin_dashboard')],
                ['label' => 'Objectifs', 'url' => $this->generateUrl('admin_objectives_list')],
                ['label' => 'Détail', 'url' => ''],
            ],
        ]);
    }

    #[Route('/admin/objectives/{id}/update', name: 'admin_objectives_update', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function update(int $id, Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['success' => false, 'message' => 'Vous devez être connecté'], 403);
        }

        $objective = $this->objectiveRepository->find($id);
        if (!$objective) {
            return new JsonResponse(['success' => false, 'message' => 'Objectif non trouvé'], 404);
        }

        // Vérifier les permissions de modification
        if (!$this->permissionService->canModifyObjective($user, $objective)) {
            return new JsonResponse(['success' => false, 'message' => 'Vous n\'avez pas le droit de modifier cet objectif'], 403);
        }

        // Pour les coaches, vérifier qu'ils sont bien le coach de l'objectif
        if ($user->isCoach()) {
            $coach = $this->getCurrentCoach($this->coachRepository, $this->security);
            if (!$coach || $objective->getCoach() !== $coach) {
                return new JsonResponse(['success' => false, 'message' => 'Vous n\'avez pas le droit de modifier cet objectif'], 403);
            }
        }

        $data = json_decode($request->getContent(), true);
        
        // Sauvegarder l'ancien statut pour détecter le changement
        $oldStatus = $objective->getStatus();
        
        if (isset($data['title'])) $objective->setTitle($data['title']);
        if (isset($data['description'])) $objective->setDescription($data['description']);
        if (isset($data['category'])) $objective->setCategory($data['category']);
        if (isset($data['categoryTags']) && is_array($data['categoryTags'])) {
            $objective->setCategoryTags($data['categoryTags']);
        }
        // Seul le coach peut changer le statut d'un objectif
        if (isset($data['status'])) {
            if (!$user->isCoach()) {
                return new JsonResponse(['success' => false, 'message' => 'Seul le coach peut changer le statut d\'un objectif'], 403);
            }
            // Valider que le statut est valide
            if (!in_array($data['status'], array_keys(Objective::STATUSES))) {
                return new JsonResponse(['success' => false, 'message' => 'Statut invalide'], 400);
            }
            $objective->setStatus($data['status']);
        }
        if (isset($data['progress'])) $objective->setProgress($data['progress']);
        // Mettre à jour les dates si fournies
        if (isset($data['createdAt']) && $data['createdAt']) {
            try {
                $objective->setCreatedAt(new \DateTimeImmutable($data['createdAt']));
            } catch (\Exception $e) {
                // Ignorer les erreurs de format de date
            }
        }
        if (isset($data['deadline'])) {
            $objective->setDeadline(new \DateTimeImmutable($data['deadline']));
        }
        if (isset($data['studentId'])) {
            $student = $this->studentRepository->find($data['studentId']);
            if ($student) {
                $objective->setStudent($student);
            } else {
                return new JsonResponse(['success' => false, 'message' => 'Élève non trouvé'], 400);
            }
        }
        $objective->setUpdatedAt(new \DateTimeImmutable());

        // Validation
        $errors = $this->validator->validate($objective);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            return new JsonResponse(['success' => false, 'message' => implode(', ', $errorMessages)], 400);
        }

        $this->em->flush();

        // Notifier si l'objectif vient d'être validé
        try {
            if ($oldStatus !== Objective::STATUS_VALIDATED && $objective->getStatus() === Objective::STATUS_VALIDATED) {
                $this->notificationService->notifyObjectiveValidated($objective);
            }
        } catch (\Exception $e) {
            error_log('Erreur notification objectif validé: ' . $e->getMessage());
        }

        return new JsonResponse(['success' => true, 'message' => 'Objectif modifié avec succès']);
    }

    #[Route('/admin/objectives/{id}/delete', name: 'admin_objectives_delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_USER')]
    public function delete(int $id): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['success' => false, 'message' => 'Vous devez être connecté'], 403);
        }

        $objective = $this->objectiveRepository->find($id);
        if (!$objective) {
            return new JsonResponse(['success' => false, 'message' => 'Objectif non trouvé'], 404);
        }

        // Vérifier les permissions de suppression
        if (!$this->permissionService->canDeleteObjective($user, $objective)) {
            return new JsonResponse(['success' => false, 'message' => 'Vous n\'avez pas le droit de supprimer cet objectif'], 403);
        }

        $this->em->remove($objective);
        $this->em->flush();

        return new JsonResponse(['success' => true, 'message' => 'Objectif supprimé avec succès']);
    }

    #[Route('/admin/objectives/{objectiveId}/comments/create', name: 'admin_objectives_comments_create', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function createComment(int $objectiveId, Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['success' => false, 'message' => 'Vous devez être connecté'], 403);
        }

        $objective = $this->objectiveRepository->find($objectiveId);
        if (!$objective) {
            return new JsonResponse(['success' => false, 'message' => 'Objectif non trouvé'], 404);
        }

        // Vérifier les permissions d'accès
        if (!$this->permissionService->canViewObjective($user, $objective)) {
            return new JsonResponse(['success' => false, 'message' => 'Vous n\'avez pas accès à cet objectif'], 403);
        }

        $data = json_decode($request->getContent(), true);
        
        $comment = Comment::createForUser([
            'content' => $data['content'] ?? '',
        ], $user, $objective, null);

        // Validation
        $errors = $this->validator->validate($comment);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            return new JsonResponse(['success' => false, 'message' => implode(', ', $errorMessages)], 400);
        }

        $this->em->persist($comment);
        $this->em->flush();

        return new JsonResponse(['success' => true, 'id' => $comment->getId(), 'message' => 'Commentaire créé avec succès']);
    }

    #[Route('/admin/objectives/{objectiveId}/comments/{commentId}/update', name: 'admin_objectives_comments_update', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function updateComment(int $objectiveId, int $commentId, Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['success' => false, 'message' => 'Vous devez être connecté'], 403);
        }

        $objective = $this->objectiveRepository->find($objectiveId);
        if (!$objective) {
            return new JsonResponse(['success' => false, 'message' => 'Objectif non trouvé'], 404);
        }

        // Vérifier les permissions d'accès
        if (!$this->permissionService->canViewObjective($user, $objective)) {
            return new JsonResponse(['success' => false, 'message' => 'Vous n\'avez pas accès à cet objectif'], 403);
        }

        $comment = $this->commentRepository->find($commentId);
        if (!$comment || $comment->getObjective() !== $objective) {
            return new JsonResponse(['success' => false, 'message' => 'Commentaire non trouvé'], 404);
        }

        // Vérifier que le commentaire appartient à l'utilisateur connecté
        if ($comment->getAuthor() !== $user) {
            return new JsonResponse(['success' => false, 'message' => 'Vous n\'avez pas le droit de modifier ce commentaire'], 403);
        }

        $data = json_decode($request->getContent(), true);
        if (isset($data['content'])) {
            $comment->setContent($data['content']);
        }
        $comment->setUpdatedAt(new \DateTimeImmutable());

        // Validation
        $errors = $this->validator->validate($comment);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            return new JsonResponse(['success' => false, 'message' => implode(', ', $errorMessages)], 400);
        }

        $this->em->flush();

        return new JsonResponse(['success' => true, 'message' => 'Commentaire modifié avec succès']);
    }

    #[Route('/admin/objectives/{objectiveId}/comments/{commentId}/delete', name: 'admin_objectives_comments_delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_USER')]
    public function deleteComment(int $objectiveId, int $commentId): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['success' => false, 'message' => 'Vous devez être connecté'], 403);
        }

        $objective = $this->objectiveRepository->find($objectiveId);
        if (!$objective) {
            return new JsonResponse(['success' => false, 'message' => 'Objectif non trouvé'], 404);
        }

        // Vérifier les permissions d'accès
        if (!$this->permissionService->canViewObjective($user, $objective)) {
            return new JsonResponse(['success' => false, 'message' => 'Vous n\'avez pas accès à cet objectif'], 403);
        }

        $comment = $this->commentRepository->find($commentId);
        if (!$comment || $comment->getObjective() !== $objective) {
            return new JsonResponse(['success' => false, 'message' => 'Commentaire non trouvé'], 404);
        }

        // Vérifier que le commentaire appartient à l'utilisateur connecté ou que l'utilisateur est le coach de l'objectif
        $canDelete = ($comment->getAuthor() === $user);
        if (!$canDelete && $user->isCoach()) {
            $coach = $this->getCurrentCoach($this->coachRepository, $this->security);
            $canDelete = ($coach && $objective->getCoach() === $coach);
        }
        
        if (!$canDelete) {
            return new JsonResponse(['success' => false, 'message' => 'Vous n\'avez pas le droit de supprimer ce commentaire'], 403);
        }

        $this->em->remove($comment);
        $this->em->flush();

        return new JsonResponse(['success' => true, 'message' => 'Commentaire supprimé avec succès']);
    }

    #[Route('/admin/objectives/{id}/generate-tasks', name: 'admin_objectives_generate_tasks', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function generateTasks(int $id, Request $request): JsonResponse
    {
        try {
            $user = $this->getUser();
            if (!$user) {
                return new JsonResponse(['success' => false, 'message' => 'Vous devez être connecté'], 403);
            }

            // Vérifier que seul le coach peut utiliser l'IA
            if (!$this->permissionService->canUseAI($user)) {
                return new JsonResponse(['success' => false, 'message' => 'Seul le coach peut utiliser l\'IA pour générer des tâches'], 403);
            }

            $coach = $this->getCurrentCoach($this->coachRepository, $this->security);
            if (!$coach) {
                return new JsonResponse(['success' => false, 'message' => 'Coach non trouvé'], 404);
            }

            $objective = $this->objectiveRepository->find($id);
            if (!$objective || $objective->getCoach() !== $coach) {
                return new JsonResponse(['success' => false, 'message' => 'Objectif non trouvé'], 404);
            }

            $data = json_decode($request->getContent(), true);
            $type = $data['type'] ?? $objective->getCategory() ?? 'general';

            // Générer les suggestions avec SmartObjectiveService
            try {
                $suggestions = $this->smartObjectiveService->generateSuggestions(
                    $objective->getTitle() ?? $objective->getDescription(),
                    $type
                );

                // Retourner uniquement les tâches suggérées
                return new JsonResponse([
                    'success' => true,
                    'tasks' => $suggestions['tasks'] ?? [],
                    'message' => count($suggestions['tasks'] ?? []) . ' tâche(s) suggérée(s)'
                ]);
            } catch (\Exception $e) {
                $this->logger->error('Erreur lors de la génération des suggestions de tâches', [
                    'objective_id' => $id,
                    'error' => $e->getMessage()
                ]);
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Erreur lors de la génération des suggestions : ' . $e->getMessage()
                ], 500);
            }
        } catch (\Exception $e) {
            $this->logger->error('Erreur non capturée lors de la génération de tâches', [
                'objective_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return new JsonResponse([
                'success' => false,
                'message' => 'Erreur serveur: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/admin/objectives/generate-suggestions', name: 'admin_objectives_generate_suggestions', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function generateSuggestions(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['success' => false, 'message' => 'Vous devez être connecté'], 403);
        }

        $data = json_decode($request->getContent(), true);
        
        if (empty($data['description'])) {
            return new JsonResponse(['success' => false, 'message' => 'La description est requise'], 400);
        }
        if (empty($data['studentId'])) {
            return new JsonResponse(['success' => false, 'message' => 'L\'élève est requis'], 400);
        }

        // Récupérer l'élève
        $student = $this->studentRepository->find($data['studentId']);
        if (!$student) {
            return new JsonResponse(['success' => false, 'message' => 'Élève non trouvé'], 400);
        }

        // Vérifier les permissions
        if (!$this->permissionService->canCreateObjective($user, $student)) {
            return new JsonResponse(['success' => false, 'message' => 'Vous n\'avez pas le droit de créer un objectif pour cet élève'], 403);
        }

        // Vérifier si l'utilisateur peut utiliser l'IA
        $canUseAI = $this->permissionService->canUseAI($user);
        if (!$canUseAI) {
            return new JsonResponse(['success' => false, 'message' => 'Vous n\'avez pas accès à l\'IA'], 403);
        }

        try {
            // Construire le prompt avec description + classe de l'élève
            $description = $data['description'];
            $studentClass = $student->getClass() ?? '';
            $category = $data['category'] ?? 'general';
            
            // Ajouter la classe dans le prompt si disponible
            $promptDescription = $description;
            if (!empty($studentClass)) {
                $promptDescription = "C'est un élève de classe {$studentClass}. {$description}";
            }

            // Vérifier si on doit générer des suggestions pour l'objectif (titre et description)
            $generateObjective = isset($data['generateObjective']) && $data['generateObjective'] === true;

            $suggestions = $this->smartObjectiveService->generateSuggestions(
                $promptDescription,
                $category,
                $generateObjective
            );

            return new JsonResponse([
                'success' => true,
                'suggestions' => $suggestions
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de la génération des suggestions', [
                'error' => $e->getMessage()
            ]);
            return new JsonResponse([
                'success' => false,
                'message' => 'Erreur lors de la génération des suggestions : ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/admin/objectives/category-tags', name: 'admin_objectives_category_tags', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getCategoryTags(): JsonResponse
    {
        // Catégories prédéfinies
        $predefinedCategories = [
            'Gestion de stress',
            'Motivation',
            'Concentration',
            'Organisation',
            'Autonomie',
            'Confiance en soi',
            'Relations sociales',
            'Communication',
            'Gestion émotionnelle',
            'Comportement',
            'Problème scolaire',
        ];
        
        // Récupérer tous les objectifs avec leurs categoryTags
        $objectives = $this->objectiveRepository->findAll();
        
        // Extraire tous les tags uniques depuis les objectifs
        $allCategories = [];
        foreach ($objectives as $objective) {
            $categoryTags = $objective->getCategoryTags();
            if ($categoryTags && is_array($categoryTags)) {
                foreach ($categoryTags as $tag) {
                    if (!empty(trim($tag)) && !in_array($tag, $allCategories, true)) {
                        $allCategories[] = trim($tag);
                    }
                }
            }
        }
        
        // Récupérer tous les tags de besoins depuis les élèves
        $students = $this->studentRepository->findAll();
        foreach ($students as $student) {
            $needTags = $student->getNeedTags();
            if ($needTags && is_array($needTags)) {
                foreach ($needTags as $tag) {
                    if (!empty(trim($tag)) && !in_array($tag, $allCategories, true)) {
                        $allCategories[] = trim($tag);
                    }
                }
            }
        }
        
        // Fusionner les catégories prédéfinies avec celles de la base de données
        // (en évitant les doublons)
        $allCategories = array_unique(array_merge($predefinedCategories, $allCategories));
        
        // Trier par ordre alphabétique
        sort($allCategories);
        
        return new JsonResponse([
            'success' => true,
            'tags' => array_values($allCategories) // array_values pour réindexer
        ]);
    }

    #[Route('/admin/objectives/{id}/share-sheet', name: 'admin_objectives_share_sheet', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function shareSheet(int $id): Response
    {
        $user = $this->getUser();
        if (!$user) {
            throw $this->createAccessDeniedException('Vous devez être connecté');
        }

        // Seul le coach peut partager un objectif
        if (!$user instanceof \App\Entity\Coach) {
            throw $this->createAccessDeniedException('Seul le coach peut partager un objectif');
        }

        $objective = $this->objectiveRepository->find($id);
        if (!$objective) {
            throw $this->createNotFoundException('Objectif non trouvé');
        }

        // Vérifier que le coach est le propriétaire de l'objectif
        if ($objective->getCoach() !== $user) {
            throw $this->createAccessDeniedException('Vous n\'avez pas le droit de partager cet objectif');
        }

        // Récupérer tous les élèves du coach
        $students = [];
        foreach ($user->getFamilies() as $family) {
            foreach ($family->getStudents() as $student) {
                $students[] = $student;
            }
        }

        // Récupérer tous les spécialistes
        $specialists = $this->specialistRepository->findAll();

        // Récupérer les utilisateurs avec qui l'objectif est déjà partagé
        $sharedStudents = $objective->getSharedStudents()->toArray();
        $sharedSpecialists = $objective->getSharedSpecialists()->toArray();

        return $this->render('tailadmin/pages/objectives/_share_sheet.html.twig', [
            'objective' => $objective,
            'students' => $students,
            'specialists' => $specialists,
            'sharedStudents' => $sharedStudents,
            'sharedSpecialists' => $sharedSpecialists,
        ]);
    }

    #[Route('/admin/objectives/{id}/share', name: 'admin_objectives_share', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function share(int $id, Request $request): Response
    {
        $user = $this->getUser();
        if (!$user) {
            throw $this->createAccessDeniedException('Vous devez être connecté');
        }

        // Seul le coach peut partager un objectif
        if (!$user instanceof \App\Entity\Coach) {
            throw $this->createAccessDeniedException('Seul le coach peut partager un objectif');
        }

        $objective = $this->objectiveRepository->find($id);
        if (!$objective) {
            throw $this->createNotFoundException('Objectif non trouvé');
        }

        // Vérifier que le coach est le propriétaire de l'objectif
        if ($objective->getCoach() !== $user) {
            throw $this->createAccessDeniedException('Vous n\'avez pas le droit de partager cet objectif');
        }

        $data = json_decode($request->getContent(), true);
        if (!$data) {
            return $this->render('tailadmin/pages/objectives/_share_sheet.html.twig', [
                'objective' => $objective,
                'students' => [],
                'specialists' => [],
                'sharedStudents' => $objective->getSharedStudents()->toArray(),
                'sharedSpecialists' => $objective->getSharedSpecialists()->toArray(),
                'error' => 'Données invalides',
            ]);
        }

        // Récupérer les IDs des élèves et spécialistes à partager
        $studentIds = $data['studentIds'] ?? [];
        $specialistIds = $data['specialistIds'] ?? [];

        // Vérifier que les étudiants appartiennent aux familles du coach
        if (!empty($studentIds)) {
            $students = $this->studentRepository->findBy(['id' => $studentIds]);
            foreach ($students as $student) {
                $family = $student->getFamily();
                if (!$family || $family->getCoach() !== $user) {
                    // Récupérer tous les élèves du coach pour réafficher le formulaire
                    $allStudents = [];
                    foreach ($user->getFamilies() as $family) {
                        foreach ($family->getStudents() as $s) {
                            $allStudents[] = $s;
                        }
                    }
                    return $this->render('tailadmin/pages/objectives/_share_sheet.html.twig', [
                        'objective' => $objective,
                        'students' => $allStudents,
                        'specialists' => $this->specialistRepository->findAll(),
                        'sharedStudents' => $objective->getSharedStudents()->toArray(),
                        'sharedSpecialists' => $objective->getSharedSpecialists()->toArray(),
                        'error' => 'Vous ne pouvez partager qu\'avec vos propres élèves',
                    ]);
                }
            }
        }

        // Partager avec les élèves
        $objective->getSharedStudents()->clear();
        if (!empty($studentIds)) {
            $students = $this->studentRepository->findBy(['id' => $studentIds]);
            foreach ($students as $student) {
                $objective->addSharedStudent($student);
            }
        }

        // Partager avec les spécialistes
        $objective->getSharedSpecialists()->clear();
        if (!empty($specialistIds)) {
            $specialists = $this->specialistRepository->findBy(['id' => $specialistIds]);
            foreach ($specialists as $specialist) {
                $objective->addSharedSpecialist($specialist);
            }
        }

        $this->em->flush();

        // Recharger l'objectif pour avoir les données à jour
        $this->em->refresh($objective);

        // Récupérer tous les élèves du coach pour réafficher le formulaire
        $allStudents = [];
        foreach ($user->getFamilies() as $family) {
            foreach ($family->getStudents() as $student) {
                $allStudents[] = $student;
            }
        }

        return $this->render('tailadmin/pages/objectives/_share_sheet.html.twig', [
            'objective' => $objective,
            'students' => $allStudents,
            'specialists' => $this->specialistRepository->findAll(),
            'sharedStudents' => $objective->getSharedStudents()->toArray(),
            'sharedSpecialists' => $objective->getSharedSpecialists()->toArray(),
        ]);
    }

    #[Route('/api/objectives/{id}/share', name: 'api_objectives_share', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function shareApi(int $id, Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['success' => false, 'message' => 'Vous devez être connecté'], 403);
        }

        // Seul le coach peut partager un objectif
        if (!$user instanceof \App\Entity\Coach) {
            return new JsonResponse(['success' => false, 'message' => 'Seul le coach peut partager un objectif'], 403);
        }

        $objective = $this->objectiveRepository->find($id);
        if (!$objective) {
            return new JsonResponse(['success' => false, 'message' => 'Objectif non trouvé'], 404);
        }

        // Vérifier que le coach est le propriétaire de l'objectif
        if ($objective->getCoach() !== $user) {
            return new JsonResponse(['success' => false, 'message' => 'Vous n\'avez pas le droit de partager cet objectif'], 403);
        }

        $data = json_decode($request->getContent(), true);
        if (!$data) {
            return new JsonResponse(['success' => false, 'message' => 'Données invalides'], 400);
        }

        // Récupérer les IDs des élèves et spécialistes à partager
        $studentIds = $data['studentIds'] ?? [];
        $specialistIds = $data['specialistIds'] ?? [];

        // Vérifier que les étudiants appartiennent aux familles du coach
        if (!empty($studentIds)) {
            $students = $this->studentRepository->findBy(['id' => $studentIds]);
            foreach ($students as $student) {
                $family = $student->getFamily();
                if (!$family || $family->getCoach() !== $user) {
                    return new JsonResponse(['success' => false, 'message' => 'Vous ne pouvez partager qu\'avec vos propres élèves'], 403);
                }
            }
        }

        // Partager avec les élèves
        $objective->getSharedStudents()->clear();
        if (!empty($studentIds)) {
            $students = $this->studentRepository->findBy(['id' => $studentIds]);
            foreach ($students as $student) {
                $objective->addSharedStudent($student);
            }
        }

        // Partager avec les spécialistes
        $objective->getSharedSpecialists()->clear();
        if (!empty($specialistIds)) {
            $specialists = $this->specialistRepository->findBy(['id' => $specialistIds]);
            foreach ($specialists as $specialist) {
                $objective->addSharedSpecialist($specialist);
            }
        }

        $this->em->flush();

        return new JsonResponse([
            'success' => true,
            'message' => 'Objectif partagé avec succès',
            'objective' => $objective->toArray()
        ]);
    }

    #[Route('/admin/objectives/{id}/duplicate-sheet', name: 'admin_objectives_duplicate_sheet', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function duplicateSheet(int $id): Response
    {
        $user = $this->getUser();
        if (!$user) {
            throw $this->createAccessDeniedException('Vous devez être connecté');
        }

        // Seul le coach peut dupliquer un objectif
        if (!$user instanceof \App\Entity\Coach) {
            throw $this->createAccessDeniedException('Seul le coach peut dupliquer un objectif');
        }

        $objective = $this->objectiveRepository->find($id);
        if (!$objective) {
            throw $this->createNotFoundException('Objectif non trouvé');
        }

        // Vérifier que le coach est le propriétaire de l'objectif
        if ($objective->getCoach() !== $user) {
            throw $this->createAccessDeniedException('Vous n\'avez pas le droit de dupliquer cet objectif');
        }

        // Récupérer tous les élèves du coach
        $students = [];
        foreach ($user->getFamilies() as $family) {
            foreach ($family->getStudents() as $student) {
                $students[] = $student;
            }
        }

        return $this->render('tailadmin/pages/objectives/_duplicate_sheet.html.twig', [
            'objective' => $objective,
            'students' => $students,
        ]);
    }

    #[Route('/admin/objectives/{id}/duplicate', name: 'admin_objectives_duplicate', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function duplicate(int $id, Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['success' => false, 'message' => 'Vous devez être connecté'], 403);
        }

        $originalObjective = $this->objectiveRepository->find($id);
        if (!$originalObjective) {
            return new JsonResponse(['success' => false, 'message' => 'Objectif non trouvé'], 404);
        }

        // Vérifier les permissions d'accès
        if (!$this->permissionService->canViewObjective($user, $originalObjective)) {
            return new JsonResponse(['success' => false, 'message' => 'Vous n\'avez pas accès à cet objectif'], 403);
        }

        // Récupérer le coach (soit l'utilisateur connecté s'il est coach, soit le coach de l'objectif)
        $coach = null;
        if ($user instanceof \App\Entity\Coach) {
            $coach = $user;
        } else {
            $coach = $this->getCurrentCoach($this->coachRepository, $this->security);
            if (!$coach) {
                $coach = $originalObjective->getCoach();
            }
        }

        if (!$coach) {
            return new JsonResponse(['success' => false, 'message' => 'Coach non trouvé'], 404);
        }

        $data = json_decode($request->getContent(), true);
        $duplicateTasks = $data['duplicateTasks'] ?? true; // Par défaut, dupliquer les tâches
        $studentIds = $data['studentIds'] ?? [];

        // Vérifier qu'au moins un élève est sélectionné
        if (empty($studentIds) || !is_array($studentIds)) {
            return new JsonResponse(['success' => false, 'message' => 'Veuillez sélectionner au moins un élève'], 400);
        }

        // Vérifier que les étudiants appartiennent aux familles du coach
        $students = $this->studentRepository->findBy(['id' => $studentIds]);
        foreach ($students as $student) {
            $family = $student->getFamily();
            if (!$family || $family->getCoach() !== $coach) {
                return new JsonResponse(['success' => false, 'message' => 'Vous ne pouvez dupliquer que pour vos propres élèves'], 403);
            }
        }

        try {
            $createdObjectives = [];
            $totalTasksCount = 0;

            // Créer un objectif dupliqué pour chaque élève sélectionné
            foreach ($students as $student) {
                // Utiliser la méthode duplicate() de l'entité Objective
                $newObjective = $originalObjective->duplicate($duplicateTasks);
                
                // Définir le coach et l'élève
                $newObjective->setCoach($coach);
                $newObjective->setStudent($student);
                
                // Mettre à jour le coach et l'élève sur toutes les tâches dupliquées
                if ($duplicateTasks) {
                    foreach ($newObjective->getTasks() as $task) {
                        $task->setCoach($coach);
                        // Si la tâche était assignée à l'élève original, l'assigner au nouvel élève
                        if ($task->getAssignedType() === 'student' && $task->getStudent() && $task->getStudent()->getId() === $originalObjective->getStudent()->getId()) {
                            $task->setStudent($student);
                        }
                    }
                }

                // Validation
                $errors = $this->validator->validate($newObjective);
                if (count($errors) > 0) {
                    $errorMessages = [];
                    foreach ($errors as $error) {
                        $errorMessages[] = $error->getMessage();
                    }
                    return new JsonResponse(['success' => false, 'message' => implode(', ', $errorMessages)], 400);
                }

                $this->em->persist($newObjective);
                $createdObjectives[] = $newObjective;
                $totalTasksCount += $duplicateTasks ? $newObjective->getTasks()->count() : 0;
            }

            $this->em->flush();

            $objectivesCount = count($createdObjectives);
            $message = $objectivesCount > 1
                ? sprintf('%d objectifs dupliqués avec succès', $objectivesCount)
                : 'Objectif dupliqué avec succès';
            
            if ($totalTasksCount > 0) {
                $message .= sprintf(' (%d tâche(s) au total)', $totalTasksCount);
            }

            // Rediriger vers le premier objectif créé si un seul, sinon vers la liste
            $redirect = $objectivesCount === 1
                ? '/admin/objectives/' . $createdObjectives[0]->getId()
                : null;

            return new JsonResponse([
                'success' => true,
                'message' => $message,
                'objectivesCount' => $objectivesCount,
                'id' => $createdObjectives[0]->getId() ?? null,
                'redirect' => $redirect,
                'redirectToList' => $objectivesCount > 1 ? '/admin/objectives' : null,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de la duplication d\'objectif', [
                'objective_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return new JsonResponse([
                'success' => false,
                'message' => 'Erreur lors de la duplication : ' . $e->getMessage()
            ], 500);
        }
    }
}

