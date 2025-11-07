<?php

namespace App\Command;

use App\Entity\Activity;
use App\Entity\ActivityCategory;
use App\Entity\ActivityImage;
use App\Entity\Comment;
use App\Entity\Coach;
use App\Entity\Objective;
use App\Repository\ActivityCategoryRepository;
use App\Repository\ActivityRepository;
use App\Repository\CoachRepository;
use App\Repository\ObjectiveRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:seed-activities',
    description: 'Initialise les catégories d\'activités, des activités d\'exemple et des commentaires',
)]
class SeedActivitiesCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private ActivityCategoryRepository $categoryRepository,
        private ActivityRepository $activityRepository,
        private CoachRepository $coachRepository,
        private ObjectiveRepository $objectiveRepository
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('🌱 Initialisation des données d\'activités...');
        $output->writeln('');

        try {
            // 1. Créer les catégories d'activités
            $output->writeln('📁 Création des catégories d\'activités...');
            $categoriesData = [
                [
                    'name' => 'Motricité',
                    'description' => 'Activités pour développer la motricité globale et fine',
                    'icon' => '🏃',
                    'sortOrder' => 1,
                ],
                [
                    'name' => 'Cognitif',
                    'description' => 'Activités pour stimuler les fonctions cognitives',
                    'icon' => '🧠',
                    'sortOrder' => 2,
                ],
                [
                    'name' => 'Social',
                    'description' => 'Activités pour développer les compétences sociales',
                    'icon' => '👥',
                    'sortOrder' => 3,
                ],
                [
                    'name' => 'Émotionnel',
                    'description' => 'Activités pour gérer et exprimer les émotions',
                    'icon' => '❤️',
                    'sortOrder' => 4,
                ],
                [
                    'name' => 'Langage',
                    'description' => 'Activités pour développer le langage et la communication',
                    'icon' => '💬',
                    'sortOrder' => 5,
                ],
                [
                    'name' => 'Autonomie',
                    'description' => 'Activités pour développer l\'autonomie et l\'indépendance',
                    'icon' => '🌟',
                    'sortOrder' => 6,
                ],
                [
                    'name' => 'Sensoriel',
                    'description' => 'Activités d\'exploration sensorielle',
                    'icon' => '👁️',
                    'sortOrder' => 7,
                ],
                [
                    'name' => 'Créativité',
                    'description' => 'Activités artistiques et créatives',
                    'icon' => '🎨',
                    'sortOrder' => 8,
                ],
            ];

            $categories = [];
            foreach ($categoriesData as $catData) {
                $existingCategory = $this->categoryRepository->findOneBy(['name' => $catData['name']]);
                
                if ($existingCategory) {
                    $output->writeln("   ⚠️  Catégorie '{$catData['name']}' existe déjà, réutilisation...");
                    $categories[] = $existingCategory;
                } else {
                    $category = new ActivityCategory();
                    $category->setName($catData['name']);
                    $category->setDescription($catData['description']);
                    $category->setIcon($catData['icon']);
                    $category->setSortOrder($catData['sortOrder']);
                    $category->setIsActive(true);
                    $this->em->persist($category);
                    $categories[] = $category;
                    $output->writeln("   ✅ Catégorie créée: {$catData['name']}");
                }
            }
            $this->em->flush();
            $output->writeln('');

            // 2. Récupérer un coach pour créer les activités
            $coach = $this->coachRepository->findOneBy([]);
            if (!$coach) {
                $output->writeln('   ❌ Aucun coach trouvé. Veuillez d\'abord exécuter app:seed-database');
                return Command::FAILURE;
            }

            // 3. Créer des activités d'exemple
            $output->writeln('🎯 Création des activités d\'exemple...');
            $activitiesData = [
                [
                    'description' => 'Parcours de motricité avec obstacles variés. L\'enfant doit sauter, ramper, grimper et se déplacer dans un parcours sécurisé.',
                    'duration' => '20-30 minutes',
                    'ageRange' => '3-6 ans',
                    'type' => Activity::TYPE_INDIVIDUAL,
                    'category' => 'Motricité',
                    'objectives' => ['Développer la coordination', 'Renforcer les muscles', 'Améliorer l\'équilibre'],
                    'workedPoints' => ['Motricité globale', 'Coordination', 'Équilibre', 'Force'],
                ],
                [
                    'description' => 'Jeu de memory avec des images adaptées. L\'enfant doit retrouver les paires de cartes identiques.',
                    'duration' => '15-20 minutes',
                    'ageRange' => '4-8 ans',
                    'type' => Activity::TYPE_INDIVIDUAL,
                    'category' => 'Cognitif',
                    'objectives' => ['Stimuler la mémoire', 'Développer la concentration', 'Améliorer la reconnaissance visuelle'],
                    'workedPoints' => ['Mémoire', 'Concentration', 'Reconnaissance visuelle', 'Attention'],
                ],
                [
                    'description' => 'Atelier de peinture libre avec différents outils (pinceaux, rouleaux, éponges). L\'enfant explore les couleurs et les textures.',
                    'duration' => '30-45 minutes',
                    'ageRange' => '2-6 ans',
                    'type' => Activity::TYPE_WITH_ADULT,
                    'category' => 'Créativité',
                    'objectives' => ['Exprimer la créativité', 'Explorer les couleurs', 'Développer la motricité fine'],
                    'workedPoints' => ['Créativité', 'Motricité fine', 'Expression artistique', 'Exploration sensorielle'],
                ],
                [
                    'description' => 'Jeu de rôle pour apprendre à partager et à jouer ensemble. Simulation de situations sociales courantes.',
                    'duration' => '20-30 minutes',
                    'ageRange' => '4-7 ans',
                    'type' => Activity::TYPE_WITH_ADULT,
                    'category' => 'Social',
                    'objectives' => ['Apprendre à partager', 'Développer l\'empathie', 'Améliorer la communication'],
                    'workedPoints' => ['Compétences sociales', 'Empathie', 'Communication', 'Coopération'],
                ],
                [
                    'description' => 'Boîte à émotions : identifier et exprimer différentes émotions à travers des cartes, des mimiques et des histoires.',
                    'duration' => '15-25 minutes',
                    'ageRange' => '3-8 ans',
                    'type' => Activity::TYPE_WITH_ADULT,
                    'category' => 'Émotionnel',
                    'objectives' => ['Identifier les émotions', 'Exprimer ses sentiments', 'Comprendre les émotions des autres'],
                    'workedPoints' => ['Gestion émotionnelle', 'Expression', 'Reconnaissance des émotions', 'Empathie'],
                ],
                [
                    'description' => 'Lecture interactive d\'histoires avec questions et discussions. L\'adulte lit et pose des questions pour encourager la participation.',
                    'duration' => '20-30 minutes',
                    'ageRange' => '3-7 ans',
                    'type' => Activity::TYPE_WITH_ADULT,
                    'category' => 'Langage',
                    'objectives' => ['Enrichir le vocabulaire', 'Développer la compréhension', 'Stimuler l\'imagination'],
                    'workedPoints' => ['Vocabulaire', 'Compréhension', 'Expression orale', 'Imagination'],
                ],
                [
                    'description' => 'Activité de la vie quotidienne : préparer un goûter simple. L\'enfant apprend à verser, couper (avec aide), et servir.',
                    'duration' => '30-40 minutes',
                    'ageRange' => '4-8 ans',
                    'type' => Activity::TYPE_WITH_ADULT,
                    'category' => 'Autonomie',
                    'objectives' => ['Développer l\'autonomie', 'Apprendre les gestes quotidiens', 'Renforcer la confiance en soi'],
                    'workedPoints' => ['Autonomie', 'Motricité fine', 'Confiance en soi', 'Vie quotidienne'],
                ],
                [
                    'description' => 'Bac sensoriel avec différents matériaux (riz, sable, eau, pâtes). L\'enfant explore les textures et les sensations.',
                    'duration' => '20-30 minutes',
                    'ageRange' => '2-5 ans',
                    'type' => Activity::TYPE_INDIVIDUAL,
                    'category' => 'Sensoriel',
                    'objectives' => ['Explorer les sens', 'Développer la curiosité', 'Stimuler le toucher'],
                    'workedPoints' => ['Exploration sensorielle', 'Curiosité', 'Toucher', 'Découverte'],
                ],
                [
                    'description' => 'Puzzle adapté à l\'âge de l\'enfant. Commencer par des puzzles simples et augmenter la difficulté progressivement.',
                    'duration' => '15-25 minutes',
                    'ageRange' => '3-8 ans',
                    'type' => Activity::TYPE_INDIVIDUAL,
                    'category' => 'Cognitif',
                    'objectives' => ['Développer la logique', 'Améliorer la patience', 'Renforcer la résolution de problèmes'],
                    'workedPoints' => ['Logique', 'Patience', 'Résolution de problèmes', 'Concentration'],
                ],
                [
                    'description' => 'Danse et mouvement libre sur différentes musiques. L\'enfant exprime ses émotions à travers le mouvement.',
                    'duration' => '15-20 minutes',
                    'ageRange' => '2-6 ans',
                    'type' => Activity::TYPE_INDIVIDUAL,
                    'category' => 'Motricité',
                    'objectives' => ['Exprimer les émotions', 'Développer la coordination', 'Libérer l\'énergie'],
                    'workedPoints' => ['Expression corporelle', 'Coordination', 'Équilibre', 'Créativité'],
                ],
                // Activités Motricité supplémentaires
                [
                    'description' => 'Jeu de ballon : lancer, attraper et faire rebondir un ballon. Adapté selon l\'âge de l\'enfant.',
                    'duration' => '15-25 minutes',
                    'ageRange' => '3-7 ans',
                    'type' => Activity::TYPE_WITH_ADULT,
                    'category' => 'Motricité',
                    'objectives' => ['Développer la coordination œil-main', 'Améliorer la précision', 'Renforcer les bras'],
                    'workedPoints' => ['Coordination œil-main', 'Précision', 'Force', 'Réflexes'],
                ],
                [
                    'description' => 'Exercices de yoga adaptés pour enfants. Postures simples et amusantes pour développer la souplesse.',
                    'duration' => '20-30 minutes',
                    'ageRange' => '4-10 ans',
                    'type' => Activity::TYPE_WITH_ADULT,
                    'category' => 'Motricité',
                    'objectives' => ['Améliorer la souplesse', 'Développer la concentration', 'Apprendre à se détendre'],
                    'workedPoints' => ['Souplesse', 'Concentration', 'Détente', 'Équilibre'],
                ],
                [
                    'description' => 'Jeu de saut à la corde. Commencer par faire tourner la corde et progresser vers le saut.',
                    'duration' => '10-15 minutes',
                    'ageRange' => '5-10 ans',
                    'type' => Activity::TYPE_INDIVIDUAL,
                    'category' => 'Motricité',
                    'objectives' => ['Améliorer la coordination', 'Développer l\'endurance', 'Renforcer les jambes'],
                    'workedPoints' => ['Coordination', 'Endurance', 'Rythme', 'Force'],
                ],
                // Activités Cognitif supplémentaires
                [
                    'description' => 'Jeu de tri et classement d\'objets par couleur, forme ou taille. Développe la logique et l\'observation.',
                    'duration' => '15-20 minutes',
                    'ageRange' => '2-5 ans',
                    'type' => Activity::TYPE_WITH_ADULT,
                    'category' => 'Cognitif',
                    'objectives' => ['Développer la logique', 'Apprendre à classer', 'Améliorer l\'observation'],
                    'workedPoints' => ['Logique', 'Observation', 'Classification', 'Attention'],
                ],
                [
                    'description' => 'Jeu de construction avec des blocs ou des cubes. Créer des structures et développer la créativité.',
                    'duration' => '20-30 minutes',
                    'ageRange' => '2-8 ans',
                    'type' => Activity::TYPE_INDIVIDUAL,
                    'category' => 'Cognitif',
                    'objectives' => ['Développer la créativité', 'Améliorer la motricité fine', 'Comprendre l\'espace'],
                    'workedPoints' => ['Créativité', 'Motricité fine', 'Repérage spatial', 'Planification'],
                ],
                [
                    'description' => 'Jeu de devinettes et énigmes adaptées à l\'âge. Stimule la réflexion et le raisonnement.',
                    'duration' => '10-15 minutes',
                    'ageRange' => '5-10 ans',
                    'type' => Activity::TYPE_WITH_ADULT,
                    'category' => 'Cognitif',
                    'objectives' => ['Stimuler la réflexion', 'Développer le raisonnement', 'Enrichir le vocabulaire'],
                    'workedPoints' => ['Réflexion', 'Raisonnement', 'Vocabulaire', 'Logique'],
                ],
                [
                    'description' => 'Jeu de séquence : reproduire une séquence de couleurs, de sons ou de gestes.',
                    'duration' => '15-20 minutes',
                    'ageRange' => '3-7 ans',
                    'type' => Activity::TYPE_WITH_ADULT,
                    'category' => 'Cognitif',
                    'objectives' => ['Développer la mémoire', 'Améliorer l\'attention', 'Comprendre les séquences'],
                    'workedPoints' => ['Mémoire', 'Attention', 'Séquençage', 'Concentration'],
                ],
                // Activités Social supplémentaires
                [
                    'description' => 'Jeu de groupe : jouer ensemble à un jeu de société adapté. Apprendre à attendre son tour et respecter les règles.',
                    'duration' => '20-30 minutes',
                    'ageRange' => '4-10 ans',
                    'type' => Activity::TYPE_WITH_ADULT,
                    'category' => 'Social',
                    'objectives' => ['Apprendre à jouer ensemble', 'Respecter les règles', 'Attendre son tour'],
                    'workedPoints' => ['Coopération', 'Respect des règles', 'Patience', 'Communication'],
                ],
                [
                    'description' => 'Activité de partage : partager des jouets, des crayons ou des matériaux avec d\'autres enfants.',
                    'duration' => '15-25 minutes',
                    'ageRange' => '3-6 ans',
                    'type' => Activity::TYPE_WITH_ADULT,
                    'category' => 'Social',
                    'objectives' => ['Apprendre à partager', 'Développer la générosité', 'Comprendre l\'autre'],
                    'workedPoints' => ['Partage', 'Générosité', 'Empathie', 'Coopération'],
                ],
                [
                    'description' => 'Jeu de mime et d\'expression : mimer des émotions, des animaux ou des actions pour que les autres devinent.',
                    'duration' => '15-20 minutes',
                    'ageRange' => '4-8 ans',
                    'type' => Activity::TYPE_WITH_ADULT,
                    'category' => 'Social',
                    'objectives' => ['Exprimer sans mots', 'Comprendre les expressions', 'Développer la communication non verbale'],
                    'workedPoints' => ['Expression', 'Communication non verbale', 'Observation', 'Empathie'],
                ],
                // Activités Émotionnel supplémentaires
                [
                    'description' => 'Création d\'un journal des émotions : dessiner ou écrire ce qu\'on ressent chaque jour.',
                    'duration' => '10-15 minutes',
                    'ageRange' => '5-10 ans',
                    'type' => Activity::TYPE_WITH_ADULT,
                    'category' => 'Émotionnel',
                    'objectives' => ['Exprimer ses émotions', 'Prendre conscience de ses sentiments', 'Développer l\'introspection'],
                    'workedPoints' => ['Expression émotionnelle', 'Conscience de soi', 'Introspection', 'Créativité'],
                ],
                [
                    'description' => 'Exercice de respiration et relaxation : apprendre des techniques simples pour se calmer.',
                    'duration' => '10-15 minutes',
                    'ageRange' => '4-10 ans',
                    'type' => Activity::TYPE_WITH_ADULT,
                    'category' => 'Émotionnel',
                    'objectives' => ['Apprendre à se calmer', 'Gérer le stress', 'Développer la sérénité'],
                    'workedPoints' => ['Gestion du stress', 'Relaxation', 'Contrôle de soi', 'Bien-être'],
                ],
                [
                    'description' => 'Histoires avec des émotions : lire des histoires et discuter des émotions des personnages.',
                    'duration' => '20-30 minutes',
                    'ageRange' => '3-8 ans',
                    'type' => Activity::TYPE_WITH_ADULT,
                    'category' => 'Émotionnel',
                    'objectives' => ['Identifier les émotions', 'Comprendre les émotions des autres', 'Développer l\'empathie'],
                    'workedPoints' => ['Reconnaissance des émotions', 'Empathie', 'Compréhension', 'Communication'],
                ],
                // Activités Langage supplémentaires
                [
                    'description' => 'Jeu de vocabulaire : nommer des objets, des actions ou des couleurs. Enrichir le vocabulaire de manière ludique.',
                    'duration' => '15-20 minutes',
                    'ageRange' => '2-6 ans',
                    'type' => Activity::TYPE_WITH_ADULT,
                    'category' => 'Langage',
                    'objectives' => ['Enrichir le vocabulaire', 'Améliorer la prononciation', 'Développer la communication'],
                    'workedPoints' => ['Vocabulaire', 'Prononciation', 'Communication', 'Mémoire'],
                ],
                [
                    'description' => 'Chansons et comptines : chanter ensemble des chansons adaptées. Développer le langage et le rythme.',
                    'duration' => '15-20 minutes',
                    'ageRange' => '2-6 ans',
                    'type' => Activity::TYPE_WITH_ADULT,
                    'category' => 'Langage',
                    'objectives' => ['Développer le langage', 'Améliorer la mémoire', 'Stimuler le rythme'],
                    'workedPoints' => ['Langage', 'Mémoire', 'Rythme', 'Expression orale'],
                ],
                [
                    'description' => 'Jeu de description : décrire un objet, une image ou une situation sans le nommer.',
                    'duration' => '15-20 minutes',
                    'ageRange' => '5-10 ans',
                    'type' => Activity::TYPE_WITH_ADULT,
                    'category' => 'Langage',
                    'objectives' => ['Développer l\'expression', 'Enrichir le vocabulaire', 'Améliorer la précision'],
                    'workedPoints' => ['Expression', 'Vocabulaire', 'Précision', 'Communication'],
                ],
                // Activités Autonomie supplémentaires
                [
                    'description' => 'Ranger sa chambre : apprendre à ranger ses jouets et ses affaires de manière organisée.',
                    'duration' => '20-30 minutes',
                    'ageRange' => '3-8 ans',
                    'type' => Activity::TYPE_WITH_ADULT,
                    'category' => 'Autonomie',
                    'objectives' => ['Développer l\'autonomie', 'Apprendre à ranger', 'Organiser ses affaires'],
                    'workedPoints' => ['Autonomie', 'Organisation', 'Responsabilité', 'Ordre'],
                ],
                [
                    'description' => 'S\'habiller seul : apprendre à mettre ses vêtements, ses chaussures et à faire ses lacets.',
                    'duration' => '15-20 minutes',
                    'ageRange' => '3-6 ans',
                    'type' => Activity::TYPE_WITH_ADULT,
                    'category' => 'Autonomie',
                    'objectives' => ['Développer l\'autonomie', 'Améliorer la motricité fine', 'Renforcer la confiance'],
                    'workedPoints' => ['Autonomie', 'Motricité fine', 'Confiance en soi', 'Persévérance'],
                ],
                [
                    'description' => 'Préparer son cartable : choisir et ranger les affaires nécessaires pour l\'école.',
                    'duration' => '10-15 minutes',
                    'ageRange' => '5-10 ans',
                    'type' => Activity::TYPE_WITH_ADULT,
                    'category' => 'Autonomie',
                    'objectives' => ['Développer l\'autonomie', 'Apprendre à s\'organiser', 'Prendre des responsabilités'],
                    'workedPoints' => ['Autonomie', 'Organisation', 'Responsabilité', 'Planification'],
                ],
                // Activités Sensoriel supplémentaires
                [
                    'description' => 'Jeu de devinette sensorielle : deviner des objets les yeux bandés en utilisant le toucher, l\'odorat ou l\'ouïe.',
                    'duration' => '15-20 minutes',
                    'ageRange' => '4-8 ans',
                    'type' => Activity::TYPE_WITH_ADULT,
                    'category' => 'Sensoriel',
                    'objectives' => ['Développer les sens', 'Améliorer la perception', 'Stimuler la curiosité'],
                    'workedPoints' => ['Perception sensorielle', 'Curiosité', 'Concentration', 'Découverte'],
                ],
                [
                    'description' => 'Exploration de textures : toucher différentes matières (doux, rugueux, lisse, collant) et les décrire.',
                    'duration' => '15-20 minutes',
                    'ageRange' => '2-5 ans',
                    'type' => Activity::TYPE_WITH_ADULT,
                    'category' => 'Sensoriel',
                    'objectives' => ['Explorer les textures', 'Développer le vocabulaire', 'Stimuler le toucher'],
                    'workedPoints' => ['Exploration sensorielle', 'Toucher', 'Vocabulaire', 'Curiosité'],
                ],
                [
                    'description' => 'Jeu de sons : identifier et reproduire différents sons (animaux, instruments, objets).',
                    'duration' => '15-20 minutes',
                    'ageRange' => '3-7 ans',
                    'type' => Activity::TYPE_WITH_ADULT,
                    'category' => 'Sensoriel',
                    'objectives' => ['Développer l\'ouïe', 'Améliorer la discrimination auditive', 'Stimuler l\'attention'],
                    'workedPoints' => ['Ouïe', 'Discrimination auditive', 'Attention', 'Mémoire auditive'],
                ],
                // Activités Créativité supplémentaires
                [
                    'description' => 'Modelage avec de la pâte à modeler : créer des formes, des animaux ou des objets. Développer la créativité et la motricité fine.',
                    'duration' => '20-30 minutes',
                    'ageRange' => '2-8 ans',
                    'type' => Activity::TYPE_INDIVIDUAL,
                    'category' => 'Créativité',
                    'objectives' => ['Développer la créativité', 'Améliorer la motricité fine', 'Exprimer son imagination'],
                    'workedPoints' => ['Créativité', 'Motricité fine', 'Imagination', 'Expression'],
                ],
                [
                    'description' => 'Collage et découpage : créer des œuvres d\'art en découpant et collant différents matériaux.',
                    'duration' => '25-35 minutes',
                    'ageRange' => '3-8 ans',
                    'type' => Activity::TYPE_WITH_ADULT,
                    'category' => 'Créativité',
                    'objectives' => ['Développer la créativité', 'Améliorer la motricité fine', 'Apprendre à utiliser des outils'],
                    'workedPoints' => ['Créativité', 'Motricité fine', 'Précision', 'Expression artistique'],
                ],
                [
                    'description' => 'Théâtre et jeu de rôle : inventer et jouer des petites scènes. Développer l\'imagination et l\'expression.',
                    'duration' => '20-30 minutes',
                    'ageRange' => '4-10 ans',
                    'type' => Activity::TYPE_WITH_ADULT,
                    'category' => 'Créativité',
                    'objectives' => ['Développer l\'imagination', 'Améliorer l\'expression', 'Renforcer la confiance'],
                    'workedPoints' => ['Imagination', 'Expression', 'Confiance en soi', 'Communication'],
                ],
                [
                    'description' => 'Création de musique : jouer avec des instruments simples ou créer des sons avec des objets du quotidien.',
                    'duration' => '15-25 minutes',
                    'ageRange' => '3-8 ans',
                    'type' => Activity::TYPE_WITH_ADULT,
                    'category' => 'Créativité',
                    'objectives' => ['Développer la créativité', 'Explorer les sons', 'Stimuler le rythme'],
                    'workedPoints' => ['Créativité', 'Exploration sonore', 'Rythme', 'Expression'],
                ],
            ];

            $activities = [];
            foreach ($activitiesData as $activityData) {
                // Trouver la catégorie correspondante
                $category = null;
                foreach ($categories as $cat) {
                    if ($cat->getName() === $activityData['category']) {
                        $category = $cat;
                        break;
                    }
                }

                if (!$category) {
                    $output->writeln("   ⚠️  Catégorie '{$activityData['category']}' non trouvée, activité ignorée");
                    continue;
                }

                $activity = Activity::create([
                    'description' => $activityData['description'],
                    'duration' => $activityData['duration'],
                    'ageRange' => $activityData['ageRange'],
                    'type' => $activityData['type'],
                    'objectives' => $activityData['objectives'],
                    'workedPoints' => $activityData['workedPoints'],
                ], $coach, $category);

                $this->em->persist($activity);
                $activities[] = $activity;
                $output->writeln("   ✅ Activité créée: {$activityData['description']} (catégorie: {$activityData['category']})");
            }
            $this->em->flush();
            $output->writeln('');

            // 4. Créer des commentaires sur certaines activités
            $output->writeln('💬 Création de commentaires d\'exemple...');
            
            // Récupérer quelques objectifs pour créer des commentaires
            $objectives = $this->objectiveRepository->findBy(['coach' => $coach], null, 3);
            
            $commentsData = [
                [
                    'activityIndex' => 0,
                    'content' => 'Très bonne activité ! Les enfants adorent le parcours. Je recommande de varier les obstacles régulièrement.',
                ],
                [
                    'activityIndex' => 1,
                    'content' => 'Excellent pour développer la mémoire. J\'ai remarqué une nette amélioration après quelques séances.',
                ],
                [
                    'activityIndex' => 2,
                    'content' => 'Activité créative qui plaît beaucoup. Attention à bien protéger les vêtements et l\'espace de travail.',
                ],
                [
                    'activityIndex' => 3,
                    'content' => 'Très utile pour les enfants qui ont des difficultés sociales. Les jeux de rôle aident vraiment.',
                ],
            ];

            foreach ($commentsData as $commentData) {
                if (!isset($activities[$commentData['activityIndex']])) {
                    continue;
                }

                $activity = $activities[$commentData['activityIndex']];
                
                // Créer un commentaire sur l'activité
                $comment = Comment::createForUser([
                    'content' => $commentData['content'],
                ], $coach, null, $activity);

                $this->em->persist($comment);
                $output->writeln("   ✅ Commentaire créé sur l'activité: " . substr($activity->getDescription(), 0, 50) . '...');
            }

            // Créer quelques commentaires sur des objectifs si disponibles
            if (count($objectives) > 0) {
                $objectiveComments = [
                    'Bon progrès observé cette semaine. Continuez ainsi !',
                    'L\'enfant montre de l\'intérêt pour cette activité. À poursuivre.',
                    'Quelques ajustements nécessaires. Je propose de modifier l\'approche.',
                ];

                foreach ($objectives as $index => $objective) {
                    if (isset($objectiveComments[$index])) {
                        $comment = Comment::createForUser([
                            'content' => $objectiveComments[$index],
                        ], $coach, $objective, null);

                        $this->em->persist($comment);
                        $output->writeln("   ✅ Commentaire créé sur l'objectif: " . ($objective->getTitle() ?? 'Sans titre'));
                    }
                }
            }

            $this->em->flush();
            $output->writeln('');

            $output->writeln('✅ Initialisation terminée avec succès !');
            $output->writeln('');
            $output->writeln('📊 Résumé :');
            $output->writeln("   - Catégories créées : " . count($categories));
            $output->writeln("   - Activités créées : " . count($activities));
            $output->writeln("   - Commentaires créés : " . (count($commentsData) + min(count($objectives), 3)));

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $output->writeln('');
            $output->writeln("❌ Erreur lors de l'initialisation : " . $e->getMessage());
            $output->writeln($e->getTraceAsString());
            return Command::FAILURE;
        }
    }
}

