<?php

namespace App\Entity;

use App\Repository\ObjectiveRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ObjectiveRepository::class)]
class Objective
{
    // Statuts de l'objectif
    public const STATUS_MODIFICATION = 'modification';
    public const STATUS_PENDING_VALIDATION = 'pending_validation';
    public const STATUS_VALIDATED = 'validated';
    public const STATUS_IN_ACTION = 'in_action';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_PAUSED = 'paused';

    public const STATUSES = [
        self::STATUS_MODIFICATION => 'En cours de Modification',
        self::STATUS_PENDING_VALIDATION => 'Attente de Validation par Coach',
        self::STATUS_VALIDATED => 'Validé par le coach',
        self::STATUS_IN_ACTION => 'En Action',
        self::STATUS_COMPLETED => 'Terminé',
        self::STATUS_PAUSED => 'En pause',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[ORM\Column(type: 'text')]
    private ?string $description = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $descriptionOrigin = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $deadline = null;

    #[ORM\Column(length: 100)]
    private ?string $category = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $categoryTags = null;

    #[ORM\Column(length: 50)]
    private ?string $status = self::STATUS_MODIFICATION;
    #[ORM\Column(nullable: true)]
    private ?int $progress = 0;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\ManyToOne(inversedBy: 'objectives')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Student $student = null;

    #[ORM\ManyToOne(inversedBy: 'objectives')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Coach $coach = null;

    #[ORM\OneToMany(targetEntity: Task::class, mappedBy: 'objective', orphanRemoval: true, fetch: 'EXTRA_LAZY')]
    private Collection $tasks;

    #[ORM\OneToMany(targetEntity: Comment::class, mappedBy: 'objective', orphanRemoval: true, fetch: 'EXTRA_LAZY')]
    private Collection $comments;

    #[ORM\ManyToMany(targetEntity: Student::class)]
    #[ORM\JoinTable(name: 'objective_shared_students')]
    private Collection $sharedStudents;

    #[ORM\ManyToMany(targetEntity: Specialist::class)]
    #[ORM\JoinTable(name: 'objective_shared_specialists')]
    private Collection $sharedSpecialists;

    public function __construct()
    {
        $this->tasks = new ArrayCollection();
        $this->comments = new ArrayCollection();
        $this->sharedStudents = new ArrayCollection();
        $this->sharedSpecialists = new ArrayCollection();
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

    public function setDescription(string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getDescriptionOrigin(): ?string
    {
        if (!empty($this->description)) {
            return $this->descriptionOrigin;
        }
        return $this->descriptionOrigin;
    }

    public function setDescriptionOrigin(?string $descriptionOrigin): static
    {
        $this->descriptionOrigin = $descriptionOrigin;
        return $this;
    }

    public function getDeadline(): ?\DateTimeImmutable
    {
        return $this->deadline;
    }

    public function setDeadline(?\DateTimeImmutable $deadline): static
    {
        $this->deadline = $deadline;
        return $this;
    }

    public function getCategory(): ?string
    {
        return $this->category;
    }

    public function setCategory(string $category): static
    {
        $this->category = $category;
        return $this;
    }

    public function getCategoryTags(): ?array
    {
        return $this->categoryTags;
    }

    public function setCategoryTags(?array $categoryTags): static
    {
        $this->categoryTags = $categoryTags;
        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
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

    public function getCoach(): ?Coach
    {
        return $this->coach;
    }

    public function setCoach(?Coach $coach): static
    {
        $this->coach = $coach;
        return $this;
    }

    /**
     * @return Collection<int, Task>
     */
    public function getTasks(): Collection
    {
        return $this->tasks;
    }

    public function addTask(Task $task): static
    {
        if (!$this->tasks->contains($task)) {
            $this->tasks->add($task);
            $task->setObjective($this);
        }
        return $this;
    }

    public function removeTask(Task $task): static
    {
        if ($this->tasks->removeElement($task)) {
            if ($task->getObjective() === $this) {
                $task->setObjective(null);
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
            $comment->setObjective($this);
        }
        return $this;
    }

    public function removeComment(Comment $comment): static
    {
        if ($this->comments->removeElement($comment)) {
            if ($comment->getObjective() === $this) {
                $comment->setObjective(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, Student>
     */
    public function getSharedStudents(): Collection
    {
        return $this->sharedStudents;
    }

    public function addSharedStudent(Student $student): static
    {
        if (!$this->sharedStudents->contains($student)) {
            $this->sharedStudents->add($student);
        }
        return $this;
    }

    public function removeSharedStudent(Student $student): static
    {
        $this->sharedStudents->removeElement($student);
        return $this;
    }

    /**
     * @return Collection<int, Specialist>
     */
    public function getSharedSpecialists(): Collection
    {
        return $this->sharedSpecialists;
    }

    public function addSharedSpecialist(Specialist $specialist): static
    {
        if (!$this->sharedSpecialists->contains($specialist)) {
            $this->sharedSpecialists->add($specialist);
        }
        return $this;
    }

    public function removeSharedSpecialist(Specialist $specialist): static
    {
        $this->sharedSpecialists->removeElement($specialist);
        return $this;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->getId(),
            'title' => $this->getTitle(),
            'description' => $this->getDescription(),
            'deadline' => $this->getDeadline()?->format('Y-m-d H:i:s'),
            'category' => $this->getCategory(),
            'categoryTags' => $this->getCategoryTags() ?? [],
            'status' => $this->getStatus(),
            'progress' => $this->getProgress(),
            'student' => $this->getStudent()?->toSimpleArray(),
            'coach' => $this->getCoach()?->toSimpleArray(),
            'tasksCount' => $this->getTasks()->count(),
            'commentsCount' => $this->getComments()->count(),
            'sharedStudents' => array_map(fn($student) => $student->toSimpleArray(), $this->getSharedStudents()->toArray()),
            'sharedSpecialists' => array_map(fn($specialist) => $specialist->toSimpleArray(), $this->getSharedSpecialists()->toArray()),
            'createdAt' => $this->getCreatedAt()?->format('Y-m-d H:i:s'),
            'updatedAt' => $this->getUpdatedAt()?->format('Y-m-d H:i:s')
        ];
    }

    /**
     * Version optimisée pour les parents - pas de chargement des collections
     */
    public function toParentArray(): array
    {
        return [
            'id' => $this->getId(),
            'title' => $this->getTitle(),
            'description' => $this->getDescription(),
            'deadline' => $this->getDeadline()?->format('Y-m-d H:i:s'),
            'category' => $this->getCategory(),
            'status' => $this->getStatus(),
            'progress' => $this->getProgress(),
            'student' => $this->getStudent() ? [
                'id' => $this->getStudent()->getId(),
                'firstName' => $this->getStudent()->getFirstName(),
                'lastName' => $this->getStudent()->getLastName(),
                'pseudo' => $this->getStudent()->getPseudo(),
            ] : null,
            'tasksCount' => $this->getTasks()->count(),
            'createdAt' => $this->getCreatedAt()?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Version complète pour les coaches - inclut les détails
     */
    public function toCoachArray(bool $includeComments = false, bool $includeTasks = false): array
    {
        $data = [
            'id' => $this->getId(),
            'title' => $this->getTitle(),
            'description' => $this->getDescription(),
            'deadline' => $this->getDeadline()?->format('Y-m-d H:i:s'),
            'category' => $this->getCategory(),
            'status' => $this->getStatus(),
            'progress' => $this->getProgress(),
            'student' => $this->getStudent()?->toSimpleArray(),
            'coach' => $this->getCoach()?->toSimpleArray(),
            'tasksCount' => $this->getTasks()->count(),
            'commentsCount' => $this->getComments()->count(),
            'sharedStudents' => array_map(fn($student) => $student->toSimpleArray(), $this->getSharedStudents()->toArray()),
            'sharedSpecialists' => array_map(fn($specialist) => $specialist->toSimpleArray(), $this->getSharedSpecialists()->toArray()),
            'createdAt' => $this->getCreatedAt()?->format('Y-m-d H:i:s'),
            'updatedAt' => $this->getUpdatedAt()?->format('Y-m-d H:i:s')
        ];

        if ($includeComments) {
            $data['comments'] = array_map(fn($comment) => $comment->toArray(), $this->getComments()->toArray());
        }

        if ($includeTasks) {
            $data['tasks'] = array_map(fn($task) => $task->toArray(), $this->getTasks()->toArray());
        }

        return $data;
    }

    /**
     * Retourne les données formatées pour le template de liste
     */
    public function toTemplateArray(): array
    {
        $student = $this->getStudent();
        $coach = $this->getCoach();
        
        return [
            'id' => $this->getId(),
            'title' => $this->getTitle(),
            'description' => $this->getDescription(),
            'category' => $this->getCategory(),
            'categoryTags' => $this->getCategoryTags() ?? [],
            'status' => $this->getStatus(),
            'statusLabel' => $this->getStatusLabel(),
            'progress' => $this->getProgress(),
            'deadline' => $this->getDeadline()?->format('Y-m-d'),
            'tasksCount' => $this->getTasks()->count(),
            'studentName' => $student 
                ? $student->getFirstName() . ' ' . $student->getLastName()
                : 'N/A',
            'coachName' => $coach 
                ? $coach->getFirstName() . ' ' . $coach->getLastName()
                : 'N/A',
            'tasks' => array_map(fn($task) => $task->toTemplateArray(), $this->getTasks()->toArray()),
        ];
    }

    public function toPublicArray(): array
    {
        return [
            'id' => $this->getId(),
            'title' => $this->getTitle(),
            'description' => $this->getDescription(),
            'deadline' => $this->getDeadline()?->format('Y-m-d H:i:s'),
            'category' => $this->getCategory(),
            'status' => $this->getStatus(),
            'progress' => $this->getProgress()
        ];
    }


    public function getProgress(): int
    {
        return $this->progress ?? 0;
    }

    public function setProgress(int $progress): static
    {
        $this->progress = $progress;
        return $this;
    }


    public static function create(array $data, Student $student, Coach $coach): self
    {
        $objective = new self();
        $objective->setTitle($data['title']);
        $objective->setDescription($data['description']);
        $objective->setStudent($student);
        $objective->setCoach($coach);
        $objective->setStatus($data['status'] ?? self::STATUS_MODIFICATION);
        $objective->setCategory($data['category'] ?? 'general');
        $objective->setProgress($data['progress'] ?? 0);
        
        if (isset($data['categoryTags']) && is_array($data['categoryTags'])) {
            $objective->setCategoryTags($data['categoryTags']);
        }
        
        if (isset($data['deadline'])) {
            $objective->setDeadline(new \DateTimeImmutable($data['deadline']));
        }
        
        return $objective;
    }

    public static function createForCoach(array $data, Student $student, Coach $coach): self
    {
        return self::create($data, $student, $coach);
    }

    /**
     * Vérifie si on peut créer ou modifier des tâches pour cet objectif
     * Seulement si le statut est "En cours de Modification" ou "Attente de Validation par Coach"
     */
    public function canModifyTasks(): bool
    {
        return in_array($this->status, [
            self::STATUS_MODIFICATION,
            self::STATUS_PENDING_VALIDATION,
        ]);
    }

    /**
     * Retourne le message descriptif du statut
     */
    public function getStatusMessage(): string
    {
        return match($this->status) {
            self::STATUS_MODIFICATION => 'Cet objectif est en cours de modification. Vous pouvez créer et modifier les tâches.',
            self::STATUS_PENDING_VALIDATION => 'Cet objectif est en attente de validation par le coach. Vous pouvez encore créer et modifier les tâches.',
            self::STATUS_VALIDATED => 'Cet objectif a été validé par le coach. Les tâches sont en lecture seule.',
            self::STATUS_IN_ACTION => 'Cet objectif est en action. Les tâches sont en cours d\'exécution et ne peuvent plus être modifiées.',
            self::STATUS_COMPLETED => 'Cet objectif est terminé. Toutes les tâches sont finalisées.',
            self::STATUS_PAUSED => 'Cet objectif est en pause. Les tâches sont en lecture seule.',
            default => 'Statut inconnu',
        };
    }

    /**
     * Retourne le label du statut
     */
    public function getStatusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }
}
