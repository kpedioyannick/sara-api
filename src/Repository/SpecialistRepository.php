<?php

namespace App\Repository;

use App\Entity\Specialist;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Specialist>
 */
class SpecialistRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Specialist::class);
    }

    public function save(Specialist $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Specialist $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Rechercher les spécialistes avec critères de recherche
     */
    public function findByWithSearch(string $search = '', ?string $specialization = null, ?string $status = null): array
    {
        $qb = $this->createQueryBuilder('s')
            ->where('1=1');

        // Filtre par statut si fourni
        if (!empty($status)) {
            $isActive = $status === 'active';
            $qb->andWhere('s.isActive = :isActive')
               ->setParameter('isActive', $isActive);
        }

        // Recherche textuelle si fournie
        if (!empty($search) && $search !== 'undefined') {
            $qb->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->like('LOWER(s.firstName)', 'LOWER(:search)'),
                    $qb->expr()->like('LOWER(s.lastName)', 'LOWER(:search)'),
                    $qb->expr()->like('LOWER(s.email)', 'LOWER(:search)')
                )
            )->setParameter('search', '%' . $search . '%');
        }

        // Filtre par spécialisation si fournie
        if (!empty($specialization)) {
            $qb->andWhere('s.specializations LIKE :specialization')
               ->setParameter('specialization', '%' . $specialization . '%');
        }

        return $qb->getQuery()->getResult();
    }
}
