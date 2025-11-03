<?php

namespace App\Entity;

use App\Repository\CommentRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CommentRepository::class)]
class Comment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'text')]
    private ?string $content = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\ManyToOne(inversedBy: 'comments')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Objective $objective = null;

    #[ORM\ManyToOne]
    private ?Coach $coach = null;

    #[ORM\ManyToOne]
    private ?ParentUser $parent = null;

    #[ORM\ManyToOne]
    private ?Student $student = null;

    #[ORM\ManyToOne]
    private ?Specialist $specialist = null;

    #[ORM\Column(length: 20, nullable: false)]
    private ?string $authorType = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(string $content): static
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

    public function getObjective(): ?Objective
    {
        return $this->objective;
    }

    public function setObjective(?Objective $objective): static
    {
        $this->objective = $objective;
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

    public function getAuthorType(): ?string
    {
        return $this->authorType;
    }

    public function setAuthorType(string $authorType): static
    {
        $this->authorType = $authorType;
        return $this;
    }

    private function getAuthor(): ?object
    {
        switch ($this->getAuthorType()) {
            case 'coach':
                return $this->getCoach();
            case 'parent':
                return $this->getParent();
            case 'student':
                return $this->getStudent();
            case 'specialist':
                return $this->getSpecialist();
            default:
                return null;
        }
    }

    private function getAuthorSimpleArray(): ?array
    {
        switch ($this->getAuthorType()) {
            case 'coach':
                return $this->getCoach()?->toSimpleArray();
            case 'parent':
                return $this->getParent()?->toSimpleArray();
            case 'student':
                return $this->getStudent()?->toSimpleArray();
            case 'specialist':
                return $this->getSpecialist()?->toSimpleArray();
            default:
                return null;
        }
    }

    public function toArray(): array
    {
        return [
            'id' => $this->getId(),
            'content' => $this->getContent(),
            'author' => $this->getAuthorSimpleArray(),
            'authorType' => $this->getAuthorType(),
            'objectiveId' => $this->getObjective()?->getId(),
            'createdAt' => $this->getCreatedAt()?->format('Y-m-d H:i:s'),
            'updatedAt' => $this->getUpdatedAt()?->format('Y-m-d H:i:s')
        ];
    }

    public static function createForCoach(array $data, $author, ?Objective $objective = null): self
    {
        $comment = new self();
        $comment->setContent($data['content']);
        $comment->setObjective($objective);

        if ($author instanceof Coach) {
            $comment->setCoach($author);
            $comment->setAuthorType('coach');
        } elseif ($author instanceof ParentUser) {
            $comment->setParent($author);
            $comment->setAuthorType('parent');
        } elseif ($author instanceof Student) {
            $comment->setStudent($author);
            $comment->setAuthorType('student');
        } elseif ($author instanceof Specialist) {
            $comment->setSpecialist($author);
            $comment->setAuthorType('specialist');
        }

        return $comment;
    }
}

