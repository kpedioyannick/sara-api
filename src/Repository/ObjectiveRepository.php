<?php

namespace App\Repository;

use App\Entity\Objective;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Objective>
 */
class ObjectiveRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Objective::class);
    }

    public function save(Objective $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Objective $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Recherche des objectifs par coach avec critères de recherche
     */
    public function findByCoachWithSearch($coach, $search = null, $creatorProfile = null, $creatorUserId = null, $status = null): array
    {
        $qb = $this->createQueryBuilder('o')
            ->leftJoin('o.student', 's')
            ->leftJoin('s.family', 'f')
            ->leftJoin('o.coach', 'creator')
            ->where('o.coach = :coach')
            ->setParameter('coach', $coach);

        if ($search) {
            $qb->andWhere('o.title LIKE :search OR o.description LIKE :search OR s.firstName LIKE :search OR s.lastName LIKE :search OR s.pseudo LIKE :search OR f.familyIdentifier LIKE :search')
               ->setParameter('search', '%' . $search . '%');
        }

        // Filtrer par profil du créateur (coach de l'objectif)
        if ($creatorProfile) {
            $roleMap = [
                'coach' => 'ROLE_COACH',
                'parent' => 'ROLE_PARENT',
                'student' => 'ROLE_STUDENT',
                'specialist' => 'ROLE_SPECIALIST',
            ];
            
            if (isset($roleMap[$creatorProfile])) {
                $qb->andWhere('creator.roles LIKE :creatorRole')
                   ->setParameter('creatorRole', '%' . $roleMap[$creatorProfile] . '%');
            }
        }

        // Filtrer par utilisateur créateur spécifique
        if ($creatorUserId) {
            $qb->andWhere('creator.id = :creatorUserId')
               ->setParameter('creatorUserId', $creatorUserId);
        }

        if ($status) {
            $qb->andWhere('o.status = :status')
               ->setParameter('status', $status);
        }

        return $qb->orderBy('o.createdAt', 'DESC')
                  ->getQuery()
                  ->getResult();
    }
}
