<?php

namespace App\Controller;

use App\Controller\Trait\CoachTrait;
use App\Entity\Planning;
use App\Repository\CoachRepository;
use App\Repository\FamilyRepository;
use App\Repository\PlanningRepository;
use App\Repository\SpecialistRepository;
use App\Repository\StudentRepository;
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
        private readonly SpecialistRepository $specialistRepository
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
        
        // Récupérer le paramètre de filtrage par élève
        $studentId = $request->query->get('student');
        $profileType = $request->query->get('profileType'); // 'parent', 'specialist', 'student', ou null
        $selectedIds = $request->query->get('selectedIds', ''); // IDs séparés par des virgules
        
        // Récupérer les événements selon le rôle de l'utilisateur
        $allEvents = [];
        $students = [];

        if ($user->isCoach()) {
            $coach = $this->getCurrentCoach($this->coachRepository, $this->security);
            if (!$coach) {
                throw $this->createNotFoundException('Aucun coach trouvé');
            }
            // Récupérer toutes les familles du coach
            $families = $this->familyRepository->findByCoachWithSearch($coach);
            // Récupérer tous les événements de toutes les familles pour la semaine
            foreach ($families as $family) {
                $events = $this->planningRepository->findByFamilyAndWeek($family, $weekStart);
                $allEvents = array_merge($allEvents, $events);
            }
            $students = $this->studentRepository->findByCoach($coach);
        } else {
            // Pour les autres rôles, utiliser PermissionService
            $accessibleStudents = $this->permissionService->getAccessibleStudents($user);
            foreach ($accessibleStudents as $student) {
                // Vérifier les permissions pour voir le planning de cet étudiant
                if ($this->permissionService->canViewStudentPlanning($user, $student)) {
                    // Utiliser findByStudentAndWeek pour récupérer directement les événements de l'étudiant
                    $studentEvents = $this->planningRepository->findByStudentAndWeek($student, $weekStart);
                    $allEvents = array_merge($allEvents, $studentEvents);
                }
            }
            $students = $accessibleStudents;
        }
        
        // Filtrer par élève si spécifié (ancien système)
        if ($studentId) {
            $allEvents = array_filter($allEvents, function($event) use ($studentId, $user) {
                $student = $event->getStudent();
                if (!$student || $student->getId() != (int)$studentId) {
                    return false;
                }
                // Vérifier les permissions pour cet étudiant
                return $this->permissionService->canViewStudentPlanning($user, $student);
            });
        }
        
        // Appliquer les filtres par profil si spécifiés
        if ($profileType && $selectedIds) {
            $ids = array_filter(array_map('intval', explode(',', $selectedIds)));
            if (!empty($ids)) {
                $filteredEvents = [];
                foreach ($allEvents as $event) {
                    $shouldInclude = false;
                    $eventStudent = $event->getStudent();
                    
                    if (!$eventStudent) {
                        continue; // Ignorer les événements sans élève
                    }
                    
                    if ($profileType === 'parent') {
                        // Filtrer par parent (planning des enfants du parent)
                        if ($eventStudent->getFamily() && $eventStudent->getFamily()->getParent()) {
                            if (in_array($eventStudent->getFamily()->getParent()->getId(), $ids)) {
                                $shouldInclude = true;
                            }
                        }
                    } elseif ($profileType === 'specialist') {
                        // Filtrer par spécialiste (planning des élèves assignés au spécialiste)
                        foreach ($eventStudent->getSpecialists() as $specialist) {
                            if (in_array($specialist->getId(), $ids)) {
                                $shouldInclude = true;
                                break;
                            }
                        }
                    } elseif ($profileType === 'student') {
                        // Filtrer par élève
                        if (in_array($eventStudent->getId(), $ids)) {
                            $shouldInclude = true;
                        }
                    }
                    
                    // Vérifier les permissions avant d'inclure l'événement
                    if ($shouldInclude && $this->permissionService->canViewStudentPlanning($user, $eventStudent)) {
                        $filteredEvents[] = $event;
                    }
                }
                $allEvents = $filteredEvents;
            }
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
        
        // Distribuer les événements dans les jours de la semaine
        foreach ($allEvents as $event) {
            $eventDate = $event->getStartDate();
            if ($eventDate) {
                $dayKey = $eventDate->format('Y-m-d');
                // Trouver le jour correspondant dans le tableau
                foreach ($weekDays as &$dayData) {
                    if ($dayData['date'] === $dayKey) {
                        $dayData['events'][] = $event->toTemplateArray();
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
        
        // Préparer les données des étudiants pour les formulaires
        $studentsData = array_map(fn($student) => [
            'id' => $student->getId(),
            'firstName' => $student->getFirstName(),
            'lastName' => $student->getLastName(),
            'pseudo' => $student->getPseudo(),
            'class' => $student->getClass(),
        ], $students);

        // Récupérer les parents et spécialistes pour les filtres (selon le rôle)
        $parentsData = [];
        $specialistsData = [];
        
        if ($user->isCoach()) {
            $coach = $this->getCurrentCoach($this->coachRepository, $this->security);
            if ($coach) {
                $families = $this->familyRepository->findByCoachWithSearch($coach);
                foreach ($families as $family) {
                    $parent = $family->getParent();
                    if ($parent) {
                        $parentsData[] = [
                            'id' => $parent->getId(),
                            'firstName' => $parent->getFirstName(),
                            'lastName' => $parent->getLastName(),
                            'email' => $parent->getEmail(),
                        ];
                    }
                }
                // Récupérer tous les spécialistes
                $specialists = $this->specialistRepository->findAll();
                $specialistsData = array_map(fn($s) => [
                    'id' => $s->getId(),
                    'firstName' => $s->getFirstName(),
                    'lastName' => $s->getLastName(),
                ], $specialists);
            }
        } elseif ($user->isParent()) {
            // Pour les parents, les élèves sont déjà récupérés via PermissionService
            // Pas besoin de filtres par parent (ils voient leurs enfants)
        } elseif ($user->isSpecialist()) {
            // Pour les spécialistes, les élèves sont déjà récupérés via PermissionService
            // Pas besoin de filtres par spécialiste (ils voient leurs élèves assignés)
        }

        return $this->render('tailadmin/pages/planning/list.html.twig', [
            'pageTitle' => 'Planning | TailAdmin',
            'pageName' => 'Planning',
            'weekDays' => $weekDays,
            'weekOffset' => $weekOffset,
            'weekRange' => $weekRange,
            'prevWeek' => $prevWeek,
            'nextWeek' => $nextWeek,
            'weekStartFormatted' => $weekStart->format('d/m/Y'),
            'weekEndFormatted' => $weekEnd->format('d/m/Y'),
            'students' => $studentsData,
            'parents' => $parentsData,
            'specialists' => $specialistsData,
            'profileType' => $profileType,
            'selectedIds' => $selectedIds,
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

        // Seuls les coaches peuvent créer des événements de planning manuellement
        if (!$user->isCoach()) {
            return new JsonResponse(['success' => false, 'message' => 'Seul le coach peut créer des événements de planning'], 403);
        }

        $coach = $this->getCurrentCoach($this->coachRepository, $this->security);
        if (!$coach) {
            return new JsonResponse(['success' => false, 'message' => 'Coach non trouvé'], 404);
        }

        $data = json_decode($request->getContent(), true);
        $planning = new Planning();
        
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
        if (isset($data['studentId'])) {
            $student = $this->studentRepository->find($data['studentId']);
            if ($student) {
                // Vérifier les permissions pour voir le planning de cet étudiant
                if (!$this->permissionService->canViewStudentPlanning($coach, $student)) {
                    return new JsonResponse(['success' => false, 'message' => 'Vous n\'avez pas accès au planning de cet élève'], 403);
                }
                $planning->setStudent($student);
            } else {
                return new JsonResponse(['success' => false, 'message' => 'Élève non trouvé'], 400);
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

        // Seuls les coaches peuvent modifier des événements de planning manuellement
        if (!$user->isCoach()) {
            return new JsonResponse(['success' => false, 'message' => 'Seul le coach peut modifier des événements de planning'], 403);
        }

        $coach = $this->getCurrentCoach($this->coachRepository, $this->security);
        if (!$coach) {
            return new JsonResponse(['success' => false, 'message' => 'Coach non trouvé'], 404);
        }

        $planning = $this->planningRepository->find($id);
        if (!$planning) {
            return new JsonResponse(['success' => false, 'message' => 'Événement non trouvé'], 404);
        }

        // Vérifier les permissions pour voir le planning de l'étudiant
        $student = $planning->getStudent();
        if ($student && !$this->permissionService->canViewStudentPlanning($coach, $student)) {
            return new JsonResponse(['success' => false, 'message' => 'Vous n\'avez pas accès au planning de cet élève'], 403);
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

        // Seuls les coaches peuvent supprimer des événements de planning manuellement
        if (!$user->isCoach()) {
            return new JsonResponse(['success' => false, 'message' => 'Seul le coach peut supprimer des événements de planning'], 403);
        }

        $coach = $this->getCurrentCoach($this->coachRepository, $this->security);
        if (!$coach) {
            return new JsonResponse(['success' => false, 'message' => 'Coach non trouvé'], 404);
        }

        $planning = $this->planningRepository->find($id);
        if (!$planning) {
            return new JsonResponse(['success' => false, 'message' => 'Événement non trouvé'], 404);
        }

        // Vérifier les permissions pour voir le planning de l'étudiant
        $student = $planning->getStudent();
        if ($student && !$this->permissionService->canViewStudentPlanning($coach, $student)) {
            return new JsonResponse(['success' => false, 'message' => 'Vous n\'avez pas accès au planning de cet élève'], 403);
        }

        $this->em->remove($planning);
        $this->em->flush();

        return new JsonResponse(['success' => true, 'message' => 'Événement supprimé avec succès']);
    }
}
