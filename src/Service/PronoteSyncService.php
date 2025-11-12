<?php

namespace App\Service;

use App\Entity\Integration;
use App\Entity\Planning;
use App\Entity\Student;
use App\Repository\PlanningRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Process\Process;

class PronoteSyncService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly PlanningRepository $planningRepository
    ) {
    }

    /**
     * Synchronise toutes les données PRONOTE pour une intégration
     */
    public function syncIntegration(Integration $integration): array
    {
        if ($integration->getType() !== Integration::TYPE_PRONOTE) {
            throw new \InvalidArgumentException('Cette intégration n\'est pas de type PRONOTE');
        }

        if (!$integration->getStudent()) {
            throw new \InvalidArgumentException('Aucun élève associé à cette intégration');
        }

        $credentials = $integration->getCredentials();
        if (!$credentials) {
            throw new \InvalidArgumentException('Aucun credential disponible pour cette intégration');
        }

        // Appeler le script Node.js (Pawnote.js) pour récupérer les données PRONOTE
        $scriptPath = __DIR__ . '/../../scripts/pawnote/fetch-data.mts';
        $process = new Process([
            'npm',
            'run',
            'fetch',
            json_encode($credentials)
        ], __DIR__ . '/../../scripts/pawnote');

        $process->run();

        $output = trim($process->getOutput());
        $errorOutput = $process->getErrorOutput();
        
        // Le script envoie le JSON sur stdout, mais npm peut ajouter des messages avant
        // npm affiche: "> pawnote@1.0.0 fetch" puis "> node_modules/.bin/tsx fetch-data.mts <args>"
        // Le JSON de la réponse du script vient après ces lignes
        $data = null;
        $rawJsonFromApi = null; // JSON brut extrait de l'API Node.js
        
        // Chercher le JSON dans stdout (la réponse du script)
        // Le JSON de la réponse commence par {"success" ou contient "success"
        if (!empty($output)) {
            // Chercher le JSON qui contient "success" (c'est la réponse du script)
            // Le JSON peut être sur plusieurs lignes
            $lines = explode("\n", $output);
            $responseStart = -1;
            
            // Trouver la ligne qui commence le JSON de réponse (contient "success")
            foreach ($lines as $idx => $line) {
                $line = trim($line);
                // Chercher une ligne qui commence par { et contient "success"
                if (strpos($line, '{') === 0 && strpos($line, '"success"') !== false) {
                    $responseStart = $idx;
                    break;
                }
            }
            
            if ($responseStart >= 0) {
                // Reconstruire le JSON à partir de cette ligne jusqu'à la fin
                $responseLines = array_slice($lines, $responseStart);
                $jsonLine = implode("\n", $responseLines);
                
                // Extraire le JSON complet (du premier { au dernier })
                $firstBrace = strpos($jsonLine, '{');
                if ($firstBrace !== false) {
                    $lastBrace = strrpos($jsonLine, '}');
                    if ($lastBrace !== false && $lastBrace > $firstBrace) {
                        $rawJsonFromApi = substr($jsonLine, $firstBrace, $lastBrace - $firstBrace + 1);
                        $data = json_decode($rawJsonFromApi, true);
                    }
                }
            } else {
                // Si on ne trouve pas "success", chercher le dernier JSON valide
                // (chercher toutes les occurrences de { et prendre la dernière)
                $lastBrace = strrpos($output, '}');
                if ($lastBrace !== false) {
                    // Remonter pour trouver le { correspondant
                    $braceCount = 0;
                    $startBrace = $lastBrace;
                    for ($i = $lastBrace; $i >= 0; $i--) {
                        if ($output[$i] === '}') {
                            $braceCount++;
                        } elseif ($output[$i] === '{') {
                            $braceCount--;
                            if ($braceCount === 0) {
                                $startBrace = $i;
                                break;
                            }
                        }
                    }
                    if ($startBrace < $lastBrace) {
                        $rawJsonFromApi = substr($output, $startBrace, $lastBrace - $startBrace + 1);
                        $data = json_decode($rawJsonFromApi, true);
                    }
                }
            }
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log("PRONOTE Sync - JSON decode error: " . json_last_error_msg());
            }
        }
        
        // Si on n'a toujours pas de JSON, chercher dans stderr (au cas où)
        if (!$data && !empty($errorOutput)) {
            $firstBrace = strpos($errorOutput, '{');
            if ($firstBrace !== false) {
                $lastBrace = strrpos($errorOutput, '}');
                if ($lastBrace !== false && $lastBrace > $firstBrace) {
                    $rawJsonFromApi = substr($errorOutput, $firstBrace, $lastBrace - $firstBrace + 1);
                    $data = json_decode($rawJsonFromApi, true);
                }
            }
        }

        // Logger uniquement les erreurs critiques depuis stderr
        if (!empty($errorOutput) && strpos($errorOutput, '❌') !== false) {
            error_log("PRONOTE Sync - Erreur: " . substr($errorOutput, 0, 500));
        }
        
        // Vérifier d'abord si on a réussi à parser le JSON
        if (!$data || !isset($data['success'])) {
            $errorMsg = $errorOutput ?: (!empty($output) ? substr($output, 0, 200) : 'Erreur inconnue');
            throw new \RuntimeException('Erreur lors de la récupération des données PRONOTE: ' . $errorMsg);
        }
        
        // Vérifier si un nouveau QR code est nécessaire (même si le processus a échoué)
            $needsQrCode = isset($data['needs_qr_code']) && $data['needs_qr_code'];
            if ($needsQrCode) {
            $errorMsg = $data['error'] ?? 'Le token a expiré';
                throw new \RuntimeException('Le token a expiré. Veuillez vous reconnecter via QR code: ' . $errorMsg);
            }
            
        // Si le processus a échoué mais qu'on a un JSON valide, vérifier le success
        if (!$process->isSuccessful() && !$data['success']) {
            $errorMsg = $data['error'] ?? ($errorOutput ?: 'Erreur inconnue');
            throw new \RuntimeException('Erreur lors de la récupération des données PRONOTE: ' . $errorMsg);
        }

        if (!$data['success']) {
            $errorMsg = $data['error'] ?? 'Erreur inconnue';
            $needsQrCode = isset($data['needs_qr_code']) && $data['needs_qr_code'];
            
            if ($needsQrCode) {
                throw new \RuntimeException('Le token a expiré. Veuillez vous reconnecter via QR code: ' . $errorMsg);
            }
            
            throw new \RuntimeException('Échec de la récupération des données: ' . $errorMsg);
        }
        
        // Si un nouveau token est disponible (pour la prochaine connexion), le sauvegarder
        // Pawnote.js génère un nouveau token après chaque connexion (comme pronotepy)
        if (isset($data['new_token'])) {
            $newToken = $data['new_token'];
            // Mettre à jour les credentials avec le nouveau token
            // Format Pawnote.js avec refresh_info
            $updatedCredentials = [
                'pronote_url' => $credentials['pronote_url'] ?? $newToken['url'],
                'base_url' => $credentials['base_url'] ?? $newToken['url'],
                'username' => $newToken['username'],
                'password' => $newToken['token'],  // Le nouveau token
                'uuid' => $credentials['uuid'] ?? $credentials['deviceUUID'] ?? '',
                'space' => $credentials['space'] ?? 'student',
                'kind' => $newToken['kind'] ?? 6,
                'deviceUUID' => $credentials['deviceUUID'] ?? $credentials['uuid'] ?? '',
                'refresh_info' => [
                    'kind' => $newToken['kind'],
                    'url' => $newToken['url'],
                    'username' => $newToken['username'],
                    'token' => $newToken['token']
                ]
            ];
            $integration->setCredentials($updatedCredentials);
            $integration->setLastSyncAt(new \DateTimeImmutable());
            $this->em->persist($integration);
            $this->em->flush();
            
            // Log pour debug
            error_log(sprintf(
                'PRONOTE: Nouveau token sauvegardé pour intégration #%d (token: %s...)',
                $integration->getId(),
                substr($newToken['token'], 0, 20)
            ));
        }
 
        $student = $integration->getStudent();
        $results = [
            'homework' => 0,
            'lessons' => 0,
            'absences' => 0,
            'notes' => [],
            'carnet' => [],
        ];

        // Synchroniser les devoirs (format Pawnote.js: data.assignments)
        if (isset($data['data']['assignments']) && is_array($data['data']['assignments'])) {
            $results['homework'] = $this->syncHomework($data['data']['assignments'], $student, $integration);
        } elseif (isset($data['homework']) && is_array($data['homework'])) {
            // Format legacy
            $results['homework'] = $this->syncHomework($data['homework'], $student, $integration);
        }

        // Synchroniser les cours (format Pawnote.js: data.lessons_list)
        if (isset($data['data']['lessons_list']) && is_array($data['data']['lessons_list'])) {
            $results['lessons'] = $this->syncLessons($data['data']['lessons_list'], $student, $integration);
        } elseif (isset($data['lessons']) && is_array($data['lessons'])) {
            // Format legacy
            $results['lessons'] = $this->syncLessons($data['lessons'], $student, $integration);
        }

        // Synchroniser les absences (depuis data.data.absences ou data.absences)
        $absences = $data['data']['absences'] ?? $data['absences'] ?? [];
        if (is_array($absences) && count($absences) > 0) {
            $results['absences'] = $this->syncAbsences($absences, $student);
        } else {
            $results['absences'] = 0;
        }

        // Stocker les évaluations/notes dans metadata (format Pawnote.js: data.evaluations)
        // Toujours sauvegarder, même si vide, pour indiquer que la synchronisation a été effectuée
            $metadata = $integration->getMetadata() ?? [];
        if (isset($data['data']['evaluations'])) {
            // Sauvegarder même si vide (tableau vide)
            $results['notes'] = is_array($data['data']['evaluations']) ? $data['data']['evaluations'] : [];
            $metadata['evaluations'] = is_array($data['data']['evaluations']) ? $data['data']['evaluations'] : [];
        } elseif (isset($data['grades'])) {
            // Format legacy
            $results['notes'] = is_array($data['grades']) ? $data['grades'] : [];
            $metadata['notes'] = is_array($data['grades']) ? $data['grades'] : [];
        }

        // Stocker le bulletin de notes dans metadata (format Pawnote.js: data.gradebook)
        if (isset($data['data']['gradebook'])) {
            $metadata['gradebook'] = $data['data']['gradebook']; // Peut être null
        }

        // Stocker le carnet de correspondance dans metadata (format Pawnote.js: data.notebook)
        // Toujours sauvegarder, même si vide, pour indiquer que la synchronisation a été effectuée
        if (isset($data['data']['notebook'])) {
            $notebookData = is_array($data['data']['notebook']) ? $data['data']['notebook'] : [];
            $results['carnet'] = $notebookData;
            $metadata['notebook'] = $notebookData;
            $metadata['carnet_correspondance'] = $notebookData; // Compatibilité legacy
        } elseif (isset($data['carnet_correspondance'])) {
            // Format legacy
            $carnetData = is_array($data['carnet_correspondance']) ? $data['carnet_correspondance'] : [];
            $results['carnet'] = $carnetData;
            $metadata['carnet_correspondance'] = $carnetData;
        }
        
        $integration->setMetadata($metadata);

        // Mettre à jour sync_data dans les métadonnées avec tous les résultats
        $metadata = $integration->getMetadata() ?? [];
        $metadata['sync_data'] = [
            'homework' => $results['homework'],
            'lessons' => $results['lessons'],
            'absences' => $results['absences'],
        ];
        $integration->setMetadata($metadata);

        // Mettre à jour lastSyncAt
        $integration->setLastSyncAt(new \DateTimeImmutable());
        $integration->setUpdatedAt(new \DateTimeImmutable());

        $this->em->persist($integration);
        $this->em->flush();

        return $results;
    }

    /**
     * Synchronise les devoirs dans Planning
     */
    private function syncHomework(array $homework, Student $student, Integration $integration): int
    {
        $count = 0;
        $updated = 0;

        foreach ($homework as $hw) {
            $pronoteId = $hw['id'] ?? null;
            $planning = null;
            
            // Chercher un événement existant par reference_id et integration
            if ($pronoteId) {
                $planning = $this->em->getRepository(Planning::class)->findOneBy([
                'user' => $student,
                'type' => Planning::TYPE_HOMEWORK,
                    'integration' => $integration,
                    'referenceId' => $pronoteId
                ]);
            }
            
            // Créer un nouvel événement si non trouvé
            if (!$planning) {
                $planning = new Planning();
                $planning->setUser($student);
                $count++;
            } else {
                $updated++;
            }
            
            // Toujours définir l'integration et referenceId (même lors de la mise à jour)
            $planning->setIntegration($integration);
            $planning->setReferenceId($pronoteId);
            
            // Récupérer les données depuis raw si disponible
            $raw = $hw['raw'] ?? [];
            
            // Titre : utiliser le subject
            $subject = trim($hw['subject'] ?? 'Devoir');
            if ($subject === 'N/A' || empty($subject)) {
                $subject = 'Devoir';
            }
            $planning->setTitle($subject);
            
            // Description : description + attachments
            $descriptionParts = [];
            
            // Ajouter la description
            $hwDescription = trim($hw['description'] ?? '');
            if (!empty($hwDescription)) {
                // Nettoyer le HTML si nécessaire
                $hwDescription = strip_tags($hwDescription);
                $descriptionParts[] = $hwDescription;
            }
            
            // Ajouter les attachments
            $attachments = $raw['attachments'] ?? [];
            if (!empty($attachments) && is_array($attachments)) {
                $attachmentList = [];
                foreach ($attachments as $attachment) {
                    if (is_string($attachment)) {
                        $attachmentList[] = $attachment;
                    } elseif (is_array($attachment) && isset($attachment['name'])) {
                        $attachmentList[] = $attachment['name'];
                    } elseif (is_array($attachment) && isset($attachment['url'])) {
                        $attachmentList[] = $attachment['url'];
                    }
                }
                if (!empty($attachmentList)) {
                    $descriptionParts[] = "\n\nPièces jointes: " . implode(', ', $attachmentList);
                }
            }
            
            $description = implode('', $descriptionParts);
            $planning->setDescription($description);
            
            $planning->setType(Planning::TYPE_HOMEWORK);
            $planning->setStatus($hw['done'] ?? false ? Planning::STATUS_COMPLETED : Planning::STATUS_TO_DO);

            // Dates : utiliser deadline depuis raw
            // Les dates Pronote sont en UTC (GMT), il faut les convertir en Europe/Paris (+1h ou +2h selon période)
            $deadline = null;
            $timezone = new \DateTimeZone('Europe/Paris');
            
            if (isset($raw['deadline']) && $raw['deadline']) {
                try {
                    // Parser la date ISO en UTC
                    $deadline = new \DateTimeImmutable($raw['deadline'], new \DateTimeZone('UTC'));
                    // Convertir en timezone Europe/Paris (ajoute automatiquement +1h ou +2h selon période)
                    $deadline = $deadline->setTimezone($timezone);
                } catch (\Exception $e) {
                    error_log("PRONOTE: Erreur parsing deadline devoir: " . $e->getMessage());
                    $deadline = new \DateTimeImmutable('now', $timezone);
                }
            } elseif (isset($hw['date']) && $hw['date']) {
                // Fallback sur date si deadline n'existe pas
                try {
                    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $hw['date'])) {
                        // Date simple sans heure, créer à minuit en timezone locale
                        $deadline = new \DateTimeImmutable($hw['date'] . ' 00:00:00', $timezone);
                    } else {
                        // Date ISO, parser en UTC puis convertir
                        $deadline = new \DateTimeImmutable($hw['date'], new \DateTimeZone('UTC'));
                        $deadline = $deadline->setTimezone($timezone);
                    }
                } catch (\Exception $e) {
                    error_log("PRONOTE: Erreur parsing date devoir: " . $e->getMessage());
                    $deadline = new \DateTimeImmutable('now', $timezone);
                }
            } else {
                $deadline = new \DateTimeImmutable('now', $timezone);
            }
            
            // startDate = deadline, endDate = deadline + 45 min
            $planning->setStartDate($deadline);
            $endDate = $deadline->modify('+45 minutes');
            $planning->setEndDate($endDate);

            // Mettre à jour les métadonnées PRONOTE (inclure backgroundColor)
            $metadata = $planning->getMetadata() ?? [];
            $metadata['pronote_id'] = $pronoteId;
            $metadata['pronote_subject'] = $hw['subject'] ?? null;
            $metadata['pronote_backgroundColor'] = $raw['backgroundColor'] ?? null;
            $metadata['pronote_raw'] = $hw;
            $planning->setMetadata($metadata);

            $this->em->persist($planning);
        }

        $this->em->flush();
        error_log(sprintf("PRONOTE: %d devoirs créés, %d devoirs mis à jour", $count, $updated));
        return $count + $updated;
    }

    /**
     * Synchronise les cours dans Planning
     */
    private function syncLessons(array $lessons, Student $student, Integration $integration): int
    {
        $count = 0;
        $updated = 0;

        foreach ($lessons as $lesson) {
            $pronoteId = $lesson['id'] ?? null;
            $planning = null;
            
            // Chercher un événement existant par reference_id et integration
            if ($pronoteId) {
                $planning = $this->em->getRepository(Planning::class)->findOneBy([
                'user' => $student,
                'type' => Planning::TYPE_COURSE,
                    'integration' => $integration,
                    'referenceId' => $pronoteId
                ]);
            }
            
            // Créer un nouvel événement si non trouvé
            if (!$planning) {
                $planning = new Planning();
                $planning->setUser($student);
                $count++;
            } else {
                $updated++;
            }
            
            // Toujours définir l'integration et referenceId (même lors de la mise à jour)
            $planning->setIntegration($integration);
            $planning->setReferenceId($pronoteId);
            
            // Parser les dates de début et de fin (format ISO string UTC)
            // Les dates Pronote sont en UTC (GMT), il faut les convertir en Europe/Paris (+1h ou +2h selon période)
            $startDate = null;
            $endDate = null;
            $timezone = new \DateTimeZone('Europe/Paris');
            
            if (isset($lesson['start']) && $lesson['start']) {
                try {
                    // Parser la date ISO en UTC
                    $startDate = new \DateTimeImmutable($lesson['start'], new \DateTimeZone('UTC'));
                    // Convertir en timezone Europe/Paris (ajoute automatiquement +1h ou +2h selon période)
                    $startDate = $startDate->setTimezone($timezone);
                } catch (\Exception $e) {
                    error_log("PRONOTE: Erreur parsing date début cours: " . $e->getMessage() . " - Valeur: " . ($lesson['start'] ?? 'null'));
                    $startDate = new \DateTimeImmutable('now', $timezone);
                }
            } else {
                $startDate = new \DateTimeImmutable('now', $timezone);
            }
            
            if (isset($lesson['end']) && $lesson['end']) {
                try {
                    // Parser la date ISO en UTC
                    $endDate = new \DateTimeImmutable($lesson['end'], new \DateTimeZone('UTC'));
                    // Convertir en timezone Europe/Paris (ajoute automatiquement +1h ou +2h selon période)
                    $endDate = $endDate->setTimezone($timezone);
                } catch (\Exception $e) {
                    error_log("PRONOTE: Erreur parsing date fin cours: " . $e->getMessage() . " - Valeur: " . ($lesson['end'] ?? 'null'));
                    $endDate = $startDate->modify('+1 hour');
                }
            } else {
                $endDate = $startDate->modify('+1 hour');
            }
            
            // Titre : sujet + heures de début et fin
            $subject = trim($lesson['subject'] ?? 'Cours');
            if ($subject === 'N/A' || empty($subject)) {
                $subject = 'Cours';
            }
            
            // Ajouter les heures dans le titre (format: "MATHEMATIQUES - 08:00-09:00")
            $startTime = $startDate->format('H:i');
            $endTime = $endDate->format('H:i');
            $title = $subject . ' - ' . $startTime . '-' . $endTime;
            $planning->setTitle($title);
            
            // Description : mettre le subject
            $planning->setDescription($subject);
            
            $planning->setType(Planning::TYPE_COURSE);
            $planning->setStatus(Planning::STATUS_TO_DO);

            $planning->setStartDate($startDate);
            $planning->setEndDate($endDate);

            // Mettre à jour les métadonnées PRONOTE (inclure backgroundColor)
            $raw = $lesson['raw'] ?? [];
            $metadata = $planning->getMetadata() ?? [];
            $metadata['pronote_id'] = $pronoteId;
            $metadata['pronote_subject'] = $lesson['subject'] ?? null;
            $metadata['pronote_room'] = $lesson['room'] ?? null;
            $metadata['pronote_backgroundColor'] = $raw['backgroundColor'] ?? null;
            $metadata['pronote_raw'] = $lesson;
            $planning->setMetadata($metadata);

            $this->em->persist($planning);
        }

        $this->em->flush();
        error_log(sprintf("PRONOTE: %d cours créés, %d cours mis à jour", $count, $updated));
        return $count + $updated;
    }

    /**
     * Synchronise les absences dans Planning
     */
    private function syncAbsences(array $absences, Student $student): int
    {
        $count = 0;

        foreach ($absences as $absence) {
            $date = new \DateTimeImmutable($absence['date'] ?? 'now');
            $existing = $this->planningRepository->findOneBy([
                'user' => $student,
                'type' => Planning::TYPE_OTHER,
                'startDate' => $date,
            ]);

            if ($existing) {
                continue;
            }

            $planning = new Planning();
            $planning->setUser($student);
            $planning->setTitle('Absence');
            $planning->setDescription($absence['reason'] ?? '');
            $planning->setType(Planning::TYPE_OTHER);
            $planning->setStatus(Planning::STATUS_COMPLETED);

            $planning->setStartDate($date);
            $planning->setEndDate($date->modify('+1 day'));

            // Stocker les métadonnées PRONOTE
            $planning->setMetadata([
                'pronote_id' => $absence['id'] ?? null,
                'pronote_type' => 'absence',
                'pronote_reason' => $absence['reason'] ?? null,
                'pronote_raw' => $absence,
            ]);

            $this->em->persist($planning);
            $count++;
        }

        $this->em->flush();
        return $count;
    }

    /**
     * Synchronise les données PRONOTE déjà récupérées (utilisé après connexion QR code)
     */
    public function syncIntegrationData(Integration $integration, array $data): array
    {
        if ($integration->getType() !== Integration::TYPE_PRONOTE) {
            throw new \InvalidArgumentException('Cette intégration n\'est pas de type PRONOTE');
        }

        if (!$integration->getStudent()) {
            throw new \InvalidArgumentException('Aucun élève associé à cette intégration');
        }

        $student = $integration->getStudent();
        $results = [
            'homework' => 0,
            'lessons' => 0,
            'absences' => 0,
            'notes' => [],
            'carnet' => [],
        ];

        // Synchroniser les devoirs
        if (isset($data['assignments']) && is_array($data['assignments'])) {
            $results['homework'] = $this->syncHomework($data['assignments'], $student, $integration);
        }

        // Synchroniser les cours
        if (isset($data['lessons_list']) && is_array($data['lessons_list'])) {
            $results['lessons'] = $this->syncLessons($data['lessons_list'], $student, $integration);
        }

        // Stocker les évaluations/notes dans metadata
        // Toujours sauvegarder, même si vide, pour indiquer que la synchronisation a été effectuée
        $metadata = $integration->getMetadata() ?? [];
        if (isset($data['evaluations'])) {
            $metadata['evaluations'] = is_array($data['evaluations']) ? $data['evaluations'] : [];
        }

        // Stocker le bulletin de notes dans metadata
        if (isset($data['gradebook'])) {
            $metadata['gradebook'] = $data['gradebook']; // Peut être null
        }

        // Synchroniser les absences
        if (isset($data['absences']) && is_array($data['absences'])) {
            $results['absences'] = $this->syncAbsences($data['absences'], $student);
        }

        // Stocker le carnet de correspondance dans metadata
        // Toujours sauvegarder, même si vide, pour indiquer que la synchronisation a été effectuée
        if (isset($data['notebook'])) {
            $notebookData = is_array($data['notebook']) ? $data['notebook'] : [];
            $metadata['notebook'] = $notebookData;
            $metadata['carnet_correspondance'] = $notebookData; // Compatibilité legacy
        }
        
        $integration->setMetadata($metadata);

        // Mettre à jour sync_data dans les métadonnées avec tous les résultats
        $metadata = $integration->getMetadata() ?? [];
        if (!isset($metadata['sync_data'])) {
            $metadata['sync_data'] = [];
        }
        $metadata['sync_data']['homework'] = $results['homework'];
        $metadata['sync_data']['lessons'] = $results['lessons'];
        $metadata['sync_data']['absences'] = $results['absences'];
        $integration->setMetadata($metadata);

        // Mettre à jour lastSyncAt
        $integration->setLastSyncAt(new \DateTimeImmutable());
        $integration->setUpdatedAt(new \DateTimeImmutable());
        $this->em->persist($integration);
        $this->em->flush();

        return $results;
    }

}

