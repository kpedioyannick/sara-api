<?php

namespace App\Repository;

use App\Entity\Family;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Family>
 */
class FamilyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Family::class);
    }

    public function save(Family $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Family $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Rechercher les familles d'un coach avec critères de recherche
     */
    public function findByCoachWithSearch($coach, string $search = '', ?string $status = null): array
    {
        $qb = $this->createQueryBuilder('f')
            ->leftJoin('f.parent', 'p')
            ->leftJoin('f.students', 's')
            ->addSelect('p', 's')
            ->where('f.coach = :coach')
            ->setParameter('coach', $coach);

        // Filtre par statut si fourni
        if (!empty($status)) {
            $isActive = $status === 'active';
            $qb->andWhere('f.isActive = :isActive')
               ->setParameter('isActive', $isActive);
        }

        // Recherche textuelle si fournie
        if (!empty($search) && $search !== 'undefined') {
            $qb->andWhere(
                $qb->expr()->orX(
                    // Recherche dans les parents
                    $qb->expr()->like('LOWER(p.firstName)', 'LOWER(:search)'),
                    $qb->expr()->like('LOWER(p.lastName)', 'LOWER(:search)'),
                    $qb->expr()->like('LOWER(f.familyIdentifier)', 'LOWER(:search)'),
                    // Recherche dans les enfants (élèves)
                    $qb->expr()->like('LOWER(s.firstName)', 'LOWER(:search)'),
                    $qb->expr()->like('LOWER(s.lastName)', 'LOWER(:search)'),
                    $qb->expr()->like('LOWER(s.pseudo)', 'LOWER(:search)'),
                    $qb->expr()->like('LOWER(s.email)', 'LOWER(:search)')
                )
            )->setParameter('search', '%' . $search . '%');
        }

        // Trier par date de création décroissante (les plus récentes en premier)
        $qb->orderBy('f.createdAt', 'DESC');

        return $qb->getQuery()->getResult();
    }

    /**
     * Compter les familles actives d'un coach
     */
    public function countActiveByCoach($coach): int
    {
        return $this->createQueryBuilder('f')
            ->select('COUNT(f.id)')
            ->where('f.coach = :coach')
            ->andWhere('f.isActive = :isActive')
            ->setParameter('coach', $coach)
            ->setParameter('isActive', true)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
