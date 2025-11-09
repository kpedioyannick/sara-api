<?php

namespace App\EventListener;

use App\Entity\Coach;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

class LoginSuccessListener implements EventSubscriberInterface
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LoginSuccessEvent::class => 'onLoginSuccess',
        ];
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();

        // Rediriger les coaches vers admin_dashboard au lieu de app_dashboard
        if ($user instanceof Coach) {
            $response = new RedirectResponse($this->urlGenerator->generate('admin_dashboard'));
            $event->setResponse($response);
            return;
        }

        // Pour les autres rôles, laisser le default_target_path gérer la redirection
        // ou rediriger vers app_dashboard explicitement
        $request = $event->getRequest();
        $targetPath = $request->getSession()->get('_security.main.target_path');
        
        // Si le target_path est app_dashboard et que l'utilisateur n'est pas un coach,
        // laisser la redirection par défaut se faire
        if (!$targetPath || $targetPath === $this->urlGenerator->generate('app_dashboard')) {
            // Pas besoin de modifier, la redirection par défaut fonctionnera
            return;
        }
    }
}

