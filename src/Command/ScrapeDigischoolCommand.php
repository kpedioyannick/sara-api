<?php

namespace App\Command;

use App\Entity\Path\Chapter;
use App\Entity\Path\SubChapter;
use App\Entity\Path\Classroom;
use App\Entity\Path\Subject;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\DomCrawler\Crawler;
use Doctrine\Persistence\ManagerRegistry;

class ScrapeDigischoolCommand extends Command
{
    protected static $defaultName = 'app:scrape-digischool';
    protected static $defaultDescription = 'Scrape content from digischool.fr';

    private HttpClientInterface $client;
    private string $baseUrl = 'https://www.digischool.fr';
    private EntityManagerInterface $em;
    private ManagerRegistry $doctrine;
    private SymfonyStyle $io;

    public function __construct(
        HttpClientInterface $client,
        EntityManagerInterface $entityManager,
        ManagerRegistry $doctrine
    ) {
        $this->client = $client;
        $this->em = $entityManager;
        $this->doctrine = $doctrine;
        parent::__construct(self::$defaultName);
    }

    protected function configure(): void
    {
        $this->setDescription(self::$defaultDescription);
    }


    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);
        $this->io->title('Starting to scrape Digischool');

        try {
            $links = [
                '/primaire',
                //'/college',
            ];

            foreach ($links as $item) {
                $mainLinks = $this->getMainLinks($item);
                   
                foreach ($mainLinks as $link) {
                    $this->io->section("Processing section: {$link['text']}");
                    
                    $subPages = $this->getSubPages($link['href']);
                    
                    foreach ($subPages as $subPage) {
                        $this->io->text("Processing sub-page: {$subPage['subject']}");
                        
                        $this->processContent($link['href'], $subPage);
                    
                        sleep(1);
                    }
                }
            }
            $this->io->success('Scraping completed successfully');
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->io->error('An error occurred: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    // ========== FONCTIONS DE SCRAPING ==========
    
    private function getMainLinks($action = '/primaire'): array
    {
        $response = $this->client->request('GET', $this->baseUrl . $action); 
        $crawler = new Crawler($response->getContent());

        return $crawler->filter('a')->reduce(function (Crawler $node) {
            return $node->text() === 'Réviser';
        })->each(function (Crawler $node) {
            return [
                'text' => $this->cleanText($node->text(), 'main link'),
                'href' => $this->baseUrl . $node->attr('href'),
                'level' => $this->extractLevel($node->attr('href'))
            ];
        });
    }

    private function extractLevel(string $href): string 
    {
        preg_match('/primaire\/([^\/]+)/', $href, $matches);
        return $matches[1] ?? '';
    }

    private function getSubPages(string $url): array
    {
        $response = $this->client->request('GET', $url);
        $crawler = new Crawler($response->getContent());
        $classroom = $crawler->filter('h1.tw-headings-h3-36-regular')->text('');
        $classroom = trim(str_replace('Cours et Exercices', '', $classroom));
        $category = $crawler->filter('li.after\:tw-inline-block')->text('');

        $subjects = [];

        $crawler->filter('a.tw-w-56')->each(function (Crawler $node) use (&$subjects, $classroom, $category) {
            $firstH3 = $node->filter('h3')->first();
            $link = $node->filter('a')->first();
            
            if (!$firstH3->count()) {
                return;
            }

            $subject = $this->cleanText($firstH3->text(), 'subject');

            $link = $node->filter('a')->first();
            
            $subjects[] = [
                'classroom' => $classroom,
                'category' => $category,
                'subject' => $subject,
                'href' => $link->count() ? $this->baseUrl . $link->attr('href') : ''
            ];
        });

        return $subjects;
    }

    private function getPageContent(string $url): array
    {
        $response = $this->client->request('GET', $url);
        $crawler = new Crawler($response->getContent());

        $contents = [];
        
        // Chercher les chapitres par ID (chapitre-1, chapitre-2, etc.) dans l'ordre
        $chapterIndex = 1;
        while (true) {
            $chapterId = 'chapitre-' . $chapterIndex;
            $chapterElement = $crawler->filter('#' . $chapterId);
            
            if ($chapterElement->count() === 0) {
                $this->io->text(sprintf('  ⚠️ %s non trouvé, arrêt de la recherche', $chapterId));
                // Si pas de chapitre avec cet ID, essayer aussi avec la classe .tw-min-w-full.tw-pt-4
                if ($chapterIndex === 1) {
                    // Fallback : utiliser l'ancienne méthode si pas de chapitre-1
                    $crawler->filter('.tw-min-w-full.tw-pt-4')->each(function (Crawler $content) use (&$contents) {
                        $contents[] = [
                            'title' => $this->cleanText($content->filter('h2')->text(''), 'content title'),
                            'links' => $content->filter('a')->each(function (Crawler $node) {
                                return [
                                    'type' => $node->filter('span.tw-ml-1')->text(''),
                                    'title' => str_replace('Cours audio - ', '', $node->filter('p.tw-body-s-16-bold')->text('')),
                                    'href' => $this->baseUrl . $node->attr('href'),
                                    'isPremium' => $node->filter('.tw-text-premium-dark')->count() > 0
                                ];
                            })
                        ];
                    });
                }
                break;
            }
            
            // Extraire le titre du chapitre (chercher dans tout le sous-arbre)
            $h2Element = $chapterElement->filter('h2');
            if ($h2Element->count() === 0) {
                // Essayer de chercher dans section > div > h2
                $h2Element = $chapterElement->filter('section h2');
            }
            
            $chapterTitle = $h2Element->count() > 0 
                ? $this->cleanText($h2Element->text(''), 'content title')
                : 'Chapitre ' . $chapterIndex;
            
            $this->io->text(sprintf('  📖 Traitement du %s: "%s"', $chapterId, $chapterTitle));
            
            // Extraire les liens (cours) du chapitre - chercher tous les liens <a> dans le chapitre
            $links = $chapterElement->filter('a')->each(function (Crawler $node) {
                // Extraire le type (Cours ou Quiz)
                $typeElement = $node->filter('span.tw-ml-1');
                $type = $typeElement->count() > 0 ? trim($typeElement->text('')) : '';
                
                // Extraire le titre
                $titleElement = $node->filter('p.tw-body-s-16-bold');
                $title = $titleElement->count() > 0 
                    ? str_replace('Cours audio - ', '', trim($titleElement->text('')))
                    : trim($node->text(''));
                
                // Extraire l'URL
                $href = $node->attr('href');
                if ($href && !str_starts_with($href, 'http')) {
                    $href = $this->baseUrl . $href;
                }
                
                // Vérifier si premium
                $isPremium = $node->filter('.tw-text-premium-dark')->count() > 0 
                    || $node->filter('img[alt="premium"]')->count() > 0;
                
                return [
                    'type' => $type,
                    'title' => $title,
                    'href' => $href,
                    'isPremium' => $isPremium
                ];
            });
            
            $contents[] = [
                'title' => $chapterTitle,
                'links' => $links
            ];
            
            $this->io->text(sprintf('    → Trouvé %d lien(s) dans %s', count($links), $chapterId));
            
            $chapterIndex++;
        }

        foreach ($contents as $key => $content) {
            foreach ($content['links'] as $linkKey => $link) {
                if ($link['type'] !== 'Quiz') {
                    try {
                        $response = $this->client->request('GET', $link['href']);
                        $crawler = new Crawler($response->getContent());
                        
                        // Essayer plusieurs sélecteurs pour trouver les sous-chapitres
                        $subchapters = [];
                        
                        // Méthode 1: Chercher dans .tw-container.tw-mx-auto > h2
                        $container = $crawler->filter('.tw-container.tw-mx-auto');
                        if ($container->count() > 0) {
                            $subchapters = $container->filter('h2')->each(function (Crawler $node) {
                                return $this->cleanText($this->removeRomanNumerals($node->text('')), 'subchapter title');
                            });
                        }
                        
                        // Méthode 2: Si pas de résultats, chercher tous les h2 de la page
                        if (empty($subchapters)) {
                            $subchapters = $crawler->filter('h2')->each(function (Crawler $node) {
                                $text = $this->cleanText($this->removeRomanNumerals($node->text('')), 'subchapter title');
                                // Ignorer les titres vides ou trop courts
                                return strlen($text) > 3 ? $text : null;
                            });
                            $subchapters = array_filter($subchapters);
                        }
                        
                        // Méthode 3: Si toujours pas de résultats, utiliser le titre du cours comme sous-chapitre
                        if (empty($subchapters) && !empty($link['title'])) {
                            $this->io->text(sprintf('Aucun sous-chapitre trouvé pour "%s", utilisation du titre du cours', $link['title']));
                            $subchapters = [$this->cleanText($link['title'], 'subchapter title')];
                        }
                        
                        $contents[$key]['links'][$linkKey]['subchapters'] = array_values($subchapters);
                        
                        if (!empty($subchapters)) {
                            $this->io->text(sprintf('  → Trouvé %d sous-chapitre(s) pour "%s"', count($subchapters), $link['title']));
                        }
                    } catch (\Exception $e) {
                        $this->io->warning(sprintf('Erreur lors de l\'extraction des sous-chapitres pour "%s": %s', $link['title'] ?? 'unknown', $e->getMessage()));
                        $contents[$key]['links'][$linkKey]['subchapters'] = [];
                    }
                } else {
                    $contents[$key]['links'][$linkKey]['subchapters'] = [];
                }
            }
        }

        return $contents;
    }

    private function removeRomanNumerals(string $text): string
    {
        // Supprime les chiffres romains suivis d'un point ou d'une parenthèse fermante
        $text = preg_replace('/[IVXLCDM]+[\.\)]\s*/', '', $text);
        // Supprime les chiffres romains seuls au début
        $text = preg_replace('/^[IVXLCDM]+\s+/', '', $text);
        return trim($text);
    }

    private function processContent(string $url, array $subPage): void
    {
        try {
            $content = $this->getPageContent($subPage['href']);
            
            // Nettoyer les données d'entrée
            $classroomName = $this->cleanText($subPage['classroom'], 'classroom');
            $subjectName = $this->cleanText($subPage['subject'], 'subject');

            // Créer ou récupérer Classroom et Subject
            $classroom = $this->getOrCreateClassroom($classroomName);
            $subject = $this->getOrCreateSubject($subjectName, $classroom);
            
            // Traiter les chapitres
            foreach ($content as $chapterData) {
                try {
                    $chapter = $this->getOrCreateChapter($chapterData, $subject);
                    
                    if (!$chapter) {
                        continue; // Chapitre déjà existant
                    }

                    // Traiter les sous-chapitres
                    // Chaque lien représente un sous-chapitre, utiliser son titre directement
                    foreach ($chapterData['links'] as $link) {
                        try {
                            if ($link['type'] === 'Quiz') {
                                continue;
                            }

                            // Utiliser le titre du lien comme sous-chapitre
                            if (!empty($link['title'])) {
                                try {
                                    $this->getOrCreateSubChapter($link['title'], $chapter);
                                } catch (\Exception $e) {
                                    $this->logError('subchapter', $link['title'], $e);
                                    $this->resetEntityManager();
                                    continue;
                                }
                            }
                        } catch (\Exception $e) {
                            $this->logError('link', $link['title'] ?? 'unknown', $e);
                            $this->resetEntityManager();
                            continue;
                        }
                    }
                } catch (\Exception $e) {
                    $this->logError('chapter', $chapterData['title'] ?? 'unknown', $e);
                    $this->resetEntityManager();
                    continue;
                }
            }

            $this->em->flush();
        } catch (\Exception $e) {
            $this->logError('page', $url, $e);
            $this->resetEntityManager();
        }
    }

    // ========== FONCTIONS CLASSROOM ==========
    
    private function getOrCreateClassroom(string $classroomName): Classroom
    {
        $classroom = $this->em->getRepository(Classroom::class)
            ->findOneBy(['name' => $classroomName]);

        if (!$classroom) {
            $classroom = new Classroom();
            $classroom->setName($classroomName);
            $this->em->persist($classroom);
            $this->em->flush();
        }

        return $classroom;
    }

    // ========== FONCTIONS SUBJECT ==========
    
    private function getOrCreateSubject(string $subjectName, Classroom $classroom): Subject
    {
        // Chercher un sujet existant pour cette classe
        $subject = $this->em->getRepository(Subject::class)
            ->findOneBy([
                'name' => $subjectName,
                'classroom' => $classroom
            ]);

        if (!$subject) {
            $subject = new Subject();
            $subject->setName($subjectName);
            $subject->setClassroom($classroom);
            $this->em->persist($subject);
            $this->em->flush();
        }

        return $subject;
    }

    // ========== FONCTIONS CHAPTER ==========
    
    private function getOrCreateChapter(array $chapterData, Subject $subject): ?Chapter
    {
        $chapterName = $this->cleanText($chapterData['title'], 'chapter title');
        
        // Vérifier si le chapitre existe déjà pour ce sujet
        $existingChapter = $this->em->getRepository(Chapter::class)
            ->findOneBy([
                'subject' => $subject,
                'name' => $chapterName
            ]);
        
        if ($existingChapter) {
            $this->io->text(sprintf('Chapitre "%s" déjà existant, ignoré', $chapterName));
            return null;
        }
        
        // Créer le nouveau chapitre
        $chapter = new Chapter();
        $chapter->setSubject($subject);
        $chapter->setName($chapterName);
        
        $this->em->persist($chapter);
        
        return $chapter;
    }

    // ========== FONCTIONS SUBCHAPTER ==========
    
    private function getOrCreateSubChapter(string $subchapterTitle, Chapter $chapter): ?SubChapter
    {
        $cleanSubchapterTitle = $this->cleanText($subchapterTitle, 'subchapter title');
        
        // Vérifier si le sous-chapitre existe déjà
        $existingSubchapter = $this->em->getRepository(SubChapter::class)
            ->findOneBy([
                'chapter' => $chapter,
                'name' => $cleanSubchapterTitle
            ]);
        
        if ($existingSubchapter) {
            $this->io->text(sprintf('Sous-chapitre "%s" déjà existant, ignoré', $cleanSubchapterTitle));
            return null;
        }
        
        // Créer le nouveau sous-chapitre
        $subchapter = new SubChapter();
        $subchapter->setChapter($chapter);
        $subchapter->setName($cleanSubchapterTitle);
        
        $this->em->persist($subchapter);
        
        return $subchapter;
    }

    // ========== FONCTIONS UTILITAIRES ==========
    
    private function cleanText(string $text, string $context): string
    {
        try {
            // Afficher le texte original pour debug
            $this->io->text(sprintf('Original text for %s: %s', $context, bin2hex($text)));
            
            // Supprimer les caractères BOM et autres caractères spéciaux
            $text = str_replace(["\xEF\xBB\xBF", "\x00"], '', $text);
            
            // Convertir en UTF-8 en utilisant une approche plus stricte
            $text = iconv('UTF-8', 'UTF-8//IGNORE', $text);
            
            // Nettoyer les espaces et caractères spéciaux
            $text = trim($text);
            $text = preg_replace('/\s+/', ' ', $text);
            
            // Supprimer les caractères non-UTF8 restants
            $text = preg_replace('/[^\x{0009}\x{000a}\x{000d}\x{0020}-\x{D7FF}\x{E000}-\x{FFFD}]+/u', '', $text);
            
            // Remplacer les caractères problématiques
            $text = str_replace(
                ['é', 'è', 'ê', 'ë', 'à', 'â', 'ô', 'ö', 'î', 'ï', 'û', 'ü', 'ç', 'ñ'],
                ['e', 'e', 'e', 'e', 'a', 'a', 'o', 'o', 'i', 'i', 'u', 'u', 'c', 'n'],
                $text
            );
            
            // Afficher le texte nettoyé pour debug
            $this->io->text(sprintf('Cleaned text for %s: %s', $text, bin2hex($text)));
            
            return $text;
        } catch (\Exception $e) {
            $this->io->error(sprintf(
                'Error cleaning text for %s: %s. Original text: %s',
                $context,
                $e->getMessage(),
                bin2hex($text)
            ));
            throw $e;
        }
    }

    private function logError(string $type, string $content, \Exception $e): void
    {
        $this->io->error(sprintf(
            'Erreur lors du traitement du %s "%s": %s',
            $type,
            $content,
            $e->getMessage()
        ));
        
        // Afficher le contenu problématique en hexadécimal
        $this->io->text(sprintf(
            'Contenu problématique (hex): %s',
            $content
        ));
    }

    private function resetEntityManager(): void
    {
        if (!$this->em->isOpen()) {
            // Fermer l'EntityManager actuel
            $this->em->close();
            
            // Récupérer une nouvelle instance via ManagerRegistry
            $this->em = $this->doctrine->resetManager();
        }
    }

} 
