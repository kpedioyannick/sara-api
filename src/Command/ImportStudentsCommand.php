<?php

namespace App\Command;

use App\Entity\Coach;
use App\Entity\Family;
use App\Entity\Objective;
use App\Entity\Student;
use App\Entity\Task;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:import-students',
    description: 'Importe les élèves depuis le fichier doc/eleves.md et leur affecte les objectifs et tâches depuis doc/objectifsetTaches.md',
)]
class ImportStudentsCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $passwordHasher
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Importation des élèves et affectation des objectifs/tâches');

        // 1. Récupérer ou créer un coach par défaut
        $coach = $this->em->getRepository(Coach::class)->findOneBy(['email' => 'sara@coach.com']);
        if (!$coach) {
            $io->warning('Aucun coach trouvé. Création d\'un coach par défaut...');
            $coach = new Coach();
            $coach->setEmail('sara@coach.com');
            $coach->setFirstName('Sara');
            $coach->setLastName('Educateur');
            $coach->setPassword($this->passwordHasher->hashPassword($coach, 'password123'));
            $coach->setIsActive(true);
            $this->em->persist($coach);
            $this->em->flush();
        }

        // 2. Parser le fichier des élèves
        $studentsFile = __DIR__ . '/../../doc/eleves.md';
        if (!file_exists($studentsFile)) {
            $io->error("Le fichier {$studentsFile} n'existe pas.");
            return Command::FAILURE;
        }

        $studentsData = $this->parseStudentsFile($studentsFile);
        $io->info(sprintf('Nombre d\'élèves trouvés : %d', count($studentsData)));

        // 3. Parser le fichier des objectifs et tâches
        $objectivesFile = __DIR__ . '/../../doc/objectifsetTaches.md';
        if (!file_exists($objectivesFile)) {
            $io->error("Le fichier {$objectivesFile} n'existe pas.");
            return Command::FAILURE;
        }

        $objectivesData = $this->parseObjectivesFile($objectivesFile);
        $io->info(sprintf('Nombre d\'objectifs trouvés : %d', count($objectivesData)));

        // 4. Créer ou récupérer la famille "Drac St Herblain"
        $familyIdentifier = 'DRAC_ST_HERBLAIN';
        $family = $this->em->getRepository(Family::class)->findOneBy(['familyIdentifier' => $familyIdentifier]);
        
        if (!$family) {
            $io->writeln('📦 Création de la famille "Drac St Herblain"...');
            $family = new Family();
            $family->setFamilyIdentifier($familyIdentifier);
            $family->setCoach($coach);
            $family->setIsActive(true);
            $this->em->persist($family);
            $this->em->flush();
            $io->writeln('  ✅ Famille créée');
        } else {
            $io->writeln('  ℹ️  Famille "Drac St Herblain" existe déjà');
        }

        // 5. Créer les élèves et leurs objectifs/tâches
        $created = 0;
        $updated = 0;
        $errors = 0;

        foreach ($studentsData as $studentInfo) {
            try {
                // Générer le pseudo à partir du nom et prénom
                $pseudo = $this->generatePseudo($studentInfo['nom'], $studentInfo['prenom']);
                
                // Vérifier si l'élève existe déjà
                $existingStudent = $this->em->getRepository(Student::class)->findOneBy(['pseudo' => $pseudo]);
                
                if ($existingStudent) {
                    $io->writeln(sprintf('  ⚠️  Élève existant : %s %s (pseudo: %s)', 
                        $studentInfo['prenom'], 
                        $studentInfo['nom'], 
                        $pseudo
                    ));
                    // Mettre à jour la famille si nécessaire
                    $currentFamily = $existingStudent->getFamily();
                    if (!$currentFamily || $currentFamily->getId() !== $family->getId()) {
                        $existingStudent->setFamily($family);
                        $this->em->flush();
                        $io->writeln('    ✅ Famille mise à jour');
                    }
                    $student = $existingStudent;
                    $updated++;
                } else {
                    // Créer l'élève et l'associer à la famille "Drac St Herblain"
                    $student = new Student();
                    $student->setEmail($pseudo . '@sara.education');
                    $student->setFirstName($studentInfo['prenom']);
                    $student->setLastName($studentInfo['nom']);
                    $student->setPseudo($pseudo);
                    $student->setClass($studentInfo['classe'] ?? '');
                    $student->setSchoolName($studentInfo['etablissement'] ?? null);
                    $student->setFamily($family);
                    $student->setPoints(0);
                    $student->setPassword($this->passwordHasher->hashPassword($student, 'password123'));
                    $student->setIsActive(true);
                    
                    $this->em->persist($student);
                    $this->em->flush();
                    
                    $io->writeln(sprintf('  ✅ Élève créé : %s %s (pseudo: %s)', 
                        $studentInfo['prenom'], 
                        $studentInfo['nom'], 
                        $pseudo
                    ));
                    $created++;
                }

                // Créer les objectifs pour cet élève
                foreach ($objectivesData as $objectiveData) {
                    // Vérifier si l'objectif existe déjà pour cet élève
                    $existingObjective = $this->em->getRepository(Objective::class)->findOneBy([
                        'title' => $objectiveData['title'],
                        'student' => $student
                    ]);

                    if ($existingObjective) {
                        $objective = $existingObjective;
                        $io->writeln(sprintf('    ⚠️  Objectif existant : %s', $objectiveData['title']));
                    } else {
                        $objective = new Objective();
                        $objective->setTitle($objectiveData['title']);
                        $objective->setDescription($objectiveData['description']);
                        $objective->setCategory('comportement');
                        $objective->setStatus(Objective::STATUS_MODIFICATION);
                        $objective->setProgress(0);
                        $objective->setStudent($student);
                        $objective->setCoach($coach);
                        
                        $this->em->persist($objective);
                        $this->em->flush();
                        
                        $io->writeln(sprintf('    ✅ Objectif créé : %s', $objectiveData['title']));
                    }

                    // Créer les tâches pour cet objectif
                    foreach ($objectiveData['tasks'] as $taskTitle) {
                        // Vérifier si la tâche existe déjà
                        $existingTask = $this->em->getRepository(Task::class)->findOneBy([
                            'title' => $taskTitle,
                            'objective' => $objective
                        ]);

                        if (!$existingTask) {
                            $task = Task::createForCoach([
                                'title' => $taskTitle,
                                'description' => $taskTitle,
                                'status' => 'pending',
                                'frequency' => 'none',
                                'requires_proof' => true,
                                'proof_type' => 'text',
                            ], $objective, $student, 'student');
                            
                            $this->em->persist($task);
                            $io->writeln(sprintf('      ✅ Tâche créée : %s', $taskTitle));
                        }
                    }
                }

                $this->em->flush();
                
            } catch (\Exception $e) {
                $io->error(sprintf('Erreur pour %s %s : %s', 
                    $studentInfo['prenom'] ?? '', 
                    $studentInfo['nom'] ?? '', 
                    $e->getMessage()
                ));
                $errors++;
            }
        }

        $io->newLine();
        $io->success(sprintf(
            'Importation terminée : %d créés, %d mis à jour, %d erreurs',
            $created,
            $updated,
            $errors
        ));

        return Command::SUCCESS;
    }

    private function parseStudentsFile(string $filePath): array
    {
        $content = file_get_contents($filePath);
        $students = [];
        
        // Diviser par les sections "## Élève"
        preg_match_all('/## Élève \d+\s*\n((?:- \*\*[^*]+\*\* [^\n]+\n?)+)/s', $content, $sections, PREG_SET_ORDER);
        
        foreach ($sections as $section) {
            $studentData = $section[1];
            
            // Extraire chaque champ
            $nom = $this->extractField($studentData, 'Nom');
            $prenom = $this->extractField($studentData, 'Prénom');
            $classe = $this->extractField($studentData, 'Classe');
            $etablissement = $this->extractField($studentData, 'Établissement');
            
            // Ignorer les lignes avec "-" (données manquantes) ou vides
            if ($nom !== '-' && $prenom !== '-' && !empty($nom) && !empty($prenom)) {
                $students[] = [
                    'nom' => $nom,
                    'prenom' => $prenom,
                    'classe' => ($classe !== '-' && !empty($classe)) ? $classe : null,
                    'etablissement' => ($etablissement !== '-' && !empty($etablissement)) ? $etablissement : null,
                ];
            }
        }
        
        return $students;
    }

    private function extractField(string $content, string $fieldName): string
    {
        // Pattern pour extraire le champ : - **Nom :** valeur
        // Utiliser [^\n]+ pour capturer toute la ligne jusqu'au saut de ligne
        $pattern = '/- \*\*' . preg_quote($fieldName, '/') . ' :\*\* ([^\n]+)/';
        if (preg_match($pattern, $content, $match)) {
            return trim($match[1]);
        }
        return '-';
    }

    private function parseObjectivesFile(string $filePath): array
    {
        $content = file_get_contents($filePath);
        $objectives = [];
        
        // Diviser le contenu par les séparateurs "---"
        $sections = preg_split('/\n---\n/', $content);
        
        foreach ($sections as $section) {
            // Extraire le titre de l'objectif
            if (preg_match('/## Objectif \d+ : (.+?)\n/', $section, $titleMatch)) {
                $title = trim($titleMatch[1]);
                
                // Extraire la description
                $description = '';
                if (preg_match('/\*\*Description :\*\* (.+?)(?=\n###|$)/s', $section, $descMatch)) {
                    $description = trim($descMatch[1]);
                }
                
                // Extraire les tâches
                $tasks = [];
                if (preg_match('/### Tâches associées :\s*\n((?:- [^\n]+\n?)+)/s', $section, $tasksMatch)) {
                    $tasksText = $tasksMatch[1];
                    // Extraire chaque ligne de tâche
                    preg_match_all('/- ([^\n]+)/', $tasksText, $taskMatches);
                    $tasks = array_map('trim', $taskMatches[1]);
                    // Filtrer les tâches vides
                    $tasks = array_filter($tasks, fn($t) => !empty($t));
                }
                
                if (!empty($title)) {
                    $objectives[] = [
                        'title' => $title,
                        'description' => $description,
                        'tasks' => array_values($tasks),
                    ];
                }
            }
        }
        
        return $objectives;
    }

    private function generatePseudo(string $nom, string $prenom): string
    {
        // Normaliser : minuscules, remplacer espaces et caractères spéciaux
        $nomNormalized = strtolower($nom);
        $prenomNormalized = strtolower($prenom);
        
        // Supprimer les accents
        $nomNormalized = $this->removeAccents($nomNormalized);
        $prenomNormalized = $this->removeAccents($prenomNormalized);
        
        // Supprimer les espaces et caractères spéciaux, garder seulement les lettres et chiffres
        $nomNormalized = preg_replace('/[^a-z0-9]/', '', $nomNormalized);
        $prenomNormalized = preg_replace('/[^a-z0-9]/', '', $prenomNormalized);
        
        // Format : prenom.nom
        $pseudo = $prenomNormalized . '.' . $nomNormalized;
        
        return $pseudo;
    }

    private function removeAccents(string $string): string
    {
        $string = htmlentities($string, ENT_NOQUOTES, 'UTF-8');
        $string = preg_replace('#&([A-za-z])(?:acute|cedil|caron|circ|grave|orn|ring|slash|th|tilde|uml);#', '\1', $string);
        $string = preg_replace('#&([A-za-z]{2})(?:lig);#', '\1', $string);
        $string = preg_replace('#&[^;]+;#', '', $string);
        return $string;
    }
}

