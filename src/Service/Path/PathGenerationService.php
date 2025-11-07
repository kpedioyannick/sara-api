<?php

namespace App\Service\Path;

use App\Entity\Path\Module;
use App\Entity\Path\Path;
use App\Enum\ModuleType;
use App\Service\Path\ModuleConversionService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class PathGenerationService
{
    private const OPENAI_API_URL = 'https://api.openai.com/v1/chat/completions';
    private const OPENAI_MODEL = 'gpt-4o-mini';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly string $openaiApiKey,
        private readonly EntityManagerInterface $entityManager,
        private readonly ModuleConversionService $moduleConversionService
    ) {}

    /**
     * Génère le contenu H5P pour tous les modules d'un parcours
     */
    public function generateModulesFromPath(Path $path): array
    {
        try {
            $this->logger->info('Début de la génération des modules pour le parcours', [
                'path_id' => $path->getId(),
                'modules_count' => $path->getModules()->count()
            ]);

            $generatedModules = [];
            $errors = [];

            foreach ($path->getModules() as $module) {
                try {
                    $this->generateModuleContent($module, $path);
                    $generatedModules[] = $module->getId();
                } catch (\Exception $e) {
                    $this->logger->error('Erreur lors de la génération du module', [
                        'module_id' => $module->getId(),
                        'error' => $e->getMessage()
                    ]);
                    $errors[] = [
                        'module_id' => $module->getId(),
                        'module_title' => $module->getTitle(),
                        'error' => $e->getMessage()
                    ];
                }
            }

            // Mettre à jour le statut du parcours
            if (empty($errors)) {
                $path->setStatus(Path::STATUS_GENERATED);
            } else {
                $path->setStatus(Path::STATUS_DRAFT);
            }
            $path->setUpdatedAt(new \DateTimeImmutable());
            $this->entityManager->flush();

            $this->logger->info('Génération des modules terminée', [
                'path_id' => $path->getId(),
                'generated_count' => count($generatedModules),
                'errors_count' => count($errors)
            ]);

            return [
                'success' => empty($errors),
                'generated_modules' => $generatedModules,
                'errors' => $errors
            ];

        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de la génération des modules du parcours', [
                'path_id' => $path->getId(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Génère le contenu H5P pour un module spécifique
     */
    private function generateModuleContent(Module $module, Path $path): void
    {
        $moduleType = $module->getType();
        if (!$moduleType) {
            throw new \Exception('Type de module non défini');
        }

        // Construire le prompt basé sur le type de module
        $prompt = $this->buildPromptForModule($module, $path, $moduleType);

        // Appeler l'IA
        $aiResponse = $this->callOpenAI($prompt, $moduleType);

        // Parser la réponse JSON
        $parsedContent = $this->parseAIResponse($aiResponse, $moduleType);

        // Convertir en format H5P
        $h5pContent = $this->moduleConversionService->convertIAOutputToModuleFormat(
            $moduleType,
            $parsedContent
        );

        // Mettre à jour le module
        $module->setContent($parsedContent);
        $module->setH5pContent($h5pContent);
        $module->setUpdatedAt(new \DateTimeImmutable());

        $this->entityManager->persist($module);
    }

    /**
     * Construit le prompt pour générer le contenu d'un module
     */
    private function buildPromptForModule(Module $module, Path $path, ModuleType $moduleType): string
    {
        $expectedInputs = $moduleType->getExpectedInputs();
        
        // Récupérer le contexte du chapitre/sous-chapitre
        $context = $this->getPathContext($path);
        
        $prompt = "Tu es un assistant pédagogique expert qui crée des contenus éducatifs interactifs H5P.\n\n";
        $prompt .= "CONTEXTE DU PARCOURS :\n";
        $prompt .= $context . "\n\n";
        
        $prompt .= "TYPE DE MODULE : " . $moduleType->value . "\n";
        $prompt .= "OBJECTIF : " . ($expectedInputs['goal'] ?? 'Créer un contenu éducatif') . "\n\n";
        
        if (!empty($module->getDescription())) {
            $prompt .= "DESCRIPTION DU MODULE :\n";
            $prompt .= $module->getDescription() . "\n\n";
        }
        
        if (!empty($expectedInputs['instructions'])) {
            $prompt .= "INSTRUCTIONS SPÉCIFIQUES :\n";
            $prompt .= $expectedInputs['instructions'] . "\n\n";
        }
        
        if (!empty($expectedInputs['systemMessage'])) {
            $prompt .= $expectedInputs['systemMessage'] . "\n\n";
        }
        
        $prompt .= "FORMAT DE SORTIE ATTENDU (JSON uniquement, pas de markdown) :\n";
        $prompt .= json_encode($expectedInputs['outputFormat'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
        
        $prompt .= "IMPORTANT :\n";
        $prompt .= "- Retourne UNIQUEMENT un objet JSON valide, sans markdown, sans code blocks\n";
        $prompt .= "- Le JSON doit correspondre exactement au format de sortie attendu\n";
        $prompt .= "- Adapte le contenu au niveau scolaire du parcours\n";
        $prompt .= "- Le contenu doit être pédagogique et adapté au contexte\n";
        
        return $prompt;
    }

    /**
     * Récupère le contexte du parcours (chapitre/sous-chapitre)
     */
    private function getPathContext(Path $path): string
    {
        $context = [];
        
        if ($path->getSubChapter()) {
            $subChapter = $path->getSubChapter();
            $context[] = "Sous-chapitre : " . $subChapter->getName();
            if ($subChapter->getContent()) {
                $context[] = "Contenu : " . $subChapter->getContent();
            }
            
            if ($subChapter->getChapter()) {
                $chapter = $subChapter->getChapter();
                $context[] = "Chapitre : " . $chapter->getName();
                if ($chapter->getContent()) {
                    $context[] = "Contenu du chapitre : " . $chapter->getContent();
                }
                
                if ($chapter->getSubject()) {
                    $subject = $chapter->getSubject();
                    $context[] = "Matière : " . $subject->getName();
                    
                    if ($subject->getClassroom()) {
                        $context[] = "Classe : " . $subject->getClassroom()->getName();
                    }
                }
            }
        } elseif ($path->getChapter()) {
            $chapter = $path->getChapter();
            $context[] = "Chapitre : " . $chapter->getName();
            if ($chapter->getContent()) {
                $context[] = "Contenu : " . $chapter->getContent();
            }
            
            if ($chapter->getSubject()) {
                $subject = $chapter->getSubject();
                $context[] = "Matière : " . $subject->getName();
                
                if ($subject->getClassroom()) {
                    $context[] = "Classe : " . $subject->getClassroom()->getName();
                }
            }
        }
        
        return implode("\n", $context);
    }

    /**
     * Appelle l'API OpenAI
     */
    private function callOpenAI(string $prompt, ModuleType $moduleType): string
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
                            'content' => 'Tu es un assistant pédagogique expert qui génère du contenu éducatif interactif au format JSON strict. Tu retournes uniquement du JSON valide, sans markdown, sans code blocks.'
                        ],
                        [
                            'role' => 'user',
                            'content' => $prompt
                        ]
                    ],
                    'max_tokens' => 4000,
                    'temperature' => 0.7,
                    'response_format' => ['type' => 'json_object']
                ]
            ]);

            $statusCode = $response->getStatusCode();
            if ($statusCode !== 200) {
                $errorContent = $response->getContent(false);
                $errorData = json_decode($errorContent, true);
                
                $this->logger->error('Erreur HTTP OpenAI', [
                    'status_code' => $statusCode,
                    'content' => $errorContent,
                    'module_type' => $moduleType->value
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
            
            if (!isset($responseArray['choices'][0]['message']['content'])) {
                throw new \Exception('Réponse OpenAI invalide: pas de contenu');
            }

            return $responseArray['choices'][0]['message']['content'];

        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de l\'appel OpenAI', [
                'error' => $e->getMessage(),
                'module_type' => $moduleType->value
            ]);
            throw $e;
        }
    }

    /**
     * Parse la réponse JSON de l'IA
     */
    private function parseAIResponse(string $aiResponse, ModuleType $moduleType): array
    {
        // Nettoyer la réponse (enlever markdown si présent)
        $cleanedResponse = $this->cleanJsonResponse($aiResponse);
        
        $decoded = json_decode($cleanedResponse, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->logger->error('Erreur de parsing JSON', [
                'error' => json_last_error_msg(),
                'response' => substr($cleanedResponse, 0, 500),
                'module_type' => $moduleType->value
            ]);
            throw new \Exception('Réponse IA invalide: JSON mal formé - ' . json_last_error_msg());
        }

        if (!is_array($decoded)) {
            throw new \Exception('Réponse IA invalide: le JSON doit être un objet/tableau');
        }

        return $decoded;
    }

    /**
     * Nettoie la réponse JSON (enlève markdown, code blocks, etc.)
     */
    private function cleanJsonResponse(string $response): string
    {
        // Enlever les code blocks markdown
        $response = preg_replace('/```json\s*/', '', $response);
        $response = preg_replace('/```\s*/', '', $response);
        
        // Enlever les espaces en début/fin
        $response = trim($response);
        
        // Chercher le premier { ou [
        $firstBrace = strpos($response, '{');
        $firstBracket = strpos($response, '[');
        
        if ($firstBrace !== false && ($firstBracket === false || $firstBrace < $firstBracket)) {
            $response = substr($response, $firstBrace);
        } elseif ($firstBracket !== false) {
            $response = substr($response, $firstBracket);
        }
        
        // Chercher le dernier } ou ]
        $lastBrace = strrpos($response, '}');
        $lastBracket = strrpos($response, ']');
        
        if ($lastBrace !== false && ($lastBracket === false || $lastBrace > $lastBracket)) {
            $response = substr($response, 0, $lastBrace + 1);
        } elseif ($lastBracket !== false) {
            $response = substr($response, 0, $lastBracket + 1);
        }
        
        return trim($response);
    }
}

