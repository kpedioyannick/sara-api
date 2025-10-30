<?php

namespace App\EventListener;

use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;

class JWTUserListener
{
    public function onJWTCreated(JWTCreatedEvent $event): void
    {
        $user = $event->getUser();
        $payload = $event->getData();
        
        // Ajouter l'email utilisateur dans le payload JWT
        $payload['email'] = $user->getEmail();
        $payload['userType'] = $user->getUserType();
        $payload['roles'] = $user->getRoles();
        
        $event->setData($payload);
    }
}
