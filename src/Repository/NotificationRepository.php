<?php

namespace App\Repository;

use App\Entity\Notification;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Notification>
 */
class NotificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Notification::class);
    }

    /**
     * Récupère les notifications d'un utilisateur avec pagination
     */
    public function findByUser(User $user, int $page = 1, int $limit = 20): array
    {
        $offset = ($page - 1) * $limit;

        return $this->createQueryBuilder('n')
            ->where('n.recipient = :user')
            ->setParameter('user', $user)
            ->orderBy('n.createdAt', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte les notifications non lues d'un utilisateur
     */
    public function countUnread(User $user): int
    {
        return $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->where('n.recipient = :user')
            ->andWhere('n.isRead = :isRead')
            ->setParameter('user', $user)
            ->setParameter('isRead', false)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Récupère les notifications récentes non lues
     */
    public function findRecentUnread(User $user, int $limit = 10): array
    {
        return $this->createQueryBuilder('n')
            ->where('n.recipient = :user')
            ->andWhere('n.isRead = :isRead')
            ->setParameter('user', $user)
            ->setParameter('isRead', false)
            ->orderBy('n.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Marque toutes les notifications comme lues
     */
    public function markAllAsRead(User $user): void
    {
        $this->createQueryBuilder('n')
            ->update()
            ->set('n.isRead', ':isRead')
            ->set('n.readAt', ':readAt')
            ->where('n.recipient = :user')
            ->andWhere('n.isRead = :false')
            ->setParameter('user', $user)
            ->setParameter('isRead', true)
            ->setParameter('readAt', new \DateTimeImmutable())
            ->setParameter('false', false)
            ->getQuery()
            ->execute();
    }

    /**
     * Supprime les notifications lues plus anciennes qu'une date
     */
    public function deleteOldReadNotifications(User $user, \DateTimeImmutable $beforeDate): int
    {
        return $this->createQueryBuilder('n')
            ->delete()
            ->where('n.recipient = :user')
            ->andWhere('n.isRead = :isRead')
            ->andWhere('n.readAt < :beforeDate')
            ->setParameter('user', $user)
            ->setParameter('isRead', true)
            ->setParameter('beforeDate', $beforeDate)
            ->getQuery()
            ->execute();
    }

    /**
     * Récupère les notifications par type
     */
    public function findByType(User $user, string $type, int $limit = 20): array
    {
        return $this->createQueryBuilder('n')
            ->where('n.recipient = :user')
            ->andWhere('n.type = :type')
            ->setParameter('user', $user)
            ->setParameter('type', $type)
            ->orderBy('n.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}

