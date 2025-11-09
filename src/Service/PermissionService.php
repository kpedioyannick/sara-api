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

class PermissionService
{
    public function __construct(
        private readonly TaskRepository $taskRepository,
        private readonly RequestRepository $requestRepository
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

        // Student : peut voir ses propres objectifs
        if ($user instanceof Student) {
            return $objective->getStudent() === $user;
        }

        // Specialist : peut voir les objectifs des tâches qui lui sont affectées
        if ($user instanceof Specialist) {
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
     * Vérifie si l'utilisateur peut modifier un objectif
     */
    public function canModifyObjective(User $user, Objective $objective): bool
    {
        // Seul le coach peut modifier un objectif
        if ($user instanceof Coach) {
            return $objective->getCoach() === $user;
        }

        // Parent : peut modifier les objectifs de ses enfants (dans son contexte)
        if ($user instanceof ParentUser) {
            $family = $user->getFamily();
            if (!$family) {
                return false;
            }
            $student = $objective->getStudent();
            return $student && $student->getFamily() === $family;
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
        // Seul le coach peut modifier une tâche
        if ($user instanceof Coach) {
            if ($task) {
                $objective = $task->getObjective();
            }
            if ($objective) {
                return $objective->getCoach() === $user;
            }
            // Si pas de task ni objective, on vérifie juste que c'est un coach
            return true;
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
     * Vérifie si l'utilisateur peut voir une activité
     */
    public function canViewActivity(User $user, Activity $activity): bool
    {
        // Coach : peut voir ses activités
        if ($user instanceof Coach) {
            return $activity->getCreatedBy() === $user;
        }

        // Specialist : peut voir toutes les activités
        if ($user instanceof Specialist) {
            return true;
        }

        return false;
    }

    /**
     * Vérifie si l'utilisateur peut CRUD une activité
     */
    public function canModifyActivity(User $user, Activity $activity): bool
    {
        // Coach : peut modifier ses activités
        if ($user instanceof Coach) {
            return $activity->getCreatedBy() === $user;
        }

        // Specialist : peut CRUD toutes les activités
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
            // Ses propres objectifs
            return $user->getObjectives()->toArray();
        }

        if ($user instanceof Specialist) {
            // Objectifs des tâches qui lui sont affectées
            $objectives = [];
            $tasks = $this->taskRepository->findBySpecialist($user);
            foreach ($tasks as $task) {
                $objective = $task->getObjective();
                if ($objective && !in_array($objective, $objectives, true)) {
                    $objectives[] = $objective;
                }
            }
            return $objectives;
        }

        return [];
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
            // TODO: Ajouter les demandes qui lui sont affectées
            return $requests;
        }

        if ($user instanceof Specialist) {
            // Toutes les demandes (utiliser le repository pour récupérer toutes les demandes)
            return $this->requestRepository->findAllWithSearch();
        }

        return [];
    }
}

