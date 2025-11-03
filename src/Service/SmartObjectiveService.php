<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class SmartObjectiveService
{
    private const OPENAI_API_URL = 'https://api.openai.com/v1/chat/completions';
    private const OPENAI_MODEL = 'gpt-3.5-turbo';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly string $openaiApiKey
    ) {}

    /**
     * Génère des suggestions d'objectifs avec critères d'évaluation basées sur une description
     */
    public function generateSuggestions(string $title, string $type = 'general'): array
    {
        try {
            $prompt = $this->buildPrompt($title, $type);
            $response = $this->callOpenAI($prompt);
            
            if ($response && isset($response['choices'][0]['message']['content'])) {
                $content = $response['choices'][0]['message']['content'];
                return $this->parseResponse($content);
            }
            
            throw new \Exception('Réponse OpenAI invalide');
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de la génération des suggestions de tâches', [
                'title' => $title,
                'type' => $type,
                'error' => $e->getMessage()
            ]);
            
            // Relancer l'exception pour que le contrôleur puisse la gérer
            throw $e;
        }
    }

    /**
     * Construit le prompt pour OpenAI
     */
    private function buildPrompt(string $title, string $typeDescription): string
    {
        return "Tu es un expert en suivi d'objectifs éducatifs. Génère un objectif avec ses critères d'évaluation et des suggestions de tâches basées sur: \"{$title}\" de type \"{$typeDescription}\"

        Retourne un JSON avec cette structure exacte:
        {
            \"objective\": {
                \"title\": \"Titre de l'objectif\",
                \"description\": \"Description de l'objectif spécifique et clair\",
            },
            \"tasks\": [
                {
                    \"title\": \"Titre de la tâche\",
                    \"description\": \"Description spécifique et claire de la tâche\",
                    \"frequency\": \"daily|weekly|monthly|once\",
                    \"requiresProof\": true ou false,
                    \"proofType\": \"image|audio|video|document|text\"
                }
            ]
        }

        Assure-toi que:
        - Les suggestions de tâches soient concrètes et réalisables
        - La description de l'objectif et des tâches soit spécifique et claire
        Réponds uniquement avec le JSON, sans texte supplémentaire.";
    }

    /**
     * Appelle l'API OpenAI
     */
    private function callOpenAI(string $prompt): ?array
    {
        try {
            $response = $this->httpClient->request('POST', self::OPENAI_API_URL, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->openaiApiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => self::OPENAI_MODEL,
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => $prompt
                        ]
                    ],
                    'max_tokens' => 2000,
                    'temperature' => 0.7
                ]
            ]);

            return $response->toArray();
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de l\'appel à OpenAI', [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Parse la réponse d'OpenAI
     */
    private function parseResponse(string $content): array
    {
        try {
            // Nettoyer le contenu pour extraire le JSON
            $content = trim($content);
            
            // Chercher le JSON dans la réponse
            if (preg_match('/\{.*\}/s', $content, $matches)) {
                $jsonContent = $matches[0];
                $data = json_decode($jsonContent, true);
                
                if (isset($data['objective'])) {
                    // Normaliser les tâches (peut être 'tasks' ou 'suggestions')
                    if (isset($data['suggestions']) && !isset($data['tasks'])) {
                        $data['tasks'] = $data['suggestions'];
                    }
                    // S'assurer qu'on a toujours un tableau tasks
                    if (!isset($data['tasks'])) {
                        $data['tasks'] = [];
                    }
                    return $data;
                }
            }
            
            throw new \Exception('Format de réponse invalide');
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors du parsing de la réponse OpenAI', [
                'content' => $content,
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }


}