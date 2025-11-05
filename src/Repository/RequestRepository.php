<?php

namespace App\Repository;

use App\Entity\Request;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Request>
 */
class RequestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Request::class);
    }

    public function save(Request $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Request $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Recherche des demandes par coach avec critères de recherche
     */
    public function findByCoachWithSearch($coach, $search = null, $familyId = null, $studentId = null, $status = null, $specialistId = null): array
    {
        $qb = $this->createQueryBuilder('r')
            ->leftJoin('r.student', 's')
            ->leftJoin('r.family', 'f')
            ->leftJoin('r.specialist', 'sp')
            ->where('r.coach = :coach')
            ->setParameter('coach', $coach);

        if ($search) {
            $qb->andWhere('r.title LIKE :search OR r.description LIKE :search OR s.firstName LIKE :search OR s.lastName LIKE :search OR f.familyIdentifier LIKE :search')
               ->setParameter('search', '%' . $search . '%');
        }

        if ($familyId) {
            $qb->andWhere('f.id = :familyId')
               ->setParameter('familyId', $familyId);
        }

        if ($studentId) {
            $qb->andWhere('s.id = :studentId')
               ->setParameter('studentId', $studentId);
        }

        if ($status) {
            $qb->andWhere('r.status = :status')
               ->setParameter('status', $status);
        }

        if ($specialistId) {
            $qb->andWhere('sp.id = :specialistId')
               ->setParameter('specialistId', $specialistId);
        }

        return $qb->orderBy('r.createdAt', 'DESC')
                  ->getQuery()
                  ->getResult();
    }
}
