<?php

namespace App\Repository;

use App\Entity\Message;
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
     * Trouve les messages d'une conversation
     */
    public function findByConversation(string $conversationId, $coach, int $limit = 50, int $offset = 0): array
    {
        return $this->createQueryBuilder('m')
            ->where('m.conversationId = :conversationId')
            ->andWhere('m.coach = :coach')
            ->setParameter('conversationId', $conversationId)
            ->setParameter('coach', $coach)
            ->orderBy('m.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }
}
