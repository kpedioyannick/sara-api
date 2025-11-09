<?php

namespace App\Command;

use App\Entity\Path\Chapter;
use App\Entity\Path\Classroom;
use App\Entity\Path\Subject;
use App\Entity\Path\SubChapter;
use App\Repository\ChapterRepository;
use App\Repository\ClassroomRepository;
use App\Repository\SubjectRepository;
use App\Repository\SubChapterRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(
    name: 'app:load-path-data',
    description: 'Charge les classes, matières, chapitres et sous-chapitres depuis l\'API externe',
)]
class LoadPathDataCommand extends Command
{
    private const API_URL = 'https://api.sara.education/api/documents/classrooms';
    

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
        $this->addOption(
            'force',
            'f',
            InputOption::VALUE_NONE,
            'Force la mise à jour des données existantes'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $force = $input->getOption('force');

        $io->title('📚 Chargement des données de parcours depuis l\'API');

        try {
            // Appeler l'API
            $io->section('Récupération des données depuis l\'API...');
            $response = $this->httpClient->request('GET', self::API_URL);

            if ($response->getStatusCode() !== 200) {
                $io->error('Erreur lors de l\'appel à l\'API : ' . $response->getStatusCode());
                return Command::FAILURE;
            }

            $data = $response->toArray();

            if (!isset($data['status']) || $data['status'] !== 'success' || !isset($data['data']['classrooms'])) {
                $io->error('Format de réponse API invalide');
                return Command::FAILURE;
            }

            $classrooms = $data['data']['classrooms'];
            $io->success(sprintf('✅ %d classe(s) récupérée(s)', count($classrooms)));

            // Traiter les données
            $io->section('Traitement des données...');
            $stats = [
                'classrooms' => ['created' => 0, 'updated' => 0, 'skipped' => 0],
                'subjects' => ['created' => 0, 'updated' => 0],
                'chapters' => ['created' => 0, 'updated' => 0],
                'subchapters' => ['created' => 0, 'updated' => 0],
            ];

            $progressBar = $io->createProgressBar(count($classrooms));
            $progressBar->start();

            foreach ($classrooms as $classroomData) {
                $result = $this->processClassroom($classroomData, $force);
                if ($result) {
                    if ($result['created']) {
                        $stats['classrooms']['created']++;
                    } else {
                        $stats['classrooms']['updated']++;
                    }
                    
                    $subjectStats = $this->processSubjects($result['classroom'], $classroomData['subjects'] ?? [], $force);
                    $stats['subjects']['created'] += $subjectStats['created'];
                    $stats['subjects']['updated'] += $subjectStats['updated'];
                    $stats['chapters']['created'] += $subjectStats['chapters']['created'];
                    $stats['chapters']['updated'] += $subjectStats['chapters']['updated'];
                    $stats['subchapters']['created'] += $subjectStats['subchapters']['created'];
                    $stats['subchapters']['updated'] += $subjectStats['subchapters']['updated'];
                } else {
                    $stats['classrooms']['skipped']++;
                }
                $progressBar->advance();
            }

            $progressBar->finish();
            $io->newLine(2);

            $io->section('Résultats');
            $io->table(
                ['Type', 'Créés', 'Mis à jour', 'Ignorés'],
                [
                    ['Classes', $stats['classrooms']['created'], $stats['classrooms']['updated'], $stats['classrooms']['skipped']],
                    ['Matières', $stats['subjects']['created'], $stats['subjects']['updated'], '-'],
                    ['Chapitres', $stats['chapters']['created'], $stats['chapters']['updated'], '-'],
                    ['Sous-chapitres', $stats['subchapters']['created'], $stats['subchapters']['updated'], '-'],
                ]
            );

            $io->success('✅ Chargement terminé avec succès !');
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error('Erreur : ' . $e->getMessage());
            $io->writeln($e->getTraceAsString());
            return Command::FAILURE;
        }
    }

    private function processClassroom(array $data, bool $force): ?array
    {
        $name = $data['name'] ?? null;
        if (!$name) {
            return null;
        }

        // Chercher une classe existante par nom
        $classroom = $this->classroomRepository->findOneBy(['name' => $name]);
        $created = false;

        if (!$classroom) {
            $classroom = new Classroom();
            $classroom->setName($name);
            $this->em->persist($classroom);
            $created = true;
        } elseif ($force) {
            $classroom->setName($name);
            $classroom->setUpdatedAt(new \DateTimeImmutable());
        }

        $this->em->flush();

        return ['classroom' => $classroom, 'created' => $created];
    }

    private function processSubjects(Classroom $classroom, array $subjectsData, bool $force): array
    {
        $stats = [
            'created' => 0,
            'updated' => 0,
            'chapters' => ['created' => 0, 'updated' => 0],
            'subchapters' => ['created' => 0, 'updated' => 0],
        ];

        foreach ($subjectsData as $subjectData) {
            $name = $subjectData['name'] ?? null;
            if (!$name) {
                continue;
            }

            // Chercher une matière existante par nom dans cette classe
            $subject = $this->subjectRepository->createQueryBuilder('s')
                ->where('s.name = :name')
                ->andWhere('s.classroom = :classroomId')
                ->setParameter('name', $name)
                ->setParameter('classroomId', $classroom->getId())
                ->getQuery()
                ->getOneOrNullResult();

            if (!$subject) {
                $subject = new Subject();
                $subject->setName($name);
                $subject->setClassroom($classroom);
                $this->em->persist($subject);
                $stats['created']++;
            } elseif ($force) {
                $subject->setName($name);
                $subject->setUpdatedAt(new \DateTimeImmutable());
                $stats['updated']++;
            }

            // Traiter les chapitres
            $chapterStats = $this->processChapters($subject, $subjectData['chapters'] ?? [], $force);
            $stats['chapters']['created'] += $chapterStats['created'];
            $stats['chapters']['updated'] += $chapterStats['updated'];
            $stats['subchapters']['created'] += $chapterStats['subchapters']['created'];
            $stats['subchapters']['updated'] += $chapterStats['subchapters']['updated'];
        }

        $this->em->flush();
        return $stats;
    }

    private function processChapters(Subject $subject, array $chaptersData, bool $force): array
    {
        $stats = [
            'created' => 0,
            'updated' => 0,
            'subchapters' => ['created' => 0, 'updated' => 0],
        ];

        foreach ($chaptersData as $chapterData) {
            $title = $chapterData['title'] ?? null;
            if (!$title) {
                continue;
            }

            // Chercher un chapitre existant par titre dans cette matière
            $chapter = $this->chapterRepository->createQueryBuilder('c')
                ->where('c.name = :name')
                ->andWhere('c.subject = :subjectId')
                ->setParameter('name', $title)
                ->setParameter('subjectId', $subject->getId())
                ->getQuery()
                ->getOneOrNullResult();

            if (!$chapter) {
                $chapter = new Chapter();
                $chapter->setName($title);
                $chapter->setSubject($subject);
                $this->em->persist($chapter);
                $stats['created']++;
            } elseif ($force) {
                $chapter->setName($title);
                $chapter->setUpdatedAt(new \DateTimeImmutable());
                $stats['updated']++;
            }

            // Traiter les sous-chapitres
            $subchapterStats = $this->processSubChapters($chapter, $chapterData['subchapters'] ?? [], $force);
            $stats['subchapters']['created'] += $subchapterStats['created'];
            $stats['subchapters']['updated'] += $subchapterStats['updated'];
        }

        $this->em->flush();
        return $stats;
    }

    private function processSubChapters(Chapter $chapter, array $subchaptersData, bool $force): array
    {
        $stats = ['created' => 0, 'updated' => 0];

        foreach ($subchaptersData as $subchapterData) {
            $title = $subchapterData['title'] ?? null;
            if (!$title) {
                continue;
            }

            // Chercher un sous-chapitre existant par titre dans ce chapitre
            $subchapter = $this->subChapterRepository->createQueryBuilder('sc')
                ->where('sc.name = :name')
                ->andWhere('sc.chapter = :chapterId')
                ->setParameter('name', $title)
                ->setParameter('chapterId', $chapter->getId())
                ->getQuery()
                ->getOneOrNullResult();

            if (!$subchapter) {
                $subchapter = new SubChapter();
                $subchapter->setName($title);
                $subchapter->setChapter($chapter);
                $this->em->persist($subchapter);
                $stats['created']++;
            } elseif ($force) {
                $subchapter->setName($title);
                $subchapter->setUpdatedAt(new \DateTimeImmutable());
                $stats['updated']++;
            }
        }

        $this->em->flush();
        return $stats;
    }
}

