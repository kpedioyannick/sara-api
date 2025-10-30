<?php

namespace App\Entity;

use App\Repository\AvailabilityRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AvailabilityRepository::class)]
class Availability
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $startTime = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $endTime = null;

    #[ORM\Column(length: 20)]
    private ?string $dayOfWeek = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?Coach $coach = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?Specialist $specialist = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getStartTime(): ?\DateTimeImmutable
    {
        return $this->startTime;
    }

    public function setStartTime(\DateTimeImmutable $startTime): static
    {
        $this->startTime = $startTime;
        return $this;
    }

    public function getEndTime(): ?\DateTimeImmutable
    {
        return $this->endTime;
    }

    public function setEndTime(\DateTimeImmutable $endTime): static
    {
        $this->endTime = $endTime;
        return $this;
    }

    public function getDayOfWeek(): ?string
    {
        return $this->dayOfWeek;
    }

    public function setDayOfWeek(string $dayOfWeek): static
    {
        $this->dayOfWeek = $dayOfWeek;
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

    public function getSpecialist(): ?Specialist
    {
        return $this->specialist;
    }

    public function setSpecialist(?Specialist $specialist): static
    {
        $this->specialist = $specialist;
        return $this;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->getId(),
            'startTime' => $this->getStartTime()?->format('H:i'),
            'endTime' => $this->getEndTime()?->format('H:i'),
            'dayOfWeek' => $this->getDayOfWeek(),
            'coach' => $this->getCoach()?->toArray(),
            'specialist' => $this->getSpecialist()?->toArray(),
            'createdAt' => $this->getCreatedAt()?->format('Y-m-d H:i:s'),
            'updatedAt' => $this->getUpdatedAt()?->format('Y-m-d H:i:s')
        ];
    }

    public function toPublicArray(): array
    {
        return [
            'id' => $this->getId(),
            'startTime' => $this->getStartTime()?->format('H:i'),
            'endTime' => $this->getEndTime()?->format('H:i'),
            'dayOfWeek' => $this->getDayOfWeek(),
            'createdAt' => $this->getCreatedAt()?->format('Y-m-d H:i:s'),
            'updatedAt' => $this->getUpdatedAt()?->format('Y-m-d H:i:s')
        ];
    }

    public function getStats(): array
    {
        return [
            'id' => $this->getId(),
            'dayOfWeek' => $this->getDayOfWeek(),
            'startTime' => $this->getStartTime()?->format('H:i'),
            'endTime' => $this->getEndTime()?->format('H:i'),
            'duration' => $this->getDuration(),
            'isActive' => $this->isActive(),
            'createdAt' => $this->getCreatedAt()?->format('Y-m-d H:i:s'),
            'updatedAt' => $this->getUpdatedAt()?->format('Y-m-d H:i:s')
        ];
    }

    public function getDuration(): ?int
    {
        if (!$this->getStartTime() || !$this->getEndTime()) {
            return null;
        }

        $start = $this->getStartTime();
        $end = $this->getEndTime();
        
        $diff = $end->diff($start);
        return $diff->h * 60 + $diff->i;
    }

    public function isActive(): bool
    {
        return $this->getStartTime() !== null && $this->getEndTime() !== null;
    }

    public function getStartOfDay(): ?\DateTimeImmutable
    {
        return $this->getStartTime()?->setTime(0, 0, 0);
    }

    public static function create(array $data, Coach $coach): self
    {
        $availability = new self();
        
        // Créer des DateTimeImmutable avec la date d'aujourd'hui et l'heure spécifiée
        $today = new \DateTimeImmutable();
        $startTime = $today->setTime(
            (int) explode(':', $data['start_time'])[0],
            (int) explode(':', $data['start_time'])[1],
            0
        );
        $endTime = $today->setTime(
            (int) explode(':', $data['end_time'])[0],
            (int) explode(':', $data['end_time'])[1],
            0
        );
        
        $availability->setStartTime($startTime);
        $availability->setEndTime($endTime);
        $availability->setDayOfWeek($data['day_of_week']);
        $availability->setCoach($coach);
        
        return $availability;
    }

    public static function createForCoach(array $data, Coach $coach): self
    {
        return self::create($data, $coach);
    }

    public static function createForSpecialist(array $data, Specialist $specialist): self
    {
        $availability = new self();
        
        // Créer des DateTimeImmutable avec la date d'aujourd'hui et l'heure spécifiée
        $today = new \DateTimeImmutable();
        $startTime = $today->setTime(
            (int) explode(':', $data['start_time'])[0],
            (int) explode(':', $data['start_time'])[1],
            0
        );
        $endTime = $today->setTime(
            (int) explode(':', $data['end_time'])[0],
            (int) explode(':', $data['end_time'])[1],
            0
        );
        
        $availability->setStartTime($startTime);
        $availability->setEndTime($endTime);
        $availability->setDayOfWeek($data['day_of_week']);
        $availability->setSpecialist($specialist);
        
        return $availability;
    }
}
