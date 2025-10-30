<?php

namespace App\Service;

use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Exception\RateLimitExceededException;
use Symfony\Component\HttpFoundation\Request;

class RateLimiterService
{
    public function __construct(
        private RateLimiterFactory $authLimiter,
        private RateLimiterFactory $apiLimiter,
        private RateLimiterFactory $strictLimiter
    ) {}

    public function checkAuthRateLimit(Request $request): void
    {
        $limiter = $this->authLimiter->create($this->getClientIdentifier($request));
        
        if (!$limiter->consume()->isAccepted()) {
            throw new RateLimitExceededException('Too many authentication attempts. Please try again later.');
        }
    }

    public function checkApiRateLimit(Request $request): void
    {
        $limiter = $this->apiLimiter->create($this->getClientIdentifier($request));
        
        if (!$limiter->consume()->isAccepted()) {
            throw new RateLimitExceededException('API rate limit exceeded. Please try again later.');
        }
    }

    public function checkStrictRateLimit(Request $request): void
    {
        $limiter = $this->strictLimiter->create($this->getClientIdentifier($request));
        
        if (!$limiter->consume()->isAccepted()) {
            throw new RateLimitExceededException('Strict rate limit exceeded. Please try again later.');
        }
    }

    private function getClientIdentifier(Request $request): string
    {
        // Utiliser l'IP client comme identifiant
        $clientIp = $request->getClientIp();
        
        // Si l'utilisateur est authentifié, inclure son ID
        $user = $request->attributes->get('_user');
        if ($user) {
            return $clientIp . '_' . $user->getId();
        }
        
        return $clientIp;
    }
}
