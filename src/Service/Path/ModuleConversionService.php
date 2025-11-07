<?php

namespace App\Service\Path;

use App\Enum\ModuleType;
use App\Entity\Module;

class ModuleConversionService
{
    private string $appUrl;

    public function __construct(
        string $appUrl = 'http://localhost:8000'
    ) {
        $this->appUrl = $appUrl;
    }

    public function convertIAOutputToModuleFormat(ModuleType $moduleType, array $iaOutput): array
    {
        $convertedOutput = match($moduleType) {
            ModuleType::BLANKS => $this->convertBlanks($iaOutput),
            ModuleType::MULTI_CHOICE => $this->convertMultiChoice($iaOutput),
            ModuleType::DRAG_WORDS => $this->convertDragWords($iaOutput),
            ModuleType::MARK_THE_WORDS => $this->convertMarkTheWords($iaOutput),
            ModuleType::TRUE_FALSE => $this->convertTrueFalse($iaOutput),
            ModuleType::ESSAY => $this->convertEssay($iaOutput),
            ModuleType::CHOOSE_CORRECT_SENTENCE => $this->convertSummary($iaOutput),
            ModuleType::MEMORY_GAME => $this->convertMemoryGame($iaOutput),
            ModuleType::SINGLE_CHOICE_SET => $this->convertSingleChoiceSet($iaOutput),
            ModuleType::QUESTION_SET => $this->convertQuestionSet($iaOutput),
            ModuleType::SORT_PARAGRAPHS => $this->convertSortParagraphs($iaOutput),
            ModuleType::ACCORDION => $this->convertAccordion($iaOutput),
            ModuleType::IMAGE_SEQUENCING => $this->convertImageSequencing($iaOutput),
            ModuleType::ADVANCED_TEXT => $this->convertAdvancedText($iaOutput),
            ModuleType::SPEAK_THE_WORDS_SET => $this->convertSpeakTheWordsSet($iaOutput),
            ModuleType::DIALOG_CARDS => $this->convertDialogCards($iaOutput),
            ModuleType::FLASHCARDS => $this->convertFlashcards($iaOutput),
            ModuleType::TIMELINE => $this->convertTimeline($iaOutput),
            ModuleType::DICTATION => $this->convertDictation($iaOutput),
            ModuleType::COURSE_PRESENTATION => $this->convertCoursePresentation($iaOutput),
            ModuleType::INTERACTIVE_BOOK => $this->convertInteractiveBook($iaOutput),
            
            // Nouveaux modules non-H5P
            ModuleType::MATCHING_PAIRS => $this->convertMatchingPairs($iaOutput),
            ModuleType::CATEGORIZATION => $this->convertCategorization($iaOutput),
            ModuleType::CORRESPONDENCE_GRID => $this->convertCorrespondenceGrid($iaOutput),
            ModuleType::READING => $this->convertReading($iaOutput),
            ModuleType::REORDERING => $this->convertReordering($iaOutput),
            ModuleType::SCALE_SORTING => $this->convertScaleSorting($iaOutput),
            ModuleType::TABLE_COMPLETION => $this->convertTableCompletion($iaOutput),
            ModuleType::TRANSLATION => $this->convertTranslation($iaOutput),
            ModuleType::SENTENCE_CORRECTION => $this->convertSentenceCorrection($iaOutput),
            ModuleType::ORAL_QUESTION => $this->convertOralQuestion($iaOutput),
            ModuleType::CREATIVE_WRITING => $this->convertCreativeWriting($iaOutput),
            ModuleType::TEXT_ANALYSIS => $this->convertTextAnalysis($iaOutput),
            ModuleType::VOCABULARY_DEFINITION => $this->convertVocabularyDefinition($iaOutput),
            ModuleType::SPEED_READING => $this->convertSpeedReading($iaOutput),
            ModuleType::SENTENCE_SELECTION => $this->convertSentenceSelection($iaOutput),
            ModuleType::SHORT_ANSWER => $this->convertShortAnswer($iaOutput),
            ModuleType::OPEN_QUESTION => $this->convertOpenQuestion($iaOutput),
            ModuleType::ORDERING => $this->convertOrdering($iaOutput),
            ModuleType::COURSE => $this->convertCourse($iaOutput),
            default => throw new \InvalidArgumentException("Type de module non supporté ModuleConversionService : {$moduleType->value}")
        };

        return $convertedOutput;
    }


    private function convertCourse(array $iaData)
    {
        $html = '';
    
        if (isset($iaData['competence'])) {
            $iaData = [$iaData];
        }
        foreach ($iaData as $competenceBlock) {
            $competence = $competenceBlock['competence'] ?? 'Compétence non définie';
            $html .= "<section class='fragment fade-in'><h2>Compétence : " . htmlspecialchars($competence) . "</h2>";
            
            foreach ($competenceBlock['content'] as $item) {
                $type = $item['type'] ?? 'section';
                $html .= "<section class=\"fragment fade-in section-{$type}\"><h3>" . ucfirst($type) . "</h3>";
    
                switch ($type) {
                    case 'course':
                        $html .= $this->renderTextBlock('Titre', $item['title'] ?? []);
                        $html .= $this->renderTextBlock('Contenu', $item['content'] ?? []);
                        break;
                    case 'exercice':
                        $html .= $this->renderTextBlock('Question', $item['question'] ?? []);
                        $html .= $this->renderTextBlock('Réponse', $item['answer'] ?? []);
                        break;
                    default:
                        $html .= $this->renderTextBlock('Contenu', $item['content'] ?? []);
                        break;
                }
    
                $html .= "</section>";
            }
    
            $html .= "</section>";
        }
    
        return $this->convertAdvancedText(['text' => $html]);
    }
    

    private function renderTextBlock(string $label, array $block): string
    {
        $html = '';
        if (!empty($block['text']) && is_array($block['text'])) {
            foreach ($block['text'] as $paragraph) {
                $html .= "<p>" . htmlspecialchars($paragraph) . "</p>";
            }
        }
        // Gère les textes audio en data-attribute (non affiché)
        if (!empty($block['text_audio']) && is_array($block['text_audio'])) {
            foreach ($block['text_audio'] as $audio) {
                $escaped = htmlspecialchars($audio, ENT_QUOTES);
                $html .= "<div data-audio=\"{$escaped}\"></div>";
            }
        }
    
        return $html;
    }
    


    private function convertBlanks(array $iaData): array
    {
        return [
            'text' => $iaData['instruction'] ?? '',
            'questions' => $iaData['text']
        ];
    }

    private function getMedia($iaData) {
        $type = $this->determineMediaType($iaData);
        if (!empty($type)) {
            // TODO: Implémenter la génération de médias
            // Pour l'instant, retourner un tableau vide
            return [];
        }
        return [];
    }

    private function convertMultiChoice(array $iaData): array
    {
        $media = $this->getMedia($iaData);

        return [
            'media' => $media,
            'question' => $iaData['question'] ?? '',
            'answers' => array_map(function($answer) {
                return [
                    'text' => $answer['text'] ?? '',
                    'correct' => $answer['correct'] ?? false,
                    'tipsAndFeedback' => [
                        'tip' => $answer['tipsAndFeedback']['tip'] ?? '',
                        'feedback' => $answer['tipsAndFeedback']['feedback'] ?? ''
                    ]
                ];
            }, $iaData['answers'] ?? [])
        ];
    }

    private function convertDragWords(array $iaData): array
    {
        $textField = $iaData['textField'] ?? '';
        
        // Vérifier si le texte contient des mots entre astérisques
        if (!preg_match('/\*.*\*/', $textField)) {
            return [];
        }

        $media = $this->getMedia($iaData);

        return [
            'media' => $media,
            'taskDescription' => $iaData['taskDescription'] ?? '',
            'textField' => $textField
        ];
    }

    private function convertMarkTheWords(array $iaData): array
    {
        $media = $this->getMedia($iaData);

        return [
            'media' => $media,
            'taskDescription' => $iaData['taskDescription'] ?? '',
            'textField' => $iaData['textField'] ?? ''
        ];
    }

    private function convertTrueFalse(array $iaData): array
    {
        $media = $this->getMedia($iaData);

        return [
            'media' => $media,
            'question' => $iaData['question'] ?? '',
            'correct' => $iaData['correct']  && $iaData['correct'] !== 'false' ? 'true':  'false'
        ];
    }

    private function convertEssay(array $iaData): array
    {
        $media = $this->getMedia($iaData);

        $instruction = $iaData['question'] ;

        return [
            'media' => $media,
            'taskDescription' => $instruction,
            'placeholderText' => $iaData['placeholderText'] ?? '',
            'solution' => [
                'introduction' => $iaData['answer'] ?? '',
                'sample' => $iaData['answer'] ?? ''
            ]
        ];
    }

    private function convertSummary(array $input): array
    {
        $summaries = [];
        foreach ($input['sentences'] as $key => $sentence) {
            $summaries[] = [
                'summary' => $sentence
            ];
        }
        return [
            'type' => 'summary',
            'intro' => $input['taskDescription'] ??  'Choisis la bonne phrase',
            'summaries' => $summaries
        ];
    }

    private function convertMemoryGame(array $iaData): array
    {
        $cards = [];

        foreach ($iaData['cards'] ?? [] as $card) {
            if (isset($card['imageToText']) && !empty($card['imageToText'])) {
                // TODO: Implémenter la génération d'images
                // Pour l'instant, retourner un tableau vide
                $image = [];
        
                $cards[] = [
                    'image' => $image,
                    'description' => $card['description'] ?? '',
                    'imageAlt' => $card['imageAlt'] ?? '',
                    'matchAlt' => $card['matchAlt'] ?? ''
                ];
            }
        }
        
        return [
            'cards' => $cards
        ];
        
    }

    public function convertInteractiveVideo(Module $module): array
    {
        // TODO: Implémenter la génération de vidéos avec Manim
        // Pour l'instant, retourner une structure vide
        throw new \RuntimeException('La génération de vidéos interactives n\'est pas encore implémentée');
        
        $sceneName = 'interactiveVideo' . $module->getId();
        // $video = $this->manimService->generate(...);
        // ...

        // Lire le fichier SRT pour obtenir les timings
        $content = $module->getContent();
        $srtContent = file_get_contents($srtPath);
        $timings = $this->parseSrt($srtContent);

        // Convertir le contenu en interactions
        $interactions = [];
        foreach ($content as $index => $section) {
            if (isset($section['questions']) && !empty($section['questions'])) {
                foreach ($section['questions'] as $question) {
                    $moduleType = $this->getModuleTypeFromVideoQuestion($question['type']);
                    $interactions[] = [
                        'x' => 56.4,
                        'y' => 33.4,
                        'duration' => [
                            'from' => $timings[$index]['start'],
                            'to' => $timings[$index]['end']
                        ],
                        'libraryTitle' => $moduleType->value,
                        'action' => [
                            'library' => $moduleType->getLibrary(),
                            'params' => $this->convertVideoQuestionToAction($question)
                        ],
                        'pause' => true,
                        'label' => $section['title']
                    ];
                }
            }
        }

        return [[
                
                    'interactiveVideo' => [
                        'video' => [
                            'type' => 'video',
                            'description' => '',
                            'files' => [
                                [
                                    'path' => $video,
                                    'mime' => 'video/mp4',
                                    'copyright' => [
                                        'license' => 'U'
                                    ],
                                    'aspectRatio' => '16:9'
                                ]
                            ],
                            'startScreenOptions' => [
                                'title' => 'Interactive Video',
                                'hideStartTitle' => false
                            ]
                        ],
                        'summary' => [
                        ],
                        'assets' => [
                            'interactions' => $interactions,
                            'bookmarks' => [],
                            'endscreens' => []
                        ]
                    ]
                ]];
    }

    private function convertSingleChoiceSet(array $iaData): array
    {
        return 
            $iaData
        ;
    }

    private function convertQuestionSet(array $iaData): array
    {
        return [
            'questions' => array_values(array_filter(
                array_map(function($question) {
                    if (empty($question['type']) || empty($question['params'])) {
                        return null;
                    }
                    $moduleType = ModuleType::ModuleType($question['type']);
                    return [
                        'library' => $question['library'] ?? '',
                        'params' => $this->convertIAOutputToModuleFormat($moduleType, $question['params'])
                    ];
                }, $iaData['questions'] ?? [])
            ))
        ];
    }

    private function convertSortParagraphs(array $iaData): array
    {
        return [
            'taskDescription' => $iaData['taskDescription'] ?? '',
            'paragraphs' => $iaData['paragraphs'] ?? []
        ];
    }

    private function convertAccordion(array $iaData): array
    {
        return [
            'panels' => array_map(function($panel) {
                return [
                    'title' => $panel['title'] ?? '',
                    'content' => [
                        'params' => [
                            'text' => $panel['content'] ?? ''
                        ],
                        'library' => 'H5P.AdvancedText 1.1',
                        'subContentId' => uniqid('', true),
                        'metadata' => [
                            'contentType' => 'Text',
                            'license' => 'U',
                            'title' => 'Untitled Text',
                            'authors' => [],
                            'changes' => []
                        ]
                    ]
                ];
            }, $iaData['panels'] ?? []),
            'hTag' => $iaData['settings']['headingLevel'] ?? 'h2'
        ];
    }

    private function convertImageSequencing(array $iaData): array
    {
        dump($iaData);
        $sequenceImages = [];

        foreach ($iaData['sequenceImages'] ?? [] as $imageData) {
            if (isset($imageData['imageDescription'])) {
                // TODO: Implémenter la génération d'images
                // Pour l'instant, retourner un tableau vide
                $image = [];
                $sequenceImages[] = [
                    'imageDescription' => $imageData['imageDescription'] ?? '',
                    'image' => $image
                ];
            }
        }
        

        return [
            'taskDescription' => $iaData['taskDescription'] ?? '',
            'altTaskDescription' => $iaData['altTaskDescription'] ?? '',
            'sequenceImages' => $sequenceImages,
            'l10n' => [
                'totalMoves' => 'Total Moves',
                'timeSpent' => 'Time spent',
                'score' => 'You got @score of @total points',
                'checkAnswer' => 'Check',
                'tryAgain' => 'Retry',
                'showSolution' => 'Show Solution',
                'resume' => 'Resume',
                'audioNotSupported' => 'Audio Not Supported',
                'ariaPlay' => 'Play the corresponding audio',
                'ariaMoveDescription' => 'Moved @cardDesc from @posSrc to @posDes',
                'ariaCardDesc' => 'sequencing item'
            ],
            'behaviour' => [
                'enableSolution' => true,
                'enableRetry' => true,
                'enableResume' => true
            ]
        ];
    }

    private function convertAdvancedText(array $iaData): array
    {
        return [
            'text' => $iaData['text'] ?? '',
            'settings' => [
                'fontSize' => 'medium',
                'fontFamily' => 'arial',
                'lineHeight' => 1.5
            ]
        ];
    }

    private function convertSpeakTheWordsSet(array $iaData): array
    {
        if (!isset($iaData['questions']) || empty($iaData['questions'])) {
            return [];
        }

        $questions = [];
        foreach ($iaData['questions'] as $key => $value) {
            $media = [];
            $params = array_merge([
                'media' => $media,
                "incorrectAnswerText" => "Incorrect answer",
                "correctAnswerText" => "Correct answer",
                "inputLanguage" => "en-US",
                'l10n' => [
                    "retryLabel" => "Retry",
                    "showSolutionLabel" => "Show solution",
                    "speakLabel" => "Push to speak",
                    "listeningLabel" => "Listening...",
                    'correctAnswersText' => 'La/les bonne(s) réponse(s) :',
                    'userAnswersText' => 'Votre réponse a été interprétée comme :',
                    'noSound' => 'Je ne vous ai pas entendu, assurez-vous que votre micro est activé',
                    'unsupportedBrowserHeader' => 'Il semble que votre navigateur ne supporte pas la reconnaissance vocale',
                    'unsupportedBrowserDetails' => 'Veuillez réessayer dans un navigateur comme Chrome',
                    'a11yShowSolution' => 'Afficher la solution. L’exercice sera marqué avec sa solution correcte.',
                    'a11yRetry' => 'Réessayer l’exercice. Réinitialiser toutes les réponses et recommencer.'
                ]
            ], $value);

            $questions[] = [
                'params' => $params,
                'library' => 'H5P.SpeakTheWords 1.5'
            ];
        }

        return [
            'introduction' => $iaData['introduction'] ?? ['showIntroPage' => false],
            'questions' => $questions
        ];
    }

    private function convertDialogCards(array $iaData): array
    {
        return [
            'cards' => array_map(function($card) {
                // TODO: Implémenter la génération d'images
                // Pour l'instant, retourner un tableau vide
                return [
                    'image' => [],
                    'text' => $card['text'] ?? '',
                    'answer' => $card['answer'] ?? '',
                    'tips' => $card['tips'] ?? ''
                ];
            }, $iaData['cards'] ?? []),
            'settings' => [
                'enableRetry' => true,
                'enableSolutionsButton' => true
            ]
        ];
    }

    private function convertFlashcards(array $iaData): array
    {
        return [
            'cards' => array_map(function($card) {
                // TODO: Implémenter la génération d'images
                // Pour l'instant, retourner un tableau vide
                return [
                    'image' => [],
                    'text' => $card['text'] ?? '',
                    'answer' => $card['answer'] ?? '',
                    'tip' => $card['tip'] ?? ''
                ];
            }, $iaData['cards'] ?? [])
        ];
    }

    private function convertTimeline(array $iaData): array
    {
        return $iaData;
    }

    private function convertDictation(array $iaData): array
    {
        
        foreach ($iaData['sentences'] as $key => $sample) {
            // TODO: Implémenter la génération d'audio
            // Pour l'instant, ne pas ajouter de média audio
            // $iaData['sentences'][$key]['sample'][] = $this->mediaGenerationService->generateMedia('audio', $sample['text']);
        }
        return $iaData;
    }

    private function determineMediaType(array $data)
    {
        if (isset($data['imageToText'])) {
            return 'image';
        }
        if (isset($data['videoToText'])) {
            return 'video';
        }
        if (isset($data['audioToText'])) {
            return 'audio';
        }
        return null;
    }

    private function parseSrt($srtContent): array
    {
        $timings = [];
        $lines = explode("\n", $srtContent);
        $currentIndex = null;
        $currentTiming = [];

        foreach ($lines as $line) {
            if (preg_match('/^\d+$/', $line)) {
                if ($currentIndex !== null) {
                    $timings[$currentIndex] = $currentTiming;
                }
                $currentIndex = (int)$line - 1;
                $currentTiming = [];
            } elseif (preg_match('/(\d{2}:\d{2}:\d{2},\d{3}) --> (\d{2}:\d{2}:\d{2},\d{3})/', $line, $matches)) {
                $currentTiming['start'] = $this->srtTimeToSeconds($matches[1]);
                $currentTiming['end'] = $this->srtTimeToSeconds($matches[2]);
            }
        }

        if ($currentIndex !== null) {
            $timings[$currentIndex] = $currentTiming;
        }

        return $timings;
    }

    private function srtTimeToSeconds($srtTime): float
    {
        $parts = explode(',', $srtTime);
        $time = $parts[0];
        $milliseconds = (int)$parts[1];
        
        list($hours, $minutes, $seconds) = explode(':', $time);
        
        return $hours * 3600 + $minutes * 60 + $seconds + $milliseconds / 1000;
    }

    private function convertVideoQuestionToAction($question): array
    {
        switch ($question['type']) {
            case 'open_ended':
                $correctAnswer = '';
                foreach ($question['answers'] as $answer) {
                    if ($answer['correct'] === true) {
                        $correctAnswer = $answer['text'];
                        break;
                    }
                }
                $input = [
                    'question' => $question['question'],
                    'answer' => $correctAnswer
                ];
                return $this->convertEssay($input);
            case 'multi_choice':
                return $this->convertMultiChoice($question);
            case 'true_false':
                $correctAnswer = $question['answers'][0]['correct'];
                $input = [
                    'question' => $question['question'],
                    'correct' => $correctAnswer,
                ];
                return $this->convertTrueFalse($input);
            case 'findGoodSentence':
                $answers = [];
                foreach ($question['answers'] as $answer) {
                    $answer['text'] = $question['question'] . ' Réponse : ' . $answer['text'];
                    if ($answer['correct'] === true) {
                        array_unshift($answers, $answer['text']);
                    } else {
                        $answers[] = $answer['text'];
                    }   
                }
                $input = [
                    'sentences' => $answers,
                    'intro' => 'Choisis la bonne phrase'
                ];
                return $this->convertSummary([$input]);
            case 'drag_words':
                $question['textField'] = $this->createGapText($question['text'])    ;
                return $this->convertDragWords($question);
            default:
                return [];
        }
    }

    private function createGapText(string $text): ?array
    {
        // Diviser le texte en mots
        $words = explode(' ', $text);
        
        // Filtrer les mots de plus de 4 caractères
        $validWords = array_filter($words, function($word) {
            return strlen($word) >= 4;
        });
        
        // Réindexer le tableau
        $validWords = array_values($validWords);
        
        // Vérifier s'il y a assez de mots valides (au moins 3)
        if (count($validWords) < 3) {
            return null;
        }
        
        // Déterminer le nombre de mots à masquer (entre 3 et 5)
        $wordsToHide = min(5, max(3, min(count($validWords), floor(count($validWords) * 0.2))));
        
        // Sélectionner aléatoirement les indices des mots à masquer
        $indicesToHide = array_rand(array_flip(range(0, count($validWords) - 1)), $wordsToHide);
        
        // Créer le texte avec les trous
        $gapText = '';
        $correctAnswers = [];
        $currentValidWordIndex = 0;
        
        foreach ($words as $word) {
            if (strlen($word) > 4) {
                if (in_array($currentValidWordIndex, $indicesToHide)) {
                    $gapText .= '*** ';
                    $correctAnswers[] = $word;
                } else {
                    $gapText .= $word . ' ';
                }
                $currentValidWordIndex++;
            } else {
                $gapText .= $word . ' ';
            }
        }
        
        return [
            'text' => trim($gapText),
            'answers' => $correctAnswers
        ];
    }

    private function getModuleTypeFromVideoQuestion(string $type)
    {
        return match($type) {
            'open_ended' => ModuleType::ESSAY,
            'multi_choice' => ModuleType::MULTI_CHOICE,
            'true_false' => ModuleType::TRUE_FALSE,
            'findGoodSentence' => ModuleType::CHOOSE_CORRECT_SENTENCE
        };
    }

    private function convertCoursePresentation(array $iaData): array
    {
        $slides = [];
        $slideIndex = 0;
	
        foreach ($iaData['slides'] as $item) {
	    $type = $item['type'];
	    $content = $item;

            $slide = [
                'elements' => [],
                'keywords' => [],
                'slideBackgroundSelector' => [
                    'imageSlideBackground' => null,
                    'fillSlideBackground' => null
                ]
            ];
            
            // Créer un élément pour chaque item
            $element = [
                'x' => 2,
                'y' => 4,
                'width' => 85,
                'height' => 90,
                'action' => $item,
                'solution' => '',
                'alwaysDisplayComments' => false,
                'backgroundOpacity' => 10,
                'displayAsButton' => false,
                'buttonLabel' => '',
                'buttonSize' => 'big',
                'title' => '',
                'goToSlideType' => 'next',
                'goToSlide' => null,
                'invisible' => false
            ];
            
            // Ajouter le contenu selon le type
            if (!empty($content)) {
                $moduleType = ModuleType::ModuleType($type);
                $element['action']['params'] = $this->convertIAOutputToModuleFormat($moduleType, $content['params']);
            }

            
            $slide['elements'][] = $element;
            $slides[] = $slide;
            $slideIndex++;
        }
        
        return [
                'presentation' => [
                    'slides' => $slides,
                    'ct' => '',
                    'keywordListEnabled' => true,
                    'keywordListAlwaysShow' => false,
                    'keywordListAutoHide' => false,
                    'keywordListOpacity' => 90,
                    'globalBackgroundSelector' => [
                        'imageGlobalBackground' => null,
                        'fillGlobalBackground' => null
                    ]
            ]
        ];
    }
    
    private function getActionForType(string $type): array
    {
        $libraryMap = [
            'text' => 'H5P.AdvancedText 1.1',
            'blanks' => 'H5P.Blanks 1.14',
            'multiChoice' => 'H5P.MultiChoice 1.16',
            'trueFalse' => 'H5P.TrueFalse 1.8',
            'singleChoiceSet' => 'H5P.SingleChoiceSet 1.11',
            'markTheWords' => 'H5P.MarkTheWords 1.11',
            'sortParagraphs' => 'H5P.SortParagraphs 0.11',
            'dictation' => 'H5P.Dictation 1.0',
            'drag_words' => 'H5P.DragText 1.10',
            'open_question' => 'H5P.Essay 1.5',
            'speak_the_words_set' => 'H5P.SpeakTheWordsSet 1.3',
            'flashcards' => 'H5P.Flashcards 1.5',
            'accordion' => 'H5P.Accordion 1.0'
        ];
        
        return [
            'library' => $libraryMap[$type] ?? 'H5P.AdvancedText 1.1',
            'params' => []
        ];
    }
    
    private function getContentForType(string $type, $content): array
    {
	return $content;
        switch ($type) {
            case 'text':
                return ['text' => is_array($content) ? $content[0] : $content];
                
            case 'blanks':
                return ['text' => is_array($content) ? $content[0] : $content];
                
            case 'multiChoice':
                return [
                    'question' => $content['question'] ?? '',
                    'answers' => array_map(function($choice) use ($content) {
                        return [
                            'text' => $choice,
                            'correct' => $choice === ($content['answer'] ?? ''),
                            'tipsAndFeedback' => [
                                'chosenFeedback' => '',
                                'notChosenFeedback' => ''
                            ]
                        ];
                    }, $content['choices'] ?? [])
                ];
                
            case 'trueFalse':
                return [
                    'question' => $content['statement'] ?? '',
                    'correct' => $content['answer'] ? 'true' : 'false'
                ];
                
            case 'singleChoiceSet':
                return [
                    'question' => $content['question'] ?? '',
                    'answers' => array_map(function($choice, $index) use ($content) {
                        return [
                            'text' => $choice,
                            'correct' => $index === ($content['answer'] ?? 0)
                        ];
                    }, $content['choices'] ?? [], array_keys($content['choices'] ?? []))
                ];
                
            case 'markTheWords':
                return [
                    'taskDescription' => 'Marquez les mots corrects',
                    'textField' => is_array($content) ? $content[0] : $content
                ];
                
            case 'sortParagraphs':
                return [
                    'taskDescription' => 'Remettez les paragraphes dans l\'ordre',
                    'paragraphs' => $content['paragraphs'] ?? []
                ];
                
            case 'dictation':
                return ['sentences' => [['text' => is_array($content) ? $content[0] : $content]]];
                
            case 'drag_words':
                return [
                    'taskDescription' => 'Glissez les mots aux bons endroits',
                    'textField' => is_array($content) ? $content[0] : $content
                ];
                
            case 'open_question':
                return [
                    'question' => $content['question'] ?? '',
                    'placeholderText' => 'Votre réponse...',
                    'solution' => [
                        'introduction' => $content['answer'] ?? '',
                        'sample' => $content['answer'] ?? ''
                    ]
                ];
                
            case 'speak_the_words_set':
                return [
                    'introduction' => [
                        'showIntroPage' => true,
                        'introductionTitle' => 'Exercice de prononciation'
                    ],
                    'questions' => array_map(function($item) {
                        return [
                            'question' => $item['question'] ?? '',
                            'correctAnswerText' => $item['answer'] ?? '',
                            'acceptedAnswers' => [$item['answer'] ?? ''],
                            'inputLanguage' => 'fr-FR'
                        ];
                    }, is_array($content) ? $content : [$content])
                ];
                
            case 'flashcards':
                return [
                    'cards' => array_map(function($item) {
                        return [
                            'text' => $item['question'] ?? '',
                            'answer' => $item['answer'] ?? '',
                            'tip' => ''
                        ];
                    }, is_array($content) ? $content : [$content])
                ];
                
            case 'accordion':
                return [
                    'panels' => array_map(function($item) {
                        return [
                            'title' => $item['title'] ?? '',
                            'content' => $item['description'] ?? ''
                        ];
                    }, is_array($content) ? $content : [$content])
                ];
                
            default:
                return ['text' => is_array($content) ? $content[0] : $content];
        }
    }

    public function convertCompleteCurriculum(Module $module): array
    {
        $curriculumData = $module->getContent();
        if (!is_array($curriculumData)) {
            throw new \RuntimeException('Curriculum data is not in expected format');
        }
        foreach ($curriculumData as $sectionIdx => $section) {
            if (!isset($section['subChapters']) || !is_array($section['subChapters'])) {
                continue;
            }
            foreach ($section['subChapters'] as $subChapterIdx => $subChapter) {
                if (isset($subChapter['trainingExercises']) && is_array($subChapter['trainingExercises'])) {
                    foreach ($subChapter['trainingExercises'] as $exerciseIdx => $exercise) {
                        $exerciseType = $exercise['exerciseType'] ?? null;
                        $exerciseContent = $exercise['content'] ?? null;
                        if ($exerciseType && $exerciseContent) {
                            try {
                                $moduleType = ModuleType::ModuleType($exerciseType);
                                $converted = $this->convertIAOutputToModuleFormat($moduleType, $exerciseContent);
                                $curriculumData[$sectionIdx]['subChapters'][$subChapterIdx]['trainingExercises'][$exerciseIdx]['h5p'] = $converted;
                            } catch (\Throwable $e) {
                                $curriculumData[$sectionIdx]['subChapters'][$subChapterIdx]['trainingExercises'][$exerciseIdx]['h5p'] = ['error' => $e->getMessage()];
                            }
                        }
                    }
                }
            }
        }
        return $curriculumData;
    }

    public function convertTrainingExercise(Module $module): array
    {
        $trainingData = $module->getContent();
        if (!is_array($trainingData)) {
            throw new \RuntimeException('Training exercise data is not in expected format');
        }
        $convertedExercises = [];
        if (isset($trainingData['exercises']) && is_array($trainingData['exercises'])) {
            foreach ($trainingData['exercises'] as $exerciseIdx => $exercise) {
                $exerciseType = $exercise['type'] ?? null;
                $exerciseContent = $exercise['content'] ?? null;
                if ($exerciseType && $exerciseContent) {
                    try {
                        $moduleType = ModuleType::ModuleType($exerciseType);
                        $converted = $this->convertIAOutputToModuleFormat($moduleType, $exerciseContent);
                        $convertedExercises[] = [
                            'type' => $exerciseType,
                            'instruction' => $exercise['instruction'] ?? '',
                            'difficulty' => $exercise['difficulty'] ?? 'medium',
                            'correction' => $exercise['correction'] ?? '',
                            'h5p' => $converted
                        ];
                    } catch (\Throwable $e) {
                        $convertedExercises[] = [
                            'type' => $exerciseType,
                            'instruction' => $exercise['instruction'] ?? '',
                            'difficulty' => $exercise['difficulty'] ?? 'medium',
                            'correction' => $exercise['correction'] ?? '',
                            'h5p' => ['error' => $e->getMessage()]
                        ];
                    }
                }
            }
        }
        return [
            'title' => $trainingData['title'] ?? '',
            'introduction' => $trainingData['introduction'] ?? '',
            'exercises' => $convertedExercises,
            'conclusion' => $trainingData['conclusion'] ?? ''
        ];
    }

    public function convertRevisionSheet(Module $module): array
    {
        $revisionData = $module->getContent();
        if (!is_array($revisionData)) {
            throw new \RuntimeException('Revision sheet data is not in expected format');
        }
        $convertedSections = [];
        foreach ($revisionData as $sectionIdx => $section) {
            $convertedSection = [
                'type' => $section['type'] ?? '',
                'content' => $section['content'] ?? '',
                'validation' => []
            ];
            if (isset($section['validation']) && is_array($section['validation'])) {
                foreach ($section['validation'] as $validationIdx => $sentences) {
                    if (is_array($sentences) && !empty($sentences)) {
                        try {
                            $converted = $this->convertIAOutputToModuleFormat(
                                ModuleType::CHOOSE_CORRECT_SENTENCE,
                                ['sentences' => $sentences]
                            );
                            $convertedSection['validation'][] = $converted;
                        } catch (\Throwable $e) {
                            $convertedSection['validation'][] = ['error' => $e->getMessage()];
                        }
                    }
                }
            }
            $convertedSections[] = $convertedSection;
        }
        return $convertedSections;
    }

    private function convertInteractiveBook(array $iaData): array
    {
        $chapters = [];
        
        foreach ($iaData['chapters'] ?? [] as $chapter) {
            if (isset($chapter['params']['content'])) {
                $content = [];
                foreach ($chapter['params']['content'] as $contentItem) {
                    $type = ModuleType::ModuleType($contentItem['type']);
                    $convertedContent = $this->convertIAOutputToModuleFormat($type, $contentItem['params']);
                    $content[] = [
                        'content' => [
                            'library' => $contentItem['library'],
                            'params' => $convertedContent,
                            'subContentId' => uniqid('', true),
                            'metadata' => [
                                'contentType' => 'Text',
                                'license' => 'U',
                                'title' => 'Untitled Text',
                                'authors' => [],
                                'changes' => []
                            ]
                        ],
                        'useSeparator' => 'auto'
                    ];
                
                
                    $chapters[] = [
                        'library' => ModuleType::COLUMN->getLibrary(),
                        'params' => [
                            'content' => $content
                        ],
                        'subContentId' => uniqid('', true),
                        'metadata' => [
                            'contentType' => 'Column',
                            'license' => 'U',
                            'title' => 'Untitled Text'
                        ]
                    ];

                }
            }
        }

        return [
            'showCoverPage' => $iaData['showCoverPage'] ?? false,
            'bookCover' => [
                'coverDescription' => $iaData['bookCover']['coverDescription'] ?? '',
                'coverMedium' => ''
            ],
            'chapters' => $chapters,
            'behaviour' => [
                'baseColor' => '#1768c4',
                'defaultTableOfContents' => $iaData['behaviour']['defaultTableOfContents'] ?? true,
                'progressIndicators' => $iaData['behaviour']['progressIndicators'] ?? true,
                'progressAuto' => $iaData['behaviour']['progressAuto'] ?? true,
                'displaySummary' => $iaData['behaviour']['displaySummary'] ?? true,
                'enableRetry' => $iaData['behaviour']['enableRetry'] ?? true
            ]
        ];
    }

    public function convertInteractiveBookParent($module): array
    {
        $chapters = [];
        foreach ($module->getChildren() as $child) {
            foreach ($child->getContent() as $key => $contentItem) {
                $content = [];
                $convertedContent = $this->convertIAOutputToModuleFormat($child->getType(), $contentItem);
                $content[] = [
                    'content' => [
                        'library' => $child->getType()->getLibrary(),
                        'params' => $convertedContent,
                        'subContentId' => 'submodule-' . $child->getId() . '-' . $key ,
                        'metadata' => [
                            'contentType' => 'Text',
                            'license' => 'U',
                            'title' => $child->getTitle(),
                            'authors' => [],
                            'changes' => []
                        ]
                    ],
                    'useSeparator' => 'auto'
                ];  
                
            

                $chapters[] = [
                    'library' => ModuleType::COLUMN->getLibrary(),
                    'params' => [
                        'content' => $content
                    ],
                    'subContentId' => 'module-' . $child->getId() . '-' . $key ,
                    'metadata' => [
                        'contentType' => 'Column',
                        'license' => 'U',
                        'title' => $child->getTitle()
                    ]
                ];
            }
        }

        return [
            'showCoverPage' => false,
            'bookCover' => [
                'coverDescription' => $module->getTitle(),
                'coverMedium' => ''
            ],
            'chapters' => $chapters,
            'behaviour' => [
                'baseColor' => '#1768c4',
                'defaultTableOfContents' => true,
                'progressIndicators' => true,
                'progressAuto' => true,
                'displaySummary' => true,
                'enableRetry' => true
            ]
        ];
    }

    // Méthodes de conversion pour les nouveaux modules non-H5P utilisant les modules H5P existants

    private function convertMatchingPairs(array $iaData): array
    {
        $text = '';
        foreach ($iaData['pairs'] ?? [] as $pair) {
            $text .= $pair['left'] . ' * ' . $pair['right'] . ' * <br>';
        }
        return $this->convertDragWords([
            'taskDescription' => $iaData['instruction'] ?? 'Associez les éléments par paires',
            'textField' => $text
        ]);
    }

    private function convertCategorization(array $iaData): array
    {
        $text = '';
        foreach ($iaData['categories'] ?? [] as $category) {
            $text .= '<strong>' . $category['name'] . '</strong>: ';
            foreach ($category['items'] as $item) {
                $text .=  ' * ' . $item . ' * <br>';
            }
            $text .= '<br><br>';
        }
        return $this->convertDragWords([
            'taskDescription' => $iaData['instruction'] ?? 'Classez les éléments dans les bonnes catégories',
            'textField' => $text
        ]);
    }

    private function convertCorrespondenceGrid(array $iaData): array
    {
        $text = '';
        $columns = $iaData['columns'] ?? [];
    
        foreach ($columns as $column) {
            $label = $column['label'] ?? '';
            foreach ($column['rows'] ?? [] as $rowData) {
                if (!isset($rowData['row'])) continue;
    
                $rowText = htmlspecialchars($rowData['row']);
    
                // Si word_to_found est vrai, on marque la bonne correspondance
                if (!empty($rowData['word_to_found'])) {
                    $text .= $rowText . ' * ' . htmlspecialchars($label) . ' * <br>';
                } else {
                    $text .= $rowText . ' : ' . htmlspecialchars($label) . '<br>';
                }
            }
        }
    
        return $this->convertDragWords([
            'taskDescription' => $iaData['instruction'] ?? 'Complétez la grille de correspondance.',
            'textField' => $text
        ]);
    }
    

    private function convertReading(array $iaData): array
    {
        $answer = '<h3>Questions de compréhension :</h3>';
        foreach ($iaData['questions'] ?? [] as $index => $question) {
            $answer .= '<p><strong>Question ' . ($index + 1) . ':</strong> ' . $question['question'] . '</p>';
            if (isset($question['answers'])) {
                $answer .= '<ul>';
                foreach ($question['answers'] as $ans) {
                    $answer .= '<li>' . $ans['text'] . ($ans['correct'] ? ' (Correct)' : '') . '</li>';
                }
                $answer .= '</ul>';
            }
        }
        return $this->convertEssay([
            'question' => $iaData['text'] ?? '',
            'placeholderText' => 'Répondez aux questions de compréhension...',
            'answer' => $answer
        ]);
    }

    private function convertReordering(array $iaData): array
    {
        return $this->convertSortParagraphs([
            'taskDescription' => $iaData['instruction'] ?? 'Remettez les éléments dans l\'ordre',
            'paragraphs' => $iaData['items'] ?? []
        ]);
    }

    private function convertScaleSorting(array $iaData): array
    {
        $items = [];
        $scale = $iaData['scale'] ?? [];
        $items[] = '<strong>Échelle : ' . ($scale['min'] ?? '') . ' → ' . ($scale['max'] ?? '') . '</strong>';
        foreach ($iaData['items'] ?? [] as $item) {
            $items[] = $item['text'] . ' (position ' . $item['position'] . ')';
        }
        return $this->convertSortParagraphs([
            'taskDescription' => $iaData['instruction'] ?? 'Classez les éléments sur l\'échelle',
            'paragraphs' => $items
        ]);
    }

    private function convertTableCompletion(array $iaData): array
    {
        $text = '<table border="1"><tr>';
    
        foreach ($iaData['headers'] ?? [] as $header) {
            $text .= '<th>' . htmlspecialchars($header) . '</th>';
        }
        $text .= '</tr>';
    
        // Traitement des lignes du tableau
        foreach ($iaData['rows'] ?? [] as $row) {
            $text .= '<tr>';
            $blanks = $row['positions_items_to_blanks'] ?? [];
    
            foreach ($row['cells'] ?? [] as $index => $cell) {
                if (in_array($index, $blanks)) {
                    $text .= '<td>*'. htmlspecialchars($cell) .'*</td>'; // ou un champ input si interactif
                } else { 
                    $text .= '<td>' . htmlspecialchars($cell) . '</td>';
                }
            }
    
            $text .= '</tr>';
        }
    
        $text .= '</table>';
    
        return $this->convertBlanks([
            'instruction' => $iaData['instruction'] ?? 'Complétez le tableau',
            'text' => $text
        ]);
    }
    

    private function convertTranslation(array $iaData): array
    {
        $text = '';
        foreach ($iaData['sentences'] ?? [] as $sentence) {
            $text .= '<p><strong>À traduire :</strong> ' . $sentence['source'] . '</p>';
            $text .= '<p><strong>Traduction :</strong> *' . $sentence['target']  .'* </p>';
            if (!empty($sentence['alternatives'])) {
                $text .= '<p><em>Alternatives acceptées : ' . implode(', ', $sentence['alternatives']) . '</em></p>';
            }
        }
        return $this->convertBlanks([
            'instruction' => $iaData['instruction'] ?? 'Traduisez les phrases',
            'text' => $text
        ]);
    }

    private function convertSentenceCorrection(array $iaData): array
    {
        
        return $this->convertMarkTheWords([
            'taskDescription' => $iaData['instruction'] ?? 'Corrigez les erreurs dans les phrases',
            'textField' => $iaData['sentences']
        ]);
    }

    private function convertOralQuestion(array $iaData): array
    {
        return $this->convertBlanks([
            'instruction' => 'Donnez votre réponse',
            'text' => $iaData['question'] . ' </br> ' . $iaData['expectedAnswer']
        ]);
    }

    private function convertCreativeWriting(array $iaData): array
    {
        $answer = '<h3>Critères d\'évaluation :</h3>';
        if (!empty($iaData['constraints'])) {
            $answer .= '<p><strong>Contraintes :</strong></p><ul>';
            foreach ($iaData['constraints'] as $constraint) {
                $answer .= '<li>' . htmlspecialchars($constraint) . '</li>';
            }
            $answer .= '</ul>';
        }
    
        // Nombre de mots
        if (!empty($iaData['wordCount'])) {
            $min = $iaData['wordCount']['min'] ?? 0;
            $max = $iaData['wordCount']['max'] ?? null;
            $answer .= '<p><strong>Nombre de mots :</strong> ' . $min;
            $answer .= $max ? ' - ' . $max : ' - illimité';
            $answer .= '</p>';
        }
    
        // Critères d'évaluation
        if (!empty($iaData['criteria'])) {
            $answer .= '<p><strong>Critères :</strong></p><ul>';
            foreach ($iaData['criteria'] as $criterion) {
                $answer .= '<li>' . htmlspecialchars($criterion) . '</li>';
            }
            $answer .= '</ul>';
        }
    
        return $this->convertEssay([
            'question' => $iaData['prompt'] ?? 'Rédigez un texte selon les consignes ci-dessous.',
            'placeholderText' => 'Rédigez votre texte...',
            'answer' => $answer
        ]);
    }
    

    private function convertTextAnalysis(array $iaData): array
    {
        $text = $iaData['text'] ?? '';
        foreach ($iaData['questions'] ?? [] as $index => $question) {
            $text .= '<p><strong>Question ' . ($index + 1) . ':</strong> ' . $question['question'] . '</p>';
            if (!empty($question['guidelines'])) {
                $text .= '<p><em>Guidelines :</em></p><ul>';
                foreach ($question['guidelines'] as $guideline) {
                    $text .= '<li>' . $guideline . '</li>';
                }
                $text .= '</ul>';
            }
            $text .= '<p>Réponse : ***</p>';
        }
        return $this->convertBlanks([
            'instruction' => $iaData['instruction'] ?? 'Analysez le texte et répondez aux questions',
            'text' => $text
        ]);
    }

    private function convertVocabularyDefinition(array $iaData): array
    {
        $text = '';
        foreach ($iaData['words'] ?? [] as $word) {
            $text .= '<p><strong>' . $word['word'] . '</strong></p>';
            $text .= '<p><em>Contexte :</em> ' . $word['context'] . '</p>';
            if (!empty($word['synonyms'])) {
                $text .= '<p><em>Synonymes :</em> ' . implode(', ', $word['synonyms']) . '</p>';
            }
            $text .= '<p>Définition : *' . $word['definition'] . '*</p>';
        }
        return $this->convertBlanks([
            'instruction' => $iaData['instruction'] ?? 'Définissez les mots suivants',
            'text' => $text
        ]);
    }

    private function convertSpeedReading(array $iaData): array
    {
        $answer = '<h3>Questions après lecture rapide :</h3>';
        foreach ($iaData['questions'] ?? [] as $index => $question) {
            $answer .= '<p><strong>Question ' . ($index + 1) . ':</strong> ' . $question['question'] . '</p>';
            if (isset($question['answers'])) {
                $answer .= '<ul>';
                foreach ($question['answers'] as $ans) {
                    $answer .= '<li>' . $ans['text'] . ($ans['correct'] ? ' (Correct)' : '') . '</li>';
                }
                $answer .= '</ul>';
            }
        }
        return $this->convertEssay([
            'question' => $iaData['text'] ?? '',
            'placeholderText' => 'Répondez aux questions après lecture rapide...',
            'answer' => $answer
        ]);
    }

    private function convertSentenceSelection(array $iaData): array
    {
        $choices = [];
        foreach ($iaData['sentences'] ?? [] as $sentence) {
            $choices[] = $sentence['correct'];
            foreach ($sentence['incorrect'] as $incorrect) {
                $choices[] = $incorrect;
            }
        }
        return $this->convertSummary(['sentences' => $choices]);
    }

    private function convertShortAnswer(array $iaData): array
    {
        dump($iaData);
        return $this->convertEssay([
            'question' => $iaData['question'] ?? '',
            'placeholderText' => $iaData['placeholderText'] ?? 'Votre réponse courte...',
            'answer' => 'Réponse attendue : ' . ($iaData['expectedAnswer'] ?? '')
        ]);
    }

    private function convertOpenQuestion(array $iaData): array
    {
        $answer = '<h3>Critères d\'évaluation :</h3>';
        if (!empty($iaData['guidelines'])) {
            $answer .= '<p><strong>Guidelines :</strong></p><ul>';
            foreach ($iaData['guidelines'] as $guideline) {
                $answer .= '<li>' . $guideline . '</li>';
            }
            $answer .= '</ul>';
        }
        if (!empty($iaData['criteria'])) {
            $answer .= '<p><strong>Critères :</strong></p><ul>';
            foreach ($iaData['criteria'] as $criterion) {
                $answer .= '<li>' . $criterion['criterion'] . ' (' . $criterion['points'] . ' points)</li>';
            }
            $answer .= '</ul>';
        }
        if (!empty($iaData['wordCount'])) {
            $answer .= '<p><strong>Nombre de mots :</strong> ' . ($iaData['wordCount']['min'] ?? 0) . ' - ' . ($iaData['wordCount']['max'] ?? 'illimité') . '</p>';
        }
        return $this->convertEssay([
            'question' => $iaData['question'] ?? '',
            'placeholderText' => $iaData['placeholderText'] ?? 'Votre réponse...',
            'answer' => $answer
        ]);
    }

    private function convertOrdering(array $iaData): array
    {
        $items = [];
        foreach ($iaData['items'] ?? [] as $item) {
            $items[] = $item['text'] . ' (position ' . $item['position'] . ')';
        }
        return $this->convertSortParagraphs([
            'taskDescription' => $iaData['instruction'] ?? 'Remettez les éléments dans l\'ordre',
            'paragraphs' => $items
        ]);
    }
} 

