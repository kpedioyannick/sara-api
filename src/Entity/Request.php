<?php

namespace App\Entity;

use App\Repository\RequestRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RequestRepository::class)]
class Request
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
    private ?string $status = 'pending';

    #[ORM\Column(length: 100)]
    private ?string $type = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $priority = 'medium';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $response = null;

    #[ORM\ManyToOne]
    private ?User $assignedTo = null;

    #[ORM\ManyToOne]
    private ?User $creator = null;

    #[ORM\ManyToOne]
    private ?User $recipient = null;

    #[ORM\ManyToOne]
    private ?Family $family = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\ManyToOne(inversedBy: 'requests')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Coach $coach = null;

    #[ORM\ManyToOne(inversedBy: 'requests')]
    private ?ParentUser $parent = null;

    #[ORM\ManyToOne(inversedBy: 'requests')]
    private ?Student $student = null;

    #[ORM\ManyToOne(inversedBy: 'requests')]
    private ?Specialist $specialist = null;

    #[ORM\OneToMany(targetEntity: Message::class, mappedBy: 'request', orphanRemoval: true)]
    private Collection $messages;

    public function __construct()
    {
        $this->messages = new ArrayCollection();
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

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
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

    public function getCoach(): ?Coach
    {
        return $this->coach;
    }

    public function setCoach(?Coach $coach): static
    {
        $this->coach = $coach;
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

    public function getStudent(): ?Student
    {
        return $this->student;
    }

    public function setStudent(?Student $student): static
    {
        $this->student = $student;
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

    /**
     * @return Collection<int, Message>
     */
    public function getMessages(): Collection
    {
        return $this->messages;
    }

    public function addMessage(Message $message): static
    {
        if (!$this->messages->contains($message)) {
            $this->messages->add($message);
            $message->setRequest($this);
        }
        return $this;
    }

    public function removeMessage(Message $message): static
    {
        if ($this->messages->removeElement($message)) {
            if ($message->getRequest() === $this) {
                $message->setRequest(null);
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
            'type' => $this->getType(),
            'priority' => $this->getPriority(),
            'response' => $this->getResponse(),
            'assignedTo' => $this->getAssignedTo()?->toArray(),
            'creator' => $this->getCreator()?->toArray(),
            'recipient' => $this->getRecipient()?->toArray(),
            'family' => $this->getFamily()?->toArray(),
            'student' => $this->getStudent()?->toArray(),
            'coach' => $this->getCoach()?->toArray(),
            'messages' => array_map(fn($message) => $message->toArray(), $this->getMessages()->toArray()),
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
            'status' => $this->getStatus(),
            'type' => $this->getType(),
            'priority' => $this->getPriority(),
            'createdAt' => $this->getCreatedAt()?->format('Y-m-d H:i:s')
        ];
    }

    public function getPriority(): string
    {
        return $this->priority ?? 'medium';
    }

    public function setPriority(string $priority): static
    {
        $this->priority = $priority;
        return $this;
    }

    public function getResponse(): ?string
    {
        return $this->response ?? null;
    }

    public function setResponse(?string $response): static
    {
        $this->response = $response;
        return $this;
    }

    public function getAssignedTo(): ?User
    {
        return $this->assignedTo ?? null;
    }

    public function setAssignedTo(?User $assignedTo): static
    {
        $this->assignedTo = $assignedTo;
        return $this;
    }

    public function getCreator(): ?User
    {
        return $this->creator ?? null;
    }

    public function setCreator(?User $creator): static
    {
        $this->creator = $creator;
        return $this;
    }

    public function getRecipient(): ?User
    {
        return $this->recipient ?? null;
    }

    public function setRecipient(?User $recipient): static
    {
        $this->recipient = $recipient;
        return $this;
    }

    public function getFamily(): ?Family
    {
        return $this->family ?? null;
    }

    public function setFamily(?Family $family): static
    {
        $this->family = $family;
        return $this;
    }

    public static function create(array $data, Coach $coach, ?User $creator = null, ?User $recipient = null): self
    {
        $request = new self();
        $request->setTitle($data['title']);
        $request->setDescription($data['description']);
        $request->setStatus($data['status'] ?? 'pending');
        $request->setType($data['type'] ?? 'general');
        $request->setPriority($data['priority'] ?? 'medium');
        $request->setCoach($coach);
        
        if ($creator) {
            $request->setCreator($creator);
        }
        
        if ($recipient) {
            $request->setRecipient($recipient);
        }
        
        if (isset($data['family_id'])) {
            // La famille sera définie par le contrôleur
        }
        
        if (isset($data['student_id'])) {
            // L'étudiant sera défini par le contrôleur
        }
        
        return $request;
    }

    public static function createForCoach(array $data, Coach $coach, ?User $creator = null, ?User $recipient = null): self
    {
        return self::create($data, $coach, $creator, $recipient);
    }
}
