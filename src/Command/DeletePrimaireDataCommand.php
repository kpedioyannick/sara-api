<?php

namespace App\Command;

use App\Entity\Path\Classroom;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Input\InputOption;

class DeletePrimaireDataCommand extends Command
{
    protected static $defaultName = 'app:delete-primaire-data';
    protected static $defaultDescription = 'Supprime toutes les données du primaire de la base de données';

    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->em = $entityManager;
        parent::__construct(self::$defaultName);
    }

    protected function configure(): void
    {
        $this
            ->setDescription(self::$defaultDescription)
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force la suppression sans confirmation');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Suppression des données du primaire');

        // Classes du primaire
        $primaireClasses = ['CP', 'CE1', 'CE2', 'CM1', 'CM2'];

        // Récupérer toutes les classes du primaire
        $classrooms = $this->em->getRepository(Classroom::class)
            ->createQueryBuilder('c')
            ->where('c.name IN (:names)')
            ->setParameter('names', $primaireClasses)
            ->getQuery()
            ->getResult();

        if (empty($classrooms)) {
            $io->success('Aucune donnée du primaire trouvée dans la base de données.');
            return Command::SUCCESS;
        }

        // Afficher un résumé
        $io->section('Données à supprimer :');
        foreach ($classrooms as $classroom) {
            $subjectsCount = $classroom->getSubjects()->count();
            $chaptersCount = 0;
            $subchaptersCount = 0;
            
            foreach ($classroom->getSubjects() as $subject) {
                $chaptersCount += $subject->getChapters()->count();
                foreach ($subject->getChapters() as $chapter) {
                    $subchaptersCount += $chapter->getSubChapters()->count();
                }
            }
            
            $io->text(sprintf(
                '  - %s : %d matière(s), %d chapitre(s), %d sous-chapitre(s)',
                $classroom->getName(),
                $subjectsCount,
                $chaptersCount,
                $subchaptersCount
            ));
        }

        // Demander confirmation si --force n'est pas utilisé
        if (!$input->getOption('force')) {
            if (!$io->confirm('Êtes-vous sûr de vouloir supprimer toutes ces données ?', false)) {
                $io->warning('Suppression annulée.');
                return Command::SUCCESS;
            }
        }

        // Supprimer les classes (les relations en cascade supprimeront automatiquement les sujets, chapitres et sous-chapitres)
        $io->section('Suppression en cours...');
        $deletedCount = 0;
        
        foreach ($classrooms as $classroom) {
            $this->em->remove($classroom);
            $deletedCount++;
            $io->text(sprintf('  ✓ Suppression de %s', $classroom->getName()));
        }

        $this->em->flush();

        $io->success(sprintf(
            'Suppression terminée : %d classe(s) du primaire supprimée(s) avec toutes leurs données associées.',
            $deletedCount
        ));

        return Command::SUCCESS;
    }
}

