<?php

namespace App\Entity;

use App\Repository\CoachRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CoachRepository::class)]
class Coach extends User
{
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $specialization = null;

    #[ORM\OneToMany(targetEntity: Family::class, mappedBy: 'coach')]
    #[ORM\JoinColumn(nullable: true)]
    private Collection $families;

    #[ORM\OneToMany(targetEntity: Objective::class, mappedBy: 'coach')]
    #[ORM\JoinColumn(nullable: true)]
    private Collection $objectives;

    #[ORM\OneToMany(targetEntity: Request::class, mappedBy: 'coach')]
    #[ORM\JoinColumn(nullable: true)]
    private Collection $requests;

    #[ORM\OneToMany(targetEntity: Availability::class, mappedBy: 'coach')]
    private Collection $availabilities;

    public function __construct()
    {
        parent::__construct();
        $this->families = new ArrayCollection();
        $this->objectives = new ArrayCollection();
        $this->requests = new ArrayCollection();
        $this->availabilities = new ArrayCollection();
        $this->setRoles(['ROLE_COACH']);
    }

    public function getSpecialization(): ?string
    {
        return $this->specialization;
    }

    public function setSpecialization(?string $specialization): static
    {
        $this->specialization = $specialization;
        return $this;
    }

    /**
     * @return Collection<int, Family>
     */
    public function getFamilies(): Collection
    {
        return $this->families;
    }

    public function addFamily(Family $family): static
    {
        if (!$this->families->contains($family)) {
            $this->families->add($family);
            $family->setCoach($this);
        }
        return $this;
    }

    public function removeFamily(Family $family): static
    {
        if ($this->families->removeElement($family)) {
            if ($family->getCoach() === $this) {
                $family->setCoach(null);
            }
        }
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
            $objective->setCoach($this);
        }
        return $this;
    }

    public function removeObjective(Objective $objective): static
    {
        if ($this->objectives->removeElement($objective)) {
            if ($objective->getCoach() === $this) {
                $objective->setCoach(null);
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
            $request->setCoach($this);
        }
        return $this;
    }

    public function removeRequest(Request $request): static
    {
        if ($this->requests->removeElement($request)) {
            if ($request->getCoach() === $this) {
                $request->setCoach(null);
            }
        }
        return $this;
    }

    /**
     * Convertir l'entité Coach en tableau pour l'API
     */
    public function toArray(): array
    {
        $baseData = parent::toArray();
        
        return array_merge($baseData, [
            'specialization' => $this->getSpecialization(),
            'familiesCount' => $this->getFamilies()->count(),
            'objectivesCount' => $this->getObjectives()->count(),
            'requestsCount' => $this->getRequests()->count()
        ]);
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
     * Obtenir les données publiques du coach (sans informations sensibles)
     */
    public function toPublicArray(): array
    {
        return [
            'id' => $this->getId(),
            'firstName' => $this->getFirstName(),
            'lastName' => $this->getLastName(),
            'specialization' => $this->getSpecialization(),
            'userType' => 'coach'
        ];
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
            $availability->setCoach($this);
        }

        return $this;
    }

    public function removeAvailability(Availability $availability): static
    {
        if ($this->availabilities->removeElement($availability)) {
            // set the owning side to null (unless already changed)
            if ($availability->getCoach() === $this) {
                $availability->setCoach(null);
            }
        }

        return $this;
    }

    /**
     * Obtenir les statistiques du coach
     */
    public function getStats(): array
    {
        return [
            'totalFamilies' => $this->getFamilies()->count(),
            'totalObjectives' => $this->getObjectives()->count(),
            'totalRequests' => $this->getRequests()->count(),
            'totalAvailabilities' => $this->getAvailabilities()->count(),
            'activeFamilies' => $this->getFamilies()->filter(fn($family) => $family->isActive())->count()
        ];
    }
}
