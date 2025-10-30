<?php

namespace App\Entity;

use App\Repository\StudentRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: StudentRepository::class)]
class Student extends User
{
    #[ORM\Column(length: 255)]
    private ?string $pseudo = null;

    #[ORM\Column(length: 50)]
    private ?string $class = null;

    #[ORM\Column]
    private ?int $points = 0;

    #[ORM\ManyToOne(inversedBy: 'students')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Family $family = null;

    #[ORM\OneToMany(targetEntity: Objective::class, mappedBy: 'student')]
    private Collection $objectives;

    #[ORM\OneToMany(targetEntity: Request::class, mappedBy: 'student')]
    private Collection $requests;

    #[ORM\OneToMany(targetEntity: Planning::class, mappedBy: 'student')]
    private Collection $plannings;

    public function __construct()
    {
        parent::__construct();
        $this->objectives = new ArrayCollection();
        $this->requests = new ArrayCollection();
        $this->plannings = new ArrayCollection();
        $this->setRoles(['ROLE_STUDENT']);
    }

    public function getPseudo(): ?string
    {
        return $this->pseudo;
    }

    public function setPseudo(string $pseudo): static
    {
        $this->pseudo = $pseudo;
        return $this;
    }

    public function getClass(): ?string
    {
        return $this->class;
    }

    public function setClass(string $class): static
    {
        $this->class = $class;
        return $this;
    }

    public function getPoints(): ?int
    {
        return $this->points;
    }

    public function setPoints(int $points): static
    {
        $this->points = $points;
        return $this;
    }

    public function addPoints(int $points): static
    {
        $this->points += $points;
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

    /**
     * @return Collection<int, Objective>
     */
    public function getObjectives(): Collection
    {
        return $this->objectives;
    }

    public function addObjective(Objective $objective): static
    {
        if (!$this->objectives->contains($objective)) {
            $this->objectives->add($objective);
            $objective->setStudent($this);
        }
        return $this;
    }

    public function removeObjective(Objective $objective): static
    {
        if ($this->objectives->removeElement($objective)) {
            if ($objective->getStudent() === $this) {
                $objective->setStudent(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, Request>
     */
    public function getRequests(): Collection
    {
        return $this->requests;
    }

    public function addRequest(Request $request): static
    {
        if (!$this->requests->contains($request)) {
            $this->requests->add($request);
            $request->setStudent($this);
        }
        return $this;
    }

    public function removeRequest(Request $request): static
    {
        if ($this->requests->removeElement($request)) {
            if ($request->getStudent() === $this) {
                $request->setStudent(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, Planning>
     */
    public function getPlannings(): Collection
    {
        return $this->plannings;
    }

    public function addPlanning(Planning $planning): static
    {
        if (!$this->plannings->contains($planning)) {
            $this->plannings->add($planning);
            $planning->setStudent($this);
        }
        return $this;
    }

    public function removePlanning(Planning $planning): static
    {
        if ($this->plannings->removeElement($planning)) {
            if ($planning->getStudent() === $this) {
                $planning->setStudent(null);
            }
        }
        return $this;
    }

    /**
     * Convertir l'entité Student en tableau pour l'API
     */
    public function toArray(): array
    {
        return [
            'id' => $this->getId(),
            'email' => $this->getEmail(),
            'firstName' => $this->getFirstName(),
            'lastName' => $this->getLastName(),
            'isActive' => $this->isActive(),
            'createdAt' => $this->getCreatedAt()?->format('Y-m-d H:i:s'),
            'updatedAt' => $this->getUpdatedAt()?->format('Y-m-d H:i:s'),
            'userType' => $this->getUserType(),
            'roles' => $this->getRoles(),
            'pseudo' => $this->getPseudo(),
            'class' => $this->getClass(),
            'family' => $this->family ? $this->family->toArray() : null,
            'objectivesCount' => $this->getObjectives()->count(),
            'requestsCount' => $this->getRequests()->count(),
            'planningsCount' => $this->getPlannings()->count()
        ];
    }

    public function toSimpleArray(): array
    {
        return [
            'id' => $this->getId(),
            'firstName' => $this->getFirstName(),
            'lastName' => $this->getLastName(),
            'pseudo' => $this->getPseudo(),
            'email' => $this->getEmail(),
            'class' => $this->getClass(),
        ];
    }

    /**
     * Obtenir les données publiques de l'étudiant
     */
    public function toPublicArray(): array
    {
        return [
            'id' => $this->getId(),
            'firstName' => $this->getFirstName(),
            'lastName' => $this->getLastName(),
            'pseudo' => $this->getPseudo(),
            'class' => $this->getClass(),
            'points' => $this->getPoints(),
            'userType' => 'student'
        ];
    }

    /**
     * Obtenir les statistiques de l'étudiant
     */
    public function getStats(): array
    {
        return [
            'totalObjectives' => $this->getObjectives()->count(),
            'totalRequests' => $this->getRequests()->count(),
            'totalPlannings' => $this->getPlannings()->count(),
            'currentPoints' => $this->getPoints()
        ];
    }

    public static function create(array $data, Family $family): self
    {
        $student = new self();
        
        // Pour les élèves, générer l'email automatiquement à partir du pseudo
        $email = $data['email'] ?? $data['pseudo'] . '@sara.education';
        $student->setEmail($email);
        
        // Utiliser le pseudo comme firstName et lastName si pas fournis
        $student->setFirstName($data['firstName'] ?? $data['pseudo']);
        $student->setLastName($data['lastName'] ?? $data['pseudo']);
        $student->setPassword($data['password'] ?? 'defaultPassword123'); // Mot de passe par défaut
        $student->setPseudo($data['pseudo']);
        $student->setClass($data['class']);
        $student->setFamily($family);
        $student->setPoints($data['points'] ?? 0);
        
        return $student;
    }

    public static function createForCoach(array $data, Family $family): self
    {
        return self::create($data, $family);
    }
}
