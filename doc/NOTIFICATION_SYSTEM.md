# 🔔 Système de Notifications - Proposition

## 📋 Vue d'Ensemble

Le système de notifications proposé s'appuie sur l'infrastructure **Mercure** déjà en place et ajoute une couche de gestion complète des notifications pour tous les rôles.

---

## 🏗️ Architecture Proposée

### 1. **Entité Notification**

```php
// src/Entity/Notification.php
#[ORM\Entity(repositoryClass: NotificationRepository::class)]
class Notification
{
    // Types de notifications
    public const TYPE_TASK_COMPLETED = 'task_completed';
    public const TYPE_TASK_VALIDATED = 'task_validated';
    public const TYPE_OBJECTIVE_CREATED = 'objective_created';
    public const TYPE_OBJECTIVE_VALIDATED = 'objective_validated';
    public const TYPE_REQUEST_CREATED = 'request_created';
    public const TYPE_REQUEST_RESPONDED = 'request_responded';
    public const TYPE_COMMENT_ADDED = 'comment_added';
    public const TYPE_PLANNING_EVENT = 'planning_event';
    public const TYPE_DEADLINE_REMINDER = 'deadline_reminder';
    public const TYPE_PROOF_SUBMITTED = 'proof_submitted';
    
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;
    
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private User $recipient; // Destinataire
    
    #[ORM\Column(length: 50)]
    private string $type; // Type de notification
    
    #[ORM\Column(length: 255)]
    private string $title; // Titre de la notification
    
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $message = null; // Message détaillé
    
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $data = null; // Données supplémentaires (ID objectif, tâche, etc.)
    
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $url = null; // URL de redirection
    
    #[ORM\Column]
    private bool $isRead = false;
    
    #[ORM\Column]
    private ?\DateTimeImmutable $readAt = null;
    
    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;
    
    // Relations optionnelles pour faciliter les requêtes
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?Objective $objective = null;
    
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?Task $task = null;
    
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?Request $request = null;
}
```

### 2. **Service de Notification**

```php
// src/Service/NotificationService.php
class NotificationService
{
    public function __construct(
        private EntityManagerInterface $em,
        private NotificationRepository $notificationRepository,
        private HubInterface $hub,
        private RouterInterface $router
    ) {}
    
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
        $notification->setCreatedAt(new \DateTimeImmutable());
        
        $this->em->persist($notification);
        $this->em->flush();
        
        // Publier via Mercure pour notification en temps réel
        $this->publishRealtimeNotification($notification);
        
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
                data: json_encode([
                    'id' => $notification->getId(),
                    'type' => $notification->getType(),
                    'title' => $notification->getTitle(),
                    'message' => $notification->getMessage(),
                    'url' => $notification->getUrl(),
                    'data' => $notification->getData(),
                    'isRead' => $notification->isRead(),
                    'createdAt' => $notification->getCreatedAt()->format('Y-m-d H:i:s'),
                ]),
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
        $notification->setReadAt(new \DateTimeImmutable());
        $this->em->flush();
        
        // Publier la mise à jour via Mercure
        $this->publishRealtimeNotification($notification);
    }
    
    /**
     * Marque toutes les notifications comme lues
     */
    public function markAllAsRead(User $user): void
    {
        $this->notificationRepository->markAllAsRead($user);
        
        // Publier la mise à jour
        $update = new Update(
            topics: ["/notifications/user/{$user->getId()}"],
            data: json_encode(['type' => 'all_read']),
            private: true
        );
        $this->hub->publish($update);
    }
    
    /**
     * Supprime une notification
     */
    public function delete(Notification $notification): void
    {
        $this->em->remove($notification);
        $this->em->flush();
    }
}
```

### 3. **Event Listeners pour Notifications Automatiques**

```php
// src/EventListener/NotificationListener.php
class NotificationListener
{
    public function __construct(
        private NotificationService $notificationService
    ) {}
    
    /**
     * Quand une tâche est complétée (preuve soumise)
     */
    #[AsEventListener(event: 'task.proof_submitted')]
    public function onTaskProofSubmitted(TaskProofSubmittedEvent $event): void
    {
        $task = $event->getTask();
        $objective = $task->getObjective();
        $coach = $objective->getCoach();
        
        // Notifier le coach
        $this->notificationService->createNotification(
            recipient: $coach,
            type: Notification::TYPE_PROOF_SUBMITTED,
            title: "Nouvelle preuve soumise",
            message: "{$event->getUser()->getFirstName()} a soumis une preuve pour la tâche : {$task->getTitle()}",
            url: "/admin/objectives/{$objective->getId()}",
            data: [
                'taskId' => $task->getId(),
                'objectiveId' => $objective->getId(),
                'studentId' => $objective->getStudent()->getId(),
            ],
            task: $task,
            objective: $objective
        );
    }
    
    /**
     * Quand une tâche est validée par le coach
     */
    #[AsEventListener(event: 'task.validated')]
    public function onTaskValidated(TaskValidatedEvent $event): void
    {
        $task = $event->getTask();
        $objective = $task->getObjective();
        
        // Notifier l'utilisateur assigné
        $recipient = $this->getTaskAssignee($task);
        if ($recipient) {
            $this->notificationService->createNotification(
                recipient: $recipient,
                type: Notification::TYPE_TASK_VALIDATED,
                title: "Tâche validée ! ✅",
                message: "Votre preuve pour '{$task->getTitle()}' a été validée par le coach",
                url: "/admin/objectives/{$objective->getId()}",
                task: $task,
                objective: $objective
            );
        }
    }
    
    /**
     * Quand un objectif est créé
     */
    #[AsEventListener(event: 'objective.created')]
    public function onObjectiveCreated(ObjectiveCreatedEvent $event): void
    {
        $objective = $event->getObjective();
        $coach = $objective->getCoach();
        
        // Notifier le coach si créé par parent/élève
        if ($event->getCreator() !== $coach) {
            $this->notificationService->createNotification(
                recipient: $coach,
                type: Notification::TYPE_OBJECTIVE_CREATED,
                title: "Nouvel objectif créé",
                message: "{$event->getCreator()->getFirstName()} a créé un objectif pour {$objective->getStudent()->getFirstName()}",
                url: "/admin/objectives/{$objective->getId()}",
                objective: $objective
            );
        }
    }
    
    /**
     * Quand un objectif est validé
     */
    #[AsEventListener(event: 'objective.validated')]
    public function onObjectiveValidated(ObjectiveValidatedEvent $event): void
    {
        $objective = $event->getObjective();
        $student = $objective->getStudent();
        $parent = $student->getFamily()?->getParent();
        
        // Notifier l'élève
        $this->notificationService->createNotification(
            recipient: $student,
            type: Notification::TYPE_OBJECTIVE_VALIDATED,
            title: "Objectif validé ! 🎉",
            message: "Votre objectif '{$objective->getTitle()}' a été validé par le coach",
            url: "/admin/objectives/{$objective->getId()}",
            objective: $objective
        );
        
        // Notifier le parent
        if ($parent) {
            $this->notificationService->createNotification(
                recipient: $parent,
                type: Notification::TYPE_OBJECTIVE_VALIDATED,
                title: "Objectif validé",
                message: "L'objectif de {$student->getFirstName()} a été validé",
                url: "/admin/objectives/{$objective->getId()}",
                objective: $objective
            );
        }
    }
    
    /**
     * Quand une demande est créée
     */
    #[AsEventListener(event: 'request.created')]
    public function onRequestCreated(RequestCreatedEvent $event): void
    {
        $request = $event->getRequest();
        $coach = $request->getStudent()->getFamily()?->getCoach();
        
        // Notifier le coach
        if ($coach) {
            $this->notificationService->createNotification(
                recipient: $coach,
                type: Notification::TYPE_REQUEST_CREATED,
                title: "Nouvelle demande",
                message: "{$event->getCreator()->getFirstName()} a créé une demande : {$request->getTitle()}",
                url: "/admin/requests/{$request->getId()}",
                request: $request
            );
        }
    }
    
    /**
     * Quand une demande reçoit une réponse
     */
    #[AsEventListener(event: 'request.responded')]
    public function onRequestResponded(RequestRespondedEvent $event): void
    {
        $request = $event->getRequest();
        $creator = $request->getCreator();
        
        // Notifier le créateur de la demande
        if ($creator) {
            $this->notificationService->createNotification(
                recipient: $creator,
                type: Notification::TYPE_REQUEST_RESPONDED,
                title: "Réponse à votre demande",
                message: "{$event->getResponder()->getFirstName()} a répondu à votre demande",
                url: "/admin/requests/{$request->getId()}",
                request: $request
            );
        }
    }
    
    /**
     * Quand un commentaire est ajouté
     */
    #[AsEventListener(event: 'comment.added')]
    public function onCommentAdded(CommentAddedEvent $event): void
    {
        $comment = $event->getComment();
        $objective = $comment->getObjective();
        
        // Notifier tous les participants de l'objectif
        $participants = $this->getObjectiveParticipants($objective);
        foreach ($participants as $participant) {
            if ($participant->getId() !== $event->getAuthor()->getId()) {
                $this->notificationService->createNotification(
                    recipient: $participant,
                    type: Notification::TYPE_COMMENT_ADDED,
                    title: "Nouveau commentaire",
                    message: "{$event->getAuthor()->getFirstName()} a commenté l'objectif '{$objective->getTitle()}'",
                    url: "/admin/objectives/{$objective->getId()}",
                    objective: $objective
                );
            }
        }
    }
}
```

### 4. **Contrôleur de Notifications**

```php
// src/Controller/NotificationController.php
#[Route('/admin/notifications')]
#[IsGranted('ROLE_USER')]
class NotificationController extends AbstractController
{
    #[Route('', name: 'admin_notifications_list', methods: ['GET'])]
    public function list(Request $request, NotificationRepository $repository): Response
    {
        $user = $this->getUser();
        $page = $request->query->getInt('page', 1);
        $limit = 20;
        
        $notifications = $repository->findByUser($user, $page, $limit);
        $unreadCount = $repository->countUnread($user);
        
        return $this->render('tailadmin/pages/notifications/list.html.twig', [
            'notifications' => $notifications,
            'unreadCount' => $unreadCount,
            'page' => $page,
        ]);
    }
    
    #[Route('/api/unread-count', name: 'api_notifications_unread_count', methods: ['GET'])]
    public function unreadCount(NotificationRepository $repository): JsonResponse
    {
        $user = $this->getUser();
        $count = $repository->countUnread($user);
        
        return new JsonResponse(['count' => $count]);
    }
    
    #[Route('/api/mark-read/{id}', name: 'api_notifications_mark_read', methods: ['POST'])]
    public function markAsRead(int $id, NotificationService $service): JsonResponse
    {
        $notification = $this->notificationRepository->find($id);
        if (!$notification || $notification->getRecipient() !== $this->getUser()) {
            return new JsonResponse(['success' => false], 404);
        }
        
        $service->markAsRead($notification);
        
        return new JsonResponse(['success' => true]);
    }
    
    #[Route('/api/mark-all-read', name: 'api_notifications_mark_all_read', methods: ['POST'])]
    public function markAllAsRead(NotificationService $service): JsonResponse
    {
        $service->markAllAsRead($this->getUser());
        
        return new JsonResponse(['success' => true]);
    }
    
    #[Route('/api/delete/{id}', name: 'api_notifications_delete', methods: ['DELETE'])]
    public function delete(int $id, NotificationService $service): JsonResponse
    {
        $notification = $this->notificationRepository->find($id);
        if (!$notification || $notification->getRecipient() !== $this->getUser()) {
            return new JsonResponse(['success' => false], 404);
        }
        
        $service->delete($notification);
        
        return new JsonResponse(['success' => true]);
    }
}
```

### 5. **Frontend - Composant de Notifications**

```javascript
// public/js/notifications.js
class NotificationManager {
    constructor(mercureUrl, mercureToken, userId) {
        this.mercureUrl = mercureUrl;
        this.mercureToken = mercureToken;
        this.userId = userId;
        this.eventSource = null;
        this.unreadCount = 0;
        this.notifications = [];
    }
    
    init() {
        this.loadNotifications();
        this.connectToMercure();
        this.setupUI();
    }
    
    /**
     * Charge les notifications depuis l'API
     */
    async loadNotifications() {
        try {
            const response = await fetch('/admin/notifications/api/unread-count');
            const data = await response.json();
            this.unreadCount = data.count;
            this.updateBadge();
        } catch (error) {
            console.error('Erreur chargement notifications:', error);
        }
    }
    
    /**
     * Se connecte à Mercure pour recevoir les notifications en temps réel
     */
    connectToMercure() {
        if (!this.mercureToken || !this.mercureUrl) {
            console.error('Token Mercure ou URL manquant');
            return;
        }
        
        const url = new URL(this.mercureUrl);
        url.searchParams.append('topic', `/notifications/user/${this.userId}`);
        
        this.eventSource = new EventSourcePolyfill(url.toString(), {
            headers: {
                'Authorization': `Bearer ${this.mercureToken}`
            }
        });
        
        this.eventSource.onmessage = (event) => {
            try {
                const notification = JSON.parse(event.data);
                this.handleNewNotification(notification);
            } catch (error) {
                console.error('Erreur parsing notification:', error);
            }
        };
        
        this.eventSource.onopen = () => {
            console.log('✅ Connecté aux notifications temps réel');
        };
        
        this.eventSource.onerror = (error) => {
            console.error('Erreur connexion notifications:', error);
        };
    }
    
    /**
     * Traite une nouvelle notification reçue
     */
    handleNewNotification(notification) {
        // Ajouter à la liste
        this.notifications.unshift(notification);
        
        // Mettre à jour le compteur
        if (!notification.isRead) {
            this.unreadCount++;
            this.updateBadge();
        }
        
        // Afficher une notification toast
        this.showToast(notification);
        
        // Mettre à jour la liste si le panneau est ouvert
        if (this.isPanelOpen()) {
            this.renderNotifications();
        }
    }
    
    /**
     * Affiche une notification toast
     */
    showToast(notification) {
        // Utiliser votre système de toast existant
        if (window.showToast) {
            window.showToast(notification.title, 'info', {
                onClick: () => {
                    if (notification.url) {
                        window.location.href = notification.url;
                    }
                }
            });
        }
    }
    
    /**
     * Met à jour le badge de notifications
     */
    updateBadge() {
        const badge = document.querySelector('[data-notification-badge]');
        if (badge) {
            badge.textContent = this.unreadCount > 99 ? '99+' : this.unreadCount;
            badge.style.display = this.unreadCount > 0 ? 'block' : 'none';
        }
    }
    
    /**
     * Ouvre le panneau de notifications
     */
    openPanel() {
        // Afficher le panneau latéral avec les notifications
        this.renderNotifications();
    }
    
    /**
     * Rend la liste des notifications
     */
    async renderNotifications() {
        // Charger et afficher les notifications
        const response = await fetch('/admin/notifications');
        const html = await response.text();
        // Injecter dans le panneau
    }
}
```

### 6. **Template - Composant UI**

```twig
{# templates/tailadmin/components/notifications.html.twig #}
<div 
    x-data="notificationManager()"
    class="relative"
    data-user-id="{{ app.user.id }}"
    data-mercure-url="{{ mercure_hub }}"
    data-mercure-token="{{ mercure_token }}"
>
    <!-- Bouton avec badge -->
    <button
        @click="togglePanel()"
        class="relative p-2 text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100"
    >
        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                  d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
        </svg>
        <span
            x-show="unreadCount > 0"
            x-text="unreadCount > 99 ? '99+' : unreadCount"
            class="absolute top-0 right-0 inline-flex items-center justify-center px-2 py-1 text-xs font-bold leading-none text-white transform translate-x-1/2 -translate-y-1/2 bg-red-600 rounded-full"
        ></span>
    </button>
    
    <!-- Panneau de notifications -->
    <div
        x-show="panelOpen"
        @click.away="panelOpen = false"
        class="absolute right-0 mt-2 w-96 bg-white rounded-lg shadow-lg z-50 dark:bg-gray-800"
        style="max-height: 600px; overflow-y: auto;"
    >
        <div class="p-4 border-b border-gray-200 dark:border-gray-700">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-semibold">Notifications</h3>
                <button
                    @click="markAllAsRead()"
                    class="text-sm text-brand-600 hover:text-brand-700"
                    x-show="unreadCount > 0"
                >
                    Tout marquer comme lu
                </button>
            </div>
        </div>
        
        <div class="divide-y divide-gray-200 dark:divide-gray-700">
            <template x-for="notification in notifications" :key="notification.id">
                <div
                    @click="openNotification(notification)"
                    :class="notification.isRead ? 'bg-white dark:bg-gray-800' : 'bg-blue-50 dark:bg-blue-900/20'"
                    class="p-4 hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer"
                >
                    <div class="flex items-start gap-3">
                        <div class="flex-1">
                            <p class="text-sm font-medium text-gray-900 dark:text-white" 
                               x-text="notification.title"></p>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1"
                               x-text="notification.message"></p>
                            <p class="text-xs text-gray-400 dark:text-gray-500 mt-1"
                               x-text="formatDate(notification.createdAt)"></p>
                        </div>
                        <button
                            @click.stop="deleteNotification(notification.id)"
                            class="text-gray-400 hover:text-gray-600"
                        >
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>
            </template>
        </div>
        
        <div class="p-4 border-t border-gray-200 dark:border-gray-700 text-center">
            <a href="/admin/notifications" class="text-sm text-brand-600 hover:text-brand-700">
                Voir toutes les notifications
            </a>
        </div>
    </div>
</div>
```

---

## 📊 Types de Notifications par Rôle

### Coach
- ✅ Nouvelle preuve soumise
- ✅ Nouvel objectif créé (par parent/élève)
- ✅ Nouvelle demande
- ✅ Commentaire ajouté sur un objectif
- ✅ Rappel : tâches non validées depuis > 3 jours
- ✅ Rappel : objectif approchant de la deadline

### Parent
- ✅ Tâche complétée par l'enfant
- ✅ Objectif validé par le coach
- ✅ Réponse à une demande
- ✅ Nouveau commentaire sur un objectif
- ✅ Rappel : tâches assignées au parent

### Élève
- ✅ Tâche validée par le coach
- ✅ Objectif validé
- ✅ Nouveau commentaire
- ✅ Nouvelle tâche assignée
- ✅ Rappel : tâches à faire aujourd'hui
- ✅ Célébration : objectif terminé

### Spécialiste
- ✅ Nouvelle demande assignée
- ✅ Réponse à une demande
- ✅ Nouvelle tâche assignée
- ✅ Rappel : deadline de tâche approchante

---

## 🚀 Avantages de cette Solution

1. **Temps Réel** : Utilise Mercure déjà en place
2. **Persistance** : Notifications stockées en base de données
3. **Historique** : Possibilité de revoir les anciennes notifications
4. **Flexible** : Facile d'ajouter de nouveaux types
5. **Performant** : Utilise les événements Symfony
6. **UX** : Badge, toast, panneau déroulant

---

## 📝 Implémentation Progressive

### Phase 1 (Priorité Haute)
1. Créer l'entité `Notification`
2. Créer le `NotificationService`
3. Créer le `NotificationController`
4. Ajouter les listeners pour les événements principaux :
   - Preuve soumise
   - Tâche validée
   - Objectif créé/validé

### Phase 2 (Priorité Moyenne)
1. Frontend : Composant de notifications
2. Intégration Mercure côté frontend
3. Page de liste des notifications
4. Actions : marquer comme lu, supprimer

### Phase 3 (Priorité Basse)
1. Notifications de rappel (cron jobs)
2. Préférences de notifications par utilisateur
3. Notifications par email (optionnel)
4. Notifications push mobile (futur)

---

## 🔧 Configuration Requise

1. **Mercure Hub** : Déjà configuré ✅
2. **Event Dispatcher** : Symfony (déjà présent) ✅
3. **Base de données** : Migration pour l'entité Notification
4. **Frontend** : EventSourcePolyfill (déjà utilisé) ✅

---

## 💡 Améliorations Futures

1. **Préférences utilisateur** : Choisir quelles notifications recevoir
2. **Groupement** : Grouper les notifications similaires
3. **Filtres** : Filtrer par type, date, lu/non lu
4. **Recherche** : Rechercher dans les notifications
5. **Export** : Exporter l'historique des notifications

