<?php

namespace App\Service;

use Symfony\Component\Mercure\Jwt\TokenProviderInterface;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key\InMemory;

class MercureJwtProvider implements TokenProviderInterface
{
    private string $jwtSecret;

    public function __construct(string $jwtSecret)
    {
        $this->jwtSecret = $jwtSecret;
    }

    public function getJwt(): string
    {
        $configuration = Configuration::forSymmetricSigner(
            new Sha256(),
            InMemory::plainText($this->jwtSecret)
        );

        $token = $configuration->builder()
            ->withClaim('mercure', ['publish' => ['*'], 'subscribe' => ['*']])
            ->getToken($configuration->signer(), $configuration->signingKey());

        return $token->toString();
    }
}

