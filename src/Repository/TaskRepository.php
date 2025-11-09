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
}