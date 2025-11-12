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
        
        // Si l'utilisateur n'est pas un coach, retourner null
        // Ne pas retourner un autre coach car cela pourrait causer des problèmes de sécurité
        return null;
    }
}

