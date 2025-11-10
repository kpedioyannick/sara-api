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
                        $jsonLine = substr($jsonLine, $firstBrace, $lastBrace - $firstBrace + 1);
                        $data = json_decode($jsonLine, true);
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
                        $jsonLine = substr($output, $startBrace, $lastBrace - $startBrace + 1);
                        $data = json_decode($jsonLine, true);
                    }
                }
            }
            
            // Log pour debug si le parsing échoue
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log("PRONOTE Sync - JSON decode error: " . json_last_error_msg());
                error_log("PRONOTE Sync - Raw output (last 1000): " . substr($output, -1000));
            }
        }
        
        // Si on n'a toujours pas de JSON, chercher dans stderr (au cas où)
        if (!$data && !empty($errorOutput)) {
            $firstBrace = strpos($errorOutput, '{');
            if ($firstBrace !== false) {
                $lastBrace = strrpos($errorOutput, '}');
                if ($lastBrace !== false && $lastBrace > $firstBrace) {
                    $jsonLine = substr($errorOutput, $firstBrace, $lastBrace - $firstBrace + 1);
                    $data = json_decode($jsonLine, true);
                }
            }
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
            $results['homework'] = $this->syncHomework($data['data']['assignments'], $student);
        } elseif (isset($data['homework']) && is_array($data['homework'])) {
            // Format legacy
            $results['homework'] = $this->syncHomework($data['homework'], $student);
        }

        // Synchroniser les cours (format Pawnote.js: data.lessons_list)
        if (isset($data['data']['lessons_list']) && is_array($data['data']['lessons_list'])) {
            $results['lessons'] = $this->syncLessons($data['data']['lessons_list'], $student);
        } elseif (isset($data['lessons']) && is_array($data['lessons'])) {
            // Format legacy
            $results['lessons'] = $this->syncLessons($data['lessons'], $student);
        }

        // Synchroniser les absences
        if (isset($data['absences']) && is_array($data['absences'])) {
            $results['absences'] = $this->syncAbsences($data['absences'], $student);
        }

        // Stocker les évaluations/notes dans metadata (format Pawnote.js: data.evaluations)
        if (isset($data['data']['evaluations']) && is_array($data['data']['evaluations'])) {
            $results['notes'] = $data['data']['evaluations'];
            $metadata = $integration->getMetadata() ?? [];
            $metadata['evaluations'] = $data['data']['evaluations'];
            $integration->setMetadata($metadata);
        } elseif (isset($data['grades']) && is_array($data['grades'])) {
            // Format legacy
            $results['notes'] = $data['grades'];
            $metadata = $integration->getMetadata() ?? [];
            $metadata['notes'] = $data['grades'];
            $integration->setMetadata($metadata);
        }

        // Stocker le bulletin de notes dans metadata (format Pawnote.js: data.gradebook)
        if (isset($data['data']['gradebook']) && $data['data']['gradebook']) {
            $metadata = $integration->getMetadata() ?? [];
            $metadata['gradebook'] = $data['data']['gradebook'];
            $integration->setMetadata($metadata);
        }

        // Stocker le carnet de correspondance dans metadata (format Pawnote.js: data.notebook)
        if (isset($data['data']['notebook']) && is_array($data['data']['notebook'])) {
            $results['carnet'] = $data['data']['notebook'];
            $metadata = $integration->getMetadata() ?? [];
            $metadata['notebook'] = $data['data']['notebook'];
            $metadata['carnet_correspondance'] = $data['data']['notebook']; // Compatibilité legacy
            $integration->setMetadata($metadata);
        } elseif (isset($data['carnet_correspondance']) && is_array($data['carnet_correspondance'])) {
            // Format legacy
            $results['carnet'] = $data['carnet_correspondance'];
            $metadata = $integration->getMetadata() ?? [];
            $metadata['carnet_correspondance'] = $data['carnet_correspondance'];
            $integration->setMetadata($metadata);
        }

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
    private function syncHomework(array $homework, Student $student): int
    {
        $count = 0;
        $updated = 0;

        foreach ($homework as $hw) {
            $pronoteId = $hw['id'] ?? null;
            $planning = null;
            
            // Chercher un événement existant par ID PRONOTE
            if ($pronoteId) {
                $existingId = $this->em->getConnection()->executeQuery(
                    'SELECT id FROM planning WHERE student_id = ? AND type = ? AND JSON_EXTRACT(metadata, \'$.pronote_id\') = ? LIMIT 1',
                    [$student->getId(), Planning::TYPE_HOMEWORK, $pronoteId]
                )->fetchOne();
                
                if ($existingId) {
                    $planning = $this->em->getRepository(Planning::class)->find($existingId);
                }
            }
            
            // Créer un nouvel événement si non trouvé
            if (!$planning) {
                $planning = new Planning();
                $planning->setStudent($student);
                $count++;
            } else {
                $updated++;
            }
            
            // Préparer la date (format YYYY-MM-DD ou ISO string)
            $date = null;
            if (isset($hw['date']) && $hw['date']) {
                try {
                    // Si c'est juste une date (YYYY-MM-DD), créer à minuit
                    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $hw['date'])) {
                        $date = new \DateTimeImmutable($hw['date'] . ' 00:00:00');
                    } else {
                        $date = new \DateTimeImmutable($hw['date']);
                    }
                } catch (\Exception $e) {
                    error_log("PRONOTE: Erreur parsing date devoir: " . $e->getMessage());
                    $date = new \DateTimeImmutable('now');
                }
            } else {
                $date = new \DateTimeImmutable('now');
            }
            
            // Titre : sujet + date/heure si disponible
            $subject = trim($hw['subject'] ?? 'Devoir');
            if ($subject === 'N/A' || empty($subject)) {
                $subject = 'Devoir';
            }
            
            // Ajouter l'heure dans le titre si disponible
            $title = $subject;
            if ($date) {
                $timeStr = $date->format('H:i');
                if ($timeStr !== '00:00') {
                    $title = $subject . ' - ' . $timeStr;
                } else {
                    // Si pas d'heure, afficher juste la date
                    $title = $subject . ' - ' . $date->format('d/m/Y');
                }
            }
            $planning->setTitle($title);
            
            // Description : utiliser la description du devoir
            $description = trim($hw['description'] ?? '');
            $planning->setDescription($description);
            
            $planning->setType(Planning::TYPE_HOMEWORK);
            $planning->setStatus($hw['done'] ?? false ? Planning::STATUS_COMPLETED : Planning::STATUS_TO_DO);

            // Définir les dates (début à la date, fin 1h après ou fin de journée)
            $planning->setStartDate($date);
            // Pour les devoirs, mettre fin à la fin de la journée (23:59:59)
            $endDate = $date->setTime(23, 59, 59);
            $planning->setEndDate($endDate);

            // Mettre à jour les métadonnées PRONOTE
            $metadata = $planning->getMetadata() ?? [];
            $metadata['pronote_id'] = $pronoteId;
            $metadata['pronote_subject'] = $hw['subject'] ?? null;
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
    private function syncLessons(array $lessons, Student $student): int
    {
        $count = 0;
        $updated = 0;

        foreach ($lessons as $lesson) {
            $pronoteId = $lesson['id'] ?? null;
            $planning = null;
            
            // Chercher un événement existant par ID PRONOTE
            if ($pronoteId) {
                $existingId = $this->em->getConnection()->executeQuery(
                    'SELECT id FROM planning WHERE student_id = ? AND type = ? AND JSON_EXTRACT(metadata, \'$.pronote_id\') = ? LIMIT 1',
                    [$student->getId(), Planning::TYPE_COURSE, $pronoteId]
                )->fetchOne();
                
                if ($existingId) {
                    $planning = $this->em->getRepository(Planning::class)->find($existingId);
                }
            }
            
            // Créer un nouvel événement si non trouvé
            if (!$planning) {
                $planning = new Planning();
                $planning->setStudent($student);
                $count++;
            } else {
                $updated++;
            }
            
            // Parser les dates de début et de fin (format ISO string UTC)
            $startDate = null;
            $endDate = null;
            
            if (isset($lesson['start']) && $lesson['start']) {
                try {
                    // Parser la date ISO (peut être en UTC)
                    $startDate = new \DateTimeImmutable($lesson['start']);
                    // Si la date est en UTC, la convertir en timezone locale (Europe/Paris par défaut)
                    // PHP gère automatiquement le timezone lors du parsing, mais on peut forcer la timezone
                    $timezone = new \DateTimeZone('Europe/Paris');
                    if ($startDate->getTimezone()->getName() === 'UTC' || $startDate->getTimezone()->getName() === '+00:00') {
                        $startDate = $startDate->setTimezone($timezone);
                    }
                } catch (\Exception $e) {
                    error_log("PRONOTE: Erreur parsing date début cours: " . $e->getMessage() . " - Valeur: " . ($lesson['start'] ?? 'null'));
                    $startDate = new \DateTimeImmutable('now');
                }
            } else {
                $startDate = new \DateTimeImmutable('now');
            }
            
            if (isset($lesson['end']) && $lesson['end']) {
                try {
                    // Parser la date ISO (peut être en UTC)
                    $endDate = new \DateTimeImmutable($lesson['end']);
                    // Si la date est en UTC, la convertir en timezone locale
                    $timezone = new \DateTimeZone('Europe/Paris');
                    if ($endDate->getTimezone()->getName() === 'UTC' || $endDate->getTimezone()->getName() === '+00:00') {
                        $endDate = $endDate->setTimezone($timezone);
                    }
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
            
            // Description : construire une description complète avec la salle, le professeur, etc.
            $descriptionParts = [];
            if (!empty($lesson['room'])) {
                $descriptionParts[] = 'Salle: ' . trim($lesson['room']);
            }
            if (!empty($lesson['teacher'])) {
                $descriptionParts[] = 'Professeur: ' . trim($lesson['teacher']);
            }
            if (!empty($lesson['group'])) {
                $descriptionParts[] = 'Groupe: ' . trim($lesson['group']);
            }
            // Si d'autres informations sont disponibles dans raw, on peut les ajouter
            if (isset($lesson['raw']) && is_array($lesson['raw'])) {
                $raw = $lesson['raw'];
                // Si teacher n'est pas déjà dans descriptionParts, essayer de le récupérer depuis raw
                if (empty($lesson['teacher']) && !empty($raw['teacher'])) {
                    if (is_string($raw['teacher'])) {
                        $descriptionParts[] = 'Professeur: ' . trim($raw['teacher']);
                    } elseif (is_array($raw['teacher']) && !empty($raw['teacher']['name'])) {
                        $descriptionParts[] = 'Professeur: ' . trim($raw['teacher']['name']);
                    }
                }
            }
            $description = !empty($descriptionParts) ? implode(' | ', $descriptionParts) : '';
            $planning->setDescription($description);
            
            $planning->setType(Planning::TYPE_COURSE);
            $planning->setStatus(Planning::STATUS_TO_DO);

            $planning->setStartDate($startDate);
            $planning->setEndDate($endDate);

            // Mettre à jour les métadonnées PRONOTE
            $metadata = $planning->getMetadata() ?? [];
            $metadata['pronote_id'] = $pronoteId;
            $metadata['pronote_subject'] = $lesson['subject'] ?? null;
            $metadata['pronote_room'] = $lesson['room'] ?? null;
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
                'student' => $student,
                'type' => Planning::TYPE_OTHER,
                'startDate' => $date,
            ]);

            if ($existing) {
                continue;
            }

            $planning = new Planning();
            $planning->setStudent($student);
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
            $results['homework'] = $this->syncHomework($data['assignments'], $student);
        }

        // Synchroniser les cours
        if (isset($data['lessons_list']) && is_array($data['lessons_list'])) {
            $results['lessons'] = $this->syncLessons($data['lessons_list'], $student);
        }

        // Stocker les évaluations/notes dans metadata
        if (isset($data['evaluations']) && is_array($data['evaluations'])) {
            $metadata = $integration->getMetadata() ?? [];
            $metadata['evaluations'] = $data['evaluations'];
            $integration->setMetadata($metadata);
        }

        // Stocker le bulletin de notes dans metadata
        if (isset($data['gradebook']) && $data['gradebook']) {
            $metadata = $integration->getMetadata() ?? [];
            $metadata['gradebook'] = $data['gradebook'];
            $integration->setMetadata($metadata);
        }

        // Synchroniser les absences
        if (isset($data['absences']) && is_array($data['absences'])) {
            $results['absences'] = $this->syncAbsences($data['absences'], $student);
        }

        // Stocker le carnet de correspondance dans metadata
        if (isset($data['notebook']) && is_array($data['notebook'])) {
            $metadata = $integration->getMetadata() ?? [];
            $metadata['notebook'] = $data['notebook'];
            $metadata['carnet_correspondance'] = $data['notebook']; // Compatibilité legacy
            $integration->setMetadata($metadata);
        }

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

