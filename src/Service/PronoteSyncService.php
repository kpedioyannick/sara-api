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

        // Appeler le script Python pour récupérer les données PRONOTE
        $scriptPath = __DIR__ . '/../../scripts/pronote_fetch_data.py';
        $process = new Process([
            'python3',
            $scriptPath,
            json_encode($credentials)
        ]);

        $process->run();

        if (!$process->isSuccessful()) {
            throw new \RuntimeException('Erreur lors de la récupération des données PRONOTE: ' . $process->getErrorOutput());
        }

        $output = $process->getOutput();
        $data = json_decode($output, true);

        if (!$data || !$data['success']) {
            throw new \RuntimeException('Échec de la récupération des données: ' . ($data['error'] ?? 'Erreur inconnue'));
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
        if (isset($data['homework']) && is_array($data['homework'])) {
            $results['homework'] = $this->syncHomework($data['homework'], $student);
        }

        // Synchroniser les cours
        if (isset($data['lessons']) && is_array($data['lessons'])) {
            $results['lessons'] = $this->syncLessons($data['lessons'], $student);
        }

        // Synchroniser les absences
        if (isset($data['absences']) && is_array($data['absences'])) {
            $results['absences'] = $this->syncAbsences($data['absences'], $student);
        }

        // Stocker les notes dans metadata
        if (isset($data['grades']) && is_array($data['grades'])) {
            $results['notes'] = $data['grades'];
            $metadata = $integration->getMetadata() ?? [];
            $metadata['notes'] = $data['grades'];
            $integration->setMetadata($metadata);
        }

        // Stocker le carnet de correspondance dans metadata
        if (isset($data['carnet_correspondance']) && is_array($data['carnet_correspondance'])) {
            $results['carnet'] = $data['carnet_correspondance'];
            $metadata = $integration->getMetadata() ?? [];
            $metadata['carnet_correspondance'] = $data['carnet_correspondance'];
            $integration->setMetadata($metadata);
        }

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

        foreach ($homework as $hw) {
            // Vérifier si le devoir existe déjà (par date et titre)
            $existing = $this->planningRepository->findOneBy([
                'student' => $student,
                'type' => Planning::TYPE_HOMEWORK,
                'startDate' => new \DateTimeImmutable($hw['date'] ?? 'now'),
            ]);

            if ($existing) {
                continue; // Déjà synchronisé
            }

            $planning = new Planning();
            $planning->setStudent($student);
            $planning->setTitle($hw['subject'] ?? 'Devoir');
            $planning->setDescription($hw['description'] ?? '');
            $planning->setType(Planning::TYPE_HOMEWORK);
            $planning->setStatus(Planning::STATUS_TO_DO);

            if (isset($hw['date'])) {
                $date = new \DateTimeImmutable($hw['date']);
                $planning->setStartDate($date);
                $planning->setEndDate($date->modify('+1 hour'));
            } else {
                $now = new \DateTimeImmutable();
                $planning->setStartDate($now);
                $planning->setEndDate($now->modify('+1 hour'));
            }

            // Stocker les métadonnées PRONOTE
            $planning->setMetadata([
                'pronote_id' => $hw['id'] ?? null,
                'pronote_subject' => $hw['subject'] ?? null,
                'pronote_raw' => $hw,
            ]);

            $this->em->persist($planning);
            $count++;
        }

        $this->em->flush();
        return $count;
    }

    /**
     * Synchronise les cours dans Planning
     */
    private function syncLessons(array $lessons, Student $student): int
    {
        $count = 0;

        foreach ($lessons as $lesson) {
            // Vérifier si le cours existe déjà
            $startDate = new \DateTimeImmutable($lesson['start'] ?? 'now');
            $existing = $this->planningRepository->findOneBy([
                'student' => $student,
                'type' => Planning::TYPE_COURSE,
                'startDate' => $startDate,
            ]);

            if ($existing) {
                continue;
            }

            $planning = new Planning();
            $planning->setStudent($student);
            $planning->setTitle($lesson['subject'] ?? 'Cours');
            $planning->setDescription($lesson['room'] ?? '');
            $planning->setType(Planning::TYPE_COURSE);
            $planning->setStatus(Planning::STATUS_TO_DO);

            $planning->setStartDate($startDate);
            $endDate = isset($lesson['end']) ? new \DateTimeImmutable($lesson['end']) : $startDate->modify('+1 hour');
            $planning->setEndDate($endDate);

            // Stocker les métadonnées PRONOTE
            $planning->setMetadata([
                'pronote_id' => $lesson['id'] ?? null,
                'pronote_subject' => $lesson['subject'] ?? null,
                'pronote_room' => $lesson['room'] ?? null,
                'pronote_raw' => $lesson,
            ]);

            $this->em->persist($planning);
            $count++;
        }

        $this->em->flush();
        return $count;
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
}

