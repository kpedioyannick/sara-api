<?php

namespace App\Command;

use App\Entity\Path\Chapter;
use App\Entity\Path\Module;
use App\Entity\Path\Path;
use App\Entity\Path\SubChapter;
use App\Entity\User;
use App\Enum\ModuleType;
use App\Repository\ChapterRepository;
use App\Repository\PathRepository;
use App\Repository\SubChapterRepository;
use App\Repository\UserRepository;
use App\Service\Path\PathGenerationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:generate-path',
    description: 'Génère le contenu H5P pour un parcours à partir d\'un chapitre, sous-chapitre et modules',
)]
class GeneratePathCommand extends Command
{
    public function __construct(
        private readonly PathRepository $pathRepository,
        private readonly ChapterRepository $chapterRepository,
        private readonly SubChapterRepository $subChapterRepository,
        private readonly UserRepository $userRepository,
        private readonly PathGenerationService $pathGenerationService,
        private readonly EntityManagerInterface $em
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('chapter-id', InputArgument::REQUIRED, 'ID du chapitre')
            ->addArgument('subchapter-id', InputArgument::OPTIONAL, 'ID du sous-chapitre (optionnel)')
            ->addOption('modules', 'm', InputOption::VALUE_REQUIRED, 'Liste des modules au format JSON: [{"type":"MultiChoice","description":"..."},...]')
            ->addOption('modules-file', 'f', InputOption::VALUE_REQUIRED, 'Fichier JSON contenant la liste des modules')
            ->addOption('user-id', 'u', InputOption::VALUE_REQUIRED, 'ID de l\'utilisateur (par défaut: premier coach trouvé)')
            ->addOption('title', 't', InputOption::VALUE_REQUIRED, 'Titre du parcours (par défaut: généré automatiquement)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $chapterId = $input->getArgument('chapter-id');
        $subChapterId = $input->getArgument('subchapter-id');
        $modulesJson = $input->getOption('modules');
        $modulesFile = $input->getOption('modules-file');
        $userId = $input->getOption('user-id');
        $title = $input->getOption('title');

        $io->title('🎯 Génération de parcours H5P');

        try {
            // Récupérer le chapitre
            $chapter = $this->chapterRepository->find($chapterId);
            if (!$chapter) {
                $io->error(sprintf('Chapitre avec l\'ID %s introuvable', $chapterId));
                return Command::FAILURE;
            }

            // Récupérer le sous-chapitre si fourni
            $subChapter = null;
            if ($subChapterId) {
                $subChapter = $this->subChapterRepository->find($subChapterId);
                if (!$subChapter) {
                    $io->error(sprintf('Sous-chapitre avec l\'ID %s introuvable', $subChapterId));
                    return Command::FAILURE;
                }
                // Vérifier que le sous-chapitre appartient au chapitre
                if ($subChapter->getChapter()->getId() !== $chapter->getId()) {
                    $io->error('Le sous-chapitre n\'appartient pas au chapitre spécifié');
                    return Command::FAILURE;
                }
            }

            // Récupérer les modules
            $modulesData = $this->parseModules($modulesJson, $modulesFile, $io);
            if (empty($modulesData)) {
                $io->error('Aucun module fourni. Utilisez --modules ou --modules-file');
                return Command::FAILURE;
            }

            // Récupérer l'utilisateur
            $user = $this->getUser($userId, $io);
            if (!$user) {
                return Command::FAILURE;
            }

            // Créer le titre si non fourni
            if (!$title) {
                $title = sprintf(
                    'Parcours - %s%s',
                    $chapter->getName(),
                    $subChapter ? ' - ' . $subChapter->getName() : ''
                );
            }

            // Créer le Path
            $path = new Path();
            $path->setTitle($title);
            $path->setChapter($chapter);
            $path->setSubChapter($subChapter);
            $path->setUser($user);
            $path->setStatus(Path::STATUS_DRAFT);
            $path->setDescription(sprintf(
                'Parcours généré pour le chapitre "%s"%s',
                $chapter->getName(),
                $subChapter ? sprintf(' et le sous-chapitre "%s"', $subChapter->getName()) : ''
            ));

            // Créer les modules
            $io->section('Création des modules...');
            $order = 0;
            foreach ($modulesData as $moduleData) {
                $moduleType = ModuleType::from($moduleData['type']);
                $module = new Module();
                $module->setTitle($moduleData['type'] . ' - Module ' . ($order + 1));
                $module->setDescription($moduleData['description'] ?? '');
                $module->setType($moduleType);
                $module->setOrder($order++);
                $path->addModule($module);
            }

            $this->em->persist($path);
            $this->em->flush();

            $io->success(sprintf('✅ Parcours créé avec l\'ID %d et %d module(s)', $path->getId(), count($modulesData)));

            // Générer le contenu H5P
            $io->section('Génération du contenu H5P...');
            $result = $this->pathGenerationService->generateModulesFromPath($path);

            if ($result['success']) {
                $io->success(sprintf(
                    '✅ Parcours "%s" généré avec succès ! (%d modules)',
                    $path->getTitle(),
                    count($result['generated_modules'])
                ));
                $path->setStatus(Path::STATUS_GENERATED);
                $this->em->flush();
                return Command::SUCCESS;
            } else {
                $io->warning(sprintf(
                    '⚠️ Parcours "%s" généré avec des erreurs. (%d modules générés, %d erreurs)',
                    $path->getTitle(),
                    count($result['generated_modules']),
                    count($result['errors'])
                ));
                foreach ($result['errors'] as $error) {
                    $io->error(sprintf(
                        '  - Module "%s" (ID: %s): %s',
                        $error['module_title'],
                        $error['module_id'],
                        $error['error']
                    ));
                }
                return Command::FAILURE;
            }

        } catch (\Exception $e) {
            $io->error('Erreur : ' . $e->getMessage());
            if ($io->isVerbose()) {
                $io->writeln($e->getTraceAsString());
            }
            return Command::FAILURE;
        }
    }

    private function parseModules(?string $modulesJson, ?string $modulesFile, SymfonyStyle $io): array
    {
        $modulesData = [];

        if ($modulesFile) {
            if (!file_exists($modulesFile)) {
                $io->error(sprintf('Fichier introuvable : %s', $modulesFile));
                return [];
            }
            $content = file_get_contents($modulesFile);
            $modulesData = json_decode($content, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $io->error('Erreur de parsing JSON dans le fichier : ' . json_last_error_msg());
                return [];
            }
        } elseif ($modulesJson) {
            $modulesData = json_decode($modulesJson, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $io->error('Erreur de parsing JSON : ' . json_last_error_msg());
                return [];
            }
        }

        // Valider le format
        if (!is_array($modulesData)) {
            $io->error('Les modules doivent être un tableau JSON');
            return [];
        }

        // Valider chaque module
        $validModules = [];
        foreach ($modulesData as $index => $moduleData) {
            if (!isset($moduleData['type'])) {
                $io->warning(sprintf('Module #%d : type manquant, ignoré', $index + 1));
                continue;
            }

            // Vérifier que le type existe
            try {
                ModuleType::from($moduleData['type']);
            } catch (\ValueError $e) {
                $io->warning(sprintf('Module #%d : type "%s" invalide, ignoré', $index + 1, $moduleData['type']));
                continue;
            }

            $validModules[] = [
                'type' => $moduleData['type'],
                'description' => $moduleData['description'] ?? ''
            ];
        }

        return $validModules;
    }

    private function getUser(?string $userId, SymfonyStyle $io): ?User
    {
        if ($userId) {
            $user = $this->userRepository->find($userId);
            if (!$user) {
                $io->error(sprintf('Utilisateur avec l\'ID %s introuvable', $userId));
                return null;
            }
            return $user;
        }

        // Chercher le premier coach
        $coach = $this->userRepository->createQueryBuilder('u')
            ->where('u INSTANCE OF App\Entity\Coach')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$coach) {
            $io->error('Aucun coach trouvé. Utilisez --user-id pour spécifier un utilisateur');
            return null;
        }

        $io->info(sprintf('Utilisation de l\'utilisateur : %s (ID: %d)', $coach->getEmail(), $coach->getId()));
        return $coach;
    }

}

