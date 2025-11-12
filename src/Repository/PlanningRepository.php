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
     * Récupère les événements d'un élève pour une semaine donnée (pour compatibilité)
     * @deprecated Utiliser findByUserAndWeek à la place
     */
    public function findByStudentAndWeek($student, \DateTimeImmutable $weekStart): array
    {
        return $this->findByUserAndWeek($student, $weekStart);
    }

    /**
     * Récupère les événements d'une famille pour une semaine donnée
     * Récupère les plannings de tous les utilisateurs (étudiants) de la famille
     */
    public function findByFamilyAndWeek($family, \DateTimeImmutable $weekStart): array
    {
        $weekEnd = $weekStart->modify('+6 days')->setTime(23, 59, 59);
        
        // Récupérer tous les étudiants de la famille
        $students = $family->getStudents()->toArray();
        $studentIds = array_map(fn($s) => $s->getId(), $students);
        
        if (empty($studentIds)) {
            return [];
        }
        
        return $this->createQueryBuilder('p')
            ->where('p.user IN (:users)')
            ->andWhere('p.startDate >= :weekStart')
            ->andWhere('p.startDate <= :weekEnd')
            ->setParameter('users', $studentIds)
            ->setParameter('weekStart', $weekStart)
            ->setParameter('weekEnd', $weekEnd)
            ->orderBy('p.startDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère les événements d'un utilisateur pour une semaine donnée
     */
    public function findByUserAndWeek($user, \DateTimeImmutable $weekStart): array
    {
        $weekEnd = $weekStart->modify('+6 days')->setTime(23, 59, 59);
        
        return $this->createQueryBuilder('p')
            ->where('p.user = :user')
            ->andWhere('p.startDate >= :weekStart')
            ->andWhere('p.startDate <= :weekEnd')
            ->setParameter('user', $user)
            ->setParameter('weekStart', $weekStart)
            ->setParameter('weekEnd', $weekEnd)
            ->orderBy('p.startDate', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
