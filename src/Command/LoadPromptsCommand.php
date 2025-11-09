<?php

namespace App\Command;

use App\Entity\Path\Chapter;
use App\Entity\Path\SubChapter;
use App\Repository\ChapterRepository;
use App\Repository\ClassroomRepository;
use App\Repository\SubChapterRepository;
use App\Repository\SubjectRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(
    name: 'app:load-prompts',
    description: 'Charge les prompts des chapitres et sous-chapitres depuis l\'API externe',
)]
class LoadPromptsCommand extends Command
{
    private const API_URL = 'https://api.sara.education/api/classroom/subject/chapters';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly HttpClientInterface $httpClient,
        private readonly ClassroomRepository $classroomRepository,
        private readonly SubjectRepository $subjectRepository,
        private readonly ChapterRepository $chapterRepository,
        private readonly SubChapterRepository $subChapterRepository
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('classroom', InputArgument::OPTIONAL, 'Nom de la classe (ex: "6ème")')
            ->addArgument('subject', InputArgument::OPTIONAL, 'Nom de la matière (ex: "Mathématiques")')
            ->addOption('all', 'a', InputOption::VALUE_NONE, 'Charger les prompts pour toutes les combinaisons classe/matière')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Forcer la mise à jour même si les prompts existent déjà');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $classroomName = $input->getArgument('classroom');
        $subjectName = $input->getArgument('subject');
        $all = $input->getOption('all');
        $force = $input->getOption('force');

        $io->title('📝 Chargement des prompts depuis l\'API');

        try {
            if ($all) {
                // Charger pour toutes les combinaisons classe/matière
                return $this->loadAllPrompts($io, $force);
            } elseif ($classroomName && $subjectName) {
                // Charger pour une combinaison spécifique
                return $this->loadPromptsForClassroomSubject($io, $classroomName, $subjectName, $force);
            } else {
                $io->error('Vous devez soit spécifier classroom et subject, soit utiliser --all');
                $io->note('Exemple: php bin/console app:load-prompts "6ème" "Mathématiques"');
                $io->note('Ou: php bin/console app:load-prompts --all');
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

    private function loadAllPrompts(SymfonyStyle $io, bool $force): int
    {
        $io->section('Récupération de toutes les combinaisons classe/matière...');

        // Récupérer toutes les classes
        $classrooms = $this->classroomRepository->findAll();
        if (empty($classrooms)) {
            $io->error('Aucune classe trouvée dans la base de données');
            return Command::FAILURE;
        }

        $stats = [
            'total' => 0,
            'success' => 0,
            'errors' => 0,
            'chapters_updated' => 0,
            'subchapters_updated' => 0,
        ];

        $progressBar = $io->createProgressBar(count($classrooms));
        $progressBar->start();

        foreach ($classrooms as $classroom) {
            $subjects = $this->subjectRepository->findBy(['classroom' => $classroom]);
            
            foreach ($subjects as $subject) {
                $stats['total']++;
                try {
                    $result = $this->loadPromptsForClassroomSubject(
                        $io,
                        $classroom->getName(),
                        $subject->getName(),
                        $force,
                        true // mode silencieux
                    );
                    
                    if ($result === Command::SUCCESS) {
                        $stats['success']++;
                    } else {
                        $stats['errors']++;
                    }
                } catch (\Exception $e) {
                    $stats['errors']++;
                    if ($io->isVerbose()) {
                        $io->writeln("\nErreur pour {$classroom->getName()}/{$subject->getName()}: " . $e->getMessage());
                    }
                }
            }
            $progressBar->advance();
        }

        $progressBar->finish();
        $io->newLine(2);

        $io->section('Résultats');
        $io->table(
            ['Métrique', 'Valeur'],
            [
                ['Total combinaisons', $stats['total']],
                ['Succès', $stats['success']],
                ['Erreurs', $stats['errors']],
            ]
        );

        if ($stats['errors'] > 0) {
            $io->warning("{$stats['errors']} erreur(s) rencontrée(s)");
            return Command::FAILURE;
        }

        $io->success('✅ Chargement terminé avec succès !');
        return Command::SUCCESS;
    }

    private function loadPromptsForClassroomSubject(
        SymfonyStyle $io,
        string $classroomName,
        string $subjectName,
        bool $force,
        bool $silent = false
    ): int {
        if (!$silent) {
            $io->section("Chargement des prompts pour {$classroomName} / {$subjectName}");
        }

        // Appeler l'API
        try {
            $response = $this->httpClient->request('POST', self::API_URL, [
                'json' => [
                    'classroom' => $classroomName,
                    'subject' => $subjectName,
                ],
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
            ]);

            if ($response->getStatusCode() !== 200) {
                if (!$silent) {
                    $io->error('Erreur HTTP : ' . $response->getStatusCode());
                }
                return Command::FAILURE;
            }

            $data = $response->toArray();

            if (!isset($data['status']) || $data['status'] !== 'success' || !isset($data['data']['chapters'])) {
                if (!$silent) {
                    $io->error('Format de réponse API invalide');
                }
                return Command::FAILURE;
            }

            $chapters = $data['data']['chapters'];
            $stats = [
                'chapters_updated' => 0,
                'subchapters_updated' => 0,
            ];

            // Traiter chaque chapitre
            foreach ($chapters as $chapterData) {
                $chapterId = $chapterData['id'] ?? null;
                $chapterTitle = $chapterData['title'] ?? null;
                $prompts = $chapterData['prompts'] ?? [];

                if (!$chapterTitle) {
                    continue;
                }

                // Trouver le chapitre dans la base de données
                $chapter = $this->findChapterByTitleAndSubject($chapterTitle, $subjectName);
                
                if ($chapter) {
                    // Mettre à jour les prompts si force ou si pas encore de prompts
                    if ($force || !$chapter->getPrompts()) {
                        $chapter->setPrompts($prompts);
                        $chapter->setUpdatedAt(new \DateTimeImmutable());
                        $this->em->persist($chapter);
                        $stats['chapters_updated']++;
                    }
                } elseif (!$silent) {
                    $io->warning("Chapitre non trouvé : {$chapterTitle}");
                }

                // Traiter les sous-chapitres
                $subChapters = $chapterData['subChapters'] ?? [];
                foreach ($subChapters as $subChapterData) {
                    $subChapterId = $subChapterData['id'] ?? null;
                    $subChapterTitle = $subChapterData['title'] ?? null;
                    $subPrompts = $subChapterData['prompts'] ?? [];

                    if (!$subChapterTitle) {
                        continue;
                    }

                    // Trouver le sous-chapitre dans la base de données
                    $subChapter = $this->findSubChapterByTitleAndChapter($subChapterTitle, $chapterTitle, $subjectName);
                    
                    if ($subChapter) {
                        // Mettre à jour les prompts si force ou si pas encore de prompts
                        if ($force || !$subChapter->getPrompts()) {
                            $subChapter->setPrompts($subPrompts);
                            $subChapter->setUpdatedAt(new \DateTimeImmutable());
                            $this->em->persist($subChapter);
                            $stats['subchapters_updated']++;
                        }
                    } elseif (!$silent) {
                        $io->warning("Sous-chapitre non trouvé : {$subChapterTitle}");
                    }
                }
            }

            $this->em->flush();

            if (!$silent) {
                $io->success(sprintf(
                    '✅ %d chapitre(s) et %d sous-chapitre(s) mis à jour',
                    $stats['chapters_updated'],
                    $stats['subchapters_updated']
                ));
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            if (!$silent) {
                $io->error('Erreur lors de l\'appel API : ' . $e->getMessage());
            }
            throw $e;
        }
    }

    private function findChapterByTitleAndSubject(string $title, string $subjectName): ?Chapter
    {
        return $this->chapterRepository->createQueryBuilder('c')
            ->join('c.subject', 's')
            ->join('s.classroom', 'cl')
            ->where('c.name = :title')
            ->andWhere('s.name = :subjectName')
            ->setParameter('title', $title)
            ->setParameter('subjectName', $subjectName)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    private function findSubChapterByTitleAndChapter(string $subChapterTitle, string $chapterTitle, string $subjectName): ?SubChapter
    {
        return $this->subChapterRepository->createQueryBuilder('sc')
            ->join('sc.chapter', 'c')
            ->join('c.subject', 's')
            ->join('s.classroom', 'cl')
            ->where('sc.name = :subChapterTitle')
            ->andWhere('c.name = :chapterTitle')
            ->andWhere('s.name = :subjectName')
            ->setParameter('subChapterTitle', $subChapterTitle)
            ->setParameter('chapterTitle', $chapterTitle)
            ->setParameter('subjectName', $subjectName)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}

