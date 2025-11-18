<?php

namespace App\Security;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class UserProvider implements UserProviderInterface, PasswordUpgraderInterface
{
    public function __construct(
        private readonly UserRepository $userRepository
    ) {
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        // Chercher par email ou pseudo
        $user = $this->userRepository->findByIdentifier($identifier);

        if (!$user) {
            throw new UserNotFoundException(sprintf('Utilisateur "%s" introuvable.', $identifier));
        }
        
        // Vérifier que l'utilisateur est actif
        if (!$user->isActive()) {
            throw new UserNotFoundException('Utilisateur désactivé.');
        }

        return $user;
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Invalid user class "%s".', get_class($user)));
        }

        $refreshedUser = $this->userRepository->find($user->getId());
        
        if (!$refreshedUser) {
            throw new UserNotFoundException('Utilisateur introuvable.');
        }
        
        // Vérifier que l'utilisateur est actif
        if (!$refreshedUser->isActive()) {
            throw new UserNotFoundException('Utilisateur désactivé.');
        }
        
        return $refreshedUser;
    }

    public function supportsClass(string $class): bool
    {
        return User::class === $class || is_subclass_of($class, User::class);
    }

    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Invalid user class "%s".', get_class($user)));
        }

        $user->setPassword($newHashedPassword);
        $this->userRepository->save($user, true);
    }
}

