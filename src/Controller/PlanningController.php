<?php

namespace App\Controller;

use App\Controller\Trait\CoachTrait;
use App\Entity\Planning;
use App\Repository\ActivityRepository;
use App\Repository\CoachRepository;
use App\Repository\FamilyRepository;
use App\Repository\PathRepository;
use App\Repository\PlanningRepository;
use App\Repository\ProofRepository;
use App\Repository\RequestRepository;
use App\Repository\SpecialistRepository;
use App\Repository\StudentRepository;
use App\Repository\TaskRepository;
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

class PlanningController extends AbstractController
{
    use CoachTrait;

    public function __construct(
        private readonly PlanningRepository $planningRepository,
        private readonly FamilyRepository $familyRepository,
        private readonly CoachRepository $coachRepository,
        private readonly Security $security,
        private readonly EntityManagerInterface $em,
        private readonly StudentRepository $studentRepository,
        private readonly ValidatorInterface $validator,
        private readonly PermissionService $permissionService,
        private readonly SpecialistRepository $specialistRepository,
        private readonly TaskRepository $taskRepository,
        private readonly ProofRepository $proofRepository,
        private readonly FileStorageService $fileStorageService,
        private readonly ActivityRepository $activityRepository,
        private readonly PathRepository $pathRepository,
        private readonly RequestRepository $requestRepository
    ) {
    }

    #[Route('/admin/planning', name: 'admin_planning_list')]
    #[IsGranted('ROLE_USER')]
    public function list(Request $request): Response
    {
        $user = $this->getUser();
        if (!$user) {
            throw $this->createAccessDeniedException('Vous devez être connecté');
        }

        // Récupérer la semaine demandée (par défaut semaine courante)
        $weekOffset = (int) $request->query->get('week', 0);
        
        // Calculer le début de la semaine (lundi)
        // 1. D'abord, calculer le lundi de la semaine courante
        $currentDate = new \DateTime();
        $dayOfWeek = (int) $currentDate->format('N'); // 1 = lundi, 7 = dimanche
        $mondayOffset = -($dayOfWeek - 1); // Si lundi (1), offset = 0; si dimanche (7), offset = -6
        $currentDate->modify($mondayOffset . ' days');
        
        // 2. Ensuite, appliquer l'offset de semaines (positif = semaines futures, négatif = semaines passées)
        $weekStart = \DateTimeImmutable::createFromMutable($currentDate);
        $weekStart = $weekStart->setTime(0, 0, 0);
        if ($weekOffset != 0) {
            // Gérer correctement les offsets négatifs
            $daysOffset = $weekOffset * 7;
            if ($daysOffset < 0) {
                $weekStart = $weekStart->modify($daysOffset . ' days');
            } else {
                $weekStart = $weekStart->modify('+' . $daysOffset . ' days');
            }
        }
        
        // Récupérer uniquement les tâches d'objectifs pour la semaine
        $weekEnd = $weekStart->modify('+6 days')->setTime(23, 59, 59);
        $taskEvents = [];
        
        // Déterminer le coach et les étudiants pour filtrer les tâches
        $coach = null;
        
        if ($user->isCoach()) {
            $coach = $this->getCurrentCoach($this->coachRepository, $this->security);
        }
        
        // Récupérer les tâches
        $tasks = $this->taskRepository->findByWeek($weekStart, $coach, null);
        
        // Convertir les tâches en événements de planning
        foreach ($tasks as $task) {
            $student = $task->getObjective()?->getStudent();
            if (!$student) {
                continue;
            }
            
            // Vérifier les permissions
            if (!$this->permissionService->canViewStudentPlanning($user, $student)) {
                continue;
            }
            
            $taskEvents = array_merge($taskEvents, $task->toPlanningEvents($weekStart, $weekEnd));
        }
        
        // Générer les 7 jours de la semaine
        $weekDays = [];
        $dayNames = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];
        
        // Utiliser une variable mutable pour éviter les problèmes avec DateTimeImmutable
        $currentDay = \DateTime::createFromImmutable($weekStart);
        
        for ($i = 0; $i < 7; $i++) {
            if ($i > 0) {
                $currentDay->modify('+1 day');
            }
            $dayKey = $currentDay->format('Y-m-d');
            
            $weekDays[] = [
                'date' => $dayKey,
                'name' => $dayNames[$i],
                'day' => $currentDay->format('d'),
                'month' => $currentDay->format('m'),
                'events' => [],
            ];
        }
        
        // Distribuer les événements de tâches dans les jours de la semaine
        foreach ($taskEvents as $taskEvent) {
            if (isset($taskEvent['startDate'])) {
                $eventDate = new \DateTimeImmutable($taskEvent['startDate']);
                $dayKey = $eventDate->format('Y-m-d');
                // Trouver le jour correspondant dans le tableau
                foreach ($weekDays as &$dayData) {
                    if ($dayData['date'] === $dayKey) {
                        $dayData['events'][] = $taskEvent;
                        break;
                    }
                }
                unset($dayData);
            }
        }
        
        $weekEnd = $weekStart->modify('+6 days');
        
        // Calculer les offsets pour la navigation
        $prevWeek = $weekOffset - 1;
        $nextWeek = $weekOffset + 1;
        
        // Formater la plage de la semaine
        $weekRange = $weekStart->format('d/m/Y') . ' - ' . $weekEnd->format('d/m/Y');

        return $this->render('tailadmin/pages/planning/list.html.twig', [
            'pageTitle' => 'Planning ',
            'pageName' => 'Planning',
            'weekDays' => $weekDays,
            'weekOffset' => $weekOffset,
            'weekRange' => $weekRange,
            'prevWeek' => $prevWeek,
            'nextWeek' => $nextWeek,
            'weekStartFormatted' => $weekStart->format('d/m/Y'),
            'weekEndFormatted' => $weekEnd->format('d/m/Y'),
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => $this->generateUrl('admin_dashboard')],
            ],
        ]);
    }

    #[Route('/admin/planning/create', name: 'admin_planning_create', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function create(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['success' => false, 'message' => 'Vous devez être connecté'], 403);
        }

        $data = json_decode($request->getContent(), true);
        $planning = new Planning();
        
        // Vérifier les champs requis
        if (empty($data['title'])) {
            return new JsonResponse(['success' => false, 'message' => 'Le titre est requis'], 400);
        }
        if (empty($data['startDate'])) {
            return new JsonResponse(['success' => false, 'message' => 'La date de début est requise'], 400);
        }
        if (empty($data['endDate'])) {
            return new JsonResponse(['success' => false, 'message' => 'La date de fin est requise'], 400);
        }
        
        $planning->setTitle($data['title']);
        if (isset($data['description'])) $planning->setDescription($data['description']);
        $planning->setType($data['type'] ?? Planning::TYPE_OTHER);
        $planning->setStatus($data['status'] ?? Planning::STATUS_TO_DO);
        
        try {
            $planning->setStartDate(new \DateTimeImmutable($data['startDate']));
        } catch (\Exception $e) {
            return new JsonResponse(['success' => false, 'message' => 'Format de date de début invalide: ' . $e->getMessage()], 400);
        }
        
        try {
            $planning->setEndDate(new \DateTimeImmutable($data['endDate']));
        } catch (\Exception $e) {
            return new JsonResponse(['success' => false, 'message' => 'Format de date de fin invalide: ' . $e->getMessage()], 400);
        }
        
        // Déterminer l'utilisateur cible pour l'événement
        $targetUser = null;
        if (isset($data['userId'])) {
            $targetUser = $this->em->getRepository(\App\Entity\User::class)->find($data['userId']);
            if (!$targetUser) {
                return new JsonResponse(['success' => false, 'message' => 'Utilisateur non trouvé'], 400);
            }
        } else {
            // Par défaut, associer le planning à l'utilisateur connecté
            $targetUser = $user;
        }

        // Vérifier les permissions de création
        if (!$this->permissionService->canCreatePlanning($user, $targetUser)) {
            return new JsonResponse(['success' => false, 'message' => 'Vous n\'avez pas le droit de créer un événement pour cet utilisateur'], 403);
        }

        $planning->setUser($targetUser);
        
        // Stocker le créateur dans les métadonnées pour permettre la suppression ultérieure
        $metadata = $planning->getMetadata() ?? [];
        $metadata['creatorId'] = $user->getId();
        $metadata['creatorType'] = $user->getUserType();
        $planning->setMetadata($metadata);

        // Validation
        $errors = $this->validator->validate($planning);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $property = $error->getPropertyPath();
                $message = $error->getMessage();
                $errorMessages[] = $property . ': ' . $message;
            }
            return new JsonResponse([
                'success' => false, 
                'message' => 'Erreurs de validation: ' . implode(', ', $errorMessages),
                'errors' => $errorMessages
            ], 400);
        }

        $this->em->persist($planning);
        $this->em->flush();

        return new JsonResponse(['success' => true, 'id' => $planning->getId(), 'message' => 'Événement créé avec succès']);
    }

    #[Route('/admin/planning/{id}/update', name: 'admin_planning_update', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function update(int $id, Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['success' => false, 'message' => 'Vous devez être connecté'], 403);
        }

        $planning = $this->planningRepository->find($id);
        if (!$planning) {
            return new JsonResponse(['success' => false, 'message' => 'Événement non trouvé'], 404);
        }

        // Vérifier les permissions de modification
        if (!$this->permissionService->canModifyPlanning($user, $planning)) {
            return new JsonResponse(['success' => false, 'message' => 'Vous n\'avez pas le droit de modifier cet événement'], 403);
        }

        $data = json_decode($request->getContent(), true);
        if (isset($data['title'])) $planning->setTitle($data['title']);
        if (isset($data['description'])) $planning->setDescription($data['description']);
        if (isset($data['type'])) $planning->setType($data['type']);
        if (isset($data['status'])) $planning->setStatus($data['status']);
        if (isset($data['startDate'])) {
            $planning->setStartDate(new \DateTimeImmutable($data['startDate']));
        }
        if (isset($data['endDate'])) {
            $planning->setEndDate(new \DateTimeImmutable($data['endDate']));
        }
        
        // Vérifier les permissions si l'utilisateur change
        if (isset($data['userId'])) {
            $targetUser = $this->em->getRepository(\App\Entity\User::class)->find($data['userId']);
            if ($targetUser) {
                // Vérifier les permissions de création pour le nouvel utilisateur
                if (!$this->permissionService->canCreatePlanning($user, $targetUser)) {
                    return new JsonResponse(['success' => false, 'message' => 'Vous n\'avez pas le droit de créer un événement pour cet utilisateur'], 403);
                }
                $planning->setUser($targetUser);
            }
        }

        // Validation
        $errors = $this->validator->validate($planning);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            return new JsonResponse(['success' => false, 'message' => implode(', ', $errorMessages)], 400);
        }

        $this->em->flush();

        return new JsonResponse(['success' => true, 'message' => 'Événement modifié avec succès']);
    }

    #[Route('/admin/planning/{id}/delete', name: 'admin_planning_delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_USER')]
    public function delete(int $id): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['success' => false, 'message' => 'Vous devez être connecté'], 403);
        }

        $planning = $this->planningRepository->find($id);
        if (!$planning) {
            return new JsonResponse(['success' => false, 'message' => 'Événement non trouvé'], 404);
        }

        // Vérifier les permissions de suppression
        if (!$this->permissionService->canModifyPlanning($user, $planning)) {
            return new JsonResponse(['success' => false, 'message' => 'Vous n\'avez pas le droit de supprimer cet événement'], 403);
        }

        $this->em->remove($planning);
        $this->em->flush();

        return new JsonResponse(['success' => true, 'message' => 'Événement supprimé avec succès']);
    }

    #[Route('/admin/planning/event-sheet', name: 'admin_planning_event_sheet', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getEventSheet(Request $request): Response
    {
        $user = $this->getUser();
        if (!$user) {
            throw $this->createAccessDeniedException('Vous devez être connecté');
        }

        // Récupérer les paramètres de la requête
        $mode = $request->query->get('mode', 'create'); // 'create', 'edit', 'view'
        $eventId = $request->query->get('id');
        $date = $request->query->get('date');

        $isEdit = $mode === 'edit';
        $isView = $mode === 'view';
        $currentId = $eventId ? (int) $eventId : null;

        // Récupérer les données nécessaires
        $coach = null;
        $students = [];
        $specialists = [];
        $parent = null;

        if ($user->isCoach()) {
            $coach = $this->getCurrentCoach($this->coachRepository, $this->security);
            if ($coach) {
                $families = $this->familyRepository->findBy(['coach' => $coach]);
                foreach ($families as $family) {
                    foreach ($family->getStudents() as $student) {
                        $students[] = $student;
                    }
                }
            }
            $specialists = $this->specialistRepository->findAll();
        } elseif ($user->isParent()) {
            $parent = $user;
            $families = $this->familyRepository->findBy(['parentUser' => $parent]);
            foreach ($families as $family) {
                foreach ($family->getStudents() as $student) {
                    $students[] = $student;
                }
            }
        } elseif ($user->isStudent()) {
            $students = [$user];
        } elseif ($user->isSpecialist()) {
            $specialists = [$user];
        }

        // Préparer les données pour le template
        $viewData = [];
        $formData = [];

        if ($isView || $isEdit) {
            // Charger l'événement existant
            $planning = $this->planningRepository->find($currentId);
            if (!$planning) {
                throw $this->createNotFoundException('Événement non trouvé');
            }

            // Vérifier les permissions
            if (!$this->permissionService->canViewStudentPlanning($user, $planning->getUser())) {
                throw $this->createAccessDeniedException('Vous n\'avez pas le droit de voir cet événement');
            }

            $startDate = $planning->getStartDate();
            $endDate = $planning->getEndDate();

            // Parser les dates
            $dateStr = '';
            $startTime = '';
            $endTime = '';

            if ($startDate) {
                $dateStr = $startDate->format('Y-m-d');
                $startTime = $startDate->format('H:i');
            }

            if ($endDate) {
                $endTime = $endDate->format('H:i');
            }

            // Trouver le nom de l'utilisateur
            $userName = 'N/A';
            $planningUser = $planning->getUser();
            if ($planningUser) {
                $userName = $planningUser->getFirstName() . ' ' . $planningUser->getLastName();
            }

            // Mapper le statut
            $statusMap = [
                'completed' => 'Terminé',
                'in_progress' => 'En cours',
                'to_do' => 'À faire',
                'scheduled' => 'Planifié',
            ];

            // Mapper le type
            $typeMap = [
                'homework' => 'Devoir',
                'course' => 'Cours',
                'revision' => 'Révision',
                'activity' => 'Activité',
                'assessment' => 'Évaluation',
                'task' => 'Tâche',
                'other' => 'Autre',
            ];

            $viewData = [
                'id' => $planning->getId(),
                'title' => $planning->getTitle() ?: 'N/A',
                'description' => $planning->getDescription() ?: 'Aucune description',
                'type' => $typeMap[$planning->getType()] ?? $planning->getType() ?: 'N/A',
                'status' => $statusMap[$planning->getStatus()] ?? $planning->getStatus() ?: 'N/A',
                'userId' => $planningUser ? $planningUser->getId() : '',
                'studentName' => $userName,
                'date' => $dateStr ?: 'N/A',
                'startTime' => $startTime ?: 'N/A',
                'endTime' => $endTime ?: 'N/A',
                'startDate' => $startDate ? $startDate->format('c') : '',
                'endDate' => $endDate ? $endDate->format('c') : '',
            ];

            if ($isEdit) {
                // Déterminer le type d'utilisateur
                $userType = '';
                if ($planningUser) {
                    if ($coach && $planningUser->getId() === $coach->getId()) {
                        $userType = 'coach';
                    } elseif ($parent && $planningUser->getId() === $parent->getId()) {
                        $userType = 'parent';
                    } elseif (in_array($planningUser, $students)) {
                        $userType = 'student';
                    } elseif (in_array($planningUser, $specialists)) {
                        $userType = 'specialist';
                    }
                }

                $formData = [
                    'title' => $planning->getTitle() ?: '',
                    'description' => $planning->getDescription() ?: '',
                    'type' => $planning->getType() ?: '',
                    'status' => $planning->getStatus() ?: 'scheduled',
                    'userType' => $userType,
                    'userId' => $planningUser ? $planningUser->getId() : '',
                    'date' => $dateStr ?: (new \DateTime())->format('Y-m-d'),
                    'startTime' => $startTime ?: '09:00',
                    'endTime' => $endTime ?: '10:00',
                    'startDate' => $startDate ? $startDate->format('c') : '',
                    'endDate' => $endDate ? $endDate->format('c') : '',
                ];
            }
        } else {
            // Mode création
            $selectedDate = $date ?: (new \DateTime())->format('Y-m-d');

            // Préremplir selon le type d'utilisateur
            $userType = '';
            $userId = '';
            if ($user->isStudent()) {
                $userType = 'student';
                $userId = $user->getId();
            } elseif ($user->isSpecialist()) {
                $userType = 'specialist';
                $userId = $user->getId();
            }

            $formData = [
                'title' => '',
                'description' => '',
                'type' => '',
                'status' => 'scheduled',
                'userType' => $userType,
                'userId' => $userId,
                'date' => $selectedDate,
                'startTime' => '09:00',
                'endTime' => '10:00',
                'startDate' => '',
                'endDate' => '',
            ];
        }

        return $this->render('tailadmin/pages/planning/_planning_rightsheet.html.twig', [
            'isEdit' => $isEdit,
            'isView' => $isView,
            'currentId' => $currentId,
            'viewData' => $viewData,
            'formData' => $formData,
            'students' => $students,
            'specialists' => $specialists,
            'coach' => $coach,
            'parent' => $parent,
        ]);
    }

    #[Route('/admin/planning/task/{taskId}/details', name: 'admin_planning_task_details', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getTaskDetails(int $taskId, Request $request): Response
    {
        $user = $this->getUser();
        if (!$user) {
            throw $this->createAccessDeniedException('Vous devez être connecté');
        }

        $task = $this->taskRepository->find($taskId);
        if (!$task) {
            throw $this->createNotFoundException('Tâche non trouvée');
        }

        $student = $task->getObjective()?->getStudent();
        if (!$student) {
            throw $this->createNotFoundException('Élève non trouvé pour cette tâche');
        }

        // Vérifier les permissions
        if (!$this->permissionService->canViewStudentPlanning($user, $student)) {
            throw $this->createAccessDeniedException('Vous n\'avez pas le droit de voir cette tâche');
        }

        // Récupérer la date de l'événement depuis le planning si fournie (depuis le paramètre eventDate)
        $eventDate = null;
        if ($request->query->has('eventDate')) {
            try {
                $eventDate = new \DateTimeImmutable($request->query->get('eventDate'));
            } catch (\Exception $e) {
                // Ignorer si la date est invalide
            }
        }

        // Récupérer les preuves
        // Si eventDate est fourni, filtrer les preuves par date de soumission (même jour)
        if ($eventDate) {
            $startOfDay = $eventDate->setTime(0, 0, 0);
            $endOfDay = $eventDate->setTime(23, 59, 59);
            $proofs = $this->proofRepository->createQueryBuilder('p')
                ->where('p.task = :task')
                ->andWhere('p.submittedAt >= :startOfDay')
                ->andWhere('p.submittedAt <= :endOfDay')
                ->setParameter('task', $task)
                ->setParameter('startOfDay', $startOfDay)
                ->setParameter('endOfDay', $endOfDay)
                ->orderBy('p.submittedAt', 'ASC')
                ->getQuery()
                ->getResult();
        } else {
            // Sinon, récupérer toutes les preuves
            $proofs = $this->proofRepository->findBy(['task' => $task], ['createdAt' => 'DESC']);
        }

        // Récupérer les données nécessaires pour les champs ManyToMany selon le type de tâche
        $taskType = $task->getType();
        $data = [
            'task' => $task,
            'student' => $student,
            'objective' => $task->getObjective(),
            'proofs' => $proofs,
            'currentUser' => $user,
            'eventDate' => $eventDate,
        ];

        // Pour WORKSHOP : specialists, activities, paths, students, families
        if ($taskType->value === 'workshop') {
            $coach = $this->getCurrentCoach($this->coachRepository, $this->security);
            $data['specialists'] = array_map(fn($s) => ['id' => $s->getId(), 'name' => $s->getFirstName() . ' ' . $s->getLastName()], $this->specialistRepository->findAll());
            $data['activities'] = array_map(fn($a) => ['id' => $a->getId(), 'title' => $a->getTitle()], $this->activityRepository->findAll());
            $data['paths'] = array_map(fn($p) => ['id' => $p->getId(), 'title' => $p->getTitle()], $this->pathRepository->findAll());
            $data['students'] = array_map(fn($s) => ['id' => $s->getId(), 'name' => $s->getFirstName() . ' ' . $s->getLastName()], $coach ? $this->studentRepository->findByCoach($coach) : []);
            $data['families'] = array_map(function($f) {
                $parent = $f->getParent();
                $name = $parent && $parent->getLastName() 
                    ? $parent->getLastName() 
                    : ($f->getType()->value === 'GROUP' ? 'Groupe' : 'Famille') . ' #' . $f->getId();
                return ['id' => $f->getId(), 'name' => $name];
            }, $coach ? $this->familyRepository->findByCoach($coach) : []);
        }

        // Pour ASSESSMENT, INDIVIDUAL_WORK* : students
        if (in_array($taskType->value, ['assessment', 'individual_work', 'individual_work_remote', 'individual_work_on_site'])) {
            $coach = $this->getCurrentCoach($this->coachRepository, $this->security);
            $data['students'] = array_map(fn($s) => ['id' => $s->getId(), 'name' => $s->getFirstName() . ' ' . $s->getLastName()], $coach ? $this->studentRepository->findByCoach($coach) : []);
        }

        // Pour INDIVIDUAL_WORK_REMOTE : requests
        if ($taskType->value === 'individual_work_remote') {
            $coach = $this->getCurrentCoach($this->coachRepository, $this->security);
            // Récupérer les demandes liées à l'étudiant ou au coach
            $data['requests'] = [];
            if ($coach && $student) {
                $family = $student->getFamily();
                $familyId = $family ? $family->getId() : null;
                $requests = $this->requestRepository->findByCoachWithSearch($coach, null, $familyId, $student->getId());
                $data['requests'] = array_map(fn($r) => ['id' => $r->getId(), 'title' => $r->getTitle()], $requests);
            }
        }

        return $this->render('tailadmin/pages/planning/_task_rightsheet.html.twig', $data);
    }

    #[Route('/admin/planning/task/{taskId}/proof', name: 'admin_planning_task_proof_create', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function createProof(int $taskId, Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['success' => false, 'message' => 'Vous devez être connecté'], 403);
        }

        $task = $this->taskRepository->find($taskId);
        if (!$task) {
            return new JsonResponse(['success' => false, 'message' => 'Tâche non trouvée'], 404);
        }

        $student = $task->getObjective()?->getStudent();
        if (!$student) {
            return new JsonResponse(['success' => false, 'message' => 'Élève non trouvé'], 404);
        }

        // Vérifier les permissions
        if (!$this->permissionService->canViewStudentPlanning($user, $student)) {
            return new JsonResponse(['success' => false, 'message' => 'Vous n\'avez pas le droit d\'ajouter une preuve'], 403);
        }

        $data = json_decode($request->getContent(), true);

        // Créer la preuve
        $proof = new \App\Entity\Proof();
        $proof->setTask($task);
        $proof->setSubmittedBy($user);
        $proof->setTitle($data['title'] ?? 'Preuve');
        $proof->setDescription($data['description'] ?? null);
        $proof->setType('text');

        // Gérer l'upload d'image si présent
        if (isset($data['fileBase64']) && isset($data['fileName'])) {
            try {
                $extension = pathinfo($data['fileName'], PATHINFO_EXTENSION) ?: 'jpg';
                $filePath = $this->fileStorageService->saveBase64File($data['fileBase64'], $extension);
                $proof->setFilePath($filePath);
                $proof->setFileName($data['fileName']);
                $proof->setFileUrl($this->fileStorageService->generateSecureUrl($filePath));
                $proof->setFileSize($data['fileSize'] ?? null);
                $proof->setMimeType($data['mimeType'] ?? null);
                $proof->setType('image');
            } catch (\Exception $e) {
                return new JsonResponse(['success' => false, 'message' => 'Erreur lors de l\'upload: ' . $e->getMessage()], 400);
            }
        } elseif (isset($data['content']) && !empty(trim($data['content']))) {
            // Si pas d'image mais du contenu texte, utiliser le contenu
            $proof->setContent($data['content']);
            $proof->setType('text');
        } elseif (isset($data['description']) && !empty(trim($data['description']))) {
            // Si seulement description, l'utiliser comme contenu
            $proof->setContent($data['description']);
            $proof->setType('text');
        }

        // Gérer les champs ManyToMany selon le type de tâche
        $taskType = $task->getType();
        
        // Specialists (pour WORKSHOP)
        if (isset($data['specialistIds']) && is_array($data['specialistIds']) && $taskType->value === 'workshop') {
            foreach ($data['specialistIds'] as $specialistId) {
                $specialist = $this->specialistRepository->find($specialistId);
                if ($specialist) {
                    $proof->addSpecialist($specialist);
                }
            }
        }

        // Activities (pour WORKSHOP)
        if (isset($data['activityIds']) && is_array($data['activityIds']) && $taskType->value === 'workshop') {
            foreach ($data['activityIds'] as $activityId) {
                $activity = $this->activityRepository->find($activityId);
                if ($activity) {
                    $proof->addActivity($activity);
                }
            }
        }

        // Paths (pour WORKSHOP)
        if (isset($data['pathIds']) && is_array($data['pathIds']) && $taskType->value === 'workshop') {
            foreach ($data['pathIds'] as $pathId) {
                $path = $this->pathRepository->find($pathId);
                if ($path) {
                    $proof->addPath($path);
                }
            }
        }

        // Students (pour WORKSHOP, ASSESSMENT, INDIVIDUAL_WORK*)
        if (isset($data['studentIds']) && is_array($data['studentIds'])) {
            $allowedTypes = ['workshop', 'assessment', 'individual_work', 'individual_work_remote', 'individual_work_on_site'];
            if (in_array($taskType->value, $allowedTypes)) {
                foreach ($data['studentIds'] as $studentId) {
                    $student = $this->studentRepository->find($studentId);
                    if ($student) {
                        $proof->addStudent($student);
                    }
                }
            }
        }

        // Request (pour INDIVIDUAL_WORK_REMOTE)
        if (isset($data['requestId']) && !empty($data['requestId']) && $taskType->value === 'individual_work_remote') {
            $request = $this->requestRepository->find($data['requestId']);
            if ($request) {
                $proof->setRequest($request);
            }
        }

        // Gérer la date de soumission (submittedAt)
        // Priorité 1 : Si planningId fourni, utiliser planning.startDate
        // Priorité 2 : Si eventDate fournie, utiliser eventDate (date de l'événement)
        // Priorité 3 : Si submittedAt du formulaire fourni, l'utiliser
        // Priorité 4 : Par défaut, date du jour
        $planning = null;
        if (isset($data['planningId']) && !empty($data['planningId'])) {
            $planning = $this->planningRepository->find($data['planningId']);
            if ($planning && $planning->getStartDate()) {
                $proof->setSubmittedAt($planning->getStartDate());
                $proof->setPlanning($planning);
            }
        }
        
        if (!$proof->getSubmittedAt()) {
            if (isset($data['eventDate']) && !empty($data['eventDate'])) {
                // Depuis planning : utiliser la date de l'événement
                try {
                    $proof->setSubmittedAt(new \DateTimeImmutable($data['eventDate']));
                } catch (\Exception $e) {
                    // En cas d'erreur, utiliser la date du jour
                    $proof->setSubmittedAt(new \DateTimeImmutable());
                }
            } elseif (isset($data['submittedAt']) && !empty($data['submittedAt'])) {
                // Depuis objectifs : utiliser la date du formulaire (modifiable via calendrier)
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

        return new JsonResponse([
            'success' => true,
            'message' => 'Preuve ajoutée avec succès',
            'proofId' => $proof->getId()
        ]);
    }
}
