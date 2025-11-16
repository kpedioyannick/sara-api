<?php

namespace App\Command;

use App\Entity\Activity;
use App\Entity\ActivityCategory;
use App\Entity\Coach;
use App\Entity\Comment;
use App\Entity\Family;
use App\Entity\Message;
use App\Entity\Note;
use App\Entity\Objective;
use App\Entity\ParentUser;
use App\Entity\Planning;
use App\Entity\Proof;
use App\Entity\Request;
use App\Entity\Specialist;
use App\Entity\Student;
use App\Entity\Task;
use App\Enum\FamilyType;
use App\Enum\NoteType;
use App\Enum\TaskType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:load-demo-data',
    description: 'Crée un environnement de démonstration complet avec des données réalistes'
)]
class LoadDemoDataCommand extends Command
{
    private Coach $coach;
    private array $specialists = [];
    private array $parents = [];
    private array $students = [];
    private array $groups = [];
    private array $objectives = [];
    private array $tasks = [];
    private array $activities = [];
    private array $requests = [];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $passwordHasher
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('force', 'f', InputOption::VALUE_NONE, 'Forcer la suppression des données existantes');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if ($input->getOption('force')) {
            $io->warning('Suppression des données existantes...');
            $this->clearExistingData($io);
        }

        $io->title('Génération des données de démonstration');

        try {
            // 1. Utilisateurs
            $io->section('1. Création des utilisateurs');
            $this->createUsers($io);

            // 2. Groupes (Familles de type GROUP)
            $io->section('2. Création des groupes');
            $this->createGroups($io);

            // 3. Objectifs
            $io->section('3. Création des objectifs');
            $this->createObjectives($io);

            // 4. Tâches
            $io->section('4. Création des tâches');
            $this->createTasks($io);

            // 5. Activités
            $io->section('5. Création des activités');
            $this->createActivities($io);

            // 6. Preuves
            $io->section('6. Création des preuves');
            $this->createProofs($io);

            // 7. Demandes et messages
            $io->section('7. Création des demandes et messages');
            $this->createRequestsAndMessages($io);

            // 8. Notes et commentaires
            $io->section('8. Création des notes et commentaires');
            $this->createNotesAndComments($io);

            // 9. Planning
            $io->section('9. Création du planning');
            $this->createPlanning($io);

            $this->em->flush();

            $io->success('Données de démonstration créées avec succès !');
            $this->displaySummary($io);

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Erreur lors de la création des données : ' . $e->getMessage());
            $io->error($e->getTraceAsString());
            return Command::FAILURE;
        }
    }

    private function clearExistingData(SymfonyStyle $io): void
    {
        $connection = $this->em->getConnection();
        
        // Désactiver temporairement les contraintes de clés étrangères
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');
        
        try {
            // Supprimer toutes les données dans l'ordre
            $tables = [
                'comment_image',
                'note_image',
                'activity_image',
                'notification',
                'proof',
                'comment',
                'note',
                'message',
                'request',
                'planning',
                'availability',
                'task',
                'activity',
                'objective',
                'objective_shared_students',
                'objective_shared_specialists',
                'family_specialists',
                'proof_specialists',
                'proof_activities',
                'proof_paths',
                'proof_students',
                'path_module',
                'path',
                'integration',
                'student',
                'parent_user',
                'family',
                'specialist',
                'coach',
                'user'
            ];
            
            foreach ($tables as $table) {
                try {
                    $connection->executeStatement("TRUNCATE TABLE `$table`");
                } catch (\Exception $e) {
                    // Ignorer si la table n'existe pas
                }
            }
        } finally {
            // Réactiver les contraintes de clés étrangères
            $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1');
        }
    }

    private function createUsers(SymfonyStyle $io): void
    {
        // Coach
        $coach = new Coach();
        $coach->setEmail('coach.demo@sara.fr');
        $coach->setPassword($this->passwordHasher->hashPassword($coach, 'demo123'));
        $coach->setFirstName('Marie');
        $coach->setLastName('Dupont');
        $coach->setSpecialization('Accompagnement scolaire et orientation');
        $coach->setIsActive(true);
        $this->em->persist($coach);
        $this->coach = $coach;
        $io->text('✓ Coach créé');

        // Spécialistes
        $specialistsData = [
            [
                'email' => 'prof.maths.demo@sara.fr',
                'firstName' => 'Sophie',
                'lastName' => 'Martin',
                'specializations' => ['mathématiques', 'algèbre', 'géométrie']
            ],
            [
                'email' => 'prof.theatre.demo@sara.fr',
                'firstName' => 'Jean',
                'lastName' => 'Bernard',
                'specializations' => ['théâtre', 'expression orale', 'art dramatique']
            ],
            [
                'email' => 'prof.musique.demo@sara.fr',
                'firstName' => 'Claire',
                'lastName' => 'Lefebvre',
                'specializations' => ['musique', 'solfège', 'instrument']
            ],
            [
                'email' => 'prof.college.demo@sara.fr',
                'firstName' => 'Pierre',
                'lastName' => 'Dubois',
                'specializations' => ['français', 'histoire', 'géographie', 'sciences']
            ],
            [
                'email' => 'prof.arts.demo@sara.fr',
                'firstName' => 'Marie',
                'lastName' => 'Garcia',
                'specializations' => ['arts plastiques', 'dessin', 'peinture', 'créativité']
            ]
        ];

        foreach ($specialistsData as $data) {
            $specialist = new Specialist();
            $specialist->setEmail($data['email']);
            $specialist->setPassword($this->passwordHasher->hashPassword($specialist, 'demo123'));
            $specialist->setFirstName($data['firstName']);
            $specialist->setLastName($data['lastName']);
            $specialist->setSpecializations($data['specializations']);
            $specialist->setIsActive(true);
            $this->em->persist($specialist);
            $this->specialists[] = $specialist;
        }
        $io->text('✓ ' . count($specialistsData) . ' spécialistes créés');

        // Parents (seront liés aux groupes plus tard)
        $parentsData = [
            ['email' => 'parent1.demo@sara.fr', 'firstName' => 'Pierre', 'lastName' => 'Durand'],
            ['email' => 'parent2.demo@sara.fr', 'firstName' => 'Isabelle', 'lastName' => 'Moreau']
        ];

        foreach ($parentsData as $data) {
            $parent = new ParentUser();
            $parent->setEmail($data['email']);
            $parent->setPassword($this->passwordHasher->hashPassword($parent, 'demo123'));
            $parent->setFirstName($data['firstName']);
            $parent->setLastName($data['lastName']);
            $parent->setIsActive(true);
            $this->em->persist($parent);
            $this->parents[] = $parent;
        }
        $io->text('✓ ' . count($parentsData) . ' parents créés');
    }

    private function createGroups(SymfonyStyle $io): void
    {
        $now = new \DateTimeImmutable();

        // Groupe 1
        $group1 = new Family();
        $group1->setType(FamilyType::GROUP);
        $group1->setFamilyIdentifier('GRP_1_DEMO');
        $group1->setCoach($this->coach);
        $group1->setIsActive(true);
        $group1->setLocation('Paris 15ème');
        $group1->setCreatedAt($now->modify('-3 months'));
        $group1->setUpdatedAt($now);
        $this->em->persist($group1);

        // Lier le parent 1 au groupe 1
        $this->parents[0]->setFamily($group1);
        $group1->setParent($this->parents[0]);

        // Élèves du groupe 1
        $students1 = [
            [
                'email' => 'eleve1.demo@sara.fr',
                'firstName' => 'Lucas',
                'lastName' => 'Durand',
                'pseudo' => 'LucasD',
                'class' => '5ème',
                'schoolName' => 'Collège Victor Hugo',
                'points' => 150,
                'needTags' => ['difficultés en mathématiques', 'manque de confiance']
            ],
            [
                'email' => 'eleve2.demo@sara.fr',
                'firstName' => 'Emma',
                'lastName' => 'Durand',
                'pseudo' => 'EmmaD',
                'class' => '4ème',
                'schoolName' => 'Collège Victor Hugo',
                'points' => 200,
                'needTags' => ['expression orale', 'prise de parole']
            ]
        ];

        foreach ($students1 as $data) {
            $student = new Student();
            $student->setEmail($data['email']);
            $student->setPassword($this->passwordHasher->hashPassword($student, 'demo123'));
            $student->setFirstName($data['firstName']);
            $student->setLastName($data['lastName']);
            $student->setPseudo($data['pseudo']);
            $student->setClass($data['class']);
            $student->setSchoolName($data['schoolName']);
            $student->setPoints($data['points']);
            $student->setNeedTags($data['needTags']);
            $student->setFamily($group1);
            $student->setIsActive(true);
            $this->em->persist($student);
            $this->students[] = $student;
        }

        // Lier spécialistes au groupe 1
        $group1->addSpecialist($this->specialists[0]); // Maths
        $group1->addSpecialist($this->specialists[1]); // Théâtre
        $group1->addSpecialist($this->specialists[3]); // Collège

        $this->groups[] = $group1;

        // Groupe 2
        $group2 = new Family();
        $group2->setType(FamilyType::GROUP);
        $group2->setFamilyIdentifier('GRP_2_DEMO');
        $group2->setCoach($this->coach);
        $group2->setIsActive(true);
        $group2->setLocation('Paris 12ème');
        $group2->setCreatedAt($now->modify('-2 months'));
        $group2->setUpdatedAt($now);
        $this->em->persist($group2);

        // Lier le parent 2 au groupe 2
        $this->parents[1]->setFamily($group2);
        $group2->setParent($this->parents[1]);

        // Élèves du groupe 2
        $students2 = [
            [
                'email' => 'eleve3.demo@sara.fr',
                'firstName' => 'Thomas',
                'lastName' => 'Moreau',
                'pseudo' => 'ThomasM',
                'class' => '6ème',
                'schoolName' => 'Collège Jean Jaurès',
                'points' => 120,
                'needTags' => ['concentration', 'attention']
            ],
            [
                'email' => 'eleve4.demo@sara.fr',
                'firstName' => 'Léa',
                'lastName' => 'Moreau',
                'pseudo' => 'LeaM',
                'class' => '3ème',
                'schoolName' => 'Collège Jean Jaurès',
                'points' => 180,
                'needTags' => ['orientation', 'méthodologie']
            ]
        ];

        foreach ($students2 as $data) {
            $student = new Student();
            $student->setEmail($data['email']);
            $student->setPassword($this->passwordHasher->hashPassword($student, 'demo123'));
            $student->setFirstName($data['firstName']);
            $student->setLastName($data['lastName']);
            $student->setPseudo($data['pseudo']);
            $student->setClass($data['class']);
            $student->setSchoolName($data['schoolName']);
            $student->setPoints($data['points']);
            $student->setNeedTags($data['needTags']);
            $student->setFamily($group2);
            $student->setIsActive(true);
            $this->em->persist($student);
            $this->students[] = $student;
        }

        // Lier spécialistes au groupe 2
        $group2->addSpecialist($this->specialists[2]); // Musique
        $group2->addSpecialist($this->specialists[3]); // Collège
        $group2->addSpecialist($this->specialists[4]); // Arts

        $this->groups[] = $group2;

        $io->text('✓ 2 groupes créés avec ' . count($this->students) . ' élèves');
    }

    private function createObjectives(SymfonyStyle $io): void
    {
        $now = new \DateTimeImmutable();

        // Objectifs individuels
        $objectivesData = [
            // Objectif 1 - Lucas (Mathématiques) - Nantes St Herblain
            [
                'title' => 'Améliorer les compétences en mathématiques',
                'description' => 'Travailler sur les fractions, les décimaux et la résolution de problèmes. Lieu : Nantes St Herblain',
                'category' => 'Scolaire',
                'categoryTags' => ['mathématiques', 'fractions', 'décimaux', 'Nantes St Herblain'],
                'status' => Objective::STATUS_IN_ACTION,
                'progress' => 40,
                'deadline' => $now->modify('+3 months'),
                'student' => $this->students[0], // Lucas
                'sharedStudents' => [],
                'sharedSpecialists' => []
            ],
            // Objectif 2 - Emma (Confiance) - Nantes St Herblain
            [
                'title' => 'Développer la confiance en soi',
                'description' => 'Améliorer l\'expression orale et la prise de parole en public. Lieu : Nantes St Herblain',
                'category' => 'Social',
                'categoryTags' => ['confiance', 'expression orale', 'prise de parole', 'Nantes St Herblain'],
                'status' => Objective::STATUS_VALIDATED,
                'progress' => 60,
                'deadline' => $now->modify('+2 months'),
                'student' => $this->students[1], // Emma
                'sharedStudents' => [],
                'sharedSpecialists' => []
            ],
            // Objectif 3 - Thomas (Concentration) - Nantes Clos Toreau
            [
                'title' => 'Améliorer la concentration en classe',
                'description' => 'Travailler sur l\'attention et la concentration pendant les cours. Lieu : Nantes Clos Toreau',
                'category' => 'Scolaire',
                'categoryTags' => ['concentration', 'attention', 'classe', 'Nantes Clos Toreau'],
                'status' => Objective::STATUS_IN_ACTION,
                'progress' => 35,
                'deadline' => $now->modify('+4 months'),
                'student' => $this->students[2], // Thomas
                'sharedStudents' => [],
                'sharedSpecialists' => []
            ],
            // Objectif 4 - Léa (Orientation) - Nantes Clos Toreau
            [
                'title' => 'Préparer l\'orientation post-3ème',
                'description' => 'Explorer les différentes filières et options d\'orientation. Lieu : Nantes Clos Toreau',
                'category' => 'Orientation',
                'categoryTags' => ['orientation', 'brevet', 'lycée', 'Nantes Clos Toreau'],
                'status' => Objective::STATUS_IN_ACTION,
                'progress' => 30,
                'deadline' => $now->modify('+6 months'),
                'student' => $this->students[3], // Léa
                'sharedStudents' => [],
                'sharedSpecialists' => []
            ],
            // Objectif 5 - Partagé Groupe 1 (Lucas et Emma) - Nantes St Herblain
            [
                'title' => 'Améliorer la communication en groupe',
                'description' => 'Apprendre à travailler en équipe et à communiquer efficacement. Lieu : Nantes St Herblain',
                'category' => 'Social',
                'categoryTags' => ['communication', 'travail d\'équipe', 'coopération', 'Nantes St Herblain'],
                'status' => Objective::STATUS_IN_ACTION,
                'progress' => 50,
                'deadline' => $now->modify('+2 months'),
                'student' => $this->students[0], // Lucas propriétaire
                'sharedStudents' => [$this->students[1]], // Emma
                'sharedSpecialists' => []
            ],
            // Objectif 6 - Partagé Groupe 2 (Thomas et Léa) - Nantes Clos Toreau
            [
                'title' => 'Développer l\'autonomie dans les apprentissages',
                'description' => 'Apprendre à organiser son travail et à être autonome. Lieu : Nantes Clos Toreau',
                'category' => 'Méthodologie',
                'categoryTags' => ['autonomie', 'organisation', 'méthodologie', 'Nantes Clos Toreau'],
                'status' => Objective::STATUS_VALIDATED,
                'progress' => 70,
                'deadline' => $now->modify('+1 month'),
                'student' => $this->students[2], // Thomas propriétaire
                'sharedStudents' => [$this->students[3]], // Léa
                'sharedSpecialists' => []
            ],
            // Objectif 7 - Partagé avec Spécialistes (Lucas) - Nantes St Herblain
            [
                'title' => 'Suivi mathématiques et pédagogique',
                'description' => 'Coordination entre le professeur de mathématiques et le coach pour le suivi des difficultés en mathématiques. Lieu : Nantes St Herblain',
                'category' => 'Scolaire',
                'categoryTags' => ['mathématiques', 'coordination', 'soutien', 'Nantes St Herblain'],
                'status' => Objective::STATUS_IN_ACTION,
                'progress' => 45,
                'deadline' => $now->modify('+3 months'),
                'student' => $this->students[0], // Lucas
                'sharedStudents' => [],
                'sharedSpecialists' => [$this->specialists[0], $this->specialists[3]] // Maths et Collège
            ],
            // Objectif 8 - Partagé avec Spécialistes (Emma) - Nantes St Herblain
            [
                'title' => 'Développement artistique et créatif',
                'description' => 'Suivi conjoint pour développer les compétences artistiques et la créativité. Lieu : Nantes St Herblain',
                'category' => 'Scolaire',
                'categoryTags' => ['arts', 'créativité', 'expression', 'Nantes St Herblain'],
                'status' => Objective::STATUS_VALIDATED,
                'progress' => 55,
                'deadline' => $now->modify('+2 months'),
                'student' => $this->students[1], // Emma
                'sharedStudents' => [],
                'sharedSpecialists' => [$this->specialists[1], $this->specialists[4]] // Théâtre et Arts
            ]
        ];

        foreach ($objectivesData as $data) {
            $objective = new Objective();
            $objective->setTitle($data['title']);
            $objective->setDescription($data['description']);
            $objective->setCategory($data['category']);
            $objective->setCategoryTags($data['categoryTags']);
            $objective->setStatus($data['status']);
            $objective->setProgress($data['progress']);
            $objective->setDeadline($data['deadline']);
            $objective->setStudent($data['student']);
            $objective->setCoach($this->coach);
            $objective->setCreatedAt($now->modify('-1 month'));
            $objective->setUpdatedAt($now);

            // Ajouter les élèves partagés
            foreach ($data['sharedStudents'] as $sharedStudent) {
                $objective->addSharedStudent($sharedStudent);
            }

            // Ajouter les spécialistes partagés
            foreach ($data['sharedSpecialists'] as $sharedSpecialist) {
                $objective->addSharedSpecialist($sharedSpecialist);
            }

            $this->em->persist($objective);
            $this->objectives[] = $objective;
        }

        $io->text('✓ ' . count($objectivesData) . ' objectifs créés');
    }

    private function createTasks(SymfonyStyle $io): void
    {
        $now = new \DateTimeImmutable();

        // Fonction helper pour calculer les dates
        $getDate = function(string $modifier) use ($now): \DateTimeImmutable {
            return (clone $now)->modify($modifier);
        };

        // Fonction helper pour obtenir le lundi de la semaine
        $getMonday = function(int $weeksOffset = 0) use ($now): \DateTimeImmutable {
            $date = (clone $now)->modify("$weeksOffset weeks");
            $dayOfWeek = (int)$date->format('w'); // 0 = dimanche, 1 = lundi, etc.
            $daysToMonday = $dayOfWeek === 0 ? 1 : (8 - $dayOfWeek) % 7;
            if ($daysToMonday === 0) $daysToMonday = 7;
            return $date->modify("+$daysToMonday days")->setTime(0, 0);
        };

        $tasksData = [
            // Objectif 1 - Lucas (Mathématiques)
            [
                'title' => 'Faire des devoirs',
                'description' => 'Faire les devoirs quotidiens dans toutes les matières',
                'type' => TaskType::TASK,
                'status' => Task::STATUS_IN_PROGRESS,
                'frequency' => Task::FREQUENCY_DAILY,
                'repeatDaysOfWeek' => [1, 2, 3, 4, 5], // Lundi à Vendredi
                'requiresProof' => true,
                'proofType' => 'file',
                'startDate' => $getMonday(-2),
                'dueDate' => $getDate('+2 months'),
                'objective' => $this->objectives[0],
                'assignedType' => 'student',
                'student' => $this->students[0]
            ],
            [
                'title' => 'Réviser tous les soirs',
                'description' => 'Réviser les leçons de la journée chaque soir pendant 30 minutes',
                'type' => TaskType::TASK,
                'status' => Task::STATUS_IN_PROGRESS,
                'frequency' => Task::FREQUENCY_DAILY,
                'repeatDaysOfWeek' => [1, 2, 3, 4, 5],
                'requiresProof' => false,
                'startDate' => $getMonday(-1),
                'dueDate' => $getDate('+2 months'),
                'objective' => $this->objectives[0],
                'assignedType' => 'student',
                'student' => $this->students[0]
            ],
            [
                'title' => 'Se coucher tôt',
                'description' => 'Se coucher avant 21h30 pour être en forme le lendemain',
                'type' => TaskType::TASK,
                'status' => Task::STATUS_PENDING,
                'frequency' => Task::FREQUENCY_DAILY,
                'repeatDaysOfWeek' => [0, 1, 2, 3, 4, 5, 6], // Tous les jours
                'requiresProof' => false,
                'startDate' => $getDate('+1 day'),
                'dueDate' => $getDate('+2 months'),
                'objective' => $this->objectives[0],
                'assignedType' => 'student',
                'student' => $this->students[0]
            ],
            [
                'title' => 'Ne pas bavarder en classe',
                'description' => 'Rester concentré et ne pas bavarder pendant les cours',
                'type' => TaskType::TASK,
                'status' => Task::STATUS_PENDING,
                'frequency' => Task::FREQUENCY_DAILY,
                'repeatDaysOfWeek' => [1, 2, 3, 4, 5],
                'requiresProof' => false,
                'startDate' => $getDate('+1 day'),
                'dueDate' => $getDate('+2 months'),
                'objective' => $this->objectives[0],
                'assignedType' => 'student',
                'student' => $this->students[0]
            ],
            [
                'title' => 'Faire 10 exercices de calcul mental',
                'description' => 'Compléter une série de 10 exercices de calcul mental',
                'type' => TaskType::INDIVIDUAL_WORK,
                'status' => Task::STATUS_IN_PROGRESS,
                'frequency' => Task::FREQUENCY_WEEKLY,
                'repeatDaysOfWeek' => [3], // Mercredi
                'requiresProof' => true,
                'proofType' => 'file',
                'startDate' => $getMonday(-1)->modify('+2 days'), // Mercredi dernier
                'dueDate' => $getDate('+1 month'),
                'objective' => $this->objectives[0],
                'assignedType' => 'student',
                'student' => $this->students[0]
            ],
            [
                'title' => 'Session de révision en ligne',
                'description' => 'Participer à une session de révision en ligne sur les fractions',
                'type' => TaskType::INDIVIDUAL_WORK_REMOTE,
                'status' => Task::STATUS_PENDING,
                'frequency' => Task::FREQUENCY_NONE,
                'repeatDaysOfWeek' => null,
                'requiresProof' => false,
                'startDate' => $getDate('+3 days'),
                'dueDate' => $getDate('+1 week'),
                'objective' => $this->objectives[0],
                'assignedType' => 'student',
                'student' => $this->students[0]
            ],
            [
                'title' => 'Séance de soutien au centre',
                'description' => 'Séance de soutien en mathématiques au centre d\'accompagnement',
                'type' => TaskType::INDIVIDUAL_WORK_ON_SITE,
                'status' => Task::STATUS_PENDING,
                'frequency' => Task::FREQUENCY_NONE,
                'repeatDaysOfWeek' => null,
                'requiresProof' => true,
                'proofType' => 'text',
                'startDate' => $getDate('+5 days'),
                'dueDate' => $getDate('+2 weeks'),
                'objective' => $this->objectives[0],
                'assignedType' => 'student',
                'student' => $this->students[0]
            ],
            // Objectif 2 - Emma (Confiance)
            [
                'title' => 'Faire des devoirs',
                'description' => 'Faire les devoirs quotidiens dans toutes les matières',
                'type' => TaskType::TASK,
                'status' => Task::STATUS_IN_PROGRESS,
                'frequency' => Task::FREQUENCY_DAILY,
                'repeatDaysOfWeek' => [1, 2, 3, 4, 5],
                'requiresProof' => true,
                'proofType' => 'file',
                'startDate' => $getMonday(-1),
                'dueDate' => $getDate('+2 months'),
                'objective' => $this->objectives[1],
                'assignedType' => 'student',
                'student' => $this->students[1]
            ],
            [
                'title' => 'Réviser tous les soirs',
                'description' => 'Réviser les leçons de la journée chaque soir pendant 30 minutes',
                'type' => TaskType::TASK,
                'status' => Task::STATUS_IN_PROGRESS,
                'frequency' => Task::FREQUENCY_DAILY,
                'repeatDaysOfWeek' => [1, 2, 3, 4, 5],
                'requiresProof' => false,
                'startDate' => $getDate('-5 days'),
                'dueDate' => $getDate('+2 months'),
                'objective' => $this->objectives[1],
                'assignedType' => 'student',
                'student' => $this->students[1]
            ],
            [
                'title' => 'Atelier "Expression orale - Théâtre"',
                'description' => 'Participer à un atelier de théâtre pour améliorer l\'expression orale et la prise de parole',
                'type' => TaskType::WORKSHOP,
                'status' => Task::STATUS_COMPLETED,
                'frequency' => Task::FREQUENCY_NONE,
                'repeatDaysOfWeek' => null,
                'requiresProof' => true,
                'proofType' => 'workshop',
                'startDate' => $getDate('-1 week'),
                'dueDate' => $getDate('-3 days'),
                'objective' => $this->objectives[1],
                'assignedType' => 'student',
                'student' => $this->students[1]
            ],
            [
                'title' => 'Bilan de progression en français',
                'description' => 'Réaliser un bilan avec le professeur de collège sur l\'évolution en français',
                'type' => TaskType::ASSESSMENT,
                'status' => Task::STATUS_IN_PROGRESS,
                'frequency' => Task::FREQUENCY_MONTHLY,
                'repeatDaysOfWeek' => [1], // Premier lundi du mois
                'requiresProof' => true,
                'proofType' => 'text',
                'startDate' => $getDate('-5 days'),
                'dueDate' => $getDate('+1 week'),
                'objective' => $this->objectives[1],
                'assignedType' => 'student',
                'student' => $this->students[1]
            ],
            // Objectif 3 - Thomas (Concentration)
            [
                'title' => 'Faire des devoirs',
                'description' => 'Faire les devoirs quotidiens dans toutes les matières',
                'type' => TaskType::TASK,
                'status' => Task::STATUS_IN_PROGRESS,
                'frequency' => Task::FREQUENCY_DAILY,
                'repeatDaysOfWeek' => [1, 2, 3, 4, 5],
                'requiresProof' => true,
                'proofType' => 'file',
                'startDate' => $getMonday(-1),
                'dueDate' => $getDate('+2 months'),
                'objective' => $this->objectives[2],
                'assignedType' => 'student',
                'student' => $this->students[2]
            ],
            [
                'title' => 'Se coucher tôt',
                'description' => 'Se coucher avant 21h30 pour être en forme le lendemain',
                'type' => TaskType::TASK,
                'status' => Task::STATUS_PENDING,
                'frequency' => Task::FREQUENCY_DAILY,
                'repeatDaysOfWeek' => [0, 1, 2, 3, 4, 5, 6],
                'requiresProof' => false,
                'startDate' => $getDate('+1 day'),
                'dueDate' => $getDate('+2 months'),
                'objective' => $this->objectives[2],
                'assignedType' => 'student',
                'student' => $this->students[2]
            ],
            [
                'title' => 'Ne pas bavarder en classe',
                'description' => 'Rester concentré et ne pas bavarder pendant les cours',
                'type' => TaskType::TASK,
                'status' => Task::STATUS_PENDING,
                'frequency' => Task::FREQUENCY_DAILY,
                'repeatDaysOfWeek' => [1, 2, 3, 4, 5],
                'requiresProof' => false,
                'startDate' => $getDate('+1 day'),
                'dueDate' => $getDate('+2 months'),
                'objective' => $this->objectives[2],
                'assignedType' => 'student',
                'student' => $this->students[2]
            ],
            [
                'title' => 'Activité scolaire - Exercices de lecture',
                'description' => 'Compléter des exercices de lecture et de compréhension',
                'type' => TaskType::SCHOOL_ACTIVITY_TASK,
                'status' => Task::STATUS_PENDING,
                'frequency' => Task::FREQUENCY_WEEKLY,
                'repeatDaysOfWeek' => [4], // Jeudi
                'requiresProof' => true,
                'proofType' => 'file',
                'startDate' => $getDate('+3 days'),
                'dueDate' => $getDate('+2 months'),
                'objective' => $this->objectives[2],
                'assignedType' => 'student',
                'student' => $this->students[2]
            ],
            // Objectif 5 - Partagé Groupe 1
            [
                'title' => 'Atelier de communication en groupe',
                'description' => 'Atelier pour apprendre à communiquer et travailler en équipe',
                'type' => TaskType::WORKSHOP,
                'status' => Task::STATUS_IN_PROGRESS,
                'frequency' => Task::FREQUENCY_NONE,
                'repeatDaysOfWeek' => null,
                'requiresProof' => true,
                'proofType' => 'workshop',
                'startDate' => $getDate('-3 days'),
                'dueDate' => $getDate('+1 week'),
                'objective' => $this->objectives[4],
                'assignedType' => 'student',
                'student' => $this->students[0] // Lucas
            ],
            // Objectif 7 - Partagé avec Spécialistes
            [
                'title' => 'Séance de suivi mathématiques',
                'description' => 'Séance de suivi avec le professeur de mathématiques',
                'type' => TaskType::ASSESSMENT,
                'status' => Task::STATUS_IN_PROGRESS,
                'frequency' => Task::FREQUENCY_WEEKLY,
                'repeatDaysOfWeek' => [2], // Mardi
                'requiresProof' => true,
                'proofType' => 'text',
                'startDate' => $getMonday(-1)->modify('+1 day'), // Mardi dernier
                'dueDate' => $getDate('+1 month'),
                'objective' => $this->objectives[6],
                'assignedType' => 'student',
                'student' => $this->students[0]
            ]
        ];

        foreach ($tasksData as $data) {
            $task = new Task();
            $task->setTitle($data['title']);
            $task->setDescription($data['description']);
            $task->setType($data['type']);
            $task->setStatus($data['status']);
            $task->setFrequency($data['frequency']);
            $task->setRepeatDaysOfWeek($data['repeatDaysOfWeek']);
            $task->setRequiresProof($data['requiresProof']);
            if (isset($data['proofType'])) {
                $task->setProofType($data['proofType']);
            }
            $task->setStartDate($data['startDate']);
            $task->setDueDate($data['dueDate']);
            $task->setObjective($data['objective']);
            $task->setCoach($this->coach);
            $task->setAssignedType($data['assignedType']);
            if ($data['assignedType'] === 'student') {
                $task->setStudent($data['student']);
            }
            $task->setCreatedAt($now->modify('-1 week'));
            $task->setUpdatedAt($now);

            $this->em->persist($task);
            $this->tasks[] = $task;
        }

        $io->text('✓ ' . count($tasksData) . ' tâches créées');
    }

    private function createActivities(SymfonyStyle $io): void
    {
        $now = new \DateTimeImmutable();

        // Créer une catégorie d'activité
        $category = new ActivityCategory();
        $category->setName('Activités éducatives');
        $category->setDescription('Activités pour le développement des compétences');
        $this->em->persist($category);

        $activitiesData = [
            [
                'title' => 'Peinture collective',
                'description' => 'Activité créative de peinture à faire en groupe. Les enfants créent une œuvre collective en utilisant différentes techniques de peinture (pinceaux, éponges, doigts).',
                'duration' => '45-60 minutes',
                'ageRange' => '6-12 ans',
                'type' => Activity::TYPE_WITH_ADULT,
                'objectives' => ['Développer la créativité', 'Travailler en groupe', 'Expression artistique'],
                'workedPoints' => ['Créativité', 'Coopération', 'Motricité fine', 'Expression'],
                'links' => [],
                'status' => Activity::STATUS_PUBLISHED
            ],
            [
                'title' => 'Construction d\'un château en carton',
                'description' => 'Activité de construction créative en groupe. Les enfants construisent ensemble un château en carton en utilisant des boîtes, du carton et de la colle. À faire en groupe pour favoriser la collaboration.',
                'duration' => '60-90 minutes',
                'ageRange' => '8-14 ans',
                'type' => Activity::TYPE_WITH_ADULT,
                'objectives' => ['Développer la créativité', 'Travailler en équipe', 'Planification et construction'],
                'workedPoints' => ['Créativité', 'Travail d\'équipe', 'Planification', 'Motricité', 'Imagination'],
                'links' => [],
                'status' => Activity::STATUS_PUBLISHED
            ],
            [
                'title' => 'Atelier de modelage en argile',
                'description' => 'Activité créative de modelage en groupe. Les enfants créent des sculptures en argile en travaillant ensemble sur un thème commun.',
                'duration' => '40-60 minutes',
                'ageRange' => '7-12 ans',
                'type' => Activity::TYPE_WITH_ADULT,
                'objectives' => ['Développer la créativité', 'Expression artistique', 'Travail collaboratif'],
                'workedPoints' => ['Créativité', 'Motricité fine', 'Coopération', 'Expression'],
                'links' => [],
                'status' => Activity::STATUS_PUBLISHED
            ],
            [
                'title' => 'Création de masques en papier mâché',
                'description' => 'Activité créative en groupe pour créer des masques en papier mâché. Les enfants travaillent ensemble pour créer une collection de masques sur un thème.',
                'duration' => '50-70 minutes',
                'ageRange' => '8-13 ans',
                'type' => Activity::TYPE_WITH_ADULT,
                'objectives' => ['Développer la créativité', 'Travail en groupe', 'Patience et précision'],
                'workedPoints' => ['Créativité', 'Patience', 'Coopération', 'Motricité fine'],
                'links' => [],
                'status' => Activity::STATUS_PUBLISHED
            ]
        ];

        foreach ($activitiesData as $data) {
            $activity = new Activity();
            $activity->setTitle($data['title']);
            $activity->setDescription($data['description']);
            $activity->setDuration($data['duration']);
            $activity->setAgeRange($data['ageRange']);
            $activity->setType($data['type']);
            $activity->setObjectives($data['objectives']);
            $activity->setWorkedPoints($data['workedPoints']);
            $activity->setLinks($data['links']);
            $activity->setStatus($data['status']);
            $activity->setCategory($category);
            $activity->setCreatedBy($this->coach);
            $activity->setCreatedAt($now->modify('-2 weeks'));
            $activity->setUpdatedAt($now);

            $this->em->persist($activity);
            $this->activities[] = $activity;
        }

        $io->text('✓ ' . count($activitiesData) . ' activités créées');
    }

    private function createProofs(SymfonyStyle $io): void
    {
        $now = new \DateTimeImmutable();
        $proofsCount = 0;

        // Créer quelques preuves pour les tâches qui en nécessitent
        foreach ($this->tasks as $task) {
            if ($task->isRequiresProof() && $task->getStatus() === Task::STATUS_IN_PROGRESS) {
                // Créer 1-3 preuves aléatoires
                $numProofs = rand(1, 3);
                for ($i = 0; $i < $numProofs; $i++) {
                    $proof = new Proof();
                    $proof->setTask($task);
                    $proof->setType($task->getProofType() ?? 'file');
                    $proof->setTitle('Preuve pour ' . $task->getTitle());
                    $proof->setDescription('Preuve soumise pour la tâche : ' . $task->getTitle());
                    $proof->setSubmittedBy($task->getStudent());
                    $proof->setCreatedAt($now->modify("-{$i} days"));
                    $proof->setUpdatedAt($now->modify("-{$i} days"));

                    $this->em->persist($proof);
                    $proofsCount++;
                }
            }
        }

        $io->text("✓ $proofsCount preuves créées");
    }

    private function createRequestsAndMessages(SymfonyStyle $io): void
    {
        $now = new \DateTimeImmutable();

        $requestsData = [
            [
                'title' => 'Résolution d\'équations',
                'description' => 'Je ne sais pas comment résoudre l\'équation 2x + 5 = 13. Pouvez-vous m\'aider ?',
                'type' => 'academic_support',
                'status' => 'in_progress',
                'priority' => 'medium',
                'student' => $this->students[0], // Lucas
                'specialist' => $this->specialists[0], // Maths
                'messages' => [
                    [
                        'content' => 'Bonjour, je ne sais pas comment résoudre l\'équation 2x + 5 = 13. Pouvez-vous m\'aider ?',
                        'isFromMe' => true,
                        'type' => 'text',
                        'createdAt' => '-5 days'
                    ],
                    [
                        'content' => 'Bonjour Lucas ! Pour résoudre cette équation, tu dois isoler x. Commence par soustraire 5 des deux côtés de l\'équation. Que trouves-tu ?',
                        'isFromMe' => false,
                        'type' => 'text',
                        'createdAt' => '-4 days 12 hours'
                    ],
                    [
                        'content' => 'J\'ai soustrait 5, donc j\'ai 2x = 8. C\'est ça ?',
                        'isFromMe' => true,
                        'type' => 'text',
                        'createdAt' => '-4 days 8 hours'
                    ],
                    [
                        'content' => 'Parfait Lucas ! Tu as bien compris. Maintenant, pour trouver x, que dois-tu faire ?',
                        'isFromMe' => false,
                        'type' => 'text',
                        'createdAt' => '-4 days 6 hours'
                    ],
                    [
                        'content' => 'Je dois diviser par 2 ! Donc x = 4. C\'est la solution ?',
                        'isFromMe' => true,
                        'type' => 'text',
                        'createdAt' => '-4 days 4 hours'
                    ],
                    [
                        'content' => 'Excellent ! Tu as trouvé la bonne solution. x = 4. Vérifie en remplaçant x par 4 dans l\'équation de départ : 2 × 4 + 5 = 13. Est-ce que ça fonctionne ?',
                        'isFromMe' => false,
                        'type' => 'text',
                        'createdAt' => '-4 days 2 hours'
                    ],
                    [
                        'content' => 'Oui ! 2 × 4 + 5 = 8 + 5 = 13. Ça marche ! Merci beaucoup pour votre aide.',
                        'isFromMe' => true,
                        'type' => 'text',
                        'createdAt' => '-3 days'
                    ]
                ]
            ],
            [
                'title' => 'Résolution d\'équations avec fractions',
                'description' => 'Je ne comprends pas comment résoudre l\'équation (x/2) + 3 = 7.',
                'type' => 'academic_support',
                'status' => 'in_progress',
                'priority' => 'medium',
                'student' => $this->students[1], // Emma
                'specialist' => $this->specialists[3], // Collège
                'messages' => [
                    [
                        'content' => 'Bonjour, je ne comprends pas comment résoudre l\'équation (x/2) + 3 = 7. Pouvez-vous m\'aider ?',
                        'isFromMe' => true,
                        'type' => 'text',
                        'createdAt' => '-3 days'
                    ],
                    [
                        'content' => 'Bonjour Emma ! Pour résoudre cette équation, commence par soustraire 3 des deux côtés. Que trouves-tu ?',
                        'isFromMe' => false,
                        'type' => 'text',
                        'createdAt' => '-2 days 18 hours'
                    ],
                    [
                        'content' => 'J\'ai soustrait 3, donc j\'ai x/2 = 4. Est-ce correct ?',
                        'isFromMe' => true,
                        'type' => 'text',
                        'createdAt' => '-2 days 16 hours'
                    ],
                    [
                        'content' => 'Très bien Emma ! Maintenant, pour isoler x, que dois-tu faire avec cette fraction ?',
                        'isFromMe' => false,
                        'type' => 'text',
                        'createdAt' => '-2 days 14 hours'
                    ],
                    [
                        'content' => 'Je dois multiplier par 2 ! Donc x = 8. C\'est ça ?',
                        'isFromMe' => true,
                        'type' => 'text',
                        'createdAt' => '-2 days 12 hours'
                    ],
                    [
                        'content' => 'Parfait ! Tu as trouvé la solution x = 8. Vérifie en remplaçant : (8/2) + 3 = 4 + 3 = 7. C\'est correct !',
                        'isFromMe' => false,
                        'type' => 'text',
                        'createdAt' => '-2 days 10 hours'
                    ]
                ]
            ],
            [
                'title' => 'Résolution d\'équations du premier degré',
                'description' => 'Je ne sais pas comment résoudre l\'équation 3x - 7 = 2x + 5.',
                'type' => 'academic_support',
                'status' => 'resolved',
                'priority' => 'medium',
                'student' => $this->students[2], // Thomas
                'specialist' => $this->specialists[0], // Maths
                'messages' => [
                    [
                        'content' => 'Bonjour, je ne sais pas comment résoudre l\'équation 3x - 7 = 2x + 5. Pouvez-vous m\'aider ?',
                        'isFromMe' => true,
                        'type' => 'text',
                        'createdAt' => '-1 week'
                    ],
                    [
                        'content' => 'Bonjour Thomas ! Pour résoudre cette équation, tu dois regrouper tous les termes avec x d\'un côté et les nombres de l\'autre. Commence par soustraire 2x des deux côtés. Que trouves-tu ?',
                        'isFromMe' => false,
                        'type' => 'text',
                        'createdAt' => '-6 days 14 hours'
                    ],
                    [
                        'content' => 'J\'ai soustrait 2x, donc j\'ai x - 7 = 5. C\'est ça ?',
                        'isFromMe' => true,
                        'type' => 'text',
                        'createdAt' => '-6 days 12 hours'
                    ],
                    [
                        'content' => 'Excellent ! Maintenant, pour isoler x, que dois-tu faire ?',
                        'isFromMe' => false,
                        'type' => 'text',
                        'createdAt' => '-6 days 10 hours'
                    ],
                    [
                        'content' => 'Je dois ajouter 7 ! Donc x = 12. C\'est la solution ?',
                        'isFromMe' => true,
                        'type' => 'text',
                        'createdAt' => '-6 days 8 hours'
                    ],
                    [
                        'content' => 'Parfait Thomas ! x = 12 est la bonne solution. Vérifie : 3 × 12 - 7 = 36 - 7 = 29, et 2 × 12 + 5 = 24 + 5 = 29. Les deux côtés sont égaux, c\'est correct !',
                        'isFromMe' => false,
                        'type' => 'text',
                        'createdAt' => '-5 days'
                    ],
                    [
                        'content' => 'Merci beaucoup ! J\'ai bien compris maintenant.',
                        'isFromMe' => true,
                        'type' => 'text',
                        'createdAt' => '-4 days'
                    ]
                ]
            ],
            [
                'title' => 'Résolution d\'équations avec parenthèses',
                'description' => 'Je ne sais pas comment résoudre l\'équation 2(x + 3) = 14.',
                'type' => 'academic_support',
                'status' => 'in_progress',
                'priority' => 'high',
                'student' => $this->students[3], // Léa
                'specialist' => $this->specialists[0], // Maths
                'messages' => [
                    [
                        'content' => 'Bonjour, je ne sais pas comment résoudre l\'équation 2(x + 3) = 14. Pouvez-vous m\'aider ?',
                        'isFromMe' => true,
                        'type' => 'text',
                        'createdAt' => '-3 days'
                    ],
                    [
                        'content' => 'Bonjour Léa ! Pour résoudre cette équation, commence par développer la parenthèse. Que trouves-tu ?',
                        'isFromMe' => false,
                        'type' => 'text',
                        'createdAt' => '-2 days 20 hours'
                    ],
                    [
                        'content' => 'J\'ai développé : 2x + 6 = 14. C\'est correct ?',
                        'isFromMe' => true,
                        'type' => 'text',
                        'createdAt' => '-2 days 18 hours'
                    ],
                    [
                        'content' => 'Parfait Léa ! Maintenant, soustrais 6 des deux côtés. Que trouves-tu ?',
                        'isFromMe' => false,
                        'type' => 'text',
                        'createdAt' => '-2 days 16 hours'
                    ],
                    [
                        'content' => 'J\'ai soustrait 6, donc 2x = 8. Maintenant je divise par 2, donc x = 4. C\'est la solution ?',
                        'isFromMe' => true,
                        'type' => 'text',
                        'createdAt' => '-2 days 14 hours'
                    ],
                    [
                        'content' => 'Excellent travail Léa ! Tu as bien suivi toutes les étapes. x = 4 est la bonne solution. Vérifie : 2(4 + 3) = 2 × 7 = 14. Parfait !',
                        'isFromMe' => false,
                        'type' => 'text',
                        'createdAt' => '-2 days 12 hours'
                    ],
                    [
                        'content' => 'Merci beaucoup ! J\'ai bien compris la méthode maintenant.',
                        'isFromMe' => true,
                        'type' => 'text',
                        'createdAt' => '-2 days'
                    ]
                ]
            ]
        ];

        foreach ($requestsData as $requestData) {
            $request = new Request();
            $request->setTitle($requestData['title']);
            $request->setDescription($requestData['description']);
            $request->setType($requestData['type']);
            $request->setStatus($requestData['status']);
            $request->setPriority($requestData['priority']);
            $request->setCoach($this->coach);
            $request->setStudent($requestData['student']);
            $request->setSpecialist($requestData['specialist']);
            $request->setFamily($requestData['student']->getFamily());
            $request->setCreator($requestData['student']);
            $request->setRecipient($requestData['specialist']);
            $request->setCreatedAt($now->modify($requestData['messages'][0]['createdAt']));
            $request->setUpdatedAt($now);

            $this->em->persist($request);

            // Créer les messages
            foreach ($requestData['messages'] as $messageData) {
                $message = new Message();
                $message->setRequest($request);
                $message->setContent($messageData['content']);
                $message->setType($messageData['type']);
                $message->setCreatedAt($now->modify($messageData['createdAt']));
                $message->setUpdatedAt($now->modify($messageData['createdAt']));
                $message->setCoach($this->coach);

                if ($messageData['isFromMe']) {
                    $message->setSender($requestData['student']);
                    $message->setReceiver($requestData['specialist']);
                    $message->setRecipient($requestData['specialist']);
                } else {
                    $message->setSender($requestData['specialist']);
                    $message->setReceiver($requestData['student']);
                    $message->setRecipient($requestData['student']);
                }

                $this->em->persist($message);
            }

            $this->requests[] = $request;
        }

        $io->text('✓ ' . count($requestsData) . ' demandes créées avec leurs messages');
    }

    private function createNotesAndComments(SymfonyStyle $io): void
    {
        $now = new \DateTimeImmutable();
        $notesCount = 0;
        $commentsCount = 0;

        // Créer des notes pour quelques élèves
        foreach ([$this->students[0], $this->students[1]] as $student) {
            $note = new Note();
            $note->setStudent($student);
            $note->setType(NoteType::ASSESSMENT);
            $note->setText('Point de situation sur les progrès de ' . $student->getFirstName() . '. Bonne évolution générale.');
            $note->setCreatedBy($this->coach);
            $note->setCreatedAt($now->modify('-1 week'));
            $note->setUpdatedAt($now->modify('-1 week'));
            $this->em->persist($note);
            $notesCount++;
        }

        // Créer des commentaires sur les objectifs
        foreach ([$this->objectives[0], $this->objectives[1], $this->objectives[6]] as $objective) {
            $comment = new Comment();
            $comment->setObjective($objective);
            $comment->setContent('Bon suivi, continuez comme ça !');
            $comment->setAuthor($this->coach);
            $comment->setCreatedAt($now->modify('-3 days'));
            $comment->setUpdatedAt($now->modify('-3 days'));
            $this->em->persist($comment);
            $commentsCount++;
        }

        // Créer des commentaires sur les activités
        foreach ($this->activities as $activity) {
            $comment = new Comment();
            $comment->setActivity($activity);
            $comment->setContent('Très bonne activité, les élèves ont beaucoup apprécié !');
            $comment->setAuthor($this->coach);
            $comment->setCreatedAt($now->modify('-1 week'));
            $comment->setUpdatedAt($now->modify('-1 week'));
            $this->em->persist($comment);
            $commentsCount++;
        }

        $io->text("✓ $notesCount notes et $commentsCount commentaires créés");
    }

    private function createPlanning(SymfonyStyle $io): void
    {
        $now = new \DateTimeImmutable();
        $planningCount = 0;

        // Créer des événements de planning pour les tâches et activités
        foreach ($this->tasks as $task) {
            if ($task->getType() === TaskType::WORKSHOP || $task->getType() === TaskType::INDIVIDUAL_WORK_ON_SITE) {
                $planning = new Planning();
                $planning->setTitle($task->getTitle());
                $planning->setDescription($task->getDescription());
                $planning->setStartDate($task->getStartDate());
                $planning->setEndDate($task->getDueDate());
                $planning->setUser($task->getStudent()); // Utiliser setUser() pour l'élève
                $planning->setType(Planning::TYPE_ACTIVITY);
                $planning->setStatus(Planning::STATUS_TO_DO);
                $planning->setCreatedAt($now);
                $planning->setUpdatedAt($now);
                $this->em->persist($planning);
                $planningCount++;
            }
        }

        $io->text("✓ $planningCount événements de planning créés");
    }

    private function displaySummary(SymfonyStyle $io): void
    {
        $io->section('Résumé des données créées');
        $io->table(
            ['Type', 'Nombre'],
            [
                ['Coach', 1],
                ['Spécialistes', count($this->specialists)],
                ['Parents', count($this->parents)],
                ['Élèves', count($this->students)],
                ['Groupes', count($this->groups)],
                ['Objectifs', count($this->objectives)],
                ['Tâches', count($this->tasks)],
                ['Activités', count($this->activities)],
                ['Demandes', count($this->requests)]
            ]
        );

        $io->note([
            'Identifiants de connexion :',
            '- Coach: coach.demo@sara.fr / demo123',
            '- Spécialistes: prof.*.demo@sara.fr / demo123',
            '- Parents: parent*.demo@sara.fr / demo123',
            '- Élèves: eleve*.demo@sara.fr / demo123'
        ]);
    }
}

