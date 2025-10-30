<?php

namespace App\EventListener;

use App\Service\SecurityHeadersService;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

class SecurityHeadersListener
{
    public function __construct(
        private SecurityHeadersService $securityHeadersService
    ) {}

    public function onKernelResponse(ResponseEvent $event): void
    {
        $response = $event->getResponse();
        
        // Appliquer les headers de sécurité à toutes les réponses
        $this->securityHeadersService->addSecurityHeaders($response);
    }
}
