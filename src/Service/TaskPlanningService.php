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
        $startDate = $objective->getCreatedAt();
        $endDate = $objective->getDeadline();
        
        // Si pas de deadline, utiliser createdAt + 3 semaines
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
        // Vérifier si un événement existe déjà pour cette tâche
        $existingPlanning = $this->planningRepository->findOneBy([
            'student' => $student,
            'type' => Planning::TYPE_TASK,
            'metadata' => ['taskId' => $task->getId()]
        ]);

        if ($existingPlanning) {
            // Mettre à jour l'événement existant
            $existingPlanning->setTitle($task->getTitle());
            $existingPlanning->setDescription($task->getDescription());
            $existingPlanning->setStartDate($startDate);
            $existingPlanning->setEndDate($endDate);
            $existingPlanning->setStatus(Planning::STATUS_TO_DO);
            
            $this->em->persist($existingPlanning);
            return [$existingPlanning];
        }

        // Créer un nouvel événement
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
            'frequency' => $task->getFrequency(),
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

        // Supprimer les anciens événements de cette tâche
        $this->removeExistingPlanningForTask($task);

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
            Task::FREQUENCY_HOURLY => $startDate->modify('+1 hour'),
            Task::FREQUENCY_DAILY => $startDate->modify('+1 day')->setTime(23, 59, 59),
            Task::FREQUENCY_HALF_DAY => $startDate->modify('+12 hours'),
            Task::FREQUENCY_EVERY_2_DAYS => $startDate->modify('+2 days')->setTime(23, 59, 59),
            Task::FREQUENCY_WEEKLY => $startDate->modify('+1 week')->setTime(23, 59, 59),
            Task::FREQUENCY_MONTHLY => $startDate->modify('+1 month')->setTime(23, 59, 59),
            Task::FREQUENCY_YEARLY => $startDate->modify('+1 year')->setTime(23, 59, 59),
            default => $startDate->modify('+1 day')->setTime(23, 59, 59),
        };
    }

    /**
     * Calcule la date de la prochaine occurrence
     */
    private function getNextOccurrenceDate(\DateTimeImmutable $currentDate, string $frequency): \DateTimeImmutable
    {
        return match ($frequency) {
            Task::FREQUENCY_HOURLY => $currentDate->modify('+1 hour'),
            Task::FREQUENCY_DAILY => $currentDate->modify('+1 day')->setTime(0, 0, 0),
            Task::FREQUENCY_HALF_DAY => $currentDate->modify('+12 hours'),
            Task::FREQUENCY_EVERY_2_DAYS => $currentDate->modify('+2 days')->setTime(0, 0, 0),
            Task::FREQUENCY_WEEKLY => $currentDate->modify('+1 week')->setTime(0, 0, 0),
            Task::FREQUENCY_MONTHLY => $currentDate->modify('+1 month')->setTime(0, 0, 0),
            Task::FREQUENCY_YEARLY => $currentDate->modify('+1 year')->setTime(0, 0, 0),
            default => $currentDate->modify('+1 day')->setTime(0, 0, 0),
        };
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

