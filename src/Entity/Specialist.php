<?php

namespace App\Entity;

use App\Repository\SpecialistRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SpecialistRepository::class)]
class Specialist extends User
{
    #[ORM\Column(type: 'json')]
    private array $specializations = [];

    #[ORM\OneToMany(targetEntity: Request::class, mappedBy: 'specialist')]
    private Collection $requests;

    #[ORM\OneToMany(targetEntity: Availability::class, mappedBy: 'specialist')]
    private Collection $availabilities;

    #[ORM\ManyToMany(targetEntity: Student::class, inversedBy: 'specialists')]
    private Collection $students;

    public function __construct()
    {
        parent::__construct();
        $this->requests = new ArrayCollection();
        $this->availabilities = new ArrayCollection();
        $this->students = new ArrayCollection();
        $this->setRoles(['ROLE_SPECIALIST']);
    }

    public function getSpecializations(): array
    {
        return $this->specializations;
    }

    public function setSpecializations(array $specializations): static
    {
        $this->specializations = $specializations;
        return $this;
    }

    public function addSpecialization(string $specialization): static
    {
        if (!in_array($specialization, $this->specializations)) {
            $this->specializations[] = $specialization;
        }
        return $this;
    }

    public function removeSpecialization(string $specialization): static
    {
        $key = array_search($specialization, $this->specializations);
        if ($key !== false) {
            unset($this->specializations[$key]);
            $this->specializations = array_values($this->specializations);
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
            $request->setSpecialist($this);
        }
        return $this;
    }

    public function removeRequest(Request $request): static
    {
        if ($this->requests->removeElement($request)) {
            if ($request->getSpecialist() === $this) {
                $request->setSpecialist(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, Availability>
     */
    public function getAvailabilities(): Collection
    {
        return $this->availabilities;
    }

    public function addAvailability(Availability $availability): static
    {
        if (!$this->availabilities->contains($availability)) {
            $this->availabilities->add($availability);
            $availability->setSpecialist($this);
        }
        return $this;
    }

    public function removeAvailability(Availability $availability): static
    {
        if ($this->availabilities->removeElement($availability)) {
            if ($availability->getSpecialist() === $this) {
                $availability->setSpecialist(null);
            }
        }
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

    /**
     * Convertir l'entité Specialist en tableau pour l'API
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
            'specializations' => $this->getSpecializations(),
            'requestsCount' => $this->getRequests()->count(),
            'availabilitiesCount' => $this->getAvailabilities()->count(),
            'studentsCount' => $this->getStudents()->count()
        ];
    }

    public function toSimpleArray(): array
    {
        return [
            'id' => $this->getId(),
            'firstName' => $this->getFirstName(),
            'lastName' => $this->getLastName(),
            'email' => $this->getEmail()
        ];
    }

    /**
     * Obtenir les données publiques du spécialiste
     */
    public function toPublicArray(): array
    {
        return [
            'id' => $this->getId(),
            'firstName' => $this->getFirstName(),
            'lastName' => $this->getLastName(),
            'specializations' => $this->getSpecializations(),
            'userType' => 'specialist'
        ];
    }

    /**
     * Obtenir les statistiques du spécialiste
     */
    public function getStats(): array
    {
        return [
            'totalRequests' => $this->getRequests()->count(),
            'totalAvailabilities' => $this->getAvailabilities()->count(),
            'totalStudents' => $this->getStudents()->count(),
            'specializations' => $this->getSpecializations()
        ];
    }

    public static function create(array $data): self
    {
        $specialist = new self();
        $specialist->setEmail($data['email']);
        $specialist->setFirstName($data['firstName']);
        $specialist->setLastName($data['lastName']);
        $specialist->setPassword($data['password']); // Devrait être hashé
        $specialist->setSpecializations($data['specializations'] ?? []);
        $specialist->setIsActive($data['isActive'] ?? true);
        
        return $specialist;
    }

    public static function createForCoach(array $data): self
    {
        return self::create($data);
    }
}
