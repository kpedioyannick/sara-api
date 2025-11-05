<?php

namespace App\Repository;

use App\Entity\Planning;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Planning>
 */
class PlanningRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Planning::class);
    }

    public function save(Planning $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Planning $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Récupère les événements d'un élève pour une semaine donnée
     */
    public function findByStudentAndWeek($student, \DateTimeImmutable $weekStart): array
    {
        $weekEnd = $weekStart->modify('+6 days')->setTime(23, 59, 59);
        
        return $this->createQueryBuilder('p')
            ->where('p.student = :student')
            ->andWhere('p.startDate >= :weekStart')
            ->andWhere('p.startDate <= :weekEnd')
            ->setParameter('student', $student)
            ->setParameter('weekStart', $weekStart)
            ->setParameter('weekEnd', $weekEnd)
            ->orderBy('p.startDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère les événements d'une famille pour une semaine donnée
     */
    public function findByFamilyAndWeek($family, \DateTimeImmutable $weekStart): array
    {
        $weekEnd = $weekStart->modify('+6 days')->setTime(23, 59, 59);
        
        return $this->createQueryBuilder('p')
            ->join('p.student', 's')
            ->where('s.family = :family')
            ->andWhere('p.startDate >= :weekStart')
            ->andWhere('p.startDate <= :weekEnd')
            ->setParameter('family', $family)
            ->setParameter('weekStart', $weekStart)
            ->setParameter('weekEnd', $weekEnd)
            ->orderBy('p.startDate', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
