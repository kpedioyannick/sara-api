<?php

namespace App\Entity\Path;

use App\Repository\ChapterRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ChapterRepository::class)]
#[ORM\Table(name: 'path_chapter')]
class Chapter
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $content = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\ManyToOne(inversedBy: 'chapters')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Subject $subject = null;

    #[ORM\OneToMany(mappedBy: 'chapter', targetEntity: SubChapter::class, cascade: ['persist', 'remove'], fetch: 'EXTRA_LAZY')]
    private Collection $subChapters;

    #[ORM\OneToMany(mappedBy: 'chapter', targetEntity: Path::class, cascade: ['persist', 'remove'], fetch: 'EXTRA_LAZY')]
    private Collection $paths;

    public function __construct()
    {
        $this->subChapters = new ArrayCollection();
        $this->paths = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
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

    public function getSubject(): ?Subject
    {
        return $this->subject;
    }

    public function setSubject(?Subject $subject): static
    {
        $this->subject = $subject;
        return $this;
    }

    /**
     * @return Collection<int, SubChapter>
     */
    public function getSubChapters(): Collection
    {
        return $this->subChapters;
    }

    public function addSubChapter(SubChapter $subChapter): static
    {
        if (!$this->subChapters->contains($subChapter)) {
            $this->subChapters->add($subChapter);
            $subChapter->setChapter($this);
        }
        return $this;
    }

    public function removeSubChapter(SubChapter $subChapter): static
    {
        if ($this->subChapters->removeElement($subChapter)) {
            if ($subChapter->getChapter() === $this) {
                $subChapter->setChapter(null);
            }
        }
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
            $path->setChapter($this);
        }
        return $this;
    }

    public function removePath(Path $path): static
    {
        if ($this->paths->removeElement($path)) {
            if ($path->getChapter() === $this) {
                $path->setChapter(null);
            }
        }
        return $this;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->getId(),
            'name' => $this->getName(),
            'content' => $this->getContent(),
            'subject' => $this->getSubject()?->toArray(),
            'subChaptersCount' => $this->getSubChapters()->count(),
            'pathsCount' => $this->getPaths()->count(),
            'createdAt' => $this->getCreatedAt()?->format('Y-m-d H:i:s'),
            'updatedAt' => $this->getUpdatedAt()?->format('Y-m-d H:i:s')
        ];
    }
}

