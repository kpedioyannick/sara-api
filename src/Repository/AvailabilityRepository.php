<?php

namespace App\Repository;

use App\Entity\Availability;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Availability>
 */
class AvailabilityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Availability::class);
    }

    public function save(Availability $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Availability $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Récupère les disponibilités d'un coach
     */
    public function findByCoach($coach): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.coach = :coach')
            ->setParameter('coach', $coach)
            ->orderBy('a.dayOfWeek', 'ASC')
            ->addOrderBy('a.startTime', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère les disponibilités d'un spécialiste
     */
    public function findBySpecialist($specialist): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.specialist = :specialist')
            ->setParameter('specialist', $specialist)
            ->orderBy('a.dayOfWeek', 'ASC')
            ->addOrderBy('a.startTime', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère les disponibilités d'un parent
     */
    public function findByParent($parent): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.parent = :parent')
            ->setParameter('parent', $parent)
            ->orderBy('a.dayOfWeek', 'ASC')
            ->addOrderBy('a.startTime', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère les disponibilités d'un élève
     */
    public function findByStudent($student): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.student = :student')
            ->setParameter('student', $student)
            ->orderBy('a.dayOfWeek', 'ASC')
            ->addOrderBy('a.startTime', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
