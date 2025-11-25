<?php

namespace App\Repository;

use App\Entity\ParentUser;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ParentUser>
 */
class ParentUserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ParentUser::class);
    }

    public function save(ParentUser $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(ParentUser $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Retourne les parents rattachés aux familles d'un coach (avec recherche optionnelle).
     */
    public function findByCoachWithSearch($coach, string $search = ''): array
    {
        $qb = $this->createQueryBuilder('p')
            ->innerJoin('p.family', 'f')
            ->addSelect('f')
            ->where('f.coach = :coach')
            ->setParameter('coach', $coach);

        if (!empty($search) && $search !== 'undefined') {
            $qb->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->like('LOWER(p.firstName)', 'LOWER(:search)'),
                    $qb->expr()->like('LOWER(p.lastName)', 'LOWER(:search)'),
                    $qb->expr()->like('LOWER(p.email)', 'LOWER(:search)'),
                    $qb->expr()->like('LOWER(f.familyIdentifier)', 'LOWER(:search)')
                )
            )->setParameter('search', '%' . $search . '%');
        }

        return $qb->getQuery()->getResult();
    }
}
