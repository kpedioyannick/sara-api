<?php

namespace App\Repository;

use App\Entity\Task;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Task>
 */
class TaskRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Task::class);
    }

    public function save(Task $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Task $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Recherche des tâches par coach avec critères de recherche
     */
    public function findByCoachWithSearch($coach, $search = null, $objectiveId = null, $studentId = null, $status = null): array
    {
        $qb = $this->createQueryBuilder('t')
            ->leftJoin('t.objective', 'o')
            ->leftJoin('o.student', 's')
            ->leftJoin('s.family', 'f')
            ->where('t.coach = :coach')
            ->setParameter('coach', $coach);

        if ($search) {
            $qb->andWhere('t.title LIKE :search OR t.description LIKE :search OR s.firstName LIKE :search OR s.lastName LIKE :search OR s.pseudo LIKE :search OR f.familyIdentifier LIKE :search')
               ->setParameter('search', '%' . $search . '%');
        }

        if ($objectiveId) {
            $qb->andWhere('o.id = :objectiveId')
               ->setParameter('objectiveId', $objectiveId);
        }

        if ($studentId) {
            $qb->andWhere('s.id = :studentId')
               ->setParameter('studentId', $studentId);
        }

        if ($status) {
            $qb->andWhere('t.status = :status')
               ->setParameter('status', $status);
        }

        return $qb->orderBy('t.createdAt', 'DESC')
                  ->getQuery()
                  ->getResult();
    }

    /**
     * Recherche des tâches assignées à un spécialiste
     */
    public function findBySpecialist($specialist): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.specialist = :specialist')
            ->setParameter('specialist', $specialist)
            ->orderBy('t.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère les tâches qui chevauchent une semaine donnée
     * Basé sur createdAt et dueDate
     */
    public function findByWeek(\DateTimeImmutable $weekStart, ?\App\Entity\Coach $coach = null, ?array $studentIds = null): array
    {
        $weekEnd = $weekStart->modify('+6 days')->setTime(23, 59, 59);
        
        $qb = $this->createQueryBuilder('t')
            ->leftJoin('t.objective', 'o')
            ->leftJoin('o.student', 's')
            ->where('(t.createdAt <= :weekEnd AND (t.dueDate >= :weekStart OR t.dueDate IS NULL))')
            ->setParameter('weekStart', $weekStart)
            ->setParameter('weekEnd', $weekEnd);

        if ($coach) {
            $qb->andWhere('t.coach = :coach')
               ->setParameter('coach', $coach);
        }

        if ($studentIds && !empty($studentIds)) {
            $qb->andWhere('s.id IN (:studentIds)')
               ->setParameter('studentIds', $studentIds);
        }

        return $qb->orderBy('t.createdAt', 'ASC')
                  ->getQuery()
                  ->getResult();
    }
}