<?php

namespace App\Service;

use App\Entity\Coach;
use App\Entity\Objective;
use App\Entity\ParentUser;
use App\Entity\Student;
use App\Entity\Specialist;
use App\Entity\Task;
use App\Entity\Request;
use App\Entity\Activity;
use App\Entity\Planning;
use App\Entity\User;
use App\Repository\TaskRepository;
use App\Repository\RequestRepository;
use Doctrine\ORM\EntityManagerInterface;

class PermissionService
{
    public function __construct(
        private readonly TaskRepository $taskRepository,
        private readonly RequestRepository $requestRepository,
        private readonly EntityManagerInterface $em
    ) {
    }
    /**
     * Vérifie si l'utilisateur peut accéder au menu Famille
     */
    public function canAccessFamilyMenu(User $user): bool
    {
        // Seuls les coaches et les parents peuvent accéder au menu Famille
        return $user instanceof Coach || $user instanceof ParentUser;
    }

    /**
     * Vérifie si l'utilisateur peut voir un objectif
     */
public function canViewObjective(User $user, Objective $objective): bool
    {
        // Coach : peut voir tous les objectifs de ses étudiants
        if ($user instanceof Coach) {
            return $objective->getCoach() === $user;
        }

        // Parent : peut voir les objectifs de ses enfants
        if ($user instanceof ParentUser) {
            $family = $user->getFamily();
            if (!$family) {
                return false;
            }
            $student = $objective->getStudent();
            return $student && $student->getFamily() === $family;
        }

        // Student : peut voir ses propres objectifs OU les objectifs partagés avec lui
        if ($user instanceof Student) {
            if ($objective->getStudent() === $user) {
                return true;
            }
            // Vérifier si l'objectif est partagé avec cet élève
            return $objective->getSharedStudents()->contains($user);
        }

        // Specialist : peut voir les objectifs des tâches qui lui sont affectées OU les objectifs partagés avec lui
        if ($user instanceof Specialist) {
            // Vérifier d'abord si l'objectif est partagé avec ce spécialiste
            if ($objective->getSharedSpecialists()->contains($user)) {
                return true;
            }
            // Sinon, vérifier les tâches affectées
            foreach ($objective->getTasks() as $task) {
                if ($task->getSpecialist() === $user) {
                    return true;
                }
            }
            return false;
        }

        return false;
    }

    /**
     * Vérifie si l'utilisateur peut créer un objectif pour un élève
     */
    public function canCreateObjective(User $user, Student $student): bool
    {
        // Coach : peut créer un objectif pour tous ses étudiants
        if ($user instanceof Coach) {
            $family = $student->getFamily();
            return $family && $family->getCoach() === $user;
        }

        // Parent : peut créer un objectif pour ses enfants
        if ($user instanceof ParentUser) {
            $family = $user->getFamily();
            return $family && $student->getFamily() === $family;
        }

        // Student : peut créer un objectif pour lui-même
        if ($user instanceof Student) {
            return $user === $student;
        }

        return false;
    }

    /**
     * Vérifie si l'utilisateur peut modifier un objectif
     */
    public function canModifyObjective(User $user, Objective $objective): bool
    {
        // Coach : peut toujours modifier un objectif dont il est le coach
        if ($user instanceof Coach) {
            return $objective->getCoach() === $user;
        }

        // Parent : peut modifier les objectifs de ses enfants uniquement si l'objectif n'est pas encore validé
        if ($user instanceof ParentUser) {
            $family = $user->getFamily();
            if (!$family) {
                return false;
            }
            $student = $objective->getStudent();
            if (!$student || $student->getFamily() !== $family) {
                return false;
            }
            
            // L'objectif ne doit pas être validé (statuts: validated, in_action, completed, paused)
            $validatedStatuses = [
                Objective::STATUS_VALIDATED,
                Objective::STATUS_IN_ACTION,
                Objective::STATUS_COMPLETED,
                Objective::STATUS_PAUSED,
            ];
            return !in_array($objective->getStatus(), $validatedStatuses);
        }

        // Student : peut modifier ses propres objectifs uniquement si l'objectif n'est pas encore validé
        if ($user instanceof Student) {
            $student = $objective->getStudent();
            if (!$student || $student !== $user) {
                return false;
            }
            
            // L'objectif ne doit pas être validé (statuts: validated, in_action, completed, paused)
            $validatedStatuses = [
                Objective::STATUS_VALIDATED,
                Objective::STATUS_IN_ACTION,
                Objective::STATUS_COMPLETED,
                Objective::STATUS_PAUSED,
            ];
            return !in_array($objective->getStatus(), $validatedStatuses);
        }

        return false;
    }

    /**
     * Vérifie si l'utilisateur peut supprimer un objectif
     */
    public function canDeleteObjective(User $user, Objective $objective): bool
    {
        // Coach : peut toujours supprimer un objectif dont il est le coach
        if ($user instanceof Coach) {
            return $objective->getCoach() === $user;
        }

        // Parent : peut supprimer les objectifs de ses enfants uniquement si l'objectif n'est pas encore validé
        if ($user instanceof ParentUser) {
            $family = $user->getFamily();
            if (!$family) {
                return false;
            }
            $student = $objective->getStudent();
            if (!$student || $student->getFamily() !== $family) {
                return false;
            }
            
            // L'objectif ne doit pas être validé (statuts: validated, in_action, completed, paused)
            $validatedStatuses = [
                Objective::STATUS_VALIDATED,
                Objective::STATUS_IN_ACTION,
                Objective::STATUS_COMPLETED,
                Objective::STATUS_PAUSED,
            ];
            return !in_array($objective->getStatus(), $validatedStatuses);
        }

        // Student : peut supprimer ses propres objectifs uniquement si l'objectif n'est pas encore validé
        if ($user instanceof Student) {
            $student = $objective->getStudent();
            if (!$student || $student !== $user) {
                return false;
            }
            
            // L'objectif ne doit pas être validé (statuts: validated, in_action, completed, paused)
            $validatedStatuses = [
                Objective::STATUS_VALIDATED,
                Objective::STATUS_IN_ACTION,
                Objective::STATUS_COMPLETED,
                Objective::STATUS_PAUSED,
            ];
            return !in_array($objective->getStatus(), $validatedStatuses);
        }

        return false;
    }

    /**
     * Vérifie si l'utilisateur peut voir une tâche
     */
    public function canViewTask(User $user, Task $task): bool
    {
        $objective = $task->getObjective();
        if (!$objective) {
            return false;
        }

        // Si on peut voir l'objectif, on peut voir la tâche
        return $this->canViewObjective($user, $objective);
    }

    /**
     * Vérifie si l'utilisateur peut modifier une tâche
     */
    public function canModifyTask(User $user, ?Task $task = null, ?Objective $objective = null): bool
    {
        // Récupérer l'objectif si on a une tâche
        if ($task && !$objective) {
            $objective = $task->getObjective();
        }
        
        if (!$objective) {
            // Si pas d'objectif, seul un coach peut créer une tâche
            return $user instanceof Coach;
        }

        // Coach : peut toujours modifier une tâche d'un objectif dont il est le coach
        if ($user instanceof Coach) {
            return $objective->getCoach() === $user;
        }

        // Parent : peut modifier les tâches des objectifs de ses enfants uniquement si l'objectif n'est pas encore validé
        if ($user instanceof ParentUser) {
            $family = $user->getFamily();
            if (!$family) {
                return false;
            }
            $student = $objective->getStudent();
            if (!$student || $student->getFamily() !== $family) {
                return false;
            }
            
            // L'objectif ne doit pas être validé (statuts: validated, in_action, completed, paused)
            $validatedStatuses = [
                Objective::STATUS_VALIDATED,
                Objective::STATUS_IN_ACTION,
                Objective::STATUS_COMPLETED,
                Objective::STATUS_PAUSED,
            ];
            return !in_array($objective->getStatus(), $validatedStatuses);
        }

        // Student : peut modifier les tâches de ses propres objectifs uniquement si l'objectif n'est pas encore validé
        if ($user instanceof Student) {
            $student = $objective->getStudent();
            if (!$student || $student !== $user) {
                return false;
            }
            
            // L'objectif ne doit pas être validé (statuts: validated, in_action, completed, paused)
            $validatedStatuses = [
                Objective::STATUS_VALIDATED,
                Objective::STATUS_IN_ACTION,
                Objective::STATUS_COMPLETED,
                Objective::STATUS_PAUSED,
            ];
            return !in_array($objective->getStatus(), $validatedStatuses);
        }

        return false;
    }

    /**
     * Vérifie si l'utilisateur peut compléter une tâche (ajouter une preuve)
     */
    public function canCompleteTask(User $user, Task $task): bool
    {
        $objective = $task->getObjective();
        if (!$objective) {
            return false;
        }

        // Coach : peut compléter toutes les tâches de ses objectifs
        if ($user instanceof Coach) {
            return $objective->getCoach() === $user;
        }

        // Student : peut compléter si la tâche lui est affectée OU si l'objectif est partagé avec lui
        if ($user instanceof Student) {
            // Vérifier si la tâche est affectée à l'élève
            if ($task->getAssignedType() === 'student' && $task->getStudent() === $user) {
                return true;
            }
            // Vérifier si l'objectif est partagé avec cet élève (peut ajouter des preuves)
            return $objective->getSharedStudents()->contains($user);
        }
        
        // Parent : peut compléter si la tâche lui est affectée
        if ($user instanceof ParentUser) {
            return $task->getAssignedType() === 'parent' && $task->getParent() === $user;
        }
        
        // Specialist : peut compléter si la tâche lui est affectée OU si l'objectif est partagé avec lui
        if ($user instanceof Specialist) {
            // Vérifier si la tâche est affectée au spécialiste
            if ($task->getAssignedType() === 'specialist' && $task->getSpecialist() === $user) {
                return true;
            }
            // Vérifier si l'objectif est partagé avec ce spécialiste (peut ajouter des preuves)
            return $objective->getSharedSpecialists()->contains($user);
        }

        return false;
    }

    /**
     * Vérifie si l'utilisateur peut créer une demande pour un élève
     */
    public function canCreateRequest(User $user, Student $student): bool
    {
        // Coach : peut créer une demande pour tous ses étudiants
        if ($user instanceof Coach) {
            $family = $student->getFamily();
            return $family && $family->getCoach() === $user;
        }

        // Parent : peut créer une demande pour ses enfants
        if ($user instanceof ParentUser) {
            $family = $user->getFamily();
            return $family && $student->getFamily() === $family;
        }

        // Student : peut créer une demande pour lui-même
        if ($user instanceof Student) {
            return $user === $student;
        }

        // Specialist : peut créer une demande pour les élèves qui lui sont assignés
        if ($user instanceof Specialist) {
            return $user->getStudents()->contains($student);
        }

        return false;
    }

    /**
     * Vérifie si l'utilisateur peut voir une demande
     */
    public function canViewRequest(User $user, Request $request): bool
    {
        // Coach : peut voir toutes les demandes de ses familles
        if ($user instanceof Coach) {
            $student = $request->getStudent();
            if ($student && $student->getFamily()) {
                return $student->getFamily()->getCoach() === $user;
            }
            return false;
        }

        // Parent : peut voir les demandes de ses enfants
        if ($user instanceof ParentUser) {
            $family = $user->getFamily();
            if (!$family) {
                return false;
            }
            $student = $request->getStudent();
            return $student && $student->getFamily() === $family;
        }

        // Student : peut voir ses propres demandes et celles qui lui sont affectées
        if ($user instanceof Student) {
            return $request->getStudent() === $user || ($request->getAssignedTo() && $request->getAssignedTo()->getId() === $user->getId());
        }

        // Specialist : peut voir toutes les demandes
        if ($user instanceof Specialist) {
            return true;
        }

        return false;
    }

    /**
     * Vérifie si l'utilisateur peut voir le planning d'un étudiant
     */
    public function canViewStudentPlanning(User $user, Student $student): bool
    {
        // Coach : peut voir le planning de tous ses étudiants
        if ($user instanceof Coach) {
            $family = $student->getFamily();
            return $family && $family->getCoach() === $user;
        }

        // Parent : peut voir le planning de ses enfants
        if ($user instanceof ParentUser) {
            $family = $user->getFamily();
            return $family && $student->getFamily() === $family;
        }

        // Student : peut voir son propre planning
        if ($user instanceof Student) {
            return $student === $user;
        }

        // Specialist : peut voir le planning des enfants qui lui sont affectés
        if ($user instanceof Specialist) {
            return $user->getStudents()->contains($student);
        }

        return false;
    }

    /**
     * Vérifie si l'utilisateur peut créer un événement de planning pour un utilisateur donné
     */
    public function canCreatePlanning(User $user, ?User $targetUser = null): bool
    {
        // Si pas d'utilisateur cible, l'utilisateur peut créer pour lui-même
        if (!$targetUser) {
            return true; // Tout utilisateur peut créer un événement pour lui-même
        }

        // Coach : peut créer pour tous ses étudiants
        if ($user instanceof Coach) {
            if ($targetUser instanceof Student) {
                $family = $targetUser->getFamily();
                return $family && $family->getCoach() === $user;
            }
            // Coach peut créer pour lui-même
            return $targetUser === $user;
        }

        // Parent : peut créer pour ses enfants
        if ($user instanceof ParentUser) {
            if ($targetUser instanceof Student) {
                $family = $user->getFamily();
                return $family && $targetUser->getFamily() === $family;
            }
            // Parent peut créer pour lui-même
            return $targetUser === $user;
        }

        // Student : peut créer pour lui-même uniquement
        if ($user instanceof Student) {
            return $targetUser === $user;
        }

        // Specialist : peut créer pour les élèves qui lui sont assignés
        if ($user instanceof Specialist) {
            if ($targetUser instanceof Student) {
                return $user->getStudents()->contains($targetUser);
            }
            // Specialist peut créer pour lui-même
            return $targetUser === $user;
        }

        return false;
    }

    /**
     * Vérifie si l'utilisateur peut modifier ou supprimer un événement de planning
     */
    public function canModifyPlanning(User $user, Planning $planning): bool
    {
        $planningUser = $planning->getUser();
        if (!$planningUser) {
            return false;
        }

        // Vérifier d'abord si l'utilisateur est le créateur de l'événement (stocké dans les métadonnées)
        $metadata = $planning->getMetadata() ?? [];
        if (isset($metadata['creatorId']) && $metadata['creatorId'] == $user->getId()) {
            return true; // Le créateur peut toujours modifier/supprimer son événement
        }

        // Coach : peut modifier les événements de ses étudiants ou les siens
        if ($user instanceof Coach) {
            if ($planningUser instanceof Student) {
                $family = $planningUser->getFamily();
                return $family && $family->getCoach() === $user;
            }
            // Coach peut modifier ses propres événements
            return $planningUser === $user;
        }

        // Parent : peut modifier les événements de ses enfants ou les siens
        if ($user instanceof ParentUser) {
            if ($planningUser instanceof Student) {
                $family = $user->getFamily();
                return $family && $planningUser->getFamily() === $family;
            }
            // Parent peut modifier ses propres événements
            return $planningUser === $user;
        }

        // Student : peut modifier uniquement ses propres événements
        if ($user instanceof Student) {
            return $planningUser === $user;
        }

        // Specialist : peut modifier les événements des élèves qui lui sont assignés ou les siens
        if ($user instanceof Specialist) {
            if ($planningUser instanceof Student) {
                return $user->getStudents()->contains($planningUser);
            }
            // Specialist peut modifier ses propres événements
            return $planningUser === $user;
        }

        return false;
    }

    /**
     * Vérifie si l'utilisateur peut voir une activité
     */
    public function canViewActivity(User $user, Activity $activity): bool
    {
        // Tous les rôles (élève, spécialiste, coach et parent) peuvent voir toutes les activités
        if ($user instanceof Coach || $user instanceof Specialist || $user instanceof ParentUser || $user instanceof Student) {
            return true;
        }

        return false;
    }

    /**
     * Vérifie si l'utilisateur peut CRUD une activité
     */
    public function canModifyActivity(User $user, Activity $activity): bool
    {
        // Coach : peut modifier toutes les activités (même celles créées par d'autres)
        if ($user instanceof Coach) {
            return true;
        }

        // Specialist : peut modifier toutes les activités (même celles créées par d'autres) - mêmes droits que le coach
        if ($user instanceof Specialist) {
            return true;
        }

        return false;
    }

    /**
     * Vérifie si l'utilisateur peut CRUD un enfant
     */
    public function canModifyStudent(User $user, Student $student): bool
    {
        // Coach : peut modifier tous les étudiants de ses familles
        if ($user instanceof Coach) {
            $family = $student->getFamily();
            return $family && $family->getCoach() === $user;
        }

        // Parent : peut modifier les enfants de sa famille
        if ($user instanceof ParentUser) {
            $family = $user->getFamily();
            return $family && $student->getFamily() === $family;
        }

        return false;
    }

    /**
     * Retourne les étudiants accessibles par l'utilisateur
     */
    public function getAccessibleStudents(User $user): array
    {
        if ($user instanceof Coach) {
            $students = [];
            foreach ($user->getFamilies() as $family) {
                foreach ($family->getStudents() as $student) {
                    $students[] = $student;
                }
            }
            return $students;
        }

        if ($user instanceof ParentUser) {
            $family = $user->getFamily();
            return $family ? $family->getStudents()->toArray() : [];
        }

        if ($user instanceof Student) {
            return [$user];
        }

        if ($user instanceof Specialist) {
            return $user->getStudents()->toArray();
        }

        return [];
    }

    /**
     * Retourne les objectifs accessibles par l'utilisateur
     */
    public function getAccessibleObjectives(User $user): array
    {
        if ($user instanceof Coach) {
            // Tous les objectifs de ses étudiants
            $objectives = [];
            foreach ($user->getFamilies() as $family) {
                foreach ($family->getStudents() as $student) {
                    foreach ($student->getObjectives() as $objective) {
                        if ($objective->getCoach() === $user) {
                            $objectives[] = $objective;
                        }
                    }
                }
            }
            return $objectives;
        }

        if ($user instanceof ParentUser) {
            // Objectifs des enfants de sa famille
            $objectives = [];
            $family = $user->getFamily();
            if ($family) {
                foreach ($family->getStudents() as $student) {
                    foreach ($student->getObjectives() as $objective) {
                        $objectives[] = $objective;
                    }
                }
            }
            return $objectives;
        }

        if ($user instanceof Student) {
            // Ses propres objectifs + objectifs partagés avec lui
            $objectives = $user->getObjectives()->toArray();
            // Ajouter les objectifs partagés
            $sharedObjectives = $this->em
                ->createQueryBuilder()
                ->select('o')
                ->from(Objective::class, 'o')
                ->join('o.sharedStudents', 's')
                ->where('s.id = :studentId')
                ->setParameter('studentId', $user->getId())
                ->getQuery()
                ->getResult();
            // Fusionner et supprimer les doublons
            $allObjectives = array_merge($objectives, $sharedObjectives);
            return array_values(array_unique($allObjectives, SORT_REGULAR));
        }

        if ($user instanceof Specialist) {
            // Objectifs des tâches qui lui sont affectées + objectifs partagés avec lui
            $objectives = [];
            $tasks = $this->taskRepository->findBySpecialist($user);
            foreach ($tasks as $task) {
                $objective = $task->getObjective();
                if ($objective && !in_array($objective, $objectives, true)) {
                    $objectives[] = $objective;
                }
            }
            // Ajouter les objectifs partagés
            $sharedObjectives = $this->em
                ->createQueryBuilder()
                ->select('o')
                ->from(Objective::class, 'o')
                ->join('o.sharedSpecialists', 's')
                ->where('s.id = :specialistId')
                ->setParameter('specialistId', $user->getId())
                ->getQuery()
                ->getResult();
            // Fusionner et supprimer les doublons
            $allObjectives = array_merge($objectives, $sharedObjectives);
            return array_values(array_unique($allObjectives, SORT_REGULAR));
        }

        return [];
    }

    /**
     * Vérifie si l'utilisateur peut utiliser l'IA pour générer des objectifs ou des tâches
     */
    public function canUseAI(User $user): bool
    {
        // Seul le coach peut utiliser l'IA
        return $user instanceof Coach;
    }

    /**
     * Retourne les demandes accessibles par l'utilisateur
     */
    public function getAccessibleRequests(User $user): array
    {
        if ($user instanceof Coach) {
            // Toutes les demandes de ses familles
            $requests = [];
            foreach ($user->getFamilies() as $family) {
                foreach ($family->getStudents() as $student) {
                    foreach ($student->getRequests() as $request) {
                        $requests[] = $request;
                    }
                }
            }
            return $requests;
        }

        if ($user instanceof ParentUser) {
            // Demandes des enfants de sa famille
            $requests = [];
            $family = $user->getFamily();
            if ($family) {
                foreach ($family->getStudents() as $student) {
                    foreach ($student->getRequests() as $request) {
                        $requests[] = $request;
                    }
                }
            }
            return $requests;
        }

        if ($user instanceof Student) {
            // Ses propres demandes et celles qui lui sont affectées
            $requests = $user->getRequests()->toArray();
            
            // Ajouter les demandes qui lui sont affectées via assignedTo
            $assignedRequests = $this->requestRepository->createQueryBuilder('r')
                ->where('r.assignedTo = :student')
                ->setParameter('student', $user)
                ->getQuery()
                ->getResult();
            
            // Fusionner les deux listes et supprimer les doublons
            $allRequests = array_merge($requests, $assignedRequests);
            $uniqueRequests = [];
            $seenIds = [];
            foreach ($allRequests as $request) {
                $id = $request->getId();
                if (!in_array($id, $seenIds)) {
                    $uniqueRequests[] = $request;
                    $seenIds[] = $id;
                }
            }
            
            return $uniqueRequests;
        }

        if ($user instanceof Specialist) {
            // Toutes les demandes (utiliser le repository pour récupérer toutes les demandes)
            return $this->requestRepository->findAllWithSearch();
        }

        return [];
    }
}

