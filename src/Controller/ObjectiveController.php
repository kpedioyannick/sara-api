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
        private readonly LoggerInterface $logger
    ) {
    }

    #[Route('/admin/objectives', name: 'admin_objectives_list')]
    #[IsGranted('ROLE_COACH')]
    public function list(Request $request): Response
    {
        $coach = $this->getCurrentCoach($this->coachRepository, $this->security);
        
        if (!$coach) {
            throw $this->createNotFoundException('Aucun coach trouvé');
        }

        // Récupération des paramètres de filtrage
        $search = $request->query->get('search', '');
        $creatorProfile = $request->query->get('creatorProfile');
        $creatorUserId = $request->query->get('creatorUser');
        $status = $request->query->get('status');

        // Récupération des objectifs avec filtrage
        $objectives = $this->objectiveRepository->findByCoachWithSearch(
            $coach,
            $search ?: null,
            $creatorProfile ?: null,
            $creatorUserId ? (int) $creatorUserId : null,
            $status ?: null
        );

        // Conversion en tableau pour le template avec commentaires
        $objectivesData = array_map(function($objective) {
            $data = $objective->toTemplateArray();
            // Ajouter les commentaires formatés
            $data['comments'] = array_map(fn($comment) => $comment->toArray(), $objective->getComments()->toArray());
            return $data;
        }, $objectives);
        
        // Récupérer tous les étudiants du coach pour les formulaires
        $students = $this->studentRepository->findByCoach($coach);
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
        
        // Récupérer les parents et spécialistes pour les tâches
        $families = $this->familyRepository->findByCoachWithSearch($coach);
        
        $parentsData = [];
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
        
        // Récupérer le coach pour le filtre
        $coachesData = [[
            'id' => $coach->getId(),
            'firstName' => $coach->getFirstName(),
            'lastName' => $coach->getLastName(),
        ]];

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
    #[IsGranted('ROLE_COACH')]
    public function detail(int $id): Response
    {
        $coach = $this->getCurrentCoach($this->coachRepository, $this->security);
        
        if (!$coach) {
            throw $this->createNotFoundException('Aucun coach trouvé');
        }

        $objective = $this->objectiveRepository->find($id);
        
        if (!$objective || $objective->getCoach() !== $coach) {
            throw $this->createNotFoundException('Objectif non trouvé');
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
        $commentsData = [];
        foreach ($comments as $comment) {
            $commentArray = $comment->toArray();
            // Ajouter les données complètes de l'auteur pour l'affichage
            if ($comment->getAuthorType() === 'coach' && $comment->getCoach()) {
                $commentArray['author'] = [
                    'firstName' => $comment->getCoach()->getFirstName(),
                    'lastName' => $comment->getCoach()->getLastName(),
                ];
            } elseif ($comment->getAuthorType() === 'parent' && $comment->getParent()) {
                $commentArray['author'] = [
                    'firstName' => $comment->getParent()->getFirstName(),
                    'lastName' => $comment->getParent()->getLastName(),
                ];
            } elseif ($comment->getAuthorType() === 'specialist' && $comment->getSpecialist()) {
                $commentArray['author'] = [
                    'firstName' => $comment->getSpecialist()->getFirstName(),
                    'lastName' => $comment->getSpecialist()->getLastName(),
                ];
            } elseif ($comment->getAuthorType() === 'student' && $comment->getStudent()) {
                $commentArray['author'] = [
                    'firstName' => $comment->getStudent()->getFirstName(),
                    'lastName' => $comment->getStudent()->getLastName(),
                ];
            }
            $commentArray['createdAt'] = $comment->getCreatedAt()?->format('Y-m-d H:i:s');
            $commentsData[] = $commentArray;
        }

        // Données de l'objectif
        $objectiveData = [
            'id' => $objective->getId(),
            'title' => $objective->getTitle(),
            'description' => $objective->getDescription(),
            'category' => $objective->getCategory(),
            'status' => $objective->getStatus(),
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

                    // Récupérer les étudiants, parents et spécialistes pour les sélecteurs d'affectation
                    $students = $this->studentRepository->findByCoach($coach);
                    $studentsData = array_map(function($student) {
                        return [
                            'id' => $student->getId(),
                            'firstName' => $student->getFirstName(),
                            'lastName' => $student->getLastName(),
                            'pseudo' => $student->getPseudo(),
                        ];
                    }, $students);
                    
                    $families = $this->familyRepository->findByCoachWithSearch($coach);
                    $parentsData = [];
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
    public function update(int $id, Request $request): JsonResponse
    {
        $coach = $this->getCurrentCoach($this->coachRepository, $this->security);
        if (!$coach) {
            return new JsonResponse(['success' => false, 'message' => 'Coach non trouvé'], 404);
        }

        $objective = $this->objectiveRepository->find($id);
        if (!$objective || $objective->getCoach() !== $coach) {
            return new JsonResponse(['success' => false, 'message' => 'Objectif non trouvé'], 404);
        }

        $data = json_decode($request->getContent(), true);
        if (isset($data['title'])) $objective->setTitle($data['title']);
        if (isset($data['description'])) $objective->setDescription($data['description']);
        if (isset($data['category'])) $objective->setCategory($data['category']);
        if (isset($data['status'])) $objective->setStatus($data['status']);
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
    public function createComment(int $objectiveId, Request $request): JsonResponse
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
        
        $comment = Comment::createForCoach([
            'content' => $data['content'] ?? '',
        ], $coach, $objective);

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
    public function updateComment(int $objectiveId, int $commentId, Request $request): JsonResponse
    {
        $coach = $this->getCurrentCoach($this->coachRepository, $this->security);
        if (!$coach) {
            return new JsonResponse(['success' => false, 'message' => 'Coach non trouvé'], 404);
        }

        $objective = $this->objectiveRepository->find($objectiveId);
        if (!$objective || $objective->getCoach() !== $coach) {
            return new JsonResponse(['success' => false, 'message' => 'Objectif non trouvé'], 404);
        }

        $comment = $this->commentRepository->find($commentId);
        if (!$comment || $comment->getObjective() !== $objective) {
            return new JsonResponse(['success' => false, 'message' => 'Commentaire non trouvé'], 404);
        }

        // Vérifier que le commentaire appartient au coach
        if ($comment->getAuthorType() !== 'coach' || $comment->getCoach() !== $coach) {
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
    public function deleteComment(int $objectiveId, int $commentId): JsonResponse
    {
        $coach = $this->getCurrentCoach($this->coachRepository, $this->security);
        if (!$coach) {
            return new JsonResponse(['success' => false, 'message' => 'Coach non trouvé'], 404);
        }

        $objective = $this->objectiveRepository->find($objectiveId);
        if (!$objective || $objective->getCoach() !== $coach) {
            return new JsonResponse(['success' => false, 'message' => 'Objectif non trouvé'], 404);
        }

        $comment = $this->commentRepository->find($commentId);
        if (!$comment || $comment->getObjective() !== $objective) {
            return new JsonResponse(['success' => false, 'message' => 'Commentaire non trouvé'], 404);
        }

        // Vérifier que le commentaire appartient au coach
        if ($comment->getAuthorType() !== 'coach' || $comment->getCoach() !== $coach) {
            return new JsonResponse(['success' => false, 'message' => 'Vous n\'avez pas le droit de supprimer ce commentaire'], 403);
        }

        $this->em->remove($comment);
        $this->em->flush();

        return new JsonResponse(['success' => true, 'message' => 'Commentaire supprimé avec succès']);
    }
}

