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

    #[ORM\ManyToOne(inversedBy: 'availabilities')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Coach $coach = null;

    #[ORM\ManyToOne(inversedBy: 'availabilities')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Specialist $specialist = null;

    #[ORM\ManyToOne(inversedBy: 'availabilities')]
    #[ORM\JoinColumn(nullable: true)]
    private ?ParentUser $parent = null;

    #[ORM\ManyToOne(inversedBy: 'availabilities')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Student $student = null;

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

    /**
     * Obtenir le propriétaire de la disponibilité (coach, specialist, parent ou student)
     */
    public function getOwner(): ?User
    {
        if ($this->coach) {
            return $this->coach;
        }
        if ($this->specialist) {
            return $this->specialist;
        }
        if ($this->parent) {
            return $this->parent;
        }
        if ($this->student) {
            return $this->student;
        }
        return null;
    }

    /**
     * Obtenir le type de propriétaire
     */
    public function getOwnerType(): ?string
    {
        if ($this->coach) {
            return 'coach';
        }
        if ($this->specialist) {
            return 'specialist';
        }
        if ($this->parent) {
            return 'parent';
        }
        if ($this->student) {
            return 'student';
        }
        return null;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->getId(),
            'startTime' => $this->getStartTime()?->format('H:i'),
            'endTime' => $this->getEndTime()?->format('H:i'),
            'dayOfWeek' => $this->getDayOfWeek(),
            'ownerType' => $this->getOwnerType(),
            'coach' => $this->getCoach()?->toSimpleArray(),
            'specialist' => $this->getSpecialist()?->toSimpleArray(),
            'parent' => $this->getParent()?->toSimpleArray(),
            'student' => $this->getStudent()?->toSimpleArray(),
            'owner' => $this->getOwner()?->toSimpleArray(),
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

    /**
     * Retourne les heures de début et fin formatées
     */
    public function getTimeRange(): array
    {
        $startTime = $this->getStartTime();
        $endTime = $this->getEndTime();
        
        if (!$startTime || !$endTime) {
            return ['start' => null, 'end' => null];
        }
        
        return [
            'start' => (int) $startTime->format('H'),
            'end' => (int) $endTime->format('H'),
        ];
    }

    /**
     * Retourne le nom du jour de la semaine en français
     */
    public function getDayName(): ?string
    {
        $dayMapping = [
            'monday' => 'Lundi',
            'tuesday' => 'Mardi',
            'wednesday' => 'Mercredi',
            'thursday' => 'Jeudi',
            'friday' => 'Vendredi',
            'saturday' => 'Samedi',
            'sunday' => 'Dimanche',
        ];
        
        $dayOfWeek = strtolower($this->getDayOfWeek() ?? '');
        return $dayMapping[$dayOfWeek] ?? $this->getDayOfWeek();
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

    public static function createForParent(array $data, ParentUser $parent): self
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
        $availability->setParent($parent);
        
        return $availability;
    }

    public static function createForStudent(array $data, Student $student): self
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
        $availability->setStudent($student);
        
        return $availability;
    }
}
