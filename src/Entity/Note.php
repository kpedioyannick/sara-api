<?php

namespace App\Entity;

use App\Enum\NoteType;
use App\Repository\NoteRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: NoteRepository::class)]
class Note
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 50, enumType: NoteType::class, nullable: false)]
    private NoteType $type;

    #[ORM\Column(type: 'text')]
    private ?string $text = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\ManyToOne(inversedBy: 'notes')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Student $student = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $createdBy = null;

    #[ORM\OneToMany(mappedBy: 'note', targetEntity: NoteImage::class, cascade: ['persist', 'remove'], fetch: 'EXTRA_LAZY')]
    private Collection $images;

    public function __construct()
    {
        $this->images = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->type = NoteType::DRAFT;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): NoteType
    {
        return $this->type;
    }

    public function setType(NoteType $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function getText(): ?string
    {
        return $this->text;
    }

    public function setText(string $text): static
    {
        $this->text = $text;
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
     * @return Collection<int, NoteImage>
     */
    public function getImages(): Collection
    {
        return $this->images;
    }

    public function addImage(NoteImage $image): static
    {
        if (!$this->images->contains($image)) {
            $this->images->add($image);
            $image->setNote($this);
        }

        return $this;
    }

    public function removeImage(NoteImage $image): static
    {
        if ($this->images->removeElement($image)) {
            // set the owning side to null (unless already changed)
            if ($image->getNote() === $this) {
                $image->setNote(null);
            }
        }

        return $this;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'typeLabel' => $this->type->getLabel(),
            'typeColor' => $this->type->getColor(),
            'text' => $this->text,
            'createdAt' => $this->createdAt?->format('Y-m-d H:i:s'),
            'updatedAt' => $this->updatedAt?->format('Y-m-d H:i:s'),
            'studentId' => $this->student?->getId(),
            'createdById' => $this->createdBy?->getId(),
            'createdBy' => $this->createdBy ? [
                'id' => $this->createdBy->getId(),
                'firstName' => $this->createdBy->getFirstName(),
                'lastName' => $this->createdBy->getLastName(),
            ] : null,
            'images' => array_map(fn($image) => $image->toArray(), $this->images->toArray()),
        ];
    }
}
