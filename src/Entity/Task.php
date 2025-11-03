<?php

namespace App\Entity;

use App\Repository\TaskRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TaskRepository::class)]
class Task
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[ORM\Column(type: 'text')]
    private ?string $description = null;

    #[ORM\Column(length: 50)]
    private ?string $status = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $frequency = null;

    #[ORM\Column(type: 'boolean')]
    private ?bool $requiresProof = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $proofType = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $dueDate = null;


    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Coach $coach = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Objective $objective = null;

    #[ORM\ManyToOne]
    private ?Student $student = null;

    #[ORM\ManyToOne]
    private ?ParentUser $parent = null;

    #[ORM\ManyToOne]
    private ?Specialist $specialist = null;

    #[ORM\Column(length: 20)]
    private ?string $assignedType = null;

    #[ORM\OneToMany(mappedBy: 'task', targetEntity: Proof::class, cascade: ['persist', 'remove'], fetch: 'EXTRA_LAZY')]
    private Collection $proofs;

    #[ORM\OneToMany(mappedBy: 'task', targetEntity: TaskHistory::class, cascade: ['persist', 'remove'], fetch: 'EXTRA_LAZY')]
    private Collection $taskHistories;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->proofs = new ArrayCollection();
        $this->taskHistories = new ArrayCollection();
        $this->requiresProof = false;
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

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }


    public function getFrequency(): ?string
    {
        return $this->frequency;
    }

    public function setFrequency(?string $frequency): static
    {
        $this->frequency = $frequency;
        return $this;
    }

    public function isRequiresProof(): ?bool
    {
        return $this->requiresProof;
    }

    public function setRequiresProof(bool $requiresProof): static
    {
        $this->requiresProof = $requiresProof;
        return $this;
    }

    public function getProofType(): ?string
    {
        return $this->proofType;
    }

    public function setProofType(?string $proofType): static
    {
        $this->proofType = $proofType;
        return $this;
    }

    public function getDueDate(): ?\DateTimeImmutable
    {
        return $this->dueDate;
    }

    public function setDueDate(?\DateTimeImmutable $dueDate): static
    {
        $this->dueDate = $dueDate;
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

    public function getCoach(): ?Coach
    {
        return $this->coach;
    }

    public function setCoach(?Coach $coach): static
    {
        $this->coach = $coach;
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

    public function getStudent(): ?Student
    {
        return $this->student;
    }

    public function setStudent(?Student $student): static
    {
        $this->student = $student;
        return $this;
    }

    public function getParent(): ?ParentUser
    {
        return $this->parent;
    }

    public function setParent(?ParentUser $parent): static
    {
        $this->parent = $parent;
        return $this;
    }

    public function getSpecialist(): ?Specialist
    {
        return $this->specialist;
    }

    public function setSpecialist(?Specialist $specialist): static
    {
        $this->specialist = $specialist;
        return $this;
    }

    public function getAssignedType(): ?string
    {
        return $this->assignedType;
    }

    public function setAssignedType(string $assignedType): static
    {
        $this->assignedType = $assignedType;
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
            $proof->setTask($this);
        }

        return $this;
    }

    public function removeProof(Proof $proof): static
    {
        if ($this->proofs->removeElement($proof)) {
            // set the owning side to null (unless already changed)
            if ($proof->getTask() === $this) {
                $proof->setTask(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, TaskHistory>
     */
    public function getTaskHistories(): Collection
    {
        return $this->taskHistories;
    }

    public function addTaskHistory(TaskHistory $taskHistory): static
    {
        if (!$this->taskHistories->contains($taskHistory)) {
            $this->taskHistories->add($taskHistory);
            $taskHistory->setTask($this);
        }

        return $this;
    }

    public function removeTaskHistory(TaskHistory $taskHistory): static
    {
        if ($this->taskHistories->removeElement($taskHistory)) {
            // set the owning side to null (unless already changed)
            if ($taskHistory->getTask() === $this) {
                $taskHistory->setTask(null);
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
            'status' => $this->getStatus(),
            'frequency' => $this->getFrequency(),
            'requiresProof' => $this->isRequiresProof(),
            'proofType' => $this->getProofType(),
            'dueDate' => $this->getDueDate()?->format('Y-m-d H:i:s'),
            'assignedType' => $this->getAssignedType(),
            'assignedTo' => $this->getAssignedToSimpleArray(),
            'objective' => $this->getObjective()?->toArray(),
            'coach' => $this->getCoach()?->toSimpleArray(),
            'proofsCount' => $this->getProofs()->count(),
            'historyCount' => $this->getTaskHistories()->count(),
            'createdAt' => $this->getCreatedAt()?->format('Y-m-d H:i:s'),
            'updatedAt' => $this->getUpdatedAt()?->format('Y-m-d H:i:s')
        ];
    }

    public function toParentArray(): array
    {
        return [
            'id' => $this->getId(),
            'title' => $this->getTitle(),
            'description' => $this->getDescription(),
            'status' => $this->getStatus(),
            'frequency' => $this->getFrequency(),
            'requiresProof' => $this->isRequiresProof(),
            'proofType' => $this->getProofType(),
            'dueDate' => $this->getDueDate()?->format('Y-m-d H:i:s'),
            'assignedType' => $this->getAssignedType(),
            'assignedTo' => $this->getAssignedToSimpleArray(),
            'coach' => $this->getCoach()?->toSimpleArray(),
            'proofs' => array_map(fn($proof) => $proof->toArray(), $this->getProofs()->toArray()),
            'createdAt' => $this->getCreatedAt()?->format('Y-m-d H:i:s'),
            'updatedAt' => $this->getUpdatedAt()?->format('Y-m-d H:i:s')
        ];
    }

    private function getAssignedToArray(): ?array
    {
        switch ($this->getAssignedType()) {
            case 'student':
                return $this->getStudent()?->toArray();
            case 'parent':
                return $this->getParent()?->toArray();
            case 'specialist':
                return $this->getSpecialist()?->toArray();
            case 'coach':
            default:
                return $this->getCoach()?->toArray();
        }
    }

    private function getAssignedToSimpleArray(): ?array
    {
        switch ($this->getAssignedType()) {
            case 'student':
                return $this->getStudent()?->toSimpleArray();
            case 'parent':
                return $this->getParent()?->toSimpleArray();
            case 'specialist':
                return $this->getSpecialist()?->toSimpleArray();
            case 'coach':
            default:
                return $this->getCoach()?->toSimpleArray();
        }
    }

    public static function createForCoach(array $data, Objective $objective, $assignedTo, string $assignedType): self
    {
        $task = new self();
        
        $task->setTitle($data['title']);
        $task->setDescription($data['description']);
        $task->setStatus($data['status'] ?? 'pending');
        $task->setFrequency($data['frequency'] ?? null);
        $task->setRequiresProof($data['requires_proof'] ?? false);
        $task->setProofType($data['proof_type'] ?? null);
        $task->setAssignedType($assignedType);
        $task->setObjective($objective);
        $task->setCoach($objective->getCoach());
        
        if ($assignedTo) {
            switch ($assignedType) {
                case 'student':
                    $task->setStudent($assignedTo);
                    break;
                case 'parent':
                    $task->setParent($assignedTo);
                    break;
                case 'specialist':
                    $task->setSpecialist($assignedTo);
                    break;
                case 'coach':
                default:
                    $task->setCoach($assignedTo);
                    break;
            }
        }
        
        if (isset($data['due_date'])) {
            $task->setDueDate(new \DateTimeImmutable($data['due_date']));
        }
        
        return $task;
    }
}