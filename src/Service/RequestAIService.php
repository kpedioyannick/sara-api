<?php

namespace App\Service;

use App\Entity\Message;
use App\Entity\Request;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class RequestAIService
{
    private const OPENAI_API_URL = 'https://api.openai.com/v1/chat/completions';
    private const OPENAI_MODEL = 'gpt-3.5-turbo';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly string $openaiApiKey,
        private readonly EntityManagerInterface $entityManager,
        private readonly ValidatorInterface $validator
    ) {}

    /**
     * Génère une réponse IA basée sur les messages sélectionnés et le contexte
     * 
     * @param Request $request La demande concernée
     * @param array $selectedMessages Les messages sélectionnés pour le contexte
     * @param string|null $userQuestion La question ou demande de l'utilisateur
     * @param string|null $additionalContext Contexte supplémentaire optionnel
     * @return array Réponse avec le contenu généré par l'IA
     * @throws \Exception En cas d'erreur
     */
    public function generateAssistance(
        Request $request,
        array $selectedMessages,
        ?string $userQuestion = null,
        ?string $additionalContext = null
    ): array {
        try {
            // Construire le prompt avec le contexte
            $prompt = $this->buildPrompt($request, $selectedMessages, $userQuestion, $additionalContext);
            
            // Appeler OpenAI
            $response = $this->callOpenAI($prompt);
            
            if (!$response || !isset($response['choices'][0]['message']['content'])) {
                throw new \Exception('Réponse OpenAI invalide');
            }
            
            $aiResponse = $response['choices'][0]['message']['content'];
            
            $this->logger->info('Assistance IA générée avec succès', [
                'request_id' => $request->getId(),
                'messages_count' => count($selectedMessages),
                'response_length' => strlen($aiResponse)
            ]);
            
            return [
                'success' => true,
                'content' => $aiResponse,
                'message' => 'Réponse générée avec succès'
            ];
            
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de la génération de l\'assistance IA', [
                'request_id' => $request->getId(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw $e;
        }
    }

    /**
     * Construit le prompt pour OpenAI
     */
    private function buildPrompt(
        Request $request,
        array $selectedMessages,
        ?string $userQuestion,
        ?string $additionalContext
    ): string {
        $context = "Tu es un assistant éducatif spécialisé dans l'aide aux demandes et communications entre coachs, parents, élèves et spécialistes.\n\n";
        
        // Informations sur la demande
        $context .= "CONTEXTE DE LA DEMANDE:\n";
        $context .= "- Titre: {$request->getTitle()}\n";
        $context .= "- Description: {$request->getDescription()}\n";
        $context .= "- Type: {$request->getType()}\n";
        $context .= "- Statut: {$request->getStatus()}\n\n";
        
        // Messages sélectionnés pour le contexte
        if (!empty($selectedMessages)) {
            $context .= "HISTORIQUE DES MESSAGES SÉLECTIONNÉS:\n";
            foreach ($selectedMessages as $message) {
                $senderName = $message->getSender() 
                    ? $message->getSender()->getFirstName() . ' ' . $message->getSender()->getLastName()
                    : 'Utilisateur';
                $content = $message->getContent() ?? '';
                $date = $message->getCreatedAt()?->format('d/m/Y H:i') ?? '';
                
                $context .= "- [{$date}] {$senderName}: {$content}\n";
            }
            $context .= "\n";
        }
        
        // Contexte supplémentaire
        if ($additionalContext) {
            $context .= "CONTEXTE SUPPLÉMENTAIRE FOURNI:\n{$additionalContext}\n\n";
        }
        
        // Question de l'utilisateur
        if ($userQuestion) {
            $context .= "QUESTION/DEMANDE DE L'UTILISATEUR:\n{$userQuestion}\n\n";
        } else {
            $context .= "L'utilisateur demande ton assistance pour répondre ou aider dans cette conversation.\n\n";
        }
        
        $context .= "INSTRUCTIONS:\n";
        $context .= "- Sois professionnel, bienveillant et pédagogique\n";
        $context .= "- Adapte ton langage au contexte éducatif\n";
        $context .= "- Fournis une réponse claire, concise et utile\n";
        $context .= "- Si c'est pour répondre à un message, sois direct et pertinent\n";
        $context .= "- Réponds en français\n";
        $context .= "- Ne génère que le contenu de la réponse, sans préambule ni explication supplémentaire";
        
        return $context;
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
                            'role' => 'system',
                            'content' => 'Tu es un assistant éducatif professionnel qui aide les coachs, parents, élèves et spécialistes dans leurs communications et demandes.'
                        ],
                        [
                            'role' => 'user',
                            'content' => $prompt
                        ]
                    ],
                    'max_tokens' => 1000,
                    'temperature' => 0.7
                ]
            ]);

            $statusCode = $response->getStatusCode();
            if ($statusCode !== 200) {
                $errorContent = $response->getContent(false);
                $errorData = json_decode($errorContent, true);
                
                $this->logger->error('Erreur HTTP OpenAI', [
                    'status_code' => $statusCode,
                    'content' => $errorContent
                ]);
                
                if ($statusCode === 401) {
                    throw new \Exception("Clé API OpenAI invalide ou expirée");
                } elseif ($statusCode === 429) {
                    throw new \Exception("Limite de taux OpenAI dépassée. Veuillez réessayer plus tard.");
                } else {
                    throw new \Exception("Erreur HTTP {$statusCode}: " . ($errorData['error']['message'] ?? $errorContent));
                }
            }

            $responseArray = $response->toArray();
            
            if (isset($responseArray['error'])) {
                throw new \Exception('Erreur OpenAI: ' . ($responseArray['error']['message'] ?? 'Erreur inconnue'));
            }

            return $responseArray;
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de l\'appel à OpenAI', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
}

