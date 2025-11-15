<?php

namespace App\Entity;

use App\Entity\Path\Path;
use App\Repository\ProofRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProofRepository::class)]
class Proof
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 50)]
    private ?string $type = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $filePath = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $content = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $fileUrl = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $fileName = null;

    #[ORM\Column(nullable: true)]
    private ?int $fileSize = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $mimeType = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $submittedAt = null; // Date de soumission de la preuve

    #[ORM\ManyToOne(inversedBy: 'proofs')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Task $task = null;

    #[ORM\ManyToOne(inversedBy: 'proofs')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Planning $planning = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $submittedBy = null;

    // Champs déplacés depuis Task pour WORKSHOP
    #[ORM\ManyToMany(targetEntity: Specialist::class)]
    #[ORM\JoinTable(name: 'proof_specialists')]
    private Collection $specialists; // spécialistes présents lors de la soumission

    #[ORM\ManyToMany(targetEntity: Activity::class)]
    #[ORM\JoinTable(name: 'proof_activities')]
    private Collection $activities; // activités liées à cette preuve

    #[ORM\ManyToMany(targetEntity: Path::class)]
    #[ORM\JoinTable(name: 'proof_paths')]
    private Collection $paths; // activités scolaires liées à cette preuve

    #[ORM\ManyToMany(targetEntity: Student::class)]
    #[ORM\JoinTable(name: 'proof_students')]
    private Collection $students; // étudiants concernés par cette preuve (WORKSHOP, ASSESSMENT, INDIVIDUAL_WORK*)

    // Champs pour INDIVIDUAL_WORK_REMOTE
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?Request $request = null; // demande liée à cette preuve

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->specialists = new ArrayCollection();
        $this->activities = new ArrayCollection();
        $this->paths = new ArrayCollection();
        $this->students = new ArrayCollection();
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

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function getFilePath(): ?string
    {
        return $this->filePath;
    }

    public function setFilePath(?string $filePath): static
    {
        $this->filePath = $filePath;
        return $this;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(?string $content): static
    {
        $this->content = $content;
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

    public function getSubmittedAt(): ?\DateTimeImmutable
    {
        return $this->submittedAt;
    }

    public function setSubmittedAt(?\DateTimeImmutable $submittedAt): static
    {
        $this->submittedAt = $submittedAt;
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

    public function getSubmittedBy(): ?User
    {
        return $this->submittedBy;
    }

    public function setSubmittedBy(?User $submittedBy): static
    {
        $this->submittedBy = $submittedBy;
        return $this;
    }

    public function getPlanning(): ?Planning
    {
        return $this->planning;
    }

    public function setPlanning(?Planning $planning): static
    {
        $this->planning = $planning;
        return $this;
    }

    public function getFileUrl(): ?string
    {
        return $this->fileUrl;
    }

    public function setFileUrl(?string $fileUrl): static
    {
        $this->fileUrl = $fileUrl;
        return $this;
    }

    public function getFileName(): ?string
    {
        return $this->fileName;
    }

    public function setFileName(?string $fileName): static
    {
        $this->fileName = $fileName;
        return $this;
    }

    public function getFileSize(): ?int
    {
        return $this->fileSize;
    }

    public function setFileSize(?int $fileSize): static
    {
        $this->fileSize = $fileSize;
        return $this;
    }

    public function getMimeType(): ?string
    {
        return $this->mimeType;
    }

    public function setMimeType(?string $mimeType): static
    {
        $this->mimeType = $mimeType;
        return $this;
    }

    /**
     * @return Collection<int, Specialist>
     */
    public function getSpecialists(): Collection
    {
        return $this->specialists;
    }

    public function addSpecialist(Specialist $specialist): static
    {
        if (!$this->specialists->contains($specialist)) {
            $this->specialists->add($specialist);
        }

        return $this;
    }

    public function removeSpecialist(Specialist $specialist): static
    {
        $this->specialists->removeElement($specialist);

        return $this;
    }

    /**
     * @return Collection<int, Activity>
     */
    public function getActivities(): Collection
    {
        return $this->activities;
    }

    public function addActivity(Activity $activity): static
    {
        if (!$this->activities->contains($activity)) {
            $this->activities->add($activity);
        }

        return $this;
    }

    public function removeActivity(Activity $activity): static
    {
        $this->activities->removeElement($activity);

        return $this;
    }

    /**
     * @return Collection<int, Path>
     */
    public function getPaths(): Collection
    {
        return $this->paths;
    }

    public function addPath(Path $path): static
    {
        if (!$this->paths->contains($path)) {
            $this->paths->add($path);
        }

        return $this;
    }

    public function removePath(Path $path): static
    {
        $this->paths->removeElement($path);

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
     * @return Collection<int, Student>
     */
    public function getStudents(): Collection
    {
        return $this->students;
    }

    public function addStudent(Student $student): static
    {
        if (!$this->students->contains($student)) {
            $this->students->add($student);
        }

        return $this;
    }

    public function removeStudent(Student $student): static
    {
        $this->students->removeElement($student);

        return $this;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->getId(),
            'title' => $this->getTitle(),
            'description' => $this->getDescription(),
            'type' => $this->getType(),
            'filePath' => $this->getFilePath(),
            'fileUrl' => $this->getFileUrl(),
            'fileName' => $this->getFileName(),
            'fileSize' => $this->getFileSize(),
            'mimeType' => $this->getMimeType(),
            'content' => $this->getContent(),
            'task' => $this->getTask()?->getId(),
            'planning' => $this->getPlanning()?->getId(),
            'submittedBy' => $this->getSubmittedBy()?->toSimpleArray(),
            'specialists' => $this->specialists->map(fn($s) => $s->getId())->toArray(),
            'activities' => $this->activities->map(fn($a) => $a->getId())->toArray(),
            'paths' => $this->paths->map(fn($p) => $p->getId())->toArray(),
            'students' => $this->students->map(fn($s) => $s->getId())->toArray(),
            'request' => $this->request?->getId(),
            'createdAt' => $this->getCreatedAt()?->format('Y-m-d H:i:s'),
            'updatedAt' => $this->getUpdatedAt()?->format('Y-m-d H:i:s'),
            'submittedAt' => $this->getSubmittedAt()?->format('Y-m-d H:i:s')
        ];
    }
}