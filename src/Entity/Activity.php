<?php

namespace App\Entity;

use App\Repository\ActivityRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ActivityRepository::class)]
class Activity
{
    // Types d'activité
    public const TYPE_INDIVIDUAL = 'individual';
    public const TYPE_WITH_ADULT = 'with_adult';

    public const TYPES = [
        self::TYPE_INDIVIDUAL => 'Individuel',
        self::TYPE_WITH_ADULT => 'Avec un adulte',
    ];

    // Statuts de l'activité
    public const STATUS_MODIFICATION = 'modification';
    public const STATUS_PENDING_VALIDATION = 'pending_validation';
    public const STATUS_VALIDATED = 'validated';
    public const STATUS_PUBLISHED = 'published';

    public const STATUSES = [
        self::STATUS_MODIFICATION => 'En cours de Modification',
        self::STATUS_PENDING_VALIDATION => 'Attente de Validation par Coach',
        self::STATUS_VALIDATED => 'Validé par le coach',
        self::STATUS_PUBLISHED => 'Publié',
    ];

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $status = self::STATUS_MODIFICATION;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'text')]
    private ?string $description = null;

    #[ORM\Column(length: 100)]
    private ?string $duration = null; // Texte libre : "30 minutes", "15-30 minutes", etc.

    #[ORM\Column(length: 50)]
    private ?string $ageRange = null; // Tranche d'âges : "3-5 ans", "6-8 ans", etc.

    #[ORM\Column(length: 50)]
    private ?string $type = null; // "individual" ou "with_adult"

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $objectives = null; // Tableau : ["objectif1", "objectif2", ...]

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $workedPoints = null; // Tableau de tags : ["Motricité fine", "Concentration", ...]

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\ManyToOne(inversedBy: 'activities')]
    #[ORM\JoinColumn(nullable: false)]
    private ?ActivityCategory $category = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $createdBy = null;

    #[ORM\OneToMany(mappedBy: 'activity', targetEntity: ActivityImage::class, cascade: ['persist', 'remove'], fetch: 'EXTRA_LAZY')]
    private Collection $images;

    #[ORM\OneToMany(mappedBy: 'activity', targetEntity: Comment::class, cascade: ['persist', 'remove'], fetch: 'EXTRA_LAZY')]
    private Collection $comments;

    public function __construct()
    {
        $this->images = new ArrayCollection();
        $this->comments = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->objectives = [];
        $this->workedPoints = [];
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getDuration(): ?string
    {
        return $this->duration;
    }

    public function setDuration(string $duration): static
    {
        $this->duration = $duration;
        return $this;
    }

    public function getAgeRange(): ?string
    {
        return $this->ageRange;
    }

    public function setAgeRange(string $ageRange): static
    {
        $this->ageRange = $ageRange;
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

    public function getObjectives(): ?array
    {
        return $this->objectives ?? [];
    }

    public function setObjectives(?array $objectives): static
    {
        $this->objectives = $objectives ?? [];
        return $this;
    }

    public function getWorkedPoints(): ?array
    {
        return $this->workedPoints ?? [];
    }

    public function setWorkedPoints(?array $workedPoints): static
    {
        $this->workedPoints = $workedPoints ?? [];
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

    public function getCategory(): ?ActivityCategory
    {
        return $this->category;
    }

    public function setCategory(?ActivityCategory $category): static
    {
        $this->category = $category;
        return $this;
    }


    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?User $createdBy): static
    {
        $this->createdBy = $createdBy;
        return $this;
    }

    /**
     * @return Collection<int, ActivityImage>
     */
    public function getImages(): Collection
    {
        return $this->images;
    }

    public function addImage(ActivityImage $image): static
    {
        if (!$this->images->contains($image)) {
            $this->images->add($image);
            $image->setActivity($this);
        }
        return $this;
    }

    public function removeImage(ActivityImage $image): static
    {
        if ($this->images->removeElement($image)) {
            if ($image->getActivity() === $this) {
                $image->setActivity(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, Comment>
     */
    public function getComments(): Collection
    {
        return $this->comments;
    }

    public function addComment(Comment $comment): static
    {
        if (!$this->comments->contains($comment)) {
            $this->comments->add($comment);
            $comment->setActivity($this);
        }
        return $this;
    }

    public function removeComment(Comment $comment): static
    {
        if ($this->comments->removeElement($comment)) {
            if ($comment->getActivity() === $this) {
                $comment->setActivity(null);
            }
        }
        return $this;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->getId(),
            'description' => $this->getDescription(),
            'duration' => $this->getDuration(),
            'ageRange' => $this->getAgeRange(),
            'type' => $this->getType(),
            'typeLabel' => self::TYPES[$this->getType()] ?? $this->getType(),
            'status' => $this->getStatus(),
            'statusLabel' => $this->getStatusLabel(),
            'statusMessage' => $this->getStatusMessage(),
            'canModify' => $this->canModify(),
            'objectives' => $this->getObjectives(),
            'workedPoints' => $this->getWorkedPoints(),
            'category' => $this->getCategory()?->toArray(),
            'createdBy' => $this->getCreatedBy()?->toSimpleArray(),
            'imagesCount' => $this->getImages()->count(),
            'commentsCount' => $this->getComments()->count(),
            'createdAt' => $this->getCreatedAt()?->format('Y-m-d H:i:s'),
            'updatedAt' => $this->getUpdatedAt()?->format('Y-m-d H:i:s')
        ];
    }

    public function getStatus(): ?string
    {
        return $this->status ?? self::STATUS_MODIFICATION;
    }

    public function setStatus(?string $status): static
    {
        $this->status = $status ?? self::STATUS_MODIFICATION;
        return $this;
    }

    /**
     * Vérifie si on peut modifier cette activité
     * Seulement si le statut est "En cours de Modification" ou "Attente de Validation par Coach"
     */
    public function canModify(): bool
    {
        return in_array($this->getStatus(), [
            self::STATUS_MODIFICATION,
            self::STATUS_PENDING_VALIDATION,
        ]);
    }

    /**
     * Retourne le message descriptif du statut
     */
    public function getStatusMessage(): string
    {
        return match($this->getStatus()) {
            self::STATUS_MODIFICATION => 'Cette activité est en cours de modification. Vous pouvez la modifier librement.',
            self::STATUS_PENDING_VALIDATION => 'Cette activité est en attente de validation par le coach. Vous pouvez encore la modifier.',
            self::STATUS_VALIDATED => 'Cette activité a été validée par le coach. Elle ne peut plus être modifiée.',
            self::STATUS_PUBLISHED => 'Cette activité est publiée. Elle ne peut plus être modifiée.',
            default => 'Statut inconnu',
        };
    }

    /**
     * Retourne le label du statut
     */
    public function getStatusLabel(): string
    {
        return self::STATUSES[$this->getStatus()] ?? $this->getStatus();
    }

    public static function create(array $data, User $createdBy, ActivityCategory $category): self
    {
        $activity = new self();
        $activity->setDescription($data['description']);
        $activity->setDuration($data['duration']);
        $activity->setAgeRange($data['ageRange']);
        $activity->setType($data['type']);
        $activity->setCategory($category);
        $activity->setObjectives($data['objectives'] ?? []);
        $activity->setWorkedPoints($data['workedPoints'] ?? []);
        $activity->setCreatedBy($createdBy);
        $activity->setStatus($data['status'] ?? self::STATUS_MODIFICATION);

        return $activity;
    }
}

