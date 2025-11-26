<?php

namespace App\Service;

use App\Entity\Objective;
use App\Entity\Planning;
use App\Entity\Task;
use App\Repository\PlanningRepository;
use Doctrine\ORM\EntityManagerInterface;

class TaskPlanningService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly PlanningRepository $planningRepository
    ) {
    }

    /**
     * Génère les événements Planning à partir d'une tâche
     * Les dates par défaut sont celles de l'objectif
     * Si pas de deadline, utilise createdAt + 3 semaines
     */
    public function generatePlanningFromTask(Task $task): array
    {
        $objective = $task->getObjective();
        if (!$objective) {
            return [];
        }

        $student = $objective->getStudent();
        if (!$student) {
            return [];
        }

        // Déterminer les dates de début et fin
        // Priorité : utiliser startDate/dueDate de la tâche, sinon utiliser les dates de l'objectif
        $startDate = $task->getStartDate() ?? $objective->getCreatedAt();
        $endDate = $task->getDueDate() ?? $objective->getDeadline();
        
        // Si pas de dueDate, utiliser startDate + 3 semaines
        if (!$endDate) {
            $endDate = $startDate->modify('+3 weeks');
        }

        // Si la tâche n'a pas de fréquence, créer un seul événement
        if (!$task->getFrequency() || $task->getFrequency() === Task::FREQUENCY_NONE) {
            return $this->createSinglePlanningEvent($task, $student, $startDate, $endDate);
        }

        // Générer les événements selon la fréquence
        return $this->generateRecurringPlanningEvents($task, $student, $startDate, $endDate);
    }

    /**
     * Crée un seul événement Planning pour une tâche sans fréquence
     */
    private function createSinglePlanningEvent(
        Task $task,
        $student,
        \DateTimeImmutable $startDate,
        \DateTimeImmutable $endDate
    ): array {
        // Supprimer les anciens événements de cette tâche pour régénérer
        $this->removeExistingPlanningForTask($task);

        // Créer un nouvel événement avec les dates de la tâche
        $planning = new Planning();
        $planning->setTitle($task->getTitle());
        $planning->setDescription($task->getDescription());
        $planning->setStartDate($startDate);
        $planning->setEndDate($endDate);
        $planning->setType(Planning::TYPE_TASK);
        $planning->setStatus(Planning::STATUS_TO_DO);
        $planning->setUser($student);
        $planning->setMetadata([
            'taskId' => $task->getId(),
            'objectiveId' => $task->getObjective()?->getId(),
            'frequency' => $task->getFrequency() ?? Task::FREQUENCY_NONE,
            'repeatDaysOfWeek' => $task->getRepeatDaysOfWeek() ?? [],
        ]);

        $this->em->persist($planning);
        return [$planning];
    }

    /**
     * Génère des événements récurrents selon la fréquence de la tâche
     */
    private function generateRecurringPlanningEvents(
        Task $task,
        $student,
        \DateTimeImmutable $startDate,
        \DateTimeImmutable $endDate
    ): array {
        $events = [];
        $currentDate = $startDate;
        $frequency = $task->getFrequency();
        $repeatDaysOfWeek = $task->getRepeatDaysOfWeek() ?? [];

        // Supprimer les anciens événements de cette tâche
        $this->removeExistingPlanningForTask($task);

        // Pour les semaines paires/impaires, on doit vérifier la parité de la semaine
        if (in_array($frequency, [Task::FREQUENCY_EVEN_WEEK, Task::FREQUENCY_ODD_WEEK]) && !empty($repeatDaysOfWeek)) {
            $currentDate = $startDate;
            while ($currentDate <= $endDate) {
                $dayOfWeek = (int)$currentDate->format('w');
                $isEvenWeek = $this->isEvenWeek($currentDate);
                
                // Vérifier si on doit créer un événement pour cette semaine
                $shouldCreate = false;
                if ($frequency === Task::FREQUENCY_EVEN_WEEK && $isEvenWeek) {
                    $shouldCreate = true;
                } elseif ($frequency === Task::FREQUENCY_ODD_WEEK && !$isEvenWeek) {
                    $shouldCreate = true;
                }
                
                // Si le jour correspond aux jours sélectionnés et que c'est la bonne semaine
                if ($shouldCreate && in_array($dayOfWeek, $repeatDaysOfWeek)) {
                    $eventStartDate = $currentDate->setTime(
                        (int)$startDate->format('H'),
                        (int)$startDate->format('i'),
                        (int)$startDate->format('s')
                    );
                    
                    $eventEndDate = $currentDate->setTime(
                        (int)$endDate->format('H'),
                        (int)$endDate->format('i'),
                        (int)$endDate->format('s')
                    );
                    if ($eventEndDate <= $eventStartDate) {
                        $eventEndDate = $currentDate->setTime(23, 59, 59);
                    }
                    
                    if ($eventStartDate <= $endDate) {
                        $planning = new Planning();
                        $planning->setTitle($task->getTitle());
                        $planning->setDescription($task->getDescription());
                        $planning->setStartDate($eventStartDate);
                        $planning->setEndDate($eventEndDate);
                        $planning->setType(Planning::TYPE_TASK);
                        $planning->setStatus(Planning::STATUS_TO_DO);
                        $planning->setUser($student);
                        $planning->setMetadata([
                            'taskId' => $task->getId(),
                            'objectiveId' => $task->getObjective()?->getId(),
                            'frequency' => $frequency,
                            'repeatDaysOfWeek' => $repeatDaysOfWeek,
                        ]);

                        $this->em->persist($planning);
                        $events[] = $planning;
                    }
                }
                
                $currentDate = $currentDate->modify('+1 day')->setTime(0, 0, 0);
            }
            
            return $events;
        }

        // Pour les répétitions hebdomadaires, on doit itérer jour par jour
        if ($frequency === Task::FREQUENCY_WEEKLY && !empty($repeatDaysOfWeek)) {
            $currentDate = $startDate;
            while ($currentDate <= $endDate) {
                $dayOfWeek = (int)$currentDate->format('w'); // 0 = dimanche, 1 = lundi, etc.
                
                // Si le jour correspond aux jours sélectionnés, créer un événement
                if (in_array($dayOfWeek, $repeatDaysOfWeek)) {
                    // Utiliser les heures de startDate pour chaque occurrence
                    $eventStartDate = $currentDate->setTime(
                        (int)$startDate->format('H'),
                        (int)$startDate->format('i'),
                        (int)$startDate->format('s')
                    );
                    
                    // Pour les répétitions hebdomadaires, chaque occurrence est d'une journée
                    // Utiliser l'heure de dueDate pour la fin de journée, ou 23:59:59 par défaut
                    if ($currentDate->format('Y-m-d') === $endDate->format('Y-m-d')) {
                        $eventEndDate = $endDate;
                    } else {
                        // Utiliser l'heure de dueDate si disponible, sinon fin de journée
                        $eventEndDate = $currentDate->setTime(
                            (int)$endDate->format('H'),
                            (int)$endDate->format('i'),
                            (int)$endDate->format('s')
                        );
                        // Si l'heure de dueDate est avant l'heure de startDate, utiliser fin de journée
                        if ($eventEndDate <= $eventStartDate) {
                            $eventEndDate = $currentDate->setTime(23, 59, 59);
                        }
                    }
                    
                    if ($eventStartDate <= $endDate) {
                        $planning = new Planning();
                        $planning->setTitle($task->getTitle());
                        $planning->setDescription($task->getDescription());
                        $planning->setStartDate($eventStartDate);
                        $planning->setEndDate($eventEndDate);
                        $planning->setType(Planning::TYPE_TASK);
                        $planning->setStatus(Planning::STATUS_TO_DO);
                        $planning->setUser($student);
                        $planning->setMetadata([
                            'taskId' => $task->getId(),
                            'objectiveId' => $task->getObjective()?->getId(),
                            'frequency' => $frequency,
                            'repeatDaysOfWeek' => $repeatDaysOfWeek,
                        ]);

                        $this->em->persist($planning);
                        $events[] = $planning;
                    }
                }
                
                // Passer au jour suivant
                $currentDate = $currentDate->modify('+1 day')->setTime(0, 0, 0);
            }
            
            return $events;
        }

        // Pour les autres fréquences (daily, monthly), utiliser la logique normale
        while ($currentDate <= $endDate) {

            $eventEndDate = $this->calculateEventEndDate($currentDate, $frequency);

            // Ne pas créer d'événement si la date de fin dépasse la deadline
            if ($eventEndDate > $endDate) {
                break;
            }

            $planning = new Planning();
            $planning->setTitle($task->getTitle());
            $planning->setDescription($task->getDescription());
            $planning->setStartDate($currentDate);
            $planning->setEndDate($eventEndDate);
            $planning->setType(Planning::TYPE_TASK);
            $planning->setStatus(Planning::STATUS_TO_DO);
            $planning->setUser($student);
            $planning->setMetadata([
                'taskId' => $task->getId(),
                'objectiveId' => $task->getObjective()?->getId(),
                'frequency' => $frequency,
                'repeatDaysOfWeek' => $repeatDaysOfWeek,
            ]);

            $this->em->persist($planning);
            $events[] = $planning;

            // Passer à la prochaine occurrence
            $currentDate = $this->getNextOccurrenceDate($currentDate, $frequency);
        }

        return $events;
    }

    /**
     * Calcule la date de fin d'un événement selon sa fréquence
     */
    private function calculateEventEndDate(\DateTimeImmutable $startDate, string $frequency): \DateTimeImmutable
    {
        return match ($frequency) {
            Task::FREQUENCY_DAILY => $startDate->modify('+1 day')->setTime(23, 59, 59),
            Task::FREQUENCY_WEEKLY => $startDate->modify('+1 week')->setTime(23, 59, 59),
            Task::FREQUENCY_EVEN_WEEK, Task::FREQUENCY_ODD_WEEK => $startDate->modify('+2 weeks')->setTime(23, 59, 59),
            Task::FREQUENCY_MONTHLY => $startDate->modify('+1 month')->setTime(23, 59, 59),
            default => $startDate->modify('+1 day')->setTime(23, 59, 59),
        };
    }

    /**
     * Calcule la date de la prochaine occurrence
     */
    private function getNextOccurrenceDate(\DateTimeImmutable $currentDate, string $frequency): \DateTimeImmutable
    {
        return match ($frequency) {
            Task::FREQUENCY_DAILY => $currentDate->modify('+1 day')->setTime(0, 0, 0),
            Task::FREQUENCY_WEEKLY => $currentDate->modify('+1 week')->setTime(0, 0, 0),
            Task::FREQUENCY_EVEN_WEEK, Task::FREQUENCY_ODD_WEEK => $currentDate->modify('+2 weeks')->setTime(0, 0, 0),
            Task::FREQUENCY_MONTHLY => $currentDate->modify('+1 month')->setTime(0, 0, 0),
            default => $currentDate->modify('+1 day')->setTime(0, 0, 0),
        };
    }

    /**
     * Détermine si une date est dans une semaine paire (basé sur le numéro de semaine ISO)
     */
    private function isEvenWeek(\DateTimeImmutable $date): bool
    {
        $weekNumber = (int)$date->format('W'); // Numéro de semaine ISO (1-53)
        return $weekNumber % 2 === 0;
    }

    /**
     * Supprime les anciens événements Planning associés à une tâche
     */
    private function removeExistingPlanningForTask(Task $task): void
    {
        // Récupérer tous les plannings de type TASK pour l'étudiant
        $student = $task->getObjective()?->getStudent();
        if (!$student) {
            return;
        }

        $existingPlannings = $this->planningRepository->createQueryBuilder('p')
            ->where('p.type = :type')
            ->andWhere('p.user = :student')
            ->setParameter('type', Planning::TYPE_TASK)
            ->setParameter('student', $student)
            ->getQuery()
            ->getResult();

        // Filtrer ceux qui correspondent à la tâche
        foreach ($existingPlannings as $planning) {
            $metadata = $planning->getMetadata();
            if (isset($metadata['taskId']) && $metadata['taskId'] === $task->getId()) {
                $this->em->remove($planning);
            }
        }
    }

    /**
     * Génère les événements Planning pour toutes les tâches d'un objectif
     */
    public function generatePlanningForObjective(Objective $objective): array
    {
        $allEvents = [];
        
        foreach ($objective->getTasks() as $task) {
            $events = $this->generatePlanningFromTask($task);
            $allEvents = array_merge($allEvents, $events);
        }

        $this->em->flush();
        return $allEvents;
    }

    /**
     * Met à jour les événements Planning d'une tâche
     */
    public function updatePlanningForTask(Task $task): void
    {
        $this->generatePlanningFromTask($task);
        $this->em->flush();
    }

    /**
     * Supprime les événements Planning associés à une tâche
     */
    public function removePlanningForTask(Task $task): void
    {
        $this->removeExistingPlanningForTask($task);
        $this->em->flush();
    }
}

