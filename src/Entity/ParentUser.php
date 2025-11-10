<?php

namespace App\Entity;

use App\Repository\ParentUserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ParentUserRepository::class)]
class ParentUser extends User
{
    #[ORM\OneToOne(inversedBy: 'parent', targetEntity: Family::class, cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false)]
    private ?Family $family = null;

    #[ORM\OneToMany(targetEntity: Request::class, mappedBy: 'parent')]
    private Collection $requests;

    #[ORM\OneToMany(targetEntity: Availability::class, mappedBy: 'parent')]
    private Collection $availabilities;

    public function __construct()
    {
        parent::__construct();
        $this->requests = new ArrayCollection();
        $this->availabilities = new ArrayCollection();
        $this->setRoles(['ROLE_PARENT']);
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
            $request->setParent($this);
        }
        return $this;
    }

    public function removeRequest(Request $request): static
    {
        if ($this->requests->removeElement($request)) {
            if ($request->getParent() === $this) {
                $request->setParent(null);
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
            $availability->setParent($this);
        }
        return $this;
    }

    public function removeAvailability(Availability $availability): static
    {
        if ($this->availabilities->removeElement($availability)) {
            if ($availability->getParent() === $this) {
                $availability->setParent(null);
            }
        }
        return $this;
    }

    /**
     * Convertir l'entité ParentUser en tableau pour l'API
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
            'family' => $this->family ? [
                'id' => $this->family->getId(),
                'familyIdentifier' => $this->family->getFamilyIdentifier(),
                'isActive' => $this->family->isActive(),
                'createdAt' => $this->family->getCreatedAt()?->format('Y-m-d H:i:s'),
                'updatedAt' => $this->family->getUpdatedAt()?->format('Y-m-d H:i:s')
            ] : null,
            'requestsCount' => $this->getRequests()->count(),
            'availabilitiesCount' => $this->getAvailabilities()->count()
        ];
    }

    public function toSimpleArray(): array
    {
        return [
            'id' => $this->getId(),
            'firstName' => $this->getFirstName(),
            'lastName' => $this->getLastName(),
            'email' => $this->getEmail(),
            'userType' => $this->getUserType()
        ];
    }

    /**
     * Obtenir les données publiques du parent
     */
    public function toPublicArray(): array
    {
        return [
            'id' => $this->getId(),
            'firstName' => $this->getFirstName(),
            'lastName' => $this->getLastName(),
            'userType' => 'parent',
            'familyId' => $this->getFamily()?->getId()
        ];
    }

    /**
     * Obtenir les statistiques du parent
     */
    public function getStats(): array
    {
        return [
            'totalRequests' => $this->getRequests()->count(),
            'familyMembers' => $this->getFamily()?->getStudents()->count() ?? 0,
            'totalAvailabilities' => $this->getAvailabilities()->count()
        ];
    }

    public static function create(array $data, Family $family): self
    {
        $parent = new self();
        $parent->setEmail($data['email']);
        $parent->setFirstName($data['firstName']);
        $parent->setLastName($data['lastName']);
        $parent->setPassword($data['password'] ?? 'defaultPassword123'); // Mot de passe par défaut
        $parent->setFamily($family);
        
        return $parent;
    }

    public static function createForCoach(array $data, Family $family): self
    {
        return self::create($data, $family);
    }
}
