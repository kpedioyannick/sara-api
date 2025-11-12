<?php

namespace App\Entity;

use App\Repository\PlanningRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PlanningRepository::class)]
class Planning
{

    public const TYPE_HOMEWORK     = 'homework';
    public const TYPE_REVISION     = 'revision';
    public const TYPE_TASK         = 'task';
    public const TYPE_ASSESSMENT   = 'assessment';
    public const TYPE_COURSE       = 'course';
    public const TYPE_TRAINING     = 'training';
    public const TYPE_DETENTION    = 'detention';
    public const TYPE_ACTIVITY     = 'activity';
    public const TYPE_EXAM         = 'exam';
    public const TYPE_OBJECTIVE    = 'objective';
    public const TYPE_OTHER        = 'other';
        // Types de disponibilités
    public const TYPE_AVAILABILITY_EXCHANGE = 'availability_exchange'; // Dispos pour échanger avec élèves ou parents
    public const TYPE_AVAILABILITY_ACTIVITY = 'availability_activity'; // Dispos pour réaliser des activités sur le site 
        


    // Statuts
    public const STATUS_TO_DO = 'to_do';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_INCOMPLETE = 'incomplete';

    public const STATUSES = [
        self::STATUS_TO_DO,
        self::STATUS_IN_PROGRESS,
        self::STATUS_COMPLETED,
        self::STATUS_INCOMPLETE,
    ];

    public const TYPES = [
        self::TYPE_HOMEWORK,
        self::TYPE_REVISION,
        self::TYPE_TASK,
        self::TYPE_ASSESSMENT,
        self::TYPE_COURSE,
        self::TYPE_TRAINING,
        self::TYPE_ACTIVITY,
        self::TYPE_DETENTION,
        self::TYPE_EXAM,
        self::TYPE_OBJECTIVE,
        self::TYPE_OTHER
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $startDate = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $endDate = null;

    #[ORM\Column(length: 100)]
    private ?string $type = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $status = 'scheduled';


    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $user = null;

    #[ORM\ManyToOne(inversedBy: 'plannings')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Integration $integration = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $referenceId = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $metadata = [];

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $recurrence = null;

    #[ORM\Column(nullable: true)]
    private ?int $recurrenceInterval = 1;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $recurrenceEnd = null;

    #[ORM\Column(nullable: true)]
    private ?int $maxOccurrences = null;

    #[ORM\OneToMany(mappedBy: 'planning', targetEntity: Proof::class, cascade: ['persist', 'remove'], fetch: 'EXTRA_LAZY')]
    private Collection $proofs;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->proofs = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getStartDate(): ?\DateTimeImmutable
    {
        return $this->startDate;
    }

    public function setStartDate(\DateTimeImmutable $startDate): static
    {
        $this->startDate = $startDate;
        return $this;
    }

    public function getEndDate(): ?\DateTimeImmutable
    {
        return $this->endDate;
    }

    public function setEndDate(\DateTimeImmutable $endDate): static
    {
        $this->endDate = $endDate;
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

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->getId(),
            'title' => $this->getTitle(),
            'description' => $this->getDescription(),
            'startDate' => $this->getStartDate()?->format('Y-m-d H:i:s'),
            'endDate' => $this->getEndDate()?->format('Y-m-d H:i:s'),
            'date' => $this->getDate()?->format('Y-m-d H:i:s'),
            'type' => $this->getType(),
            'status' => $this->getStatus(),
            'user' => $this->getUser()?->getId(),
            'recurrence' => $this->getRecurrence(),
            'recurrenceInterval' => $this->getRecurrenceInterval(),
            'recurrenceEnd' => $this->getRecurrenceEnd()?->format('Y-m-d H:i:s'),
            'maxOccurrences' => $this->getMaxOccurrences(),
            'isRecurring' => !is_null($this->getRecurrence()),
            'metadata' => $this->getMetadata() ?? [],
            'proofsCount' => $this->getProofs()->count(),
            'createdAt' => $this->getCreatedAt()?->format('Y-m-d H:i:s'),
            'updatedAt' => $this->getUpdatedAt()?->format('Y-m-d H:i:s')
        ];
    }

    /**
     * Version optimisée pour les parents - données minimales
     */
    public function toParentArray(): array
    {
        return [
            'id' => $this->getId(),
            'title' => $this->getTitle(),
            'description' => $this->getDescription(),
            'startDate' => $this->getStartDate()?->format('Y-m-d H:i:s'),
            'endDate' => $this->getEndDate()?->format('Y-m-d H:i:s'),
            'date' => $this->getDate()?->format('Y-m-d H:i:s'),
            'type' => $this->getType(),
            'status' => $this->getStatus(),
            'user' => $this->getUser() ? [
                'id' => $this->getUser()->getId(),
                'firstName' => $this->getUser()->getFirstName(),
                'lastName' => $this->getUser()->getLastName(),
                'pseudo' => $this->getUser()->getPseudo(),
            ] : null,
            'createdAt' => $this->getCreatedAt()?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Version détaillée avec proofs (pour la vue complète)
     */
    public function toDetailedArray(): array
    {
        $data = $this->toArray();
        $data['proofs'] = array_map(fn($proof) => $proof->toArray(), $this->getProofs()->toArray());
        return $data;
    }

    public function toPublicArray(): array
    {
        return [
            'id' => $this->getId(),
            'title' => $this->getTitle(),
            'description' => $this->getDescription(),
            'date' => $this->getDate()?->format('Y-m-d H:i:s'),
            'type' => $this->getType(),
            'status' => $this->getStatus(),
        ];
    }

    /**
     * Mappe le statut du planning pour le template
     */
    public function getMappedStatus(): string
    {
        return match ($this->getStatus()) {
            'completed', 'done' => 'termine',
            'in_progress', 'ongoing' => 'en_cours',
            default => 'planifie',
        };
    }

    /**
     * Retourne le libellé traduit du type
     */
    public function getTypeLabel(): string
    {
        return match ($this->getType()) {
            self::TYPE_HOMEWORK => 'Devoir',
            self::TYPE_REVISION => 'Révision',
            self::TYPE_TASK => 'Tâche',
            self::TYPE_ASSESSMENT => 'Évaluation',
            self::TYPE_COURSE => 'Cours',
            self::TYPE_TRAINING => 'Entraînement',
            self::TYPE_DETENTION => 'Retenue',
            self::TYPE_ACTIVITY => 'Activité',
            self::TYPE_EXAM => 'Examen',
            self::TYPE_OBJECTIVE => 'Objectif',
            self::TYPE_OTHER => 'Autre',
            default => 'Autre',
        };
    }

    /**
     * Retourne le libellé traduit d'un type donné (méthode statique)
     */
    public static function getTypeLabelStatic(string $type): string
    {
        return match ($type) {
            self::TYPE_HOMEWORK => 'Devoir',
            self::TYPE_REVISION => 'Révision',
            self::TYPE_TASK => 'Tâche',
            self::TYPE_ASSESSMENT => 'Évaluation',
            self::TYPE_COURSE => 'Cours',
            self::TYPE_TRAINING => 'Entraînement',
            self::TYPE_DETENTION => 'Retenue',
            self::TYPE_ACTIVITY => 'Activité',
            self::TYPE_EXAM => 'Examen',
            self::TYPE_OBJECTIVE => 'Objectif',
            self::TYPE_OTHER => 'Autre',
            default => 'Autre',
        };
    }

    /**
     * Retourne les données formatées pour le template de liste
     */
    public function toTemplateArray(): array
    {
        $user = $this->getUser();
        $metadata = $this->getMetadata() ?? [];
        
        return [
            'id' => $this->getId(),
            'title' => $this->getTitle(),
            'description' => $this->getDescription(),
            'userId' => $user?->getId(),
            'userName' => $user 
                ? $user->getFirstName() . ' ' . $user->getLastName()
                : 'N/A',
            'type' => $this->getType(),
            'typeLabel' => $this->getTypeLabel(),
            'startDate' => $this->getStartDate()?->format('Y-m-d H:i:s'),
            'endDate' => $this->getEndDate()?->format('Y-m-d H:i:s'),
            'status' => $this->getMappedStatus(),
            'backgroundColor' => $metadata['pronote_backgroundColor'] ?? null,
        ];
    }

    public function getDate(): ?\DateTimeImmutable
    {
        return $this->getStartDate();
    }

    public function setDate(\DateTimeImmutable $date): static
    {
        $this->startDate = $date;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status ?? 'scheduled';
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getDuration(): int
    {
        if (!$this->getStartDate() || !$this->getEndDate()) {
            return $this->duration ?? 60;
        }
        
        $start = $this->getStartDate();
        $end = $this->getEndDate();
        $diff = $end->diff($start);
        
        return ($diff->h * 60) + $diff->i;
    }

    public function setDuration(int $duration): static
    {
        $this->duration = $duration;
        return $this;
    }


    public static function create(array $data, User $user): self
    {
        $planning = new self();
        $planning->setTitle($data['title']);
        $planning->setDescription($data['description']);
        
        // Gérer les dates
        if (isset($data['date'])) {
            $planning->setDate(new \DateTimeImmutable($data['date']));
        } elseif (isset($data['start_date'])) {
            $planning->setStartDate(new \DateTimeImmutable($data['start_date']));
            if (isset($data['end_date'])) {
                $planning->setEndDate(new \DateTimeImmutable($data['end_date']));
            }
        }
        
        $planning->setUser($user);
        $planning->setType($data['type'] ?? 'session');
        $planning->setStatus($data['status'] ?? 'scheduled');
        
        // Gérer les métadonnées
        if (isset($data['metadata']) && is_array($data['metadata'])) {
            $planning->setMetadata($data['metadata']);
        }
        
        return $planning;
    }

    public static function createForCoach(array $data, User $user): self
    {
        return self::create($data, $user);
    }

    public function getRecurrence(): ?string
    {
        return $this->recurrence;
    }

    public function setRecurrence(?string $recurrence): static
    {
        $this->recurrence = $recurrence;
        return $this;
    }

    public function getRecurrenceInterval(): ?int
    {
        return $this->recurrenceInterval;
    }

    public function setRecurrenceInterval(?int $recurrenceInterval): static
    {
        $this->recurrenceInterval = $recurrenceInterval;
        return $this;
    }

    public function getRecurrenceEnd(): ?\DateTimeImmutable
    {
        return $this->recurrenceEnd;
    }

    public function setRecurrenceEnd(?\DateTimeImmutable $recurrenceEnd): static
    {
        $this->recurrenceEnd = $recurrenceEnd;
        return $this;
    }

    public function getMaxOccurrences(): ?int
    {
        return $this->maxOccurrences;
    }

    public function setMaxOccurrences(?int $maxOccurrences): static
    {
        $this->maxOccurrences = $maxOccurrences;
        return $this;
    }

    public function getIntegration(): ?Integration
    {
        return $this->integration;
    }

    public function setIntegration(?Integration $integration): static
    {
        $this->integration = $integration;
        return $this;
    }

    public function getReferenceId(): ?string
    {
        return $this->referenceId;
    }

    public function setReferenceId(?string $referenceId): static
    {
        $this->referenceId = $referenceId;
        return $this;
    }

    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    public function setMetadata(?array $metadata): static
    {
        $this->metadata = $metadata;
        return $this;
    }

    /**
     * @return Collection<int, Proof>
     */
    public function getProofs(): Collection
    {
        return $this->proofs;
    }

    public function addProof(Proof $proof): static
    {
        if (!$this->proofs->contains($proof)) {
            $this->proofs->add($proof);
            $proof->setPlanning($this);
        }
        return $this;
    }

    public function removeProof(Proof $proof): static
    {
        if ($this->proofs->removeElement($proof)) {
            if ($proof->getPlanning() === $this) {
                $proof->setPlanning(null);
            }
        }
        return $this;
    }
}
