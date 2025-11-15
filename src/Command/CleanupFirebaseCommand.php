<?php

namespace App\Command;

use App\Service\FirebaseService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:cleanup-firebase',
    description: 'Nettoie les anciens messages de Firebase Realtime Database (garder uniquement les 24 dernières heures)'
)]
class CleanupFirebaseCommand extends Command
{
    public function __construct(
        private readonly FirebaseService $firebaseService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('hours', null, InputOption::VALUE_OPTIONAL, 'Nombre d\'heures à conserver (défaut: 24)', 24)
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Afficher ce qui serait supprimé sans supprimer');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $hours = (int) $input->getOption('hours');
        $dryRun = $input->getOption('dry-run');

        if (!$this->firebaseService->isConfigured()) {
            $io->error('Firebase n\'est pas configuré. Vérifiez vos variables d\'environnement.');
            return Command::FAILURE;
        }

        $io->title('🧹 Nettoyage Firebase Realtime Database');

        if ($dryRun) {
            $io->warning('Mode DRY-RUN : aucune suppression ne sera effectuée');
        }

        $io->info("Suppression des messages de plus de {$hours} heures...");

        try {
            $cutoffTime = time() - ($hours * 3600);
            $cutoffDate = date('Y-m-d H:i:s', $cutoffTime);

            // Nettoyer les messages des conversations
            $this->cleanupPath($io, '/conversations', $cutoffDate, $dryRun);

            // Nettoyer les messages des requests
            $this->cleanupPath($io, '/requests', $cutoffDate, $dryRun);

            // Nettoyer les notifications
            $this->cleanupPath($io, '/notifications', $cutoffDate, $dryRun);

            if (!$dryRun) {
                $io->success("✅ Nettoyage terminé ! Messages de plus de {$hours} heures supprimés.");
            } else {
                $io->info("✅ DRY-RUN terminé. Relancez sans --dry-run pour effectuer la suppression.");
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Erreur lors du nettoyage : ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function cleanupPath(SymfonyStyle $io, string $basePath, string $cutoffDate, bool $dryRun): void
    {
        try {
            $database = $this->firebaseService->getDatabase();
            if (!$database) {
                return;
            }

            $ref = $database->getReference($basePath);
            $snapshot = $ref->getSnapshot();

            if (!$snapshot->exists()) {
                return;
            }

            $deletedCount = 0;
            $this->recursiveCleanup($ref, $snapshot, $cutoffDate, $dryRun, $deletedCount);

            if ($deletedCount > 0) {
                $io->info("  {$basePath}: {$deletedCount} messages supprimés");
            }
        } catch (\Exception $e) {
            $io->warning("  {$basePath}: Erreur - " . $e->getMessage());
        }
    }

    private function recursiveCleanup($ref, $snapshot, string $cutoffDate, bool $dryRun, int &$count): void
    {
        if (!$snapshot->exists()) {
            return;
        }

        foreach ($snapshot->getValue() as $key => $value) {
            $childRef = $ref->getChild($key);

            // Si c'est un message avec createdAt
            if (is_array($value) && isset($value['createdAt'])) {
                $messageDate = $value['createdAt'];
                if ($messageDate < $cutoffDate) {
                    if (!$dryRun) {
                        $childRef->remove();
                    }
                    $count++;
                }
            } else {
                // Récursion pour les sous-nœuds
                $childSnapshot = $childRef->getSnapshot();
                if ($childSnapshot->exists()) {
                    $this->recursiveCleanup($childRef, $childSnapshot, $cutoffDate, $dryRun, $count);
                }
            }
        }
    }
}

