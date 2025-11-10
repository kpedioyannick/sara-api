<?php

namespace App\Controller;

use App\Controller\Trait\CoachTrait;
use App\Entity\Comment;
use App\Entity\Objective;
use App\Entity\Student;
use App\Form\ObjectiveType as ObjectiveFormType;
use App\Repository\CommentRepository;
use App\Repository\CoachRepository;
use App\Repository\FamilyRepository;
use App\Repository\ObjectiveRepository;
use App\Repository\SpecialistRepository;
use App\Repository\StudentRepository;
use App\Repository\TaskRepository;
use App\Service\SmartObjectiveService;
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
        private readonly PermissionService $permissionService
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
        $creatorProfile = $request->query->get('creatorProfile');
        $creatorUserId = $request->query->get('creatorUser');
        $status = $request->query->get('status');
        $studentId = $request->query->get('student');
        $profileType = $request->query->get('profileType'); // 'parent', 'specialist', 'student', ou null
        $selectedIds = $request->query->get('selectedIds', ''); // IDs séparés par des virgules

        // Récupération des objectifs selon le rôle de l'utilisateur
        if ($user->isCoach()) {
            $coach = $this->getCurrentCoach($this->coachRepository, $this->security);
            if (!$coach) {
                throw $this->createNotFoundException('Aucun coach trouvé');
            }
            $objectives = $this->objectiveRepository->findByCoachWithSearch(
                $coach,
                $search ?: null,
                $creatorProfile ?: null,
                $creatorUserId ? (int) $creatorUserId : null,
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
        }
        
        // Filtrer par élève si spécifié
        if ($studentId) {
            $objectives = array_filter($objectives, function($objective) use ($studentId) {
                return $objective->getStudent() && $objective->getStudent()->getId() == (int)$studentId;
            });
        }
        
        // Appliquer les filtres par profil si spécifiés (pour les coaches uniquement)
        if ($user->isCoach() && $profileType && $selectedIds) {
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
                'familyIdentifier' => $familyIdentifier,
                'parentLastName' => $parentLastName,
            ];
        }, $students);

        return $this->render('tailadmin/pages/objectives/list.html.twig', [
            'pageTitle' => 'Liste des Objectifs | TailAdmin',
            'pageName' => 'objectives',
            'objectives' => $objectivesData,
            'students' => $studentsData,
            'parents' => $parentsData,
            'specialists' => $specialistsData,
            'coaches' => $coachesData,
            'creatorProfileFilter' => $creatorProfile,
            'creatorUserFilter' => $creatorUserId,
            'profileType' => $profileType,
            'selectedIds' => $selectedIds,
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => $this->generateUrl('admin_dashboard')],
            ],
        ]);
    }

    #[Route('/admin/objectives/create', name: 'admin_objectives_create', methods: ['POST'])]
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
            if (empty($data['studentId'])) {
                return new JsonResponse(['success' => false, 'message' => 'L\'élève est requis'], 400);
            }
            if (empty($data['category'])) {
                return new JsonResponse(['success' => false, 'message' => 'La catégorie est requise'], 400);
            }

            // Récupérer l'élève
            $student = $this->studentRepository->find($data['studentId']);
            if (!$student) {
                return new JsonResponse(['success' => false, 'message' => 'Élève non trouvé'], 400);
            }

            // Générer les suggestions avec SmartObjectiveService
            try {
                $suggestions = $this->smartObjectiveService->generateSuggestions(
                    $data['description'],
                    $data['category']
                );
            } catch (\Exception $e) {
                $this->logger->error('Erreur lors de la génération des suggestions', [
                    'error' => $e->getMessage()
                ]);
                return new JsonResponse([
                    'success' => false, 
                    'message' => 'Erreur lors de la génération des suggestions : ' . $e->getMessage()
                ], 500);
            }

            // Créer l'objectif avec les données générées
            $objective = new Objective();
            $objective->setCoach($coach);
            $objective->setStudent($student);
            $objective->setDescription($data['description']);
            $objective->setCategory($data['category']);
            $objective->setStatus('pending');
            $objective->setProgress(0);
            
            // Utiliser le titre généré par OpenAI
            if (isset($suggestions['objective']['title'])) {
                $objective->setTitle($suggestions['objective']['title']);
            } else {
                // Fallback si pas de titre généré
                $objective->setTitle('Objectif - ' . substr($data['description'], 0, 50));
            }

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

            // Créer les tâches suggérées
            if (isset($suggestions['tasks']) && is_array($suggestions['tasks'])) {
                foreach ($suggestions['tasks'] as $taskData) {
                    // Valider la fréquence
                    $frequency = $taskData['frequency'] ?? 'none';
                    $validFrequencies = [
                        Task::FREQUENCY_NONE,
                        Task::FREQUENCY_HOURLY,
                        Task::FREQUENCY_DAILY,
                        Task::FREQUENCY_HALF_DAY,
                        Task::FREQUENCY_EVERY_2_DAYS,
                        Task::FREQUENCY_WEEKLY,
                        Task::FREQUENCY_MONTHLY,
                        Task::FREQUENCY_YEARLY
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
                    $task->setRequiresProof(true); // Par défaut, toutes les tâches nécessitent des preuves
                    $task->setProofType($taskData['proofType'] ?? '');
                    $task->setAssignedType('coach');
                    
                    $this->em->persist($task);
                }
                $this->em->flush();
            }

            return new JsonResponse([
                'success' => true, 
                'id' => $objective->getId(), 
                'message' => 'Objectif créé avec succès avec ' . count($suggestions['tasks'] ?? []) . ' tâche(s) générée(s)'
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

        // Données de l'objectif
        $objectiveData = [
            'id' => $objective->getId(),
            'title' => $objective->getTitle(),
            'description' => $objective->getDescription(),
            'category' => $objective->getCategory(),
            'status' => $objective->getStatus(),
            'statusLabel' => $objective->getStatusLabel(),
            'statusMessage' => $objective->getStatusMessage(),
            'canModifyTasks' => $objective->canModifyTasks(),
            'progress' => $objective->getProgress(),
            'deadline' => $objective->getDeadline()?->format('Y-m-d'),
            'createdAt' => $objective->getCreatedAt()?->format('d/m/Y H:i'),
            'updatedAt' => $objective->getUpdatedAt()?->format('d/m/Y H:i'),
            'student' => [
                'id' => $objective->getStudent()->getId(),
                'firstName' => $objective->getStudent()->getFirstName(),
                'lastName' => $objective->getStudent()->getLastName(),
                'pseudo' => $objective->getStudent()->getPseudo(),
            ],
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

        return $this->render('tailadmin/pages/objectives/detail.html.twig', [
            'pageTitle' => 'Détail de l\'Objectif | TailAdmin',
            'pageName' => 'objectives-detail',
            'objective' => $objectiveData,
            'tasks' => $tasksData,
            'comments' => $commentsData,
            'userType' => $userType,
            'students' => $studentsData,
            'parents' => $parentsData,
            'specialists' => $specialistsData,
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
        if (isset($data['title'])) $objective->setTitle($data['title']);
        if (isset($data['description'])) $objective->setDescription($data['description']);
        if (isset($data['category'])) $objective->setCategory($data['category']);
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

        return new JsonResponse(['success' => true, 'message' => 'Objectif modifié avec succès']);
    }

    #[Route('/admin/objectives/{id}/delete', name: 'admin_objectives_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $coach = $this->getCurrentCoach($this->coachRepository, $this->security);
        if (!$coach) {
            return new JsonResponse(['success' => false, 'message' => 'Coach non trouvé'], 404);
        }

        $objective = $this->objectiveRepository->find($id);
        if (!$objective || $objective->getCoach() !== $coach) {
            return new JsonResponse(['success' => false, 'message' => 'Objectif non trouvé'], 404);
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
    public function generateTasks(int $id, Request $request): JsonResponse
    {
        try {
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
}

