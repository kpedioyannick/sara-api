<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class ShortUrlService
{
    private const TINY_URL_ENDPOINT = 'https://tinyurl.com/api-create.php';

    public function __construct(private readonly HttpClientInterface $httpClient)
    {
    }

    /**
     * Raccourcit une URL via TinyURL. Retourne l'URL d'origine en cas d'erreur.
     */
    public function shorten(string $url): string
    {
        if (empty($url)) {
            return $url;
        }

        try {
            $response = $this->httpClient->request('GET', self::TINY_URL_ENDPOINT, [
                'query' => ['url' => $url],
                'timeout' => 5,
            ]);

            if ($response->getStatusCode() === 200) {
                $shortUrl = trim($response->getContent());
                if (!empty($shortUrl) && str_starts_with($shortUrl, 'http')) {
                    return $shortUrl;
                }
            }
        } catch (\Throwable $e) {
            error_log('ShortUrlService error: ' . $e->getMessage());
        }

        return $url;
    }
}

