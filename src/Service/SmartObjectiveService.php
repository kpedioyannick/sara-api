<?php

namespace App\Service;

use App\Entity\Objective;
use App\Entity\Task;
use App\Service\TaskPlanningService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class SmartObjectiveService
{
    private const OPENAI_API_URL = 'https://api.openai.com/v1/chat/completions';
    private const OPENAI_MODEL = 'gpt-3.5-turbo';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly string $openaiApiKey,
        private readonly EntityManagerInterface $entityManager,
        private readonly ValidatorInterface $validator,
        private readonly TaskPlanningService $taskPlanningService
    ) {}

    /**
     * Génère des suggestions d'objectifs avec critères d'évaluation basées sur une description
     */
    public function generateSuggestions(string $title, string $type = 'general', bool $generateObjective = false): array
    {
        try {
            $prompt = $this->buildPrompt($title, $type, $generateObjective);
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
            
            return $this->parseResponse($content, $generateObjective);
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
    private function buildPrompt(string $title, string $typeDescription, bool $generateObjective = false): string
    {
        if ($generateObjective) {
            // Prompt avec génération d'objectif (titre et description)
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
        } else {
            // Prompt sans génération d'objectif (seulement des tâches)
            return "Tu es un expert en suivi d'objectifs éducatifs. Génère UNIQUEMENT des suggestions de tâches basées sur: \"{$title}\" de type \"{$typeDescription}\"

        Retourne un JSON avec cette structure exacte (SANS la clé \"objective\"):
        {
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
        - La description des tâches soit spécifique et claire
        - NE PAS inclure de clé \"objective\" dans le JSON
        Réponds uniquement avec le JSON, sans texte supplémentaire.";
        }
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
    private function parseResponse(string $content, bool $generateObjective = false): array
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
                'content_length' => strlen($content),
                'generateObjective' => $generateObjective
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
                
                // Normaliser les tâches (peut être 'tasks' ou 'suggestions')
                if (isset($data['suggestions']) && !isset($data['tasks'])) {
                    $data['tasks'] = $data['suggestions'];
                }
                // S'assurer qu'on a toujours un tableau tasks
                if (!isset($data['tasks'])) {
                    $data['tasks'] = [];
                }
                
                // Si on ne doit pas générer d'objectif, supprimer la clé objective si elle existe
                if (!$generateObjective && isset($data['objective'])) {
                    unset($data['objective']);
                }
                
                // Si on doit générer un objectif, vérifier qu'il existe
                if ($generateObjective && !isset($data['objective'])) {
                    $this->logger->error('Format de réponse invalide: pas de clé objective alors que demandé', [
                        'data_keys' => array_keys($data ?? []),
                        'content_preview' => substr($content, 0, 500)
                    ]);
                    throw new \Exception('Format de réponse invalide: pas de clé "objective" alors que demandé');
                }
                
                $this->logger->debug('Parsing réussi', [
                    'has_objective' => isset($data['objective']),
                    'tasks_count' => count($data['tasks'] ?? []),
                    'generateObjective' => $generateObjective
                ]);
                
                return $data;
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

    /**
     * Gère les tâches à partir d'un objectif et d'une suggestion optionnelle
     * 
     * @param Objective $objective L'objectif auquel associer les tâches (obligatoire)
     * @param array|null $suggestion Les suggestions de tâches au format retourné par generateSuggestions (optionnel)
     * @param string $assignedType Le type d'assignation ('student', 'parent', 'specialist', 'coach')
     * @param mixed $assignedTo L'entité à qui assigner les tâches (Student, ParentUser, Specialist, ou Coach)
     * @return array Tableau contenant les tâches créées et les informations de traitement
     * @throws \Exception En cas d'erreur lors de la création des tâches
     */
    public function manageTasksFromObjective(
        Objective $objective,
        ?array $suggestion = null,
        string $assignedType = 'coach',
        $assignedTo = null
    ): array {
        try {
            $createdTasks = [];
            $errors = [];

            // Si aucune suggestion n'est fournie, on utilise les tâches existantes de l'objectif
            if ($suggestion === null) {
                $this->logger->debug('Aucune suggestion fournie, utilisation des tâches existantes de l\'objectif', [
                    'objective_id' => $objective->getId(),
                    'existing_tasks_count' => $objective->getTasks()->count()
                ]);
                
                return [
                    'success' => true,
                    'tasks' => $objective->getTasks()->toArray(),
                    'created_count' => 0,
                    'message' => 'Aucune nouvelle tâche créée, utilisation des tâches existantes'
                ];
            }

            // Valider la structure de la suggestion
            if (!isset($suggestion['tasks']) || !is_array($suggestion['tasks'])) {
                $suggestionKeys = $suggestion !== null ? array_keys($suggestion) : [];
                $hasTasks = isset($suggestion['tasks']);
                
                $this->logger->warning('Structure de suggestion invalide', [
                    'suggestion_keys' => $suggestionKeys,
                    'has_tasks' => $hasTasks
                ]);
                
                return [
                    'success' => false,
                    'tasks' => [],
                    'created_count' => 0,
                    'errors' => ['Structure de suggestion invalide: clé "tasks" manquante ou invalide']
                ];
            }

            // Déterminer l'entité assignée par défaut
            if ($assignedTo === null) {
                $assignedTo = $objective->getCoach();
                $assignedType = 'coach';
            }

            // Créer les tâches à partir de la suggestion
            foreach ($suggestion['tasks'] as $taskData) {
                try {
                    // Valider les données de la tâche
                    if (empty($taskData['title'])) {
                        $errors[] = 'Tâche ignorée: titre manquant';
                        continue;
                    }

                    // Créer la tâche en utilisant la méthode statique de l'entité Task
                    $task = Task::createForCoach([
                        'title' => $taskData['title'],
                        'description' => $taskData['description'] ?? '',
                        'status' => Task::STATUS_PENDING,
                        'frequency' => $taskData['frequency'] ?? Task::FREQUENCY_NONE,
                        'requires_proof' => $taskData['requiresProof'] ?? true,
                        'proof_type' => $taskData['proofType'] ?? null,
                        'due_date' => $taskData['dueDate'] ?? null,
                    ], $objective, $assignedTo, $assignedType);

                    // Valider la tâche
                    $validationErrors = $this->validator->validate($task);
                    if (count($validationErrors) > 0) {
                        $errorMessages = [];
                        foreach ($validationErrors as $error) {
                            $errorMessages[] = $error->getPropertyPath() . ': ' . $error->getMessage();
                        }
                        $errors[] = sprintf('Tâche "%s" invalide: %s', $taskData['title'], implode(', ', $errorMessages));
                        continue;
                    }

                    // Persister la tâche
                    $this->entityManager->persist($task);
                    $objective->addTask($task);
                    $createdTasks[] = $task;

                    $this->logger->debug('Tâche créée avec succès', [
                        'task_title' => $task->getTitle(),
                        'objective_id' => $objective->getId(),
                        'assigned_type' => $assignedType
                    ]);

                } catch (\Exception $e) {
                    $errorMessage = sprintf('Erreur lors de la création de la tâche "%s": %s', 
                        $taskData['title'] ?? 'sans titre', 
                        $e->getMessage()
                    );
                    $errors[] = $errorMessage;
                    
                    $this->logger->error('Erreur lors de la création d\'une tâche', [
                        'task_data' => $taskData,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                }
            }

            // Flush toutes les tâches créées
            if (count($createdTasks) > 0) {
                $this->entityManager->flush();
                
                // Générer les événements Planning pour chaque tâche créée
                foreach ($createdTasks as $task) {
                    try {
                        $this->taskPlanningService->generatePlanningFromTask($task);
                    } catch (\Exception $e) {
                        $this->logger->warning('Erreur lors de la génération du planning pour une tâche', [
                            'task_id' => $task->getId(),
                            'error' => $e->getMessage()
                        ]);
                    }
                }
                
                // Flush les événements Planning créés
                $this->entityManager->flush();
                
                $this->logger->info('Tâches créées avec succès', [
                    'objective_id' => $objective->getId(),
                    'tasks_count' => count($createdTasks),
                    'errors_count' => count($errors)
                ]);
            }

            return [
                'success' => count($errors) === 0 || count($createdTasks) > 0,
                'tasks' => $createdTasks,
                'created_count' => count($createdTasks),
                'errors' => $errors,
                'message' => sprintf(
                    '%d tâche(s) créée(s) avec succès%s',
                    count($createdTasks),
                    count($errors) > 0 ? sprintf(', %d erreur(s)', count($errors)) : ''
                )
            ];

        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de la gestion des tâches', [
                'objective_id' => $objective->getId(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw new \Exception('Erreur lors de la gestion des tâches: ' . $e->getMessage(), 0, $e);
        }
    }

}