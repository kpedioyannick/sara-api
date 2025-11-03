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
            // 1. Créer un Coach
            $output->writeln('👨‍🏫 Création du Coach...');
            $coachEmail = 'sara@coach.com';
            $existingCoach = $this->em->getRepository(Coach::class)->findOneBy(['email' => $coachEmail]);
            
            if ($existingCoach) {
                $output->writeln('   ⚠️  Coach existe déjà, réutilisation...');
                $coach = $existingCoach;
            } else {
                $coach = new Coach();
                $coach->setEmail($coachEmail);
                $coach->setFirstName('Sara');
                $coach->setLastName('Educateur');
                $coach->setSpecialization('Pédagogie adaptée');
                $hashedPassword = $this->passwordHasher->hashPassword($coach, $defaultPassword);
                $coach->setPassword($hashedPassword);
                $coach->setIsActive(true);
                $this->em->persist($coach);
                $this->em->flush();
                $output->writeln("   ✅ Coach créé: {$coachEmail} / {$defaultPassword}");
            }

            // 2. Créer un Spécialiste
            $output->writeln('');
            $output->writeln('👨‍⚕️  Création du Spécialiste...');
            $specialistEmail = 'prof@specialist.com';
            $existingSpecialist = $this->em->getRepository(Specialist::class)->findOneBy(['email' => $specialistEmail]);
            
            if ($existingSpecialist) {
                $output->writeln('   ⚠️  Spécialiste existe déjà, réutilisation...');
                $specialist = $existingSpecialist;
            } else {
                $specialist = new Specialist();
                $specialist->setEmail($specialistEmail);
                $specialist->setFirstName('Marie');
                $specialist->setLastName('Orthophoniste');
                $specialist->setSpecializations(['orthophonie', 'dyslexie']);
                $hashedPassword = $this->passwordHasher->hashPassword($specialist, $defaultPassword);
                $specialist->setPassword($hashedPassword);
                $specialist->setIsActive(true);
                $this->em->persist($specialist);
                $this->em->flush();
                $output->writeln("   ✅ Spécialiste créé: {$specialistEmail} / {$defaultPassword}");
            }

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
                ['pseudo' => 'lucas', 'class' => 'CM2', 'firstName' => 'Lucas', 'lastName' => 'Dupont'],
                ['pseudo' => 'sophie', 'class' => '6ème', 'firstName' => 'Sophie', 'lastName' => 'Dupont'],
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
                    $student->setPoints(50);
                    $student->setIsActive(true);
                    $this->em->persist($student);
                    $students[] = $student;
                    $output->writeln("   ✅ Étudiant créé: {$studentData['pseudo']} ({$studentEmail}) / {$defaultPassword}");
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
            
            // Objectif pour Lucas
            $objective1 = new Objective();
            $objective1->setTitle('Améliorer la lecture');
            $objective1->setDescription('Lire plus couramment et comprendre les textes');
            $objective1->setCategory('academic');
            $objective1->setStatus('in_progress');
            $objective1->setDeadline(new \DateTimeImmutable('+3 months'));
            $objective1->setStudent($lucas);
            $objective1->setCoach($coach);
            $this->em->persist($objective1);
            
            // Tâches pour l'objectif 1
            $task1 = Task::createForCoach([
                'title' => 'Lire 15 minutes chaque jour',
                'description' => 'Lecture quotidienne pendant 15 minutes',
                'status' => 'in_progress',
                'frequency' => 'daily',
                'requiresProof' => true,
                'proofType' => 'text'
            ], $objective1, $lucas, 'student');
            $this->em->persist($task1);
            
            $task2 = Task::createForCoach([
                'title' => 'Résumer un texte par semaine',
                'description' => 'Faire un résumé d\'un texte lu dans la semaine',
                'status' => 'pending',
                'frequency' => 'weekly',
                'requiresProof' => true,
                'proofType' => 'text'
            ], $objective1, $specialist, 'specialist');
            $this->em->persist($task2);
            
            // Objectif pour Sophie
            $objective2 = new Objective();
            $objective2->setTitle('Maîtriser les tables de multiplication');
            $objective2->setDescription('Connaître toutes les tables de 1 à 10 par cœur');
            $objective2->setCategory('academic');
            $objective2->setStatus('pending');
            $objective2->setDeadline(new \DateTimeImmutable('+2 months'));
            $objective2->setStudent($sophie);
            $objective2->setCoach($coach);
            $this->em->persist($objective2);
            
            $task3 = Task::createForCoach([
                'title' => 'Réciter une table par jour',
                'description' => 'Réciter une table de multiplication chaque jour',
                'status' => 'pending',
                'frequency' => 'daily',
                'requiresProof' => true,
                'proofType' => 'audio'
            ], $objective2, $sophie, 'student');
            $this->em->persist($task3);
            
            $this->em->flush();
            $output->writeln('   ✅ Objectifs et tâches créés');

            // 6. Créer des Planning Events
            $output->writeln('');
            $output->writeln('📅 Création des événements de Planning...');
            $planning1Date = new \DateTimeImmutable('+2 days 14:00');
            $planning1 = new Planning();
            $planning1->setTitle('Révision mathématiques');
            $planning1->setDescription('Révision des multiplications');
            $planning1->setType('revision');
            $planning1->setStatus('scheduled');
            $planning1->setStartDate($planning1Date);
            $planning1->setEndDate($planning1Date->modify('+1 hour'));
            $planning1->setStudent($lucas);
            $this->em->persist($planning1);
            
            $planning2Date = new \DateTimeImmutable('+5 days 16:00');
            $planning2 = new Planning();
            $planning2->setTitle('Devoir de français');
            $planning2->setDescription('Rédaction sur un sujet libre');
            $planning2->setType('homework');
            $planning2->setStatus('to_do');
            $planning2->setStartDate($planning2Date);
            $planning2->setEndDate($planning2Date->modify('+2 hours'));
            $planning2->setStudent($sophie);
            $this->em->persist($planning2);
            
            $this->em->flush();
            $output->writeln('   ✅ Événements de planning créés');

            // 7. Créer des Demandes (Requests)
            $output->writeln('');
            $output->writeln('📋 Création des Demandes...');
            $request1 = new RequestEntity();
            $request1->setTitle('Besoin d\'aide en orthographe');
            $request1->setDescription('Sophie a besoin de soutien supplémentaire en orthographe');
            $request1->setStatus('pending');
            $request1->setType('general');
            $request1->setPriority('medium');
            $request1->setStudent($sophie);
            $request1->setParent($parent);
            $request1->setCoach($coach);
            $request1->setSpecialist($specialist);
            $this->em->persist($request1);
            
            $request2 = new RequestEntity();
            $request2->setTitle('Suivi régulier');
            $request2->setDescription('Suivi hebdomadaire de Lucas');
            $request2->setStatus('in_progress');
            $request2->setType('general');
            $request2->setPriority('high');
            $request2->setStudent($lucas);
            $request2->setParent($parent);
            $request2->setCoach($coach);
            $request2->setSpecialist($specialist);
            $this->em->persist($request2);
            
            $this->em->flush();
            $output->writeln('   ✅ Demandes créées');

            // 8. Créer des Messages
            $output->writeln('');
            $output->writeln('💬 Création des Messages...');
            $message1 = new Message();
            $message1->setContent('Bonjour, je souhaite planifier un rendez-vous pour Sophie.');
            $message1->setSender($parent);
            $message1->setReceiver($specialist);
            $message1->setCoach($coach);
            $message1->setRecipient($specialist);
            $message1->setRequest($request1);
            $this->em->persist($message1);
            
            $message2 = new Message();
            $message2->setContent('Je suis disponible la semaine prochaine, quelle date vous convient ?');
            $message2->setSender($specialist);
            $message2->setReceiver($parent);
            $message2->setCoach($coach);
            $message2->setRecipient($parent);
            $message2->setRequest($request1);
            $this->em->persist($message2);
            
            $this->em->flush();
            $output->writeln('   ✅ Messages créés');

            // 9. Créer des Disponibilités pour le Spécialiste
            $output->writeln('');
            $output->writeln('⏰ Création des Disponibilités...');
            $availability1 = new Availability();
            $availability1->setSpecialist($specialist);
            $availability1->setDayOfWeek('monday');
            $availability1->setStartTime(new \DateTimeImmutable('2025-01-01 09:00:00'));
            $availability1->setEndTime(new \DateTimeImmutable('2025-01-01 12:00:00'));
            $this->em->persist($availability1);
            
            $availability2 = new Availability();
            $availability2->setSpecialist($specialist);
            $availability2->setDayOfWeek('wednesday');
            $availability2->setStartTime(new \DateTimeImmutable('2025-01-01 14:00:00'));
            $availability2->setEndTime(new \DateTimeImmutable('2025-01-01 17:00:00'));
            $this->em->persist($availability2);
            
            $this->em->flush();
            $output->writeln('   ✅ Disponibilités créées');

            // 10. Créer des Commentaires
            $output->writeln('');
            $output->writeln('💭 Création des Commentaires...');
            $comment1 = new Comment();
            $comment1->setContent('Lucas progresse bien, continuez comme ça !');
            $comment1->setObjective($objective1);
            $comment1->setCoach($coach);
            $comment1->setAuthorType('coach');
            $this->em->persist($comment1);
            
            $comment2 = new Comment();
            $comment2->setContent('Sophie a besoin de plus d\'entraînement sur les tables de 7 et 8');
            $comment2->setObjective($objective2);
            $comment2->setSpecialist($specialist);
            $comment2->setAuthorType('specialist');
            $this->em->persist($comment2);
            
            $this->em->flush();
            $output->writeln('   ✅ Commentaires créés');

            $output->writeln('');
            $output->writeln('✅ Seeding terminé avec succès !');
            $output->writeln('');
            $output->writeln('📋 Récapitulatif des comptes créés :');
            $output->writeln('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
            $output->writeln("👨‍🏫 Coach:       {$coachEmail}         / {$defaultPassword}");
            $output->writeln("👨‍⚕️  Spécialiste: {$specialistEmail}      / {$defaultPassword}");
            $output->writeln("👨‍👩 Parent:      {$parentEmail}     / {$defaultPassword}");
            $output->writeln("👦 Lucas:        lucas@sara.education        / {$defaultPassword}");
            $output->writeln("👧 Sophie:       sophie@sara.education       / {$defaultPassword}");
            $output->writeln('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
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

