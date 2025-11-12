<?php

namespace App\Entity;

use App\Repository\NotificationRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: NotificationRepository::class)]
#[ORM\Table(name: 'notifications')]
#[ORM\Index(columns: ['recipient_id', 'is_read'], name: 'idx_recipient_read')]
#[ORM\Index(columns: ['type'], name: 'idx_type')]
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
    public const TYPE_NEW_TASK_ASSIGNED = 'new_task_assigned';

    public const TYPES = [
        self::TYPE_TASK_COMPLETED => 'Tâche complétée',
        self::TYPE_TASK_VALIDATED => 'Tâche validée',
        self::TYPE_OBJECTIVE_CREATED => 'Objectif créé',
        self::TYPE_OBJECTIVE_VALIDATED => 'Objectif validé',
        self::TYPE_REQUEST_CREATED => 'Demande créée',
        self::TYPE_REQUEST_RESPONDED => 'Réponse à une demande',
        self::TYPE_COMMENT_ADDED => 'Commentaire ajouté',
        self::TYPE_PLANNING_EVENT => 'Événement de planning',
        self::TYPE_DEADLINE_REMINDER => 'Rappel de deadline',
        self::TYPE_PROOF_SUBMITTED => 'Preuve soumise',
        self::TYPE_NEW_TASK_ASSIGNED => 'Nouvelle tâche assignée',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $recipient = null;

    #[ORM\Column(length: 50)]
    private ?string $type = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $message = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $data = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $url = null;

    #[ORM\Column]
    private bool $isRead = false;

    #[ORM\Column(nullable: true)]
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

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->isRead = false;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRecipient(): ?User
    {
        return $this->recipient;
    }

    public function setRecipient(?User $recipient): static
    {
        $this->recipient = $recipient;
        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;
        return $this;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(?string $message): static
    {
        $this->message = $message;
        return $this;
    }

    public function getData(): ?array
    {
        return $this->data;
    }

    public function setData(?array $data): static
    {
        $this->data = $data;
        return $this;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(?string $url): static
    {
        $this->url = $url;
        return $this;
    }

    public function isRead(): bool
    {
        return $this->isRead;
    }

    public function setIsRead(bool $isRead): static
    {
        $this->isRead = $isRead;
        if ($isRead && !$this->readAt) {
            $this->readAt = new \DateTimeImmutable();
        } elseif (!$isRead) {
            $this->readAt = null;
        }
        return $this;
    }

    public function getReadAt(): ?\DateTimeImmutable
    {
        return $this->readAt;
    }

    public function setReadAt(?\DateTimeImmutable $readAt): static
    {
        $this->readAt = $readAt;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getObjective(): ?Objective
    {
        return $this->objective;
    }

    public function setObjective(?Objective $objective): static
    {
        $this->objective = $objective;
        return $this;
    }

    public function getTask(): ?Task
    {
        return $this->task;
    }

    public function setTask(?Task $task): static
    {
        $this->task = $task;
        return $this;
    }

    public function getRequest(): ?Request
    {
        return $this->request;
    }

    public function setRequest(?Request $request): static
    {
        $this->request = $request;
        return $this;
    }

    /**
     * Convertit la notification en tableau pour l'API
     */
    public function toArray(): array
    {
        return [
            'id' => $this->getId(),
            'type' => $this->getType(),
            'typeLabel' => self::TYPES[$this->getType()] ?? $this->getType(),
            'title' => $this->getTitle(),
            'message' => $this->getMessage(),
            'data' => $this->getData(),
            'url' => $this->getUrl(),
            'isRead' => $this->isRead(),
            'readAt' => $this->getReadAt()?->format('Y-m-d H:i:s'),
            'createdAt' => $this->getCreatedAt()?->format('Y-m-d H:i:s'),
            'objectiveId' => $this->getObjective()?->getId(),
            'taskId' => $this->getTask()?->getId(),
            'requestId' => $this->getRequest()?->getId(),
        ];
    }
}

