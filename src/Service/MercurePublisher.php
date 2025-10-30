<?php

namespace App\Service;

use Symfony\Component\Mercure\PublisherInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Mercure\Jwt\TokenProviderInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class MercurePublisher implements PublisherInterface
{
    private string $hubUrl;
    private TokenProviderInterface $jwtProvider;
    private HttpClientInterface $httpClient;

    public function __construct(string $hubUrl, TokenProviderInterface $jwtProvider, HttpClientInterface $httpClient)
    {
        $this->hubUrl = $hubUrl;
        $this->jwtProvider = $jwtProvider;
        $this->httpClient = $httpClient;
    }

    public function __invoke(Update $update): string
    {
        $jwt = $this->jwtProvider->getJwt();

        $response = $this->httpClient->request('POST', $this->hubUrl, [
            'headers' => [
                'Authorization' => 'Bearer ' . $jwt,
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'body' => http_build_query([
                'topic' => is_array($update->getTopics()) ? implode(',', $update->getTopics()) : $update->getTopics(),
                'data' => $update->getData(),
                'private' => $update->isPrivate() ? 'on' : '',
            ]),
        ]);

        return $response->getContent();
    }
}

