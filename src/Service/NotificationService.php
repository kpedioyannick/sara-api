<?php

namespace App\Service;

use App\Entity\Notification;
use App\Entity\Objective;
use App\Entity\Request;
use App\Entity\Task;
use App\Entity\User;
use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Routing\RouterInterface;

class NotificationService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly NotificationRepository $notificationRepository,
        private readonly HubInterface $hub,
        private readonly RouterInterface $router,
        private readonly ?EmailNotificationService $emailNotificationService = null
    ) {
    }

    /**
     * Crée et envoie une notification
     */
    public function createNotification(
        User $recipient,
        string $type,
        string $title,
        ?string $message = null,
        ?array $data = null,
        ?string $url = null,
        ?Objective $objective = null,
        ?Task $task = null,
        ?Request $request = null
    ): Notification {
        $notification = new Notification();
        $notification->setRecipient($recipient);
        $notification->setType($type);
        $notification->setTitle($title);
        $notification->setMessage($message);
        $notification->setData($data);
        $notification->setUrl($url);
        $notification->setObjective($objective);
        $notification->setTask($task);
        $notification->setRequest($request);

        $this->em->persist($notification);
        $this->em->flush();

        // Publier via Mercure pour notification en temps réel
        $this->publishRealtimeNotification($notification);

        // Envoyer un email pour la notification
        if ($this->emailNotificationService) {
            try {
                $this->emailNotificationService->sendNotificationEmail($notification);
            } catch (\Exception $e) {
                // Log l'erreur mais ne bloque pas la création de la notification
                error_log('Erreur envoi email notification: ' . $e->getMessage());
            }
        }

        return $notification;
    }

    /**
     * Publie la notification via Mercure
     */
    private function publishRealtimeNotification(Notification $notification): void
    {
        try {
            $update = new Update(
                topics: ["/notifications/user/{$notification->getRecipient()->getId()}"],
                data: json_encode($notification->toArray()),
                private: true
            );
            $this->hub->publish($update);
        } catch (\Exception $e) {
            // Log l'erreur mais ne bloque pas la création de la notification
            error_log('Erreur Mercure notification: ' . $e->getMessage());
        }
    }

    /**
     * Marque une notification comme lue
     */
    public function markAsRead(Notification $notification): void
    {
        $notification->setIsRead(true);
        $this->em->flush();

        // Publier la mise à jour via Mercure
        try {
            $update = new Update(
                topics: ["/notifications/user/{$notification->getRecipient()->getId()}"],
                data: json_encode([
                    'type' => 'notification_read',
                    'id' => $notification->getId(),
                    'isRead' => true,
                ]),
                private: true
            );
            $this->hub->publish($update);
        } catch (\Exception $e) {
            error_log('Erreur Mercure notification read: ' . $e->getMessage());
        }
    }

    /**
     * Marque toutes les notifications comme lues
     */
    public function markAllAsRead(User $user): void
    {
        $this->notificationRepository->markAllAsRead($user);

        // Publier la mise à jour
        try {
            $update = new Update(
                topics: ["/notifications/user/{$user->getId()}"],
                data: json_encode(['type' => 'all_read']),
                private: true
            );
            $this->hub->publish($update);
        } catch (\Exception $e) {
            error_log('Erreur Mercure mark all read: ' . $e->getMessage());
        }
    }

    /**
     * Supprime une notification
     */
    public function delete(Notification $notification): void
    {
        $recipientId = $notification->getRecipient()->getId();
        $this->em->remove($notification);
        $this->em->flush();

        // Publier la suppression via Mercure
        try {
            $update = new Update(
                topics: ["/notifications/user/{$recipientId}"],
                data: json_encode([
                    'type' => 'notification_deleted',
                    'id' => $notification->getId(),
                ]),
                private: true
            );
            $this->hub->publish($update);
        } catch (\Exception $e) {
            error_log('Erreur Mercure notification delete: ' . $e->getMessage());
        }
    }

    /**
     * Notifie qu'une preuve a été soumise
     */
    public function notifyProofSubmitted(Task $task, User $submitter): void
    {
        $objective = $task->getObjective();
        $coach = $objective->getCoach();
        $student = $objective->getStudent();

        $this->createNotification(
            recipient: $coach,
            type: Notification::TYPE_PROOF_SUBMITTED,
            title: 'Nouvelle preuve soumise',
            message: "{$submitter->getFirstName()} {$submitter->getLastName()} a soumis une preuve pour la tâche : {$task->getTitle()}",
            url: $this->router->generate('admin_objectives_detail', ['id' => $objective->getId()]),
            data: [
                'taskId' => $task->getId(),
                'objectiveId' => $objective->getId(),
                'studentId' => $student->getId(),
                'submitterId' => $submitter->getId(),
            ],
            task: $task,
            objective: $objective
        );
    }

    /**
     * Notifie qu'une tâche a été validée
     */
    public function notifyTaskValidated(Task $task): void
    {
        $objective = $task->getObjective();
        $recipient = $this->getTaskAssignee($task);

        if (!$recipient) {
            return;
        }

        $this->createNotification(
            recipient: $recipient,
            type: Notification::TYPE_TASK_VALIDATED,
            title: 'Tâche validée ! ✅',
            message: "Votre preuve pour '{$task->getTitle()}' a été validée par le coach",
            url: $this->router->generate('admin_objectives_detail', ['id' => $objective->getId()]),
            task: $task,
            objective: $objective
        );
    }

    /**
     * Notifie qu'un objectif a été créé
     */
    public function notifyObjectiveCreated(Objective $objective, User $creator): void
    {
        $coach = $objective->getCoach();

        // Ne notifier que si créé par quelqu'un d'autre que le coach
        if ($creator->getId() !== $coach->getId()) {
            $this->createNotification(
                recipient: $coach,
                type: Notification::TYPE_OBJECTIVE_CREATED,
                title: 'Nouvel objectif créé',
                message: "{$creator->getFirstName()} {$creator->getLastName()} a créé un objectif pour {$objective->getStudent()->getFirstName()}",
                url: $this->router->generate('admin_objectives_detail', ['id' => $objective->getId()]),
                data: [
                    'objectiveId' => $objective->getId(),
                    'studentId' => $objective->getStudent()->getId(),
                    'creatorId' => $creator->getId(),
                ],
                objective: $objective
            );
        }
    }

    /**
     * Notifie qu'un objectif a été validé
     */
    public function notifyObjectiveValidated(Objective $objective): void
    {
        $student = $objective->getStudent();
        $parent = $student->getFamily()?->getParent();

        // Notifier l'élève
        $this->createNotification(
            recipient: $student,
            type: Notification::TYPE_OBJECTIVE_VALIDATED,
            title: 'Objectif validé ! 🎉',
            message: "Votre objectif '{$objective->getTitle()}' a été validé par le coach",
            url: $this->router->generate('admin_objectives_detail', ['id' => $objective->getId()]),
            objective: $objective
        );

        // Notifier le parent
        if ($parent) {
            $this->createNotification(
                recipient: $parent,
                type: Notification::TYPE_OBJECTIVE_VALIDATED,
                title: 'Objectif validé',
                message: "L'objectif de {$student->getFirstName()} '{$objective->getTitle()}' a été validé",
                url: $this->router->generate('admin_objectives_detail', ['id' => $objective->getId()]),
                objective: $objective
            );
        }
    }

    /**
     * Notifie qu'une demande a été créée
     */
    public function notifyRequestCreated(Request $request, User $creator): void
    {
        $coach = $request->getCoach();

        if ($coach && $creator->getId() !== $coach->getId()) {
            $this->createNotification(
                recipient: $coach,
                type: Notification::TYPE_REQUEST_CREATED,
                title: 'Nouvelle demande',
                message: "{$creator->getFirstName()} {$creator->getLastName()} a créé une demande : {$request->getTitle()}",
                url: $this->router->generate('admin_requests_detail', ['id' => $request->getId()]),
                data: [
                    'requestId' => $request->getId(),
                    'creatorId' => $creator->getId(),
                ],
                request: $request
            );
        }
    }

    /**
     * Notifie qu'une demande a reçu une réponse
     */
    public function notifyRequestResponded(Request $request, User $responder): void
    {
        $creator = $request->getCreator();

        if ($creator && $creator->getId() !== $responder->getId()) {
            $this->createNotification(
                recipient: $creator,
                type: Notification::TYPE_REQUEST_RESPONDED,
                title: 'Réponse à votre demande',
                message: "{$responder->getFirstName()} {$responder->getLastName()} a répondu à votre demande : {$request->getTitle()}",
                url: $this->router->generate('admin_requests_detail', ['id' => $request->getId()]),
                data: [
                    'requestId' => $request->getId(),
                    'responderId' => $responder->getId(),
                ],
                request: $request
            );
        }
    }

    /**
     * Notifie qu'un commentaire a été ajouté
     */
    public function notifyCommentAdded(Objective $objective, User $author, array $participants): void
    {
        foreach ($participants as $participant) {
            // Ne pas notifier l'auteur du commentaire
            if ($participant->getId() === $author->getId()) {
                continue;
            }

            $this->createNotification(
                recipient: $participant,
                type: Notification::TYPE_COMMENT_ADDED,
                title: 'Nouveau commentaire',
                message: "{$author->getFirstName()} {$author->getLastName()} a commenté l'objectif '{$objective->getTitle()}'",
                url: $this->router->generate('admin_objectives_detail', ['id' => $objective->getId()]),
                data: [
                    'objectiveId' => $objective->getId(),
                    'authorId' => $author->getId(),
                ],
                objective: $objective
            );
        }
    }

    /**
     * Notifie qu'une nouvelle tâche a été assignée
     */
    public function notifyNewTaskAssigned(Task $task): void
    {
        $recipient = $this->getTaskAssignee($task);
        $objective = $task->getObjective();

        if (!$recipient) {
            return;
        }

        $this->createNotification(
            recipient: $recipient,
            type: Notification::TYPE_NEW_TASK_ASSIGNED,
            title: 'Nouvelle tâche assignée',
            message: "Une nouvelle tâche vous a été assignée : {$task->getTitle()}",
            url: $this->router->generate('admin_objectives_detail', ['id' => $objective->getId()]),
            data: [
                'taskId' => $task->getId(),
                'objectiveId' => $objective->getId(),
            ],
            task: $task,
            objective: $objective
        );
    }

    /**
     * Récupère l'utilisateur assigné à une tâche
     */
    private function getTaskAssignee(Task $task): ?User
    {
        $assignedType = $task->getAssignedType();

        return match ($assignedType) {
            'student' => $task->getStudent(),
            'parent' => $task->getParent(),
            'specialist' => $task->getSpecialist(),
            'coach' => $task->getObjective()?->getCoach(),
            default => null,
        };
    }
}

