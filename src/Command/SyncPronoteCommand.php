<?php

namespace App\Command;

use App\Entity\Integration;
use App\Repository\IntegrationRepository;
use App\Service\PronoteSyncService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:sync-pronote',
    description: 'Synchronise les données PRONOTE pour toutes les intégrations actives'
)]
class SyncPronoteCommand extends Command
{
    public function __construct(
        private readonly IntegrationRepository $integrationRepository,
        private readonly PronoteSyncService $pronoteSyncService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('integration-id', 'i', InputOption::VALUE_OPTIONAL, 'ID de l\'intégration à synchroniser')
            ->addOption('student-id', 's', InputOption::VALUE_OPTIONAL, 'ID de l\'étudiant à synchroniser')
            ->addOption('all', 'a', InputOption::VALUE_NONE, 'Synchroniser toutes les intégrations actives')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Forcer la synchronisation même si récente');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $integrationId = $input->getOption('integration-id');
        $studentId = $input->getOption('student-id');
        $all = $input->getOption('all');
        $force = $input->getOption('force');

        // Déterminer quelles intégrations synchroniser
        $integrations = [];

        if ($integrationId) {
            $integration = $this->integrationRepository->find($integrationId);
            if (!$integration) {
                $io->error("Intégration #{$integrationId} non trouvée");
                return Command::FAILURE;
            }
            if ($integration->getType() !== Integration::TYPE_PRONOTE) {
                $io->error("L'intégration #{$integrationId} n'est pas de type PRONOTE");
                return Command::FAILURE;
            }
            $integrations = [$integration];
        } elseif ($studentId) {
            $integrations = array_filter(
                $this->integrationRepository->findBy([
                    'type' => Integration::TYPE_PRONOTE,
                    'isActive' => true,
                ]),
                fn($integration) => $integration->getStudent() && $integration->getStudent()->getId() == $studentId
            );
            if (empty($integrations)) {
                $io->warning("Aucune intégration PRONOTE active trouvée pour l'étudiant #{$studentId}");
                return Command::SUCCESS;
            }
        } elseif ($all || (!$integrationId && !$studentId)) {
            // Par défaut, synchroniser toutes les intégrations actives
            $integrations = $this->integrationRepository->findBy([
                'type' => Integration::TYPE_PRONOTE,
                'isActive' => true,
            ]);
            if (empty($integrations)) {
                $io->warning('Aucune intégration PRONOTE active trouvée');
                return Command::SUCCESS;
            }
        }

        $io->title('Synchronisation PRONOTE');
        $io->info(sprintf('Synchronisation de %d intégration(s)', count($integrations)));

        $successCount = 0;
        $errorCount = 0;

        foreach ($integrations as $integration) {
            $student = $integration->getStudent();
            $studentName = $student 
                ? $student->getFirstName() . ' ' . $student->getLastName()
                : 'Non associé';

            $io->section("Intégration #{$integration->getId()} - {$studentName}");

            // Vérifier si la synchronisation est nécessaire
            if (!$force && $integration->getLastSyncAt()) {
                $lastSync = $integration->getLastSyncAt();
                $now = new \DateTimeImmutable();
                $diff = $now->diff($lastSync);

                // Si synchronisé il y a moins d'une heure, skip
                if ($diff->h < 1 && $diff->days === 0) {
                    $io->note("Synchronisation récente (il y a {$diff->i} minutes), ignorée. Utilisez --force pour forcer.");
                    continue;
                }
            }

            try {
                $results = $this->pronoteSyncService->syncIntegration($integration);

                $io->success([
                    'Synchronisation réussie',
                    sprintf('Devoirs: %d', $results['homework']),
                    sprintf('Cours: %d', $results['lessons']),
                    sprintf('Absences: %d', $results['absences']),
                    sprintf('Notes: %d', count($results['notes'])),
                ]);

                $successCount++;
            } catch (\Exception $e) {
                $io->error("Erreur: " . $e->getMessage());
                $errorCount++;
            }
        }

        $io->newLine();
        if ($successCount > 0) {
            $io->success("{$successCount} intégration(s) synchronisée(s) avec succès");
        }
        if ($errorCount > 0) {
            $io->error("{$errorCount} erreur(s) rencontrée(s)");
        }

        return $errorCount > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}

