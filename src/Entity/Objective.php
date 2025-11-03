<?php

namespace App\Entity;

use App\Repository\ObjectiveRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ObjectiveRepository::class)]
class Objective
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[ORM\Column(type: 'text')]
    private ?string $description = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $deadline = null;

    #[ORM\Column(length: 100)]
    private ?string $category = null;

    #[ORM\Column(length: 50)]
    private ?string $status = 'pending';
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

    public function __construct()
    {
        $this->tasks = new ArrayCollection();
        $this->comments = new ArrayCollection();
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

    public function getDeadline(): ?\DateTimeImmutable
    {
        return $this->deadline;
    }

    public function setDeadline(\DateTimeImmutable $deadline): static
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

    public function toArray(): array
    {
        return [
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
        $objective->setStatus($data['status'] ?? 'pending');
        $objective->setCategory($data['category'] ?? 'general');
        $objective->setProgress($data['progress'] ?? 0);
        
        if (isset($data['deadline'])) {
            $objective->setDeadline(new \DateTimeImmutable($data['deadline']));
        }
        
        return $objective;
    }

    public static function createForCoach(array $data, Student $student, Coach $coach): self
    {
        return self::create($data, $student, $coach);
    }
}
