<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function save(User $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(User $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->save($user, true);
    }

    /**
     * Trouve un utilisateur par email ou pseudo (pour les étudiants)
     */
    public function findByIdentifier(string $identifier): ?User
    {
        // Chercher d'abord par email
        $user = $this->findOneBy(['email' => $identifier]);
        
        if ($user) {
            return $user;
        }

        // Si pas trouvé, chercher par pseudo dans les étudiants
        // Utiliser une requête DQL qui joint correctement la table student
        $qb = $this->getEntityManager()->createQueryBuilder();
        $qb->select('s')
           ->from('App\Entity\Student', 's')
           ->where('s.pseudo = :identifier')
           ->setParameter('identifier', $identifier);
        
        try {
            return $qb->getQuery()->getOneOrNullResult();
        } catch (\Exception $e) {
            // Si erreur, retourner null
            return null;
        }
    }
}
