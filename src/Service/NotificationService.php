<?php

namespace App\Service;

use App\Entity\Notification;
use App\Entity\Objective;
use App\Entity\Request;
use App\Entity\Task;
use App\Entity\User;
use App\Repository\MessageRepository;
use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Service\FirebaseService;
use Symfony\Component\Routing\RouterInterface;

class NotificationService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly NotificationRepository $notificationRepository,
        private readonly FirebaseService $firebaseService,
        private readonly RouterInterface $router,
        private readonly ?EmailNotificationService $emailNotificationService = null,
        private readonly ?MessageRepository $messageRepository = null
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
        ?Request $request = null,
        bool $sendEmail = true
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

        // Publier via Firebase pour notification en temps réel
        $this->publishRealtimeNotification($notification);

        // Envoyer un email pour la notification seulement si demandé
        if ($sendEmail && $this->emailNotificationService) {
            try {
                $this->emailNotificationService->sendNotificationEmail($notification);
                error_log('Email de notification envoyé pour: ' . $notification->getTitle() . ' à ' . $recipient->getEmail());
            } catch (\Exception $e) {
                // Log l'erreur mais ne bloque pas la création de la notification
                error_log('Erreur envoi email notification: ' . $e->getMessage() . ' - Trace: ' . $e->getTraceAsString());
            }
        } else {
            if (!$sendEmail) {
                error_log('Email non envoyé pour notification: ' . $notification->getTitle() . ' (sendEmail = false)');
            } else {
                error_log('EmailNotificationService non disponible - aucun email ne sera envoyé pour la notification: ' . $notification->getTitle());
            }
        }

        return $notification;
    }

    /**
     * Publie la notification via Firebase
     */
    private function publishRealtimeNotification(Notification $notification): void
    {
        try {
            $this->firebaseService->publishMessage(
                "/notifications/user/{$notification->getRecipient()->getId()}/notifications",
                $notification->toArray()
            );
        } catch (\Exception $e) {
            // Log l'erreur mais ne bloque pas la création de la notification
            error_log('Erreur Firebase notification: ' . $e->getMessage());
        }
    }

    /**
     * Marque une notification comme lue
     */
    public function markAsRead(Notification $notification): void
    {
        $notification->setIsRead(true);
        $this->em->flush();

        // Publier la mise à jour via Firebase
        try {
            $this->firebaseService->publishMessage(
                "/notifications/user/{$notification->getRecipient()->getId()}/updates",
                [
                    'type' => 'notification_read',
                    'id' => $notification->getId(),
                    'isRead' => true,
                ]
            );
        } catch (\Exception $e) {
            error_log('Erreur Firebase notification read: ' . $e->getMessage());
        }
    }

    /**
     * Marque toutes les notifications comme lues
     */
    public function markAllAsRead(User $user): void
    {
        $this->notificationRepository->markAllAsRead($user);

        // Publier la mise à jour via Firebase
        try {
            $this->firebaseService->publishMessage(
                "/notifications/user/{$user->getId()}/updates",
                ['type' => 'all_read']
            );
        } catch (\Exception $e) {
            error_log('Erreur Firebase mark all read: ' . $e->getMessage());
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

        // Publier la suppression via Firebase
        try {
            $this->firebaseService->publishMessage(
                "/notifications/user/{$recipientId}/updates",
                [
                    'type' => 'notification_deleted',
                    'id' => $notification->getId(),
                ]
            );
        } catch (\Exception $e) {
            error_log('Erreur Firebase notification delete: ' . $e->getMessage());
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
     * Notifie qu'un nouveau message a été reçu
     */
    public function notifyNewMessage(\App\Entity\Message $message, User $recipient): void
    {
        $sender = $message->getSender();
        $request = $message->getRequest();
        
        // Déterminer l'URL de redirection
        $url = null;
        if ($request) {
            $url = $this->router->generate('admin_requests_detail', ['id' => $request->getId()]);
        } else {
            $url = $this->router->generate('admin_messages_list');
        }

        // Créer le titre et le message
        $senderName = "{$sender->getFirstName()} {$sender->getLastName()}";
        $title = 'Nouveau message de ' . $senderName;
        $messageText = $message->getContent();
        // Ne pas tronquer le message pour l'email - afficher le message complet
        $notificationMessage = $messageText ? $messageText : "Nouveau message de {$senderName}";

        // Vérifier si c'est le premier message de la première conversation du jour
        // Si oui, envoyer l'email. Sinon, créer la notification sans envoyer d'email
        $shouldSendEmail = false;
        if ($this->messageRepository) {
            $shouldSendEmail = $this->isFirstMessageOfFirstConversationToday($message, $recipient);
        }

        $this->createNotification(
            recipient: $recipient,
            type: Notification::TYPE_NEW_MESSAGE,
            title: $title,
            message: $notificationMessage,
            url: $url,
            data: [
                'messageId' => $message->getId(),
                'senderId' => $sender->getId(),
                'senderName' => $senderName,
                'conversationId' => $message->getConversationId(),
                'requestId' => $request?->getId(),
            ],
            request: $request,
            sendEmail: $shouldSendEmail
        );
    }

    /**
     * Vérifie si c'est le premier message de la première conversation du jour
     */
    private function isFirstMessageOfFirstConversationToday(\App\Entity\Message $message, User $recipient): bool
    {

        $conversationId = $message->getConversationId();

        $todayStart = new \DateTimeImmutable('today');
        $todayEnd = new \DateTimeImmutable('tomorrow');

        // Vérifier si c'est la première conversation du jour
        $otherConversationsCount = (int) $this->em->createQueryBuilder()
            ->select('COUNT(DISTINCT m.conversationId)')
            ->from(\App\Entity\Message::class, 'm')
            ->where('m.receiver = :recipient')
            ->andWhere('m.createdAt >= :todayStart')
            ->andWhere('m.createdAt < :todayEnd')
            ->andWhere('m.conversationId != :conversationId')
            ->setParameter('recipient', $recipient)
            ->setParameter('todayStart', $todayStart)
            ->setParameter('todayEnd', $todayEnd)
            ->setParameter('conversationId', $conversationId)
            ->getQuery()
            ->getSingleScalarResult();

        return $otherConversationsCount === 0;
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

