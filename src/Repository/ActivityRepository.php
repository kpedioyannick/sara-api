<?php

namespace App\Repository;

use App\Entity\Activity;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Activity>
 */
class ActivityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Activity::class);
    }

    /**
     * Retourne toutes les activités avec filtres optionnels
     */
    public function findWithFilters(array $filters = []): array
    {
        $qb = $this->createQueryBuilder('a')
            ->leftJoin('a.category', 'c')
            ->addSelect('c');

        if (isset($filters['categoryId']) && $filters['categoryId']) {
            $qb->andWhere('a.category = :categoryId')
                ->setParameter('categoryId', $filters['categoryId']);
        }

        if (isset($filters['ageRange']) && $filters['ageRange']) {
            $qb->andWhere('a.ageRange = :ageRange')
                ->setParameter('ageRange', $filters['ageRange']);
        }

        if (isset($filters['type']) && $filters['type']) {
            $qb->andWhere('a.type = :type')
                ->setParameter('type', $filters['type']);
        }

        if (isset($filters['workedPoint']) && $filters['workedPoint']) {
            // Recherche dans le tableau JSON des points travaillés
            $qb->andWhere('a.workedPoints LIKE :workedPoint')
                ->setParameter('workedPoint', '%' . $filters['workedPoint'] . '%');
        }

        if (isset($filters['createdBy']) && $filters['createdBy'] instanceof User) {
            $qb->andWhere('a.createdBy = :createdBy')
                ->setParameter('createdBy', $filters['createdBy']);
        }

        // Recherche textuelle dans la description
        if (isset($filters['search']) && $filters['search']) {
            $qb->andWhere('a.description LIKE :search')
                ->setParameter('search', '%' . $filters['search'] . '%');
        }

        $qb->orderBy('a.createdAt', 'DESC');

        return $qb->getQuery()->getResult();
    }

    /**
     * Retourne les activités les plus récentes
     */
    public function findRecent(int $limit = 10): array
    {
        return $this->createQueryBuilder('a')
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}

