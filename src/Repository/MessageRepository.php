<?php

namespace App\Repository;

use App\Entity\Message;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Message>
 */
class MessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Message::class);
    }

    public function save(Message $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Message $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Trouve les conversations d'un coach
     */
    public function findConversationsByCoach($coach): array
    {
        $qb = $this->createQueryBuilder('m')
            ->select('DISTINCT m.conversationId')
            ->where('m.coach = :coach')
            ->setParameter('coach', $coach)
            ->orderBy('m.createdAt', 'DESC');

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve les conversations d'un utilisateur (tous les rôles)
     */
    public function findConversationsByUser(User $user): array
    {
        $qb = $this->createQueryBuilder('m')
            ->where('m.sender = :user OR m.receiver = :user')
            ->setParameter('user', $user)
            ->orderBy('m.createdAt', 'DESC')
            ->groupBy('m.conversationId');

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve les conversations avec les détails du dernier message
     */
    public function findConversationsWithDetails(User $user): array
    {
        $qb = $this->createQueryBuilder('m')
            ->where('m.sender = :user OR m.receiver = :user')
            ->setParameter('user', $user)
            ->orderBy('m.createdAt', 'DESC');

        $messages = $qb->getQuery()->getResult();

        // Grouper par conversationId et garder le dernier message de chaque conversation
        $conversations = [];
        foreach ($messages as $message) {
            $conversationId = $message->getConversationId();
            if (!$conversationId) {
                continue;
            }

            if (!isset($conversations[$conversationId])) {
                $conversations[$conversationId] = [
                    'conversationId' => $conversationId,
                    'lastMessage' => $message,
                    'unreadCount' => 0,
                    'otherUser' => $message->getSender() === $user ? $message->getReceiver() : $message->getSender(),
                ];
            }

            // Compter les messages non lus
            if (!$message->isRead() && $message->getReceiver() === $user) {
                $conversations[$conversationId]['unreadCount']++;
            }
        }

        return array_values($conversations);
    }

    /**
     * Trouve les messages d'une conversation
     */
    public function findByConversation(string $conversationId, ?User $user = null, int $limit = 50, int $offset = 0): array
    {
        $qb = $this->createQueryBuilder('m')
            ->where('m.conversationId = :conversationId')
            ->setParameter('conversationId', $conversationId)
            ->orderBy('m.createdAt', 'ASC')
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        if ($user) {
            $qb->andWhere('m.sender = :user OR m.receiver = :user')
               ->setParameter('user', $user);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Génère un conversationId unique entre deux utilisateurs
     */
    public function generateConversationId(User $user1, User $user2): string
    {
        $ids = [$user1->getId(), $user2->getId()];
        sort($ids);
        return 'conv_' . $ids[0] . '_' . $ids[1];
    }

    /**
     * Trouve une conversation entre deux utilisateurs
     */
    public function findConversationBetweenUsers(User $user1, User $user2): ?string
    {
        $conversationId = $this->generateConversationId($user1, $user2);

        $qb = $this->createQueryBuilder('m')
            ->where('m.conversationId = :conversationId')
            ->setParameter('conversationId', $conversationId)
            ->setMaxResults(1);

        $message = $qb->getQuery()->getOneOrNullResult();

        return $message ? $conversationId : null;
    }

    /**
     * Compte les messages non lus pour un utilisateur
     */
    public function countUnreadMessages(User $user): int
    {
        return $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->where('m.receiver = :user')
            ->andWhere('m.isRead = false')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Marque tous les messages d'une conversation comme lus
     */
    public function markConversationAsRead(string $conversationId, User $user): void
    {
        $messages = $this->createQueryBuilder('m')
            ->where('m.conversationId = :conversationId')
            ->andWhere('m.receiver = :user')
            ->andWhere('m.isRead = false')
            ->setParameter('conversationId', $conversationId)
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();

        foreach ($messages as $message) {
            $message->setIsRead(true);
        }

        $this->getEntityManager()->flush();
    }
}
