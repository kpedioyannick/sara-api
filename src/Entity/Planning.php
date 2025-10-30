<?php

namespace App\Entity;

use App\Repository\PlanningRepository;
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

    #[ORM\ManyToOne(inversedBy: 'plannings')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Student $student = null;


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



    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
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

    public function getStudent(): ?Student
    {
        return $this->student;
    }

    public function setStudent(?Student $student): static
    {
        $this->student = $student;
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
            'student' => $this->getStudent()?->toSimpleArray(),
            'recurrence' => $this->getRecurrence(),
            'recurrenceInterval' => $this->getRecurrenceInterval(),
            'recurrenceEnd' => $this->getRecurrenceEnd()?->format('Y-m-d H:i:s'),
            'maxOccurrences' => $this->getMaxOccurrences(),
            'isRecurring' => !is_null($this->getRecurrence()),
            'metadata' => $this->getMetadata() ?? [],
            'createdAt' => $this->getCreatedAt()?->format('Y-m-d H:i:s'),
            'updatedAt' => $this->getUpdatedAt()?->format('Y-m-d H:i:s')
        ];
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


    public static function create(array $data, Student $student): self
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
        
        $planning->setStudent($student);
        $planning->setType($data['type'] ?? 'session');
        $planning->setStatus($data['status'] ?? 'scheduled');
        
        // Gérer les métadonnées
        if (isset($data['metadata']) && is_array($data['metadata'])) {
            $planning->setMetadata($data['metadata']);
        }
        
        return $planning;
    }

    public static function createForCoach(array $data, Student $student): self
    {
        return self::create($data, $student);
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

    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    public function setMetadata(?array $metadata): static
    {
        $this->metadata = $metadata;
        return $this;
    }
}
