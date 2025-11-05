<?php

namespace App\Controller\Trait;

use App\Entity\Coach;
use App\Repository\CoachRepository;
use Symfony\Bundle\SecurityBundle\Security;

trait CoachTrait
{
    /**
     * Récupère le coach actuellement connecté ou le premier coach disponible (pour développement)
     */
    protected function getCurrentCoach(CoachRepository $coachRepository, Security $security): ?Coach
    {
        $user = $security->getUser();
        
        // Si l'utilisateur connecté est un coach, on le retourne
        if ($user instanceof Coach) {
            return $user;
        }
        
        // Pour le développement, on retourne le premier coach actif
        // En production, cela devrait être géré par l'authentification
        $coaches = $coachRepository->findBy(['isActive' => true], ['id' => 'ASC'], 1);
        
        return $coaches[0] ?? null;
    }
}

