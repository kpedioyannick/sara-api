<?php

namespace App\Command;

use App\Entity\Coach;
use App\Entity\ParentUser;
use App\Entity\Student;
use App\Entity\Specialist;
use App\Entity\Family;
use App\Entity\Objective;
use App\Entity\Task;
use App\Entity\Planning;
use App\Entity\Request as RequestEntity;
use App\Entity\Message;
use App\Entity\Availability;
use App\Entity\Comment;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:seed-database',
    description: 'Crée des comptes de test et des données pour chaque profil',
)]
class SeedDatabaseCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $passwordHasher
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('🌱 Démarrage du seeding de la base de données...');
        $output->writeln('');

        // Mot de passe par défaut pour tous les comptes
        $defaultPassword = 'password123';

        try {
            // 1. Créer des Coachs
            $output->writeln('👨‍🏫 Création des Coachs...');
            $coachesData = [
                ['email' => 'sara@coach.com', 'firstName' => 'Sara', 'lastName' => 'Educateur', 'specialization' => 'Pédagogie adaptée'],
                ['email' => 'marie@coach.com', 'firstName' => 'Marie', 'lastName' => 'Dupont', 'specialization' => 'Soutien scolaire'],
                ['email' => 'pierre@coach.com', 'firstName' => 'Pierre', 'lastName' => 'Leroy', 'specialization' => 'Gestion comportementale'],
            ];
            
            $coaches = [];
            foreach ($coachesData as $coachData) {
                $existingCoach = $this->em->getRepository(Coach::class)->findOneBy(['email' => $coachData['email']]);
            
            if ($existingCoach) {
                    $output->writeln("   ⚠️  Coach {$coachData['email']} existe déjà, réutilisation...");
                    $coaches[] = $existingCoach;
            } else {
                $coach = new Coach();
                    $coach->setEmail($coachData['email']);
                    $coach->setFirstName($coachData['firstName']);
                    $coach->setLastName($coachData['lastName']);
                    $coach->setSpecialization($coachData['specialization']);
                $hashedPassword = $this->passwordHasher->hashPassword($coach, $defaultPassword);
                $coach->setPassword($hashedPassword);
                $coach->setIsActive(true);
                $this->em->persist($coach);
                    $coaches[] = $coach;
                    $output->writeln("   ✅ Coach créé: {$coachData['email']} / {$defaultPassword}");
                }
            }
            $this->em->flush();
            $coach = $coaches[0]; // Premier coach comme coach principal

            // 2. Créer des Spécialistes
            $output->writeln('');
            $output->writeln('👨‍⚕️  Création des Spécialistes...');
            $specialistsData = [
                ['email' => 'sarah@specialist.com', 'firstName' => 'Sarah', 'lastName' => 'Cohen', 'specializations' => ['Psychologie', 'Troubles de l\'apprentissage']],
                ['email' => 'marc@specialist.com', 'firstName' => 'Marc', 'lastName' => 'Dubois', 'specializations' => ['Orthophonie', 'Langage']],
                ['email' => 'julie@specialist.com', 'firstName' => 'Julie', 'lastName' => 'Moreau', 'specializations' => ['Psychomotricité', 'Développement moteur']],
            ];
            
            $specialists = [];
            foreach ($specialistsData as $specData) {
                $existingSpecialist = $this->em->getRepository(Specialist::class)->findOneBy(['email' => $specData['email']]);
            
            if ($existingSpecialist) {
                    $output->writeln("   ⚠️  Spécialiste {$specData['email']} existe déjà, réutilisation...");
                    $specialists[] = $existingSpecialist;
            } else {
                $specialist = new Specialist();
                    $specialist->setEmail($specData['email']);
                    $specialist->setFirstName($specData['firstName']);
                    $specialist->setLastName($specData['lastName']);
                    $specialist->setSpecializations($specData['specializations']);
                $hashedPassword = $this->passwordHasher->hashPassword($specialist, $defaultPassword);
                $specialist->setPassword($hashedPassword);
                $specialist->setIsActive(true);
                $this->em->persist($specialist);
                    $specialists[] = $specialist;
                    $output->writeln("   ✅ Spécialiste créé: {$specData['email']} / {$defaultPassword}");
                }
            }
            $this->em->flush();
            $specialist = $specialists[0]; // Premier spécialiste comme spécialiste principal

            // 3. Créer une Famille avec un Parent
            $output->writeln('');
            $output->writeln('👨‍👩‍👧 Création de la Famille et du Parent...');
            $parentEmail = 'parent@sara.education';
            $existingParent = $this->em->getRepository(ParentUser::class)->findOneBy(['email' => $parentEmail]);
            
            if ($existingParent) {
                $output->writeln('   ⚠️  Parent existe déjà, réutilisation...');
                $parent = $existingParent;
                $family = $parent->getFamily();
            } else {
                $family = new Family();
                $family->setFamilyIdentifier('FAM_DUPONT_J');
                $family->setCoach($coach);
                $this->em->persist($family);
                
                $parent = new ParentUser();
                $parent->setEmail($parentEmail);
                $parent->setFirstName('Jean');
                $parent->setLastName('Dupont');
                $hashedPassword = $this->passwordHasher->hashPassword($parent, $defaultPassword);
                $parent->setPassword($hashedPassword);
                $parent->setFamily($family);
                $parent->setIsActive(true);
                $this->em->persist($parent);
                $this->em->flush();
                $output->writeln("   ✅ Parent créé: {$parentEmail} / {$defaultPassword}");
            }

            // 4. Créer des Enfants (Students)
            $output->writeln('');
            $output->writeln('👦 Création des Enfants...');
            $studentsData = [
                ['pseudo' => 'lucas', 'class' => 'CM2', 'firstName' => 'Lucas', 'lastName' => 'Dupont', 'points' => 150],
                ['pseudo' => 'sophie', 'class' => '6ème', 'firstName' => 'Sophie', 'lastName' => 'Dupont', 'points' => 120],
            ];
            
            $students = [];
            foreach ($studentsData as $studentData) {
                $studentEmail = $studentData['pseudo'] . '@sara.education';
                $existingStudent = $this->em->getRepository(Student::class)->findOneBy(['email' => $studentEmail]);
                
                if ($existingStudent) {
                    $output->writeln("   ⚠️  Étudiant {$studentData['pseudo']} existe déjà, réutilisation...");
                    $students[] = $existingStudent;
                } else {
                    $student = new Student();
                    $student->setEmail($studentEmail);
                    $student->setFirstName($studentData['firstName']);
                    $student->setLastName($studentData['lastName']);
                    $student->setPseudo($studentData['pseudo']);
                    $student->setClass($studentData['class']);
                    $hashedPassword = $this->passwordHasher->hashPassword($student, $defaultPassword);
                    $student->setPassword($hashedPassword);
                    $student->setFamily($family);
                    $student->setPoints($studentData['points']);
                    $student->setIsActive(true);
                    $this->em->persist($student);
                    $students[] = $student;
                    $output->writeln("   ✅ Étudiant créé: {$studentData['pseudo']} ({$studentEmail}) / {$defaultPassword}");
                }
            }
            
            // Créer une deuxième famille avec plus d'enfants
            $output->writeln('');
            $output->writeln('👨‍👩‍👧 Création de la deuxième Famille...');
            $parent2Email = 'sophie.martin@sara.education';
            $existingParent2 = $this->em->getRepository(ParentUser::class)->findOneBy(['email' => $parent2Email]);
            
            if ($existingParent2) {
                $output->writeln("   ⚠️  Parent {$parent2Email} existe déjà, réutilisation...");
                $parent2 = $existingParent2;
                $family2 = $parent2->getFamily();
            } else {
                $family2 = new Family();
                $family2->setFamilyIdentifier('FAM_MARTIN_S');
                $family2->setCoach($coach);
                $this->em->persist($family2);
                
                $parent2 = new ParentUser();
                $parent2->setEmail($parent2Email);
                $parent2->setFirstName('Sophie');
                $parent2->setLastName('Martin');
                $hashedPassword = $this->passwordHasher->hashPassword($parent2, $defaultPassword);
                $parent2->setPassword($hashedPassword);
                $parent2->setFamily($family2);
                $parent2->setIsActive(true);
                $this->em->persist($parent2);
                $this->em->flush();
                $output->writeln("   ✅ Parent créé: {$parent2Email} / {$defaultPassword}");
            }
            
            $studentsData2 = [
                ['pseudo' => 'tom', 'class' => 'CM1', 'firstName' => 'Tom', 'lastName' => 'Martin', 'points' => 200],
                ['pseudo' => 'emma', 'class' => 'CE2', 'firstName' => 'Emma', 'lastName' => 'Martin', 'points' => 180],
            ];
            
            foreach ($studentsData2 as $studentData) {
                $studentEmail = $studentData['pseudo'] . '@sara.education';
                $existingStudent = $this->em->getRepository(Student::class)->findOneBy(['email' => $studentEmail]);
                
                if (!$existingStudent) {
                    $student = new Student();
                    $student->setEmail($studentEmail);
                    $student->setFirstName($studentData['firstName']);
                    $student->setLastName($studentData['lastName']);
                    $student->setPseudo($studentData['pseudo']);
                    $student->setClass($studentData['class']);
                    $hashedPassword = $this->passwordHasher->hashPassword($student, $defaultPassword);
                    $student->setPassword($hashedPassword);
                    $student->setFamily($family2);
                    $student->setPoints($studentData['points']);
                    $student->setIsActive(true);
                    $this->em->persist($student);
                    $students[] = $student;
                    $output->writeln("   ✅ Étudiant créé: {$studentData['pseudo']} ({$studentEmail}) / {$defaultPassword}");
                } else {
                    $students[] = $existingStudent;
                }
            }
            
            $this->em->flush();

            // Note: L'assignation du spécialiste se fait via les demandes (Requests)
            // Pas besoin de lien direct ici

            // 5. Créer des Objectifs et Tâches
            $output->writeln('');
            $output->writeln('🎯 Création des Objectifs et Tâches...');
            $lucas = $students[0];
            $sophie = $students[1] ?? $students[0];
            $tom = $students[2] ?? $students[0];
            $emma = $students[3] ?? $students[0];
            
            $objectivesData = [
                [
                    'student' => $lucas,
                    'title' => 'Améliorer la concentration',
                    'description' => 'Réviser les tables de multiplication et les divisions',
                    'category' => 'Comportement',
                    'status' => 'en_cours',
                    'progress' => 65,
                    'deadline' => new \DateTimeImmutable('+3 months'),
                    'tasks' => [
                        ['title' => 'Faire 10 minutes de méditation chaque matin', 'status' => 'completed', 'requiresProof' => false, 'assignedTo' => $lucas, 'type' => 'student'],
                        ['title' => 'Réviser les tables de multiplication', 'status' => 'completed', 'requiresProof' => true, 'assignedTo' => $lucas, 'type' => 'student'],
                        ['title' => 'Faire les exercices de concentration', 'status' => 'in_progress', 'requiresProof' => true, 'assignedTo' => $lucas, 'type' => 'student'],
                        ['title' => 'Participer activement en classe', 'status' => 'pending', 'requiresProof' => false, 'assignedTo' => $lucas, 'type' => 'student'],
                        ['title' => 'Noter les moments de distraction', 'status' => 'pending', 'requiresProof' => false, 'assignedTo' => $parent, 'type' => 'parent'],
                    ],
                ],
                [
                    'student' => $sophie,
                    'title' => 'Développer l\'autonomie',
                    'description' => 'Apprendre à gérer ses affaires et son temps',
                    'category' => 'Autonomie',
                    'status' => 'en_cours',
                    'progress' => 40,
                    'deadline' => new \DateTimeImmutable('+2 months'),
                    'tasks' => [
                        ['title' => 'Préparer son cartable le soir', 'status' => 'completed', 'requiresProof' => false, 'assignedTo' => $sophie, 'type' => 'student'],
                        ['title' => 'Ranger sa chambre chaque semaine', 'status' => 'completed', 'requiresProof' => true, 'assignedTo' => $sophie, 'type' => 'student'],
                        ['title' => 'Faire ses devoirs seul(e)', 'status' => 'in_progress', 'requiresProof' => false, 'assignedTo' => $sophie, 'type' => 'student'],
                        ['title' => 'Gérer son réveil-matin', 'status' => 'pending', 'requiresProof' => false, 'assignedTo' => $parent, 'type' => 'parent'],
                    ],
                ],
                [
                    'student' => $lucas,
                    'title' => 'Renforcer la confiance en soi',
                    'description' => 'Participer plus activement en classe et aux activités sportives',
                    'category' => 'Émotionnel',
                    'status' => 'termine',
                    'progress' => 100,
                    'deadline' => new \DateTimeImmutable('-1 month'),
                    'tasks' => [
                        ['title' => 'Participer à une activité sportive', 'status' => 'completed', 'requiresProof' => true, 'assignedTo' => $lucas, 'type' => 'student'],
                        ['title' => 'Lever la main en classe au moins 3 fois', 'status' => 'completed', 'requiresProof' => false, 'assignedTo' => $lucas, 'type' => 'student'],
                        ['title' => 'Faire une présentation devant la classe', 'status' => 'completed', 'requiresProof' => true, 'assignedTo' => $lucas, 'type' => 'student'],
                    ],
                ],
                [
                    'student' => $tom,
                    'title' => 'Améliorer les relations sociales',
                    'description' => 'Développer les compétences sociales et la communication',
                    'category' => 'Social',
                    'status' => 'en_cours',
                    'progress' => 25,
                    'deadline' => new \DateTimeImmutable('+4 months'),
                    'tasks' => [
                        ['title' => 'Saluer 3 camarades chaque jour', 'status' => 'completed', 'requiresProof' => false, 'assignedTo' => $tom, 'type' => 'student'],
                        ['title' => 'Participer à un jeu en groupe', 'status' => 'in_progress', 'requiresProof' => true, 'assignedTo' => $tom, 'type' => 'student'],
                        ['title' => 'Inviter un ami à jouer', 'status' => 'pending', 'requiresProof' => false, 'assignedTo' => $tom, 'type' => 'student'],
                    ],
                ],
            ];
            
            foreach ($objectivesData as $objData) {
                $existingObjective = $this->em->getRepository(Objective::class)->findOneBy([
                    'title' => $objData['title'],
                    'student' => $objData['student']
                ]);
                
                if (!$existingObjective) {
                    $objective = new Objective();
                    $objective->setTitle($objData['title']);
                    $objective->setDescription($objData['description']);
                    $objective->setCategory($objData['category']);
                    $objective->setStatus($objData['status']);
                    $objective->setProgress($objData['progress']);
                    $objective->setDeadline($objData['deadline']);
                    $objective->setStudent($objData['student']);
                    $objective->setCoach($coach);
                    $this->em->persist($objective);
                    
                    // Créer les tâches
                    foreach ($objData['tasks'] as $taskData) {
                        $task = Task::createForCoach([
                            'title' => $taskData['title'],
                            'description' => $taskData['title'],
                            'status' => $taskData['status'],
                'frequency' => 'daily',
                            'requires_proof' => $taskData['requiresProof'],
                            'proof_type' => $taskData['requiresProof'] ? 'text' : null,
                        ], $objective, $taskData['assignedTo'], $taskData['type']);
                        $this->em->persist($task);
                    }
                }
            }
            
            $this->em->flush();
            $output->writeln('   ✅ Objectifs et tâches créés');

            // 6. Créer des Planning Events
            $output->writeln('');
            $output->writeln('📅 Création des événements de Planning...');
            
            $today = new \DateTimeImmutable();
            $planningEvents = [
                ['student' => $lucas, 'title' => 'Session de révision', 'type' => 'revision', 'status' => 'scheduled', 'date' => $today->modify('+2 days')->setTime(14, 0), 'duration' => 1],
                ['student' => $sophie, 'title' => 'Atelier créatif', 'type' => 'activity', 'status' => 'in_progress', 'date' => $today->modify('+3 days')->setTime(10, 0), 'duration' => 2],
                ['student' => $tom, 'title' => 'Soutien scolaire', 'type' => 'course', 'status' => 'scheduled', 'date' => $today->modify('+4 days')->setTime(15, 0), 'duration' => 2],
                ['student' => $lucas, 'title' => 'Évaluation trimestrielle', 'type' => 'assessment', 'status' => 'completed', 'date' => $today->modify('+5 days')->setTime(9, 0), 'duration' => 2],
                ['student' => $sophie, 'title' => 'Réunion parents', 'type' => 'activity', 'status' => 'scheduled', 'date' => $today->modify('+6 days')->setTime(14, 0), 'duration' => 1],
                ['student' => $tom, 'title' => 'Atelier mathématiques', 'type' => 'activity', 'status' => 'scheduled', 'date' => $today->modify('+7 days')->setTime(10, 0), 'duration' => 2],
                ['student' => $emma, 'title' => 'Devoir de français', 'type' => 'homework', 'status' => 'to_do', 'date' => $today->modify('+1 day')->setTime(16, 0), 'duration' => 2],
            ];
            
            foreach ($planningEvents as $eventData) {
                $planning = new Planning();
                $planning->setTitle($eventData['title']);
                $planning->setDescription($eventData['title']);
                $planning->setType($eventData['type']);
                $planning->setStatus($eventData['status']);
                $planning->setStartDate($eventData['date']);
                $planning->setEndDate($eventData['date']->modify('+' . $eventData['duration'] . ' hours'));
                $planning->setUser($eventData['student']);
                $this->em->persist($planning);
            }
            
            $this->em->flush();
            $output->writeln('   ✅ Événements de planning créés');

            // 7. Créer des Demandes (Requests)
            $output->writeln('');
            $output->writeln('📋 Création des Demandes...');
            
            $requestsData = [
                [
                    'title' => 'Demande de consultation',
                    'description' => 'Besoin d\'aide en orthographe pour Sophie',
                    'status' => 'pending',
                    'type' => 'consultation',
                    'priority' => 'high',
                    'student' => $sophie,
                    'parent' => $parent,
                    'creator' => $parent,
                    'recipient' => $coach,
                    'specialist' => $specialists[0],
                ],
                [
                    'title' => 'Besoin d\'aide pour devoirs',
                    'description' => 'Lucas a besoin d\'aide pour ses devoirs de mathématiques',
                    'status' => 'in_progress',
                    'type' => 'aide',
                    'priority' => 'medium',
                    'student' => $lucas,
                    'parent' => $parent,
                    'creator' => $lucas,
                    'recipient' => $coach,
                    'specialist' => null,
                ],
                [
                    'title' => 'Question sur l\'objectif',
                    'description' => 'Question concernant l\'objectif de développement de l\'autonomie',
                    'status' => 'resolved',
                    'type' => 'question',
                    'priority' => 'low',
                    'student' => $sophie,
                    'parent' => $parent2,
                    'creator' => $parent2,
                    'recipient' => $coach,
                    'specialist' => null,
                ],
                [
                    'title' => 'Demande spécialiste',
                    'description' => 'Demande d\'intervention d\'un spécialiste pour Tom',
                    'status' => 'pending',
                    'type' => 'specialiste',
                    'priority' => 'high',
                    'student' => $tom,
                    'parent' => $parent2,
                    'creator' => $tom,
                    'recipient' => $specialists[0],
                    'specialist' => $specialists[0],
                ],
            ];
            
            foreach ($requestsData as $reqData) {
                $request = new RequestEntity();
                $request->setTitle($reqData['title']);
                $request->setDescription($reqData['description']);
                $request->setStatus($reqData['status']);
                $request->setType($reqData['type']);
                $request->setPriority($reqData['priority']);
                $request->setStudent($reqData['student']);
                $request->setParent($reqData['parent']);
                $request->setCoach($coach);
                $request->setCreator($reqData['creator']);
                $request->setRecipient($reqData['recipient']);
                if ($reqData['specialist']) {
                    $request->setSpecialist($reqData['specialist']);
                }
                $this->em->persist($request);
            }
            
            $this->em->flush();
            $output->writeln('   ✅ Demandes créées');

            // 8. Créer des Messages
            $output->writeln('');
            $output->writeln('💬 Création des Messages...');
            
            $requests = $this->em->getRepository(RequestEntity::class)->findAll();
            if (!empty($requests)) {
                $messagesData = [
                    [
                        'content' => 'Bonjour, je souhaite planifier un rendez-vous pour Sophie.',
                        'sender' => $parent,
                        'receiver' => $specialists[0],
                        'recipient' => $specialists[0],
                        'request' => $requests[0],
                    ],
                    [
                        'content' => 'Je suis disponible la semaine prochaine, quelle date vous convient ?',
                        'sender' => $specialists[0],
                        'receiver' => $parent,
                        'recipient' => $parent,
                        'request' => $requests[0],
                    ],
                    [
                        'content' => 'Merci pour votre aide, Lucas progresse bien.',
                        'sender' => $parent,
                        'receiver' => $coach,
                        'recipient' => $coach,
                        'request' => count($requests) > 1 ? $requests[1] : $requests[0],
                    ],
                ];
                
                foreach ($messagesData as $msgData) {
                    if (isset($msgData['request'])) {
                        $message = new Message();
                        $message->setContent($msgData['content']);
                        $message->setSender($msgData['sender']);
                        $message->setReceiver($msgData['receiver']);
                        $message->setCoach($coach);
                        $message->setRecipient($msgData['recipient']);
                        $message->setRequest($msgData['request']);
                        $this->em->persist($message);
                    }
                }
            }
            
            $this->em->flush();
            $output->writeln('   ✅ Messages créés');

            // 9. Créer des Disponibilités
            $output->writeln('');
            $output->writeln('⏰ Création des Disponibilités...');
            
            // Disponibilités pour le coach
            $coachAvailabilities = [
                ['day' => 'monday', 'start' => 9, 'end' => 12],
                ['day' => 'monday', 'start' => 14, 'end' => 17],
                ['day' => 'wednesday', 'start' => 9, 'end' => 12],
                ['day' => 'friday', 'start' => 14, 'end' => 18],
            ];
            
            foreach ($coachAvailabilities as $availData) {
                $availability = new Availability();
                $availability->setCoach($coach);
                $availability->setDayOfWeek($availData['day']);
                $availability->setStartTime(new \DateTimeImmutable('2025-01-01 ' . str_pad($availData['start'], 2, '0', STR_PAD_LEFT) . ':00:00'));
                $availability->setEndTime(new \DateTimeImmutable('2025-01-01 ' . str_pad($availData['end'], 2, '0', STR_PAD_LEFT) . ':00:00'));
                $this->em->persist($availability);
            }
            
            // Disponibilités pour les spécialistes
            foreach ($specialists as $spec) {
                $specAvailabilities = [
                    ['day' => 'tuesday', 'start' => 10, 'end' => 13],
                    ['day' => 'thursday', 'start' => 14, 'end' => 17],
                ];
                
                foreach ($specAvailabilities as $availData) {
                    $availability = new Availability();
                    $availability->setSpecialist($spec);
                    $availability->setDayOfWeek($availData['day']);
                    $availability->setStartTime(new \DateTimeImmutable('2025-01-01 ' . str_pad($availData['start'], 2, '0', STR_PAD_LEFT) . ':00:00'));
                    $availability->setEndTime(new \DateTimeImmutable('2025-01-01 ' . str_pad($availData['end'], 2, '0', STR_PAD_LEFT) . ':00:00'));
                    $this->em->persist($availability);
                }
            }
            
            // Disponibilités pour les parents
            $parentAvailabilities = [
                ['parent' => $parent, 'day' => 'monday', 'start' => 18, 'end' => 20],
                ['parent' => $parent, 'day' => 'wednesday', 'start' => 18, 'end' => 20],
                ['parent' => $parent2, 'day' => 'tuesday', 'start' => 17, 'end' => 19],
            ];
            
            foreach ($parentAvailabilities as $availData) {
                $availability = new Availability();
                $availability->setParent($availData['parent']);
                $availability->setDayOfWeek($availData['day']);
                $availability->setStartTime(new \DateTimeImmutable('2025-01-01 ' . str_pad($availData['start'], 2, '0', STR_PAD_LEFT) . ':00:00'));
                $availability->setEndTime(new \DateTimeImmutable('2025-01-01 ' . str_pad($availData['end'], 2, '0', STR_PAD_LEFT) . ':00:00'));
                $this->em->persist($availability);
            }
            
            // Disponibilités pour les élèves
            $studentAvailabilities = [
                ['student' => $lucas, 'day' => 'monday', 'start' => 16, 'end' => 18],
                ['student' => $sophie, 'day' => 'tuesday', 'start' => 16, 'end' => 17],
                ['student' => $tom, 'day' => 'wednesday', 'start' => 15, 'end' => 17],
            ];
            
            foreach ($studentAvailabilities as $availData) {
                $availability = new Availability();
                $availability->setStudent($availData['student']);
                $availability->setDayOfWeek($availData['day']);
                $availability->setStartTime(new \DateTimeImmutable('2025-01-01 ' . str_pad($availData['start'], 2, '0', STR_PAD_LEFT) . ':00:00'));
                $availability->setEndTime(new \DateTimeImmutable('2025-01-01 ' . str_pad($availData['end'], 2, '0', STR_PAD_LEFT) . ':00:00'));
                $this->em->persist($availability);
            }
            
            $this->em->flush();
            $output->writeln('   ✅ Disponibilités créées');

            // 10. Créer des Commentaires
            $output->writeln('');
            $output->writeln('💭 Création des Commentaires...');
            
            $objectives = $this->em->getRepository(Objective::class)->findAll();
            if (!empty($objectives)) {
                $commentsData = [
                    ['objective' => $objectives[0], 'content' => 'Lucas progresse bien, continuez comme ça !', 'author' => $coach, 'type' => 'coach'],
                    ['objective' => $objectives[0], 'content' => 'Très bien, on voit des améliorations', 'author' => $specialists[0], 'type' => 'specialist'],
                    ['objective' => count($objectives) > 1 ? $objectives[1] : $objectives[0], 'content' => 'Sophie a besoin de plus d\'entraînement sur les tables de 7 et 8', 'author' => $specialists[0], 'type' => 'specialist'],
                    ['objective' => count($objectives) > 2 ? $objectives[2] : $objectives[0], 'content' => 'Excellent travail sur la confiance en soi !', 'author' => $coach, 'type' => 'coach'],
                ];
                
                foreach ($commentsData as $commentData) {
                    if (isset($commentData['objective'])) {
                        $comment = new Comment();
                        $comment->setContent($commentData['content']);
                        $comment->setObjective($commentData['objective']);
                        $comment->setAuthor($commentData['author']);
                        
                        $this->em->persist($comment);
                    }
                }
            }
            
            $this->em->flush();
            $output->writeln('   ✅ Commentaires créés');

            $output->writeln('');
            $output->writeln('✅ Seeding terminé avec succès !');
            $output->writeln('');
            $output->writeln('📋 Récapitulatif des comptes créés :');
            $output->writeln('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
            $output->writeln('👨‍🏫 COACHS:');
            foreach ($coaches as $c) {
                $output->writeln("   - {$c->getEmail()} ({$c->getFirstName()} {$c->getLastName()}) / {$defaultPassword}");
            }
            $output->writeln('');
            $output->writeln('👨‍⚕️  SPÉCIALISTES:');
            foreach ($specialists as $s) {
                $output->writeln("   - {$s->getEmail()} ({$s->getFirstName()} {$s->getLastName()}) / {$defaultPassword}");
            }
            $output->writeln('');
            $output->writeln('👨‍👩 PARENTS:');
            $output->writeln("   - {$parent->getEmail()} ({$parent->getFirstName()} {$parent->getLastName()}) / {$defaultPassword}");
            if (isset($parent2)) {
                $output->writeln("   - {$parent2->getEmail()} ({$parent2->getFirstName()} {$parent2->getLastName()}) / {$defaultPassword}");
            }
            $output->writeln('');
            $output->writeln('👦👧 ÉLÈVES:');
            foreach ($students as $student) {
                $output->writeln("   - {$student->getEmail()} ({$student->getFirstName()} {$student->getLastName()}) / {$defaultPassword}");
            }
            $output->writeln('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
            $output->writeln('');
            $output->writeln('📊 Statistiques :');
            $output->writeln('   - ' . count($coaches) . ' coach(s)');
            $output->writeln('   - ' . count($specialists) . ' spécialiste(s)');
            $output->writeln('   - ' . count($students) . ' élève(s)');
            $output->writeln('   - ' . count($objectives) . ' objectif(s)');
            $output->writeln('   - ' . count($planningEvents) . ' événement(s) de planning');
            $output->writeln('   - ' . count($requestsData) . ' demande(s)');
            $output->writeln('');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $output->writeln('');
            $output->writeln("❌ Erreur lors du seeding : " . $e->getMessage());
            $output->writeln("Stack trace:\n" . $e->getTraceAsString());
            return Command::FAILURE;
        }
    }
}

