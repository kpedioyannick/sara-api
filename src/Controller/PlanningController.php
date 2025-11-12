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
        $eventType = $request->query->get('type'); // Type d'événement pour le filtre
        
        // Appliquer un filtre par défaut selon le rôle de l'utilisateur si aucun filtre n'est spécifié
        // Par défaut, afficher le planning de l'utilisateur connecté
        if (!$profileType && !$selectedIds && !$studentId) {
            // Récupérer le planning de l'utilisateur connecté
            $userEvents = $this->planningRepository->findByUserAndWeek($user, $weekStart);
            $allEvents = $userEvents;
            $students = [];
            
            // Récupérer les élèves pour les filtres (selon le rôle)
            if ($user->isCoach()) {
                $coach = $this->getCurrentCoach($this->coachRepository, $this->security);
                if ($coach) {
                    $students = $this->studentRepository->findByCoach($coach);
                }
            } else {
                $students = $this->permissionService->getAccessibleStudents($user);
            }
        } else {
            // Récupérer les événements selon le rôle de l'utilisateur et les filtres
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
        }
        
        // Filtrer par élève si spécifié (ancien système)
        if ($studentId) {
            $allEvents = array_filter($allEvents, function($event) use ($studentId, $user) {
                $eventUser = $event->getUser();
                if (!$eventUser || $eventUser->getId() != (int)$studentId) {
                    return false;
                }
                // Si c'est un étudiant, vérifier les permissions
                if ($eventUser->isStudent()) {
                    return $this->permissionService->canViewStudentPlanning($user, $eventUser);
                }
                return true;
            });
        }
        
        // Appliquer les filtres par profil si spécifiés
        if ($profileType && $selectedIds) {
            $ids = array_filter(array_map('intval', explode(',', $selectedIds)));
            if (!empty($ids)) {
                $filteredEvents = [];
                foreach ($allEvents as $event) {
                    $shouldInclude = false;
                    $eventUser = $event->getUser();
                    
                    if (!$eventUser) {
                        continue; // Ignorer les événements sans utilisateur
                    }
                    
                    if ($profileType === 'parent') {
                        // Filtrer par parent (planning des enfants du parent)
                        if ($eventUser->isStudent() && $eventUser->getFamily() && $eventUser->getFamily()->getParent()) {
                            if (in_array($eventUser->getFamily()->getParent()->getId(), $ids)) {
                                $shouldInclude = true;
                            }
                        }
                    } elseif ($profileType === 'specialist') {
                        // Filtrer par spécialiste (planning des élèves assignés au spécialiste)
                        if ($eventUser->isStudent()) {
                            foreach ($eventUser->getSpecialists() as $specialist) {
                                if (in_array($specialist->getId(), $ids)) {
                                    $shouldInclude = true;
                                    break;
                                }
                            }
                        }
                    } elseif ($profileType === 'student') {
                        // Filtrer par élève
                        if ($eventUser->isStudent() && in_array($eventUser->getId(), $ids)) {
                            $shouldInclude = true;
                        }
                    }
                    
                    // Vérifier les permissions avant d'inclure l'événement
                    if ($shouldInclude) {
                        if ($eventUser->isStudent()) {
                            if ($this->permissionService->canViewStudentPlanning($user, $eventUser)) {
                                $filteredEvents[] = $event;
                            }
                        } else {
                            // Pour les autres utilisateurs (coach, parent, specialist), inclure si c'est leur propre planning
                            if ($eventUser->getId() === $user->getId()) {
                                $filteredEvents[] = $event;
                            }
                        }
                    }
                }
                $allEvents = $filteredEvents;
            }
        }
        
        // Filtrer par type d'événement si spécifié
        if ($eventType) {
            $allEvents = array_filter($allEvents, function($event) use ($eventType) {
                return $event->getType() === $eventType;
            });
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
        
        $coachData = null;
        if ($user->isCoach()) {
            $coach = $this->getCurrentCoach($this->coachRepository, $this->security);
            if ($coach) {
                $coachData = [
                    'id' => $coach->getId(),
                    'firstName' => $coach->getFirstName(),
                    'lastName' => $coach->getLastName(),
                    'email' => $coach->getEmail(),
                ];
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
            // Ajouter les données du parent connecté
            $coachData = [
                'id' => $user->getId(),
                'firstName' => $user->getFirstName(),
                'lastName' => $user->getLastName(),
                'email' => $user->getEmail(),
            ];
        } elseif ($user->isSpecialist()) {
            // Pour les spécialistes, les élèves sont déjà récupérés via PermissionService
            // Ajouter le spécialiste connecté à specialistsData pour qu'il puisse se sélectionner
            $specialistsData = [[
                'id' => $user->getId(),
                'firstName' => $user->getFirstName(),
                'lastName' => $user->getLastName(),
            ]];
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
            'coach' => $coachData,
            'profileType' => $profileType,
            'selectedIds' => $selectedIds,
            'currentUserId' => $user->getId(),
            'currentUserType' => $user->getUserType(),
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
}
