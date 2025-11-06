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
            
            if (!$response) {
                throw new \Exception('Réponse OpenAI vide ou null');
            }
            
            // Log de la réponse pour debug
            $this->logger->debug('Réponse OpenAI brute', [
                'response_keys' => array_keys($response),
                'has_choices' => isset($response['choices']),
                'choices_count' => isset($response['choices']) ? count($response['choices']) : 0,
            ]);
            
            if (!isset($response['choices'])) {
                $this->logger->error('Réponse OpenAI sans clé choices', ['response' => $response]);
                throw new \Exception('Réponse OpenAI invalide: pas de clé "choices"');
            }
            
            if (empty($response['choices'])) {
                $this->logger->error('Réponse OpenAI avec choices vide', ['response' => $response]);
                throw new \Exception('Réponse OpenAI invalide: tableau "choices" vide');
            }
            
            if (!isset($response['choices'][0]['message']['content'])) {
                $this->logger->error('Réponse OpenAI sans content', [
                    'response' => $response,
                    'first_choice' => $response['choices'][0] ?? null
                ]);
                throw new \Exception('Réponse OpenAI invalide: pas de contenu dans la réponse');
            }
            
            $content = $response['choices'][0]['message']['content'];
            $this->logger->debug('Contenu OpenAI extrait', ['content_length' => strlen($content)]);
            
            return $this->parseResponse($content);
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de la génération des suggestions de tâches', [
                'title' => $title,
                'type' => $type,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
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
                    \"frequency\": \"none|hourly|daily|half_day|every_2_days|weekly|monthly|yearly\",
                    \"requiresProof\": true,
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

            $statusCode = $response->getStatusCode();
            if ($statusCode !== 200) {
                $errorContent = $response->getContent(false);
                $errorData = json_decode($errorContent, true);
                
                $this->logger->error('Erreur HTTP OpenAI', [
                    'status_code' => $statusCode,
                    'content' => $errorContent,
                    'api_key_length' => strlen($this->openaiApiKey ?? ''),
                    'api_key_prefix' => substr($this->openaiApiKey ?? '', 0, 7)
                ]);
                
                // Messages d'erreur plus explicites selon le code de statut
                if ($statusCode === 401) {
                    $errorMessage = $errorData['error']['message'] ?? 'Non autorisé';
                    throw new \Exception("Erreur 401: Clé API OpenAI invalide ou expirée. Message: {$errorMessage}");
                } elseif ($statusCode === 429) {
                    throw new \Exception("Erreur 429: Limite de taux OpenAI dépassée. Veuillez réessayer plus tard.");
                } else {
                    throw new \Exception("Erreur HTTP {$statusCode} lors de l'appel à OpenAI: " . ($errorData['error']['message'] ?? $errorContent));
                }
            }

            $responseArray = $response->toArray();
            
            // Vérifier les erreurs OpenAI dans la réponse
            if (isset($responseArray['error'])) {
                $this->logger->error('Erreur OpenAI dans la réponse', [
                    'error' => $responseArray['error']
                ]);
                throw new \Exception('Erreur OpenAI: ' . ($responseArray['error']['message'] ?? 'Erreur inconnue'));
            }

            return $responseArray;
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de l\'appel à OpenAI', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e; // Relancer l'exception au lieu de retourner null
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
            
            // Supprimer les markdown code blocks si présents
            $content = preg_replace('/```json\s*/', '', $content);
            $content = preg_replace('/```\s*/', '', $content);
            $content = trim($content);
            
            $this->logger->debug('Contenu nettoyé pour parsing', [
                'content_preview' => substr($content, 0, 200),
                'content_length' => strlen($content)
            ]);
            
            // Chercher le JSON dans la réponse (plus permissif avec plusieurs lignes)
            if (preg_match('/\{[\s\S]*\}/', $content, $matches)) {
                $jsonContent = $matches[0];
                $data = json_decode($jsonContent, true);
                
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $this->logger->error('Erreur de parsing JSON', [
                        'json_error' => json_last_error_msg(),
                        'content' => substr($jsonContent, 0, 500)
                    ]);
                    throw new \Exception('Erreur de parsing JSON: ' . json_last_error_msg());
                }
                
                if (isset($data['objective'])) {
                    // Normaliser les tâches (peut être 'tasks' ou 'suggestions')
                    if (isset($data['suggestions']) && !isset($data['tasks'])) {
                        $data['tasks'] = $data['suggestions'];
                    }
                    // S'assurer qu'on a toujours un tableau tasks
                    if (!isset($data['tasks'])) {
                        $data['tasks'] = [];
                    }
                    
                    $this->logger->debug('Parsing réussi', [
                        'has_objective' => isset($data['objective']),
                        'tasks_count' => count($data['tasks'] ?? [])
                    ]);
                    
                    return $data;
                } else {
                    $this->logger->error('Format de réponse invalide: pas de clé objective', [
                        'data_keys' => array_keys($data ?? []),
                        'content_preview' => substr($content, 0, 500)
                    ]);
                    throw new \Exception('Format de réponse invalide: pas de clé "objective"');
                }
            }
            
            $this->logger->error('Aucun JSON trouvé dans la réponse', [
                'content' => substr($content, 0, 500)
            ]);
            throw new \Exception('Aucun JSON valide trouvé dans la réponse');
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors du parsing de la réponse OpenAI', [
                'content_preview' => substr($content, 0, 500),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw $e;
        }
    }


}