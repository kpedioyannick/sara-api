<?php

namespace App\Entity;

use App\Enum\FamilyType;
use App\Repository\FamilyRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FamilyRepository::class)]
class Family
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $familyIdentifier = null;

    #[ORM\Column(type: 'string', enumType: FamilyType::class)]
    private FamilyType $type = FamilyType::FAMILY;

    #[ORM\Column]
    private ?bool $isActive = true;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\ManyToOne(inversedBy: 'families')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Coach $coach = null;

    #[ORM\OneToOne(targetEntity: ParentUser::class, mappedBy: 'family', cascade: ['persist', 'remove'])]
    private ?ParentUser $parent = null;

    #[ORM\OneToMany(targetEntity: Student::class, mappedBy: 'family', orphanRemoval: true)]
    private Collection $students;

    public function __construct()
    {
        $this->students = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->familyIdentifier = 'FAM_' . uniqid();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFamilyIdentifier(): ?string
    {
        return $this->familyIdentifier;
    }

    public function setFamilyIdentifier(string $familyIdentifier): static
    {
        $this->familyIdentifier = $familyIdentifier;
        return $this;
    }

    public function isActive(): ?bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;
        return $this;
    }

    public function getType(): FamilyType
    {
        return $this->type;
    }

    public function setType(FamilyType $type): static
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
            // Ne pas appeler setFamily pour éviter la récursion
        }
        return $this;
    }

    public function removeStudent(Student $student): static
    {
        if ($this->students->removeElement($student)) {
            if ($student->getFamily() === $this) {
                $student->setFamily(null);
            }
        }
        return $this;
    }

    public function toArray(): array
    {
        $parent = $this->getParent();
        return [
            'id' => $this->getId(),
            'familyIdentifier' => $this->getFamilyIdentifier(),
            'type' => $this->getType()->value,
            'isActive' => $this->isActive(),
            'parent' => $parent ? [
                'id' => $parent->getId(),
                'firstName' => $parent->getFirstName(),
                'lastName' => $parent->getLastName(),
                'email' => $parent->getEmail()
            ] : null,
            'students' => array_map(fn($student) => $student->toSimpleArray(), $this->getStudents()->toArray()),
            'studentsCount' => $this->getStudents()->count(),
            'createdAt' => $this->getCreatedAt()?->format('Y-m-d H:i:s'),
            'updatedAt' => $this->getUpdatedAt()?->format('Y-m-d H:i:s')
        ];
    }

    public function toPublicArray(): array
    {
        return [
            'id' => $this->getId(),
            'familyIdentifier' => $this->getFamilyIdentifier(),
            'isActive' => $this->isActive(),
            'studentsCount' => $this->getStudents()->count()
        ];
    }

    public static function create(array $data, Coach $coach): self
    {
        $family = new self();
        $family->setCoach($coach);
        $family->setIsActive($data['isActive'] ?? true);
        
        if (isset($data['familyIdentifier'])) {
            $family->setFamilyIdentifier($data['familyIdentifier']);
        }
        
        return $family;
    }

    /**
     * Retourne les données formatées pour le template de liste
     */
    public function toTemplateArray(?Coach $coach = null): array
    {
        $data = $this->toArray();
        
        // Ajout du nom du coach (utiliser le coach passé en paramètre ou celui de la famille)
        $coachToUse = $coach ?? $this->getCoach();
        if ($coachToUse) {
            $data['coachName'] = $coachToUse->getFirstName() . ' ' . $coachToUse->getLastName();
        } else {
            $data['coachName'] = null;
        }
        
        // Formatage de la date en français (DD/MM/YYYY)
        $data['createdAt'] = $this->getCreatedAt()?->format('d/m/Y');
        
        // Formatage de l'identifiant
        $data['identifier'] = $this->getFamilyIdentifier();
        
        // Formatage du parent
        $parent = $this->getParent();
        if ($parent) {
            $data['parent'] = $parent->toSimpleArray();
        } else {
            $data['parent'] = null;
        }
        
        // Formatage des étudiants
        $data['students'] = array_map(function ($student) {
            // Compter les objectifs
            $objectivesCount = $student->getObjectives()->count();
            
            // Compter les demandes
            $requestsCount = $student->getRequests()->count();
            
            return [
                'id' => $student->getId(),
                'firstName' => $student->getFirstName(),
                'lastName' => $student->getLastName(),
                'pseudo' => $student->getPseudo(),
                'class' => $student->getClass(),
                'schoolName' => $student->getSchoolName(),
                'points' => $student->getPoints(),
                'needTags' => $student->getNeedTags() ?? [],
                'isActive' => $student->isActive(),
                'objectivesCount' => $objectivesCount,
                'requestsCount' => $requestsCount,
                'specialists' => array_map(fn($s) => [
                    'id' => $s->getId(),
                    'firstName' => $s->getFirstName(),
                    'lastName' => $s->getLastName(),
                ], $student->getSpecialists()->toArray()),
            ];
        }, $this->getStudents()->toArray());
        
        return $data;
    }

    public static function createForCoach(array $data, Coach $coach): self
    {
        return self::create($data, $coach);
    }
}
