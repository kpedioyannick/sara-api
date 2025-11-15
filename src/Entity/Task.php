<?php

namespace App\Entity;

use App\Entity\Path\Path;
use App\Enum\TaskType;
use App\Repository\TaskRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TaskRepository::class)]
class Task
{
    // Constantes pour les statuts
    public const STATUS_PENDING = 'pending';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED = 'completed';

    // Constantes pour les temporalités/fréquences (simplifiées)
    public const FREQUENCY_NONE = 'none';
    public const FREQUENCY_DAILY = 'daily';
    public const FREQUENCY_WEEKLY = 'weekly';
    public const FREQUENCY_MONTHLY = 'monthly';

    public const FREQUENCIES = [
        self::FREQUENCY_NONE => 'Aucune',
        self::FREQUENCY_DAILY => 'Quotidienne',
        self::FREQUENCY_WEEKLY => 'Hebdomadaire',
        self::FREQUENCY_MONTHLY => 'Mensuelle',
    ];

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

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $startDate = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $repeatDaysOfWeek = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Coach $coach = null;

    #[ORM\ManyToOne(inversedBy: 'tasks')]
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

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?Activity $activity = null;

    #[ORM\Column(type: 'string', length: 50, enumType: TaskType::class, nullable: false, options: ['default' => 'task'])]
    private TaskType $type = TaskType::TASK;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?Path $path = null;

    // Champs pour WORKSHOP
    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $location = null; // lieu

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?Family $family = null; // pour WORKSHOP

    // Champs pour ASSESSMENT
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $assessmentNotes = null; // notes

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->proofs = new ArrayCollection();
        $this->requiresProof = true;
        $this->type = TaskType::TASK;
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
        return $this->requiresProof ?? true;
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

    public function getStartDate(): ?\DateTimeImmutable
    {
        return $this->startDate;
    }

    public function setStartDate(?\DateTimeImmutable $startDate): static
    {
        $this->startDate = $startDate;
        return $this;
    }

    public function getRepeatDaysOfWeek(): ?array
    {
        return $this->repeatDaysOfWeek;
    }

    public function setRepeatDaysOfWeek(?array $repeatDaysOfWeek): static
    {
        $this->repeatDaysOfWeek = $repeatDaysOfWeek;
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

    public function getActivity(): ?Activity
    {
        return $this->activity;
    }

    public function setActivity(?Activity $activity): static
    {
        $this->activity = $activity;
        return $this;
    }

    public function getType(): TaskType
    {
        // S'assurer qu'on retourne toujours une valeur valide
        if ($this->type === null) {
            $this->type = TaskType::TASK;
        }
        return $this->type;
    }

    public function setType(?TaskType $type): static
    {
        $this->type = $type ?? TaskType::TASK;
        return $this;
    }

    public function getPath(): ?Path
    {
        return $this->path;
    }

    public function setPath(?Path $path): static
    {
        $this->path = $path;
        return $this;
    }

    // Getters et setters pour WORKSHOP
    public function getLocation(): ?string
    {
        return $this->location;
    }

    public function setLocation(?string $location): static
    {
        $this->location = $location;
        return $this;
    }

    public function getFamily(): ?Family
    {
        return $this->family;
    }

    public function setFamily(?Family $family): static
    {
        $this->family = $family;
        return $this;
    }

    // Getters et setters pour ASSESSMENT
    public function getAssessmentNotes(): ?string
    {
        return $this->assessmentNotes;
    }

    public function setAssessmentNotes(?string $assessmentNotes): static
    {
        $this->assessmentNotes = $assessmentNotes;
        return $this;
    }


    public function toArray(): array
    {
        return [
            'id' => $this->getId(),
            'title' => $this->getTitle(),
            'description' => $this->getDescription(),
            'status' => $this->getStatus(),
            'type' => $this->getType()->value,
            'frequency' => $this->getFrequency(),
            'requiresProof' => $this->isRequiresProof(),
            'proofType' => $this->getProofType(),
            'startDate' => $this->getStartDate()?->format('Y-m-d H:i:s'),
            'dueDate' => $this->getDueDate()?->format('Y-m-d H:i:s'),
            'repeatDaysOfWeek' => $this->getRepeatDaysOfWeek(),
            'assignedType' => $this->getAssignedType(),
            'assignedTo' => $this->getAssignedToSimpleArray(),
            'objective' => $this->getObjective()?->toArray(),
            'coach' => $this->getCoach()?->toSimpleArray(),
            'proofsCount' => $this->getProofs()->count(),
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
            'startDate' => $this->getStartDate()?->format('Y-m-d H:i:s'),
            'dueDate' => $this->getDueDate()?->format('Y-m-d H:i:s'),
            'repeatDaysOfWeek' => $this->getRepeatDaysOfWeek(),
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

    /**
     * Mappe le statut de la tâche pour le template
     */
    public function getMappedStatus(): string
    {
        return match ($this->getStatus()) {
            'completed', 'done', 'finished' => 'completed',
            'in_progress', 'ongoing' => 'in_progress',
            default => 'pending',
        };
    }

    /**
     * Retourne les données formatées pour le template (liste des tâches)
     */
    public function toTemplateArray(): array
    {
        $assignedTo = null;
        $assignedToName = null;
        
        switch ($this->getAssignedType()) {
            case 'student':
                if ($this->getStudent()) {
                    $assignedTo = [
                        'id' => $this->getStudent()->getId(),
                        'firstName' => $this->getStudent()->getFirstName(),
                        'lastName' => $this->getStudent()->getLastName(),
                        'pseudo' => $this->getStudent()->getPseudo(),
                    ];
                    $assignedToName = $this->getStudent()->getFirstName() . ' ' . $this->getStudent()->getLastName();
                }
                break;
            case 'parent':
                if ($this->getParent()) {
                    $assignedTo = [
                        'id' => $this->getParent()->getId(),
                        'firstName' => $this->getParent()->getFirstName(),
                        'lastName' => $this->getParent()->getLastName(),
                    ];
                    $assignedToName = $this->getParent()->getFirstName() . ' ' . $this->getParent()->getLastName();
                }
                break;
            case 'specialist':
                if ($this->getSpecialist()) {
                    $assignedTo = [
                        'id' => $this->getSpecialist()->getId(),
                        'firstName' => $this->getSpecialist()->getFirstName(),
                        'lastName' => $this->getSpecialist()->getLastName(),
                    ];
                    $assignedToName = $this->getSpecialist()->getFirstName() . ' ' . $this->getSpecialist()->getLastName();
                }
                break;
            case 'coach':
            default:
                if ($this->getCoach()) {
                    $assignedTo = [
                        'id' => $this->getCoach()->getId(),
                        'firstName' => $this->getCoach()->getFirstName(),
                        'lastName' => $this->getCoach()->getLastName(),
                    ];
                    $assignedToName = $this->getCoach()->getFirstName() . ' ' . $this->getCoach()->getLastName();
                }
                break;
        }
        
        return [
            'id' => $this->getId(),
            'title' => $this->getTitle(),
            'description' => $this->getDescription(),
            'status' => $this->getStatus(),
            'mappedStatus' => $this->getMappedStatus(),
            'frequency' => $this->getFrequency(),
            'requiresProof' => $this->isRequiresProof() ?? false,
            'proofType' => $this->getProofType(),
            'assignedType' => $this->getAssignedType(),
            'assignedTo' => $assignedTo,
            'assignedToName' => $assignedToName,
            'student' => $this->getStudent() ? [
                'id' => $this->getStudent()->getId(),
                'firstName' => $this->getStudent()->getFirstName(),
                'lastName' => $this->getStudent()->getLastName(),
                'pseudo' => $this->getStudent()->getPseudo(),
            ] : null,
            'parent' => $this->getParent() ? [
                'id' => $this->getParent()->getId(),
                'firstName' => $this->getParent()->getFirstName(),
                'lastName' => $this->getParent()->getLastName(),
            ] : null,
            'specialist' => $this->getSpecialist() ? [
                'id' => $this->getSpecialist()->getId(),
                'firstName' => $this->getSpecialist()->getFirstName(),
                'lastName' => $this->getSpecialist()->getLastName(),
            ] : null,
            'startDate' => $this->getStartDate()?->format('Y-m-d H:i:s'),
            'dueDate' => $this->getDueDate()?->format('Y-m-d H:i:s'),
            'repeatDaysOfWeek' => $this->getRepeatDaysOfWeek(),
            'createdAt' => $this->getCreatedAt()?->format('Y-m-d H:i:s'),
            'activity' => $this->getActivity() ? [
                'id' => $this->getActivity()->getId(),
                'title' => $this->getActivity()->getTitle(),
            ] : null,
            'type' => $this->getType()->value,
            'path' => $this->getPath() ? [
                'id' => $this->getPath()->getId(),
                'title' => $this->getPath()->getTitle(),
            ] : null,
        ];
    }

    /**
     * Convertit la tâche en format compatible avec le planning
     * Retourne un tableau d'événements (un par jour si la tâche s'étend sur plusieurs jours)
     */
    public function toPlanningEvents(\DateTimeImmutable $weekStart, \DateTimeImmutable $weekEnd): array
    {
        $events = [];
        $objective = $this->getObjective();
        $student = $objective?->getStudent();
        
        if (!$student) {
            return [];
        }

        // Utiliser startDate/dueDate de la tâche si disponibles, sinon createdAt/dueDate
        $taskStartDate = $this->getStartDate() ?? $this->getCreatedAt();
        $taskDueDate = $this->getDueDate() ?? $taskStartDate->modify('+1 day');
        $frequency = $this->getFrequency();
        $repeatDaysOfWeek = $this->getRepeatDaysOfWeek() ?? [];

        // Si la tâche a une fréquence, générer les événements selon la fréquence
        if ($frequency && $frequency !== self::FREQUENCY_NONE) {
            return $this->generateRecurringEventsForPlanning($weekStart, $weekEnd, $taskStartDate, $taskDueDate, $frequency, $repeatDaysOfWeek, $student);
        }

        // Sinon, créer un événement simple pour la période
        $currentDate = $taskStartDate > $weekStart ? $taskStartDate : $weekStart;
        $endDate = $taskDueDate < $weekEnd ? $taskDueDate : $weekEnd;

        // Créer un événement pour chaque jour où la tâche est active
        while ($currentDate <= $endDate && $currentDate <= $weekEnd) {
            if ($currentDate >= $weekStart) {
                $dayStart = $currentDate->setTime(9, 0, 0); // 9h du matin par défaut
                $dayEnd = $currentDate->setTime(17, 0, 0); // 17h par défaut

                // Si c'est le premier jour, utiliser l'heure de startDate si disponible
                if ($currentDate->format('Y-m-d') === $taskStartDate->format('Y-m-d')) {
                    $dayStart = $taskStartDate;
                }

                // Si c'est le dernier jour, utiliser l'heure de dueDate si disponible
                if ($currentDate->format('Y-m-d') === $taskDueDate->format('Y-m-d')) {
                    $dayEnd = $taskDueDate;
                }

                $events[] = [
                    'id' => 'task_' . $this->getId() . '_' . $currentDate->format('Y-m-d'),
                    'title' => $this->getTitle(),
                    'description' => $this->getDescription(),
                    'startDate' => $dayStart->format('Y-m-d H:i:s'),
                    'endDate' => $dayEnd->format('Y-m-d H:i:s'),
                    'userId' => $student->getId(),
                    'userName' => $student->getFirstName() . ' ' . $student->getLastName(),
                    'type' => 'task',
                    'typeLabel' => 'Tâche d\'objectif',
                    'status' => $this->getMappedStatus(),
                    'backgroundColor' => '#10B981', // Vert pour les tâches
                    'sourceType' => 'task',
                    'sourceId' => $this->getId(),
                    'objectiveId' => $objective->getId(),
                    'clickUrl' => '/admin/objectives/' . $objective->getId(),
                ];
            }

            $currentDate = $currentDate->modify('+1 day');
        }

        return $events;
    }

    /**
     * Génère des événements récurrents pour le planning selon la fréquence
     */
    private function generateRecurringEventsForPlanning(
        \DateTimeImmutable $weekStart,
        \DateTimeImmutable $weekEnd,
        \DateTimeImmutable $taskStartDate,
        \DateTimeImmutable $taskDueDate,
        string $frequency,
        array $repeatDaysOfWeek,
        $student
    ): array {
        $events = [];
        $objective = $this->getObjective();
        $currentDate = $taskStartDate > $weekStart ? $taskStartDate : $weekStart;
        $endDate = $taskDueDate < $weekEnd ? $taskDueDate : $weekEnd;

        while ($currentDate <= $endDate && $currentDate <= $weekEnd) {
            // Pour les répétitions hebdomadaires, vérifier si le jour correspond
            if ($frequency === self::FREQUENCY_WEEKLY && !empty($repeatDaysOfWeek)) {
                $dayOfWeek = (int)$currentDate->format('w'); // 0 = dimanche, 1 = lundi, etc.
                if (!in_array($dayOfWeek, $repeatDaysOfWeek)) {
                    // Passer au jour suivant (pas à la semaine suivante)
                    $currentDate = $currentDate->modify('+1 day')->setTime(0, 0, 0);
                    continue;
                }
            }

            if ($currentDate >= $weekStart) {
                // Utiliser les heures de startDate pour chaque occurrence
                $dayStart = $currentDate->setTime(
                    (int)$taskStartDate->format('H'),
                    (int)$taskStartDate->format('i'),
                    (int)$taskStartDate->format('s')
                );
                
                // Calculer la date de fin selon la fréquence
                if ($frequency === self::FREQUENCY_WEEKLY) {
                    // Pour les répétitions hebdomadaires, chaque occurrence est d'une journée
                    if ($currentDate->format('Y-m-d') === $taskDueDate->format('Y-m-d')) {
                        $dayEnd = $taskDueDate;
                    } else {
                        // Utiliser l'heure de dueDate si disponible, sinon fin de journée
                        $dayEnd = $currentDate->setTime(
                            (int)$taskDueDate->format('H'),
                            (int)$taskDueDate->format('i'),
                            (int)$taskDueDate->format('s')
                        );
                        // Si l'heure de dueDate est avant l'heure de startDate, utiliser fin de journée
                        if ($dayEnd <= $dayStart) {
                            $dayEnd = $currentDate->setTime(23, 59, 59);
                        }
                    }
                } else {
                    // Pour daily et monthly, calculer selon la fréquence
                    $dayEnd = match ($frequency) {
                        self::FREQUENCY_DAILY => $dayStart->modify('+1 day')->setTime(23, 59, 59),
                        self::FREQUENCY_MONTHLY => $dayStart->modify('+1 month')->setTime(23, 59, 59),
                        default => $dayStart->modify('+1 day')->setTime(23, 59, 59),
                    };
                    
                    // Si c'est le dernier jour, utiliser l'heure de dueDate
                    if ($currentDate->format('Y-m-d') === $taskDueDate->format('Y-m-d')) {
                        $dayEnd = $taskDueDate;
                    }
                }

                $events[] = [
                    'id' => 'task_' . $this->getId() . '_' . $currentDate->format('Y-m-d'),
                    'title' => $this->getTitle(),
                    'description' => $this->getDescription(),
                    'startDate' => $dayStart->format('Y-m-d H:i:s'),
                    'endDate' => $dayEnd->format('Y-m-d H:i:s'),
                    'userId' => $student->getId(),
                    'userName' => $student->getFirstName() . ' ' . $student->getLastName(),
                    'type' => 'task',
                    'typeLabel' => 'Tâche d\'objectif',
                    'status' => $this->getMappedStatus(),
                    'backgroundColor' => '#10B981',
                    'sourceType' => 'task',
                    'sourceId' => $this->getId(),
                    'objectiveId' => $objective->getId(),
                    'clickUrl' => '/admin/objectives/' . $objective->getId(),
                ];
            }

            // Passer à la prochaine occurrence
            $currentDate = $this->getNextDateForFrequency($currentDate, $frequency);
        }

        return $events;
    }

    /**
     * Calcule la prochaine date selon la fréquence
     */
    private function getNextDateForFrequency(\DateTimeImmutable $currentDate, string $frequency): \DateTimeImmutable
    {
        return match ($frequency) {
            self::FREQUENCY_DAILY => $currentDate->modify('+1 day')->setTime(0, 0, 0),
            self::FREQUENCY_WEEKLY => $currentDate->modify('+1 week')->setTime(0, 0, 0),
            self::FREQUENCY_MONTHLY => $currentDate->modify('+1 month')->setTime(0, 0, 0),
            default => $currentDate->modify('+1 day')->setTime(0, 0, 0),
        };
    }

    public static function createForCoach(array $data, Objective $objective, $assignedTo, string $assignedType): self
    {
        $task = new self();
        
        $task->setTitle($data['title']);
        $task->setDescription($data['description']);
        $task->setStatus($data['status'] ?? 'pending');
        $task->setFrequency($data['frequency'] ?? null);
        $task->setRequiresProof(true); // Par défaut, toutes les tâches nécessitent des preuves
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
        
        // Gérer la date de création
        if (isset($data['created_at']) && $data['created_at']) {
            try {
                $task->setCreatedAt(new \DateTimeImmutable($data['created_at']));
            } catch (\Exception $e) {
                // En cas d'erreur, utiliser la date actuelle (définie dans le constructeur)
            }
        }
        // Si createdAt n'a pas été défini, il sera défini automatiquement dans le constructeur
        
        // Gérer la date de fin
        if (isset($data['due_date'])) {
            $task->setDueDate(new \DateTimeImmutable($data['due_date']));
        } else {
            // Date limite par défaut : date de création + 3 semaines
            $createdAt = $task->getCreatedAt() ?? new \DateTimeImmutable();
            $dueDate = $createdAt->modify('+3 weeks');
            $task->setDueDate($dueDate);
        }
        
        return $task;
    }
}