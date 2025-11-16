<?php

namespace App\Enum;

use App\Enum\ModuleType;


enum ModuleType: string
{

    
    case SPEAK_THE_WORDS_SET = 'SpeakTheWordsSet';
    case ACCORDION = 'Accordion';
    case DIALOG_CARDS = 'DialogCards';
    case DRAG_WORDS = 'DragWords';
    case ESSAY = 'Essay';
    case ADVANCED_TEXT = 'AdvancedText';
    case INTERACTIVE_VIDEO = 'InteractiveVideo';
    case MULTI_CHOICE = 'MultiChoice';
    case QUESTION_SET = 'QuestionSet';
    case TRUE_FALSE = 'TrueFalse';
    case CHOOSE_CORRECT_SENTENCE = 'ChooseCorrectSentence';
    case FLASHCARDS = 'Flashcards';
    case SINGLE_CHOICE_SET = 'SingleChoiceSet';
    case BLANKS = 'Blanks';
    case MARK_THE_WORDS = 'MarkTheWords';
    case TIMELINE = 'Timeline';
    case SORT_PARAGRAPHS = 'SortParagraphs';
    case MEMORY_GAME = 'MemoryGame';
    case DICTATION = 'Dictation';
    case IMAGE_SEQUENCING = 'ImageSequencing';
    case COLUMN = 'Column';
    case INTERACTIVE_VIDEO_COURSE = 'INTERACTIVE_VIDEO_COURSE';
    case TEXT = 'Text';
    case COURSE_PRESENTATION = 'CoursePresentation';
    case INTERACTIVE_BOOK = 'InteractiveBook';
    
    // Nouveaux modules non-H5P
    case MATCHING_PAIRS = 'matching_pairs'; // DRAG_WORDS 
    case CATEGORIZATION = 'categorization'; // DRAG_WORDS
    case CORRESPONDENCE_GRID = 'correspondence_grid'; // DRAG_WORDS
    case READING = 'reading'; // ESSAY
    case REORDERING = 'reordering'; // SORT_PARAGRAPHS
    case SCALE_SORTING = 'scale_sorting'; // SORT_PARAGRAPHS
    case TABLE_COMPLETION = 'table_completion'; // BLANKS or DRAG_WORDS
    case TRANSLATION = 'translation'; // BLANKS
    case SENTENCE_CORRECTION = 'sentence_correction'; // ESSAY OR MARK_THE_WORDS
    case ORAL_QUESTION = 'oral_question'; // ESSAY OR BLANKS
    case CREATIVE_WRITING = 'creative_writing'; // ESSAY
    case TEXT_ANALYSIS = 'text_analysis'; // ESSAY
    case VOCABULARY_DEFINITION = 'vocabulary_definition'; // ESSAY
    case SPEED_READING = 'speed_reading'; 
    case SENTENCE_SELECTION = 'sentence_selection'; // CHOOSE_CORRECT_SENTENCE
    case SHORT_ANSWER = 'short_answer';
    case OPEN_QUESTION = 'open_question';
    case ORDERING = 'ordering';
    case COURSE = 'course';
    case HOMEWORK = 'homework';

    /**
     * Trouve un type similaire quand le type exact n'existe pas
     * Mappe les types non-H5P vers des types H5P existants
     */
    public static function ModuleType(string $type): ?self
    {
        $typeMapping = [
            'multiple_choice' => self::MULTI_CHOICE,
            'true_false' => self::TRUE_FALSE,
            'fill_in_the_blank' => self::BLANKS,
            'short_answer' => self::SHORT_ANSWER,
            'open_question' => self::OPEN_QUESTION,
            'flashcards' => self::FLASHCARDS,
        ];

        $exactMatch = self::tryFrom($type);
        if ($exactMatch) {
            return $exactMatch;
        }

        if (isset($typeMapping[$type])) {
            return $typeMapping[$type];
        }

        return self::TEXT;
    }


    const MEDIA_CONFIG =  [
        "imageToText"   => "mots pour retrouver l'image sur unsplash ou null",
        "audioToText"   => "podcast",
    ];

    public function getLibrary(): string
    {
        return match($this) {
            self::ACCORDION => 'H5P.Accordion 1.0',
            self::DIALOG_CARDS => 'H5P.Dialogcards 1.9',
            self::DRAG_WORDS => 'H5P.DragText 1.10',
            self::ESSAY => 'H5P.Essay 1.5',
            self::ADVANCED_TEXT => 'H5P.AdvancedText 1.1',
            self::INTERACTIVE_VIDEO => 'H5P.InteractiveVideo 1.27',
            self::MULTI_CHOICE => 'H5P.MultiChoice 1.16',
            self::QUESTION_SET => 'H5P.QuestionSet 1.20',
            self::TRUE_FALSE => 'H5P.TrueFalse 1.8',
            self::COLUMN => 'H5P.Column 1.18',
            self::CHOOSE_CORRECT_SENTENCE => 'H5P.Summary 1.10',
            self::FLASHCARDS => 'H5P.Flashcards 1.5',
            self::SPEAK_THE_WORDS_SET => 'H5P.SpeakTheWordsSet 1.3',
            self::SINGLE_CHOICE_SET => 'H5P.SingleChoiceSet 1.11',
            self::BLANKS => 'H5P.Blanks 1.14',
            self::MARK_THE_WORDS => 'H5P.MarkTheWords 1.11',
            self::TIMELINE => 'H5P.Timeline 1.1',
            self::SORT_PARAGRAPHS => 'H5P.SortParagraphs 0.11',
            self::MEMORY_GAME => 'H5P.MemoryGame 1.0',
            self::DICTATION => 'H5P.Dictation 1.0',
            self::IMAGE_SEQUENCING => 'H5P.ImageSequencing 1.0',
            self::COURSE_PRESENTATION => 'H5P.CoursePresentation 1.24',
            self::INTERACTIVE_BOOK => 'H5P.InteractiveBook 1.4',

            // Nouveaux modules non-H5P mappés vers leurs modules H5P correspondants
            self::MATCHING_PAIRS => self::DRAG_WORDS->getLibrary(),
            self::CATEGORIZATION => self::DRAG_WORDS->getLibrary(),
            self::CORRESPONDENCE_GRID => self::DRAG_WORDS->getLibrary(),
            self::READING => self::ESSAY->getLibrary(),
            self::REORDERING => self::DRAG_WORDS->getLibrary(),
            self::SCALE_SORTING => self::DRAG_WORDS->getLibrary(),
            self::TABLE_COMPLETION => self::BLANKS->getLibrary(),
            self::TRANSLATION => self::BLANKS->getLibrary(),
            self::SENTENCE_CORRECTION => self::ESSAY->getLibrary(),
            self::ORAL_QUESTION => self::ESSAY->getLibrary(),
            self::CREATIVE_WRITING => self::ESSAY->getLibrary(),
            self::TEXT_ANALYSIS => self::BLANKS->getLibrary(),
            self::VOCABULARY_DEFINITION => self::BLANKS->getLibrary(),
            self::SPEED_READING => self::ESSAY->getLibrary(),
            self::SENTENCE_SELECTION => self::CHOOSE_CORRECT_SENTENCE->getLibrary(),
            self::SHORT_ANSWER => self::ESSAY->getLibrary(),
            self::OPEN_QUESTION => self::ESSAY->getLibrary(),
            self::ORDERING => self::DRAG_WORDS->getLibrary(),
            self::COURSE => self::ADVANCED_TEXT->getLibrary()
        };
    }


    public  function getExpectedInputs(): array
    {
        return match($this) {
            self::BLANKS => [
                'goal' => 'Exercices à trous pour valider la compréhension et la mémorisation',
                'instructions' => "Mettez les réponses entre *astérisques*.\nUtilisez / pour les réponses alternatives.\nAjoutez des indices après : si nécessaire.",
                'systemMessage' => "Génère uniquement des exercices à trous. Les mots à deviner doivent être encadrés par des astérisques (*mot*).",
                'outputFormat' => [[
                    'instruction' => "Consigne claire et précise pour guider l'élève",
                    'text' => [
                        'text', 'texte_avec_mots_entourés_par_astérisques'
                    ]

                ]]
            ],
            self::MULTI_CHOICE => [
                'goal' => 'Question à choix multiples',
                'systemMessage' => "Crée des QCM ",
                'outputFormat' => [[
                    'question' => 'La question peut contenir des tags HTML',
                    'answers' => [
                        [
                            'text' => 'string',
                            'correct' => 'boolean',
                            'tipsAndFeedback' => [
                                'chosenFeedback' => 'Le texte  peut contenir des tags HTML'
                            ]
                        ]
                    ]
                ]]
            ],

            self::DRAG_WORDS => [
                'goal' => 'Glisser-déposer de mots',
                'instructions' => "Mettez les mots à déplacer entre *astérisques*
                    Ajoutez des indices après : si nécessaire",
                'systemMessage' => "Crée un exercice où il faut placer les réponses aux bons endroits, 
                les mots à déplacer sont entre *astérisques*",
                'outputFormat' => [[
                    'taskDescription' => 'Consigne instruction',
                    'textField' => 'texte_avec_mots_entourés_par_astérisques',
                ]]
            ],

            self::MARK_THE_WORDS => [
                'goal' => 'Marquage de mots',
                'systemMessage' => "Crée un texte avec les éléments à identifier entre astérisques (*). 'taskDescription' et 'textField' peuvent contenir des tags HTML.",
                'outputFormat' => [[
                    'taskDescription' => 'Consigne ou instruction, peut contenir des tags HTML.',
                    'textField' => "texte_avec_mots_entourés_par_astérisques"
                ]]
            ],

            self::TRUE_FALSE => [
                'goal' => 'Question vrai/faux',
                'systemMessage' => "Crée des affirmations vrai/faux pour",
                'outputFormat' => [[
                    'question' => 'Le texte  peut contenir des tags HTML',
                    'correct' => 'boolean',  // "true" or "false"
                ]]
            ],

            self::ESSAY => [
                'goal' => 'Rédaction avec mots-clés à détecter',
                'systemMessage' => "Crée des questions ouvertes",
                'outputFormat' => [
                    [
                        'question' => 'La question peut contenir des tags HTML',
                        'placeholderText' => 'string',
                        'answer' => 'La response peut contenir des tags HTML'
                    ]
                ]
            ],


            self::CHOOSE_CORRECT_SENTENCE => [
                'goal' => 'Créer plusieurs listes de phrases où la première phrase de chaque liste est la bonne réponse',
                'systemMessage' => "Créer plusieurs listes de phrases où la première phrase de chaque liste est la bonne réponse",
                'outputFormat' => [[
                    'sentences' => [
                        [
                            'string',
                            'string',
                            'string',
                            'string',
                        ]
                    ]
                ]]
            ],

            self::MEMORY_GAME => [
                'goal' => 'Jeu de mémoire avec paires de cartes à associer',
                'systemMessage' => "Crée un jeu de mémoire",
                'outputFormat' => [[
                    'cards' => [
                        [
                            ...self::MEDIA_CONFIG,
                            'imageAlt' => 'string',
                            'matchAlt' => 'string',
                        ]
                    ]
                ]]
            ],

            self::SPEAK_THE_WORDS_SET => [
                'goal' => 'Série d\'exercices de prononciation',
                'systemMessage' => "Crée une série d'exercices où l'utilisateur doit répondre à l'oral",
                'outputFormat' => [[
                    'introduction' => [
                        'showIntroPage' => 'boolean',
                        'introductionTitle' => 'string'
                    ],
                    'questions' => [
                        [
                            "imageToText"   => "mots pour retrouver l'image sur unsplash ou null",
                            'question' => 'string',
                            'correctAnswerText' => 'string',
                            'acceptedAnswers' => ['string'],
                            "inputLanguage"=> "fr-FR",
                        ]
                    ],
                ]]
            ],

            self::TIMELINE => [
                'goal' => 'Frise chronologique interactive',
                'systemMessage' => "Crée une frise chronologique avec :
                1. Titre et introduction
                2. Liste d'événements avec :
                   - Date
                   - Titre
                   - Description
                   - Média associé",
                'outputFormat' => [[
                    'timeline' => [
                        'headline' => 'string',
                        'text' => 'string',
                        'defaultZoomLevel' => 'string',
                        'date' => [
                            [
                                'startDate' => 'string',  // YYYY,MM,DD format
                                'endDate' => 'string',    // YYYY,MM,DD format (optional)
                                'headline' => 'string',
                                'text' => 'string',
                                'asset' => []
                            ]
                        ],
                        'language' => 'string'  // Language code (e.g., 'en', 'fr', etc.)
                    ]
                ]]
            ],

            self::FLASHCARDS => [
                'goal' => 'Cartes mémoire avec question/réponse',
                'systemMessage' => "Crée des cartes question/réponse",
                'outputFormat' => [[
                    'cards' => [
                        [
                            'text' => 'string',
                            'answer' => 'string',
                           // "imageToText"   => "mots pour retrouver l'image sur unsplash  ou null",
                            'tip' => 'string'
                        ]
                    ]
                ]]
            ],

            self::INTERACTIVE_VIDEO_COURSE => [
                'goal' => 'Créer une présentation avec des sections, sous-sections et des questions interactives',
                'systemMessage' => 'Crée une fiche de révision claire et précis pertinents structurée',
                'outputFormat' => [[
                            'title' => 'titre du cours',
                            'course' => 'détail de la leçon',
                            'remark'  => 'remarque ou conseil du cours sinon null',
                            'definition' => 'quelques définition sinon null',
                            'rule' => 'des régles à appliquer pour le cours  sinon null',
                            'essential' => "mettre que l'essentiel",
                            'example' => ["des exemples d'applications"],
                            'questions' => [
                                [
                                    'type' => 'multi_choice|true_false|findGoodSentence',
                                    'question' => 'string',
                                    'answers' => [
                                        [
                                            'text' => 'string',
                                            'correct' => 'boolean'
                                        ]
                                    ]
                                ]
                            ]
                    ]
                ]
            ],
            self::INTERACTIVE_VIDEO => [
                'goal' => 'Créer une présentation vidéo interactive avec des sections structurées',
                'systemMessage' => 'Crée une présentation vidéo pédagogique structurée en slides avec des étapes claires',
                'outputFormat' => [[
                    'title' => 'Titre de la présentation',
                    'voiceover' => 'Texte à dire pendant cette étape',
                    'slides' => [
                        [
                            'title' => 'Titre du slide',
                            'type' => 'introduction|concept|exemple|exercice|conclusion',
                            'voiceover' => 'Texte à dire pendant cette étape',
                            'duration' => 'durée en secondes',
                            'steps' => [
                                [
                                    'type' => 'text|latex',
                                    'content' => 'Contenu de l\'étape',
                                    'duration' => 'durée en secondes',
                                    'animation' => 'fade_in|write|transform',
                                    'position' => 'center|left|right|top|bottom',
                                    'voiceover' => 'Texte à dire pendant cette étape'
                                ]
                            ]
                        ]
                    ]
                ]]
            ],

            self::SINGLE_CHOICE_SET => [
                'goal' => 'Série de questions à choix unique',
                'systemMessage' => "Crée des séries de questions test",
                'outputFormat' => [[
                    'choices' => [
                        [
                            'question' => 'Le texte  peut contenir des tags HTML',
                            'answers' => [
                                'answer',
                                'incorrect answer',
                                'incorrect answer',  // First answer is correct, others are incorrect
                            ]
                        ]
                    ]
                ]]
            ],

            self::DIALOG_CARDS => [
                'goal' => 'Cartes de dialogue recto-verso',
                'systemMessage' => "Crée des Cartes de dialogue recto-verso question et réponse",
                'outputFormat' => [[
                    'type' => 'devinette | question/réponse | quiz mémoire | flashcards illustrées',
                    'title' => 'string',
                    'mode' => 'string',  // 'normal' or 'repetition'
                    'description' => 'string',
                    'dialogs' => [
                        [
                            "imageToText"   => "mots pour retrouver l'image sur unsplash  ou null",
                            'text' => 'string',
                            'answer' => 'string',
                            'tips' => [
                                'front' => 'string',
                                'back' => 'string'
                            ]
                        ]
                    ]
                ]]
            ],

            self::DICTATION => [
                'goal' => 'Exercice de dictée audio',
                'systemMessage' => "Crée une dictée avec :
                1. Texte à dicter
                2. Instructions de génération audio
                3. Indices et feedback",
                'outputFormat' => [
                    [
                        'sentences' => [
                            [
                                'text' => 'le texte à dicter'
                            ]
                        ]
                    ]
                ]
            ],

            self::ADVANCED_TEXT => [
                'goal' => 'Texte enrichi avec mise en forme avancée',
                'systemMessage' => "Crée un texte enrichi avec :
                1. Contenu formaté (HTML)
                2. Styles personnalisés",
                'outputFormat' => [[
                    'text' => "Le texte  avec des tags HTML (br, li, etc)",
                ]]
            ],

            self::QUESTION_SET => [
                'goal' => 'Série de questions variées',
                'systemMessage' => "Crée une série d'exercices.
                On peut appeler un même type plusieurs fois. Choisis les types les plus adaptés au besoin pour créer un parcours complet.
                ",
                'outputFormat' => [ [  // Index explicite
                        'questions' => [
                            [
                                'library' => ModuleType::MULTI_CHOICE->getLibrary(),
                                'type' => ModuleType::MULTI_CHOICE->value,
                                'params' => ModuleType::MULTI_CHOICE->getExpectedInputs()['outputFormat'][0]
                            ],
                            [
                                'library' => ModuleType::DRAG_WORDS->getLibrary(),
                                'type' => ModuleType::TRUE_FALSE->value,
                                'params' => ModuleType::TRUE_FALSE->getExpectedInputs()['outputFormat'][0]
                            ],
                            [
                                'library' => ModuleType::BLANKS->getLibrary(),
                                'type' => ModuleType::BLANKS->value,
                                'params' => ModuleType::BLANKS->getExpectedInputs()['outputFormat'][0]
                            ],
                            [
                                'library' => ModuleType::MARK_THE_WORDS->getLibrary(),
                                'type' => ModuleType::MARK_THE_WORDS->value,
                                'params' => ModuleType::MARK_THE_WORDS->getExpectedInputs()['outputFormat'][0]
                            ]
                        ]
                    ]
                ]
            ],

            self::SORT_PARAGRAPHS => [
                'goal' => 'Exercice de remise en ordre de paragraphes',
                'systemMessage' => "Crée des exercices de remise en ordre",
                'outputFormat' => [
                    [
                        'taskDescription' => 'instruction : Le texte  peut contenir des tags HTML',
                        'paragraphs' => [
                             'Les textes pour cahque item doit être en HTML, avec une mise en forme propre et lisible.'
                        ]
                 ]
                ]
            ],

            self::ACCORDION => [
                'goal' => 'Contenu organisé en sections dépliables',
                'systemMessage' => "Crée un accordéon avec :
                1. Liste de panneaux  contenant :
                   - Titre de section
                   - Contenu texte enrichi
                2. Niveau de titre HTML (h2-h4)",
                'outputFormat' => [[
                    'panels' => [
                        [
                            'title' => 'string',
                            'content' => 'Le texte doit être en HTML, avec une mise en forme belle propre et lisible.'
                        ]
                    ],
                    'settings' => [
                        'headingLevel' => 'string (h2|h3|h4)'
                    ]
                ]]
            ],
            self::TEXT => [
                'goal' => 'Texte enrichi avec mise en forme avancée',
                'systemMessage' => "Crée un texte mise en forme et emoji",
                'outputFormat' => 'Text'
            ],
            self::IMAGE_SEQUENCING => [
                'goal' => 'Séquençage d\'images',
                'systemMessage' => "Crée une séquence d'images à ordonner avec :
                1. Description de la tâche
                2. Liste d'images avec descriptions
                3. Options de comportement",
                'outputFormat' => [[
                    'type' => "tri d'images | séquencement visuel | étapes d'un processus",
                    'taskDescription' => 'Consigne ou instruction',
                    'altTaskDescription' => 'string',
                    'sequenceImages' => [
                        [
                            'imageDescription' => 'string',
                            "imageToText"   => "mots pour retrouver l'image sur unsplash  ou null",
                        ],
                        'behaviour' => [
                            'enableSolution' => true,
                            'enableRetry' => true,
                            'enableResume' => true
                        ]
                    ]
                ]]
            ],

             self::COURSE_PRESENTATION => [
                'goal' => 'Créer une présentation de cours interactive avec des slides et des éléments variés',
                'systemMessage' => '
                    Crée un parcours de révision pour un élève selon ce besoin.
                     On peut appeler un même type plusieurs fois. Choisis les types les plus adaptés au besoin pour créer un parcours complet : introduction, prérequis, exercices, conclusion, etc..
                     Retourne uniquement un tableau JSON.',
                    'outputFormat' => [ [  // Index explicite
                        'slides' => [
                            [
                                'library' => ModuleType::MULTI_CHOICE->getLibrary(),
                                'type' => ModuleType::MULTI_CHOICE->value,
                                'params' => ModuleType::MULTI_CHOICE->getExpectedInputs()['outputFormat'][0]
                            ],
                            [
                                'library' => ModuleType::DRAG_WORDS->getLibrary(),
                                'type' => ModuleType::TRUE_FALSE->value,
                                'params' => ModuleType::TRUE_FALSE->getExpectedInputs()['outputFormat'][0]
                            ],
                            [
                                'library' => ModuleType::BLANKS->getLibrary(),
                                'type' => ModuleType::BLANKS->value,
                                'params' => ModuleType::BLANKS->getExpectedInputs()['outputFormat'][0]
                            ],
                            [
                                'library' => ModuleType::MARK_THE_WORDS->getLibrary(),
                                'type' => ModuleType::MARK_THE_WORDS->value,
                                'params' => ModuleType::MARK_THE_WORDS->getExpectedInputs()['outputFormat'][0]
                            ],
                            [
                                'library' => ModuleType::SINGLE_CHOICE_SET->getLibrary(),
                                'type' => ModuleType::SINGLE_CHOICE_SET->value,
                                'params' => ModuleType::SINGLE_CHOICE_SET->getExpectedInputs()['outputFormat'][0]
                            ],
                            [
                                'library' => ModuleType::DIALOG_CARDS->getLibrary(),
                                'type' => ModuleType::DIALOG_CARDS->value,
                                'params' => ModuleType::DIALOG_CARDS->getExpectedInputs()['outputFormat'][0]
                            ],
                            [
                                'library' => ModuleType::CHOOSE_CORRECT_SENTENCE->getLibrary(),
                                'type' => ModuleType::CHOOSE_CORRECT_SENTENCE->value,
                                'params' => ModuleType::CHOOSE_CORRECT_SENTENCE->getExpectedInputs()['outputFormat'][0]
                            ],
                            [
                                'library' => ModuleType::ADVANCED_TEXT->getLibrary(),     
                                'type' => ModuleType::ADVANCED_TEXT->value,
                                'params' => ModuleType::ADVANCED_TEXT->getExpectedInputs()['outputFormat'][0]
                            ]
                        ]
                    ]
                ]
            ],
            self::INTERACTIVE_BOOK => [
                'goal' => 'Créer un livre interactif avec des chapitres et des éléments variés',
                'systemMessage' => '
                    Crée un livre interactif structuré avec des chapitres contenant différents types de contenu.
                    Chaque chapitre peut contenir des éléments interactifs variés.
                    Retourne uniquement un tableau JSON.',
                'outputFormat' => [[
                    'showCoverPage' => 'boolean',
                    'bookCover' => [
                        'coverDescription' => 'string (HTML)',
                    ],
                    'chapters' => [
                        [
                                'library' => ModuleType::COLUMN->getLibrary(),
                                'type' => ModuleType::COLUMN->value,
                                'params' => [
                                    'content' => [
                                        [
                                            'library' => ModuleType::MULTI_CHOICE->getLibrary(),
                                            'type' => ModuleType::MULTI_CHOICE->value,
                                            'params' => ModuleType::MULTI_CHOICE->getExpectedInputs()['outputFormat'][0]
                                        ],
                                        [
                                            'library' => ModuleType::DRAG_WORDS->getLibrary(),
                                            'type' => ModuleType::DRAG_WORDS->value,
                                            'params' => ModuleType::DRAG_WORDS->getExpectedInputs()['outputFormat'][0]
                                        ],
                                        [
                                            'library' => ModuleType::BLANKS->getLibrary(),
                                            'type' => ModuleType::BLANKS->value,
                                            'params' => ModuleType::BLANKS->getExpectedInputs()['outputFormat'][0]
                                        ],
                                        [
                                            'library' => ModuleType::MARK_THE_WORDS->getLibrary(),
                                            'type' => ModuleType::MARK_THE_WORDS->value,
                                            'params' => ModuleType::MARK_THE_WORDS->getExpectedInputs()['outputFormat'][0]
                                        ],
                                        [
                                            'library' => ModuleType::SINGLE_CHOICE_SET->getLibrary(),
                                            'type' => ModuleType::SINGLE_CHOICE_SET->value,
                                            'params' => ModuleType::SINGLE_CHOICE_SET->getExpectedInputs()['outputFormat'][0]
                                        ],
                                        [
                                            'library' => ModuleType::DIALOG_CARDS->getLibrary(),
                                            'type' => ModuleType::DIALOG_CARDS->value,
                                            'params' => ModuleType::DIALOG_CARDS->getExpectedInputs()['outputFormat'][0]
                                        ],
                                        [
                                            'library' => ModuleType::CHOOSE_CORRECT_SENTENCE->getLibrary(),
                                            'type' => ModuleType::CHOOSE_CORRECT_SENTENCE->value,
                                            'params' => ModuleType::CHOOSE_CORRECT_SENTENCE->getExpectedInputs()['outputFormat'][0]
                                        ],
                                        [
                                            'library' => ModuleType::ADVANCED_TEXT->getLibrary(),
                                            'type' => ModuleType::ADVANCED_TEXT->value,
                                            'params' => ModuleType::ADVANCED_TEXT->getExpectedInputs()['outputFormat'][0]
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ],
                    'behaviour' => [
                        'baseColor' => 'string (hex color)',
                        'defaultTableOfContents' => 'boolean',
                        'progressIndicators' => 'boolean',
                        'progressAuto' => 'boolean',
                        'displaySummary' => 'boolean',
                        'enableRetry' => 'boolean'
                    ]
                ]
            ],

            // Nouveaux modules non-H5P
            self::MATCHING_PAIRS => [
                'goal' => 'Associer des éléments par paires',
                'systemMessage' => "Crée un exercice d'association par paires avec des éléments à faire correspondre",
                'outputFormat' => [[
                    'instruction' => 'Consigne pour l\'exercice',
                    'pairs' => [
                        [
                            'left' => 'string',
                            'right' => 'string'
                        ]
                    ]
                ]]
            ],

            self::CATEGORIZATION => [
                'goal' => 'Regrouper des éléments dans des catégories',
                'systemMessage' => "Crée un exercice de catégorisation avec des éléments à classer",
                'outputFormat' => [[
                    'instruction' => 'Consigne pour l\'exercice',
                    'categories' => [
                        [
                            'name' => 'string',
                            'description' => 'string',
                            'items' => ['string']
                        ]
                    ]
                ]]
            ],

            self::CORRESPONDENCE_GRID => [
                'goal' => 'Grille de correspondance entre éléments',
                'systemMessage' => "Crée une grille de correspondance avec des éléments à faire correspondre.",
                'outputFormat' => [[
                    'instruction' => 'Consigne pour l\'exercice',
                    'columns' => [[
                        'label' => 'string',   // nom de la colonne (ex : "Nom")
                        'rows' => [[
                            'row' => 'string',         // mot ou élément à classer (ex : "Beauté")
                            'word_to_found' => "boolean ('true ou false si distracteur ou déjà placé')"    // ou false si distracteur ou déjà placé
                        ]]
                    ]]
                ]]
            ],

            self::READING => [
                'goal' => 'Exercice de lecture et compréhension',
                'systemMessage' => "Crée un exercice de lecture avec questions de compréhension",
                'outputFormat' => [[
                    'text' => 'Texte à lire',
                    'questions' => [
                        [
                            'question' => 'string',
                            'type' => 'multiple_choice|true_false|open_ended',
                            'answers' => [
                                [
                                    'text' => 'string',
                                    'correct' => 'boolean'
                                ]
                            ]
                        ]
                    ]
                ]]
            ],

            self::REORDERING => [
                'goal' => 'Remettre des éléments dans l\'ordre',
                'systemMessage' => "Crée un exercice de remise en ordre d'éléments. Les éléments doivent être fournis dans le bon ordre par défaut.",
                'outputFormat' => [[
                    'instruction' => 'Consigne pour l\'exercice',
                    'items' => ['string'] // liste ordonnée
                ]]
            ],

            self::SCALE_SORTING => [
                'goal' => 'Classement sur une échelle',
                'systemMessage' => "Crée un exercice de classement sur une échelle",
                'outputFormat' => [[
                    'instruction' => 'Consigne pour l\'exercice',
                    'scale' => [
                        'min' => 'string',
                        'max' => 'string'
                    ],
                    'items' => [
                        [
                            'text' => 'string',
                            'position' => 'int'
                        ]
                    ]
                ]]
            ],

            self::TABLE_COMPLETION => [
                'goal' => 'Compléter un tableau',
                'systemMessage' => "Crée un exercice de complétion de tableau",
                'outputFormat' => [[
                    'instruction' => 'Consigne pour l\'exercice',
                    'headers' => ['string'],
                    'rows' => [
                        [
                            'cells' => ['string'],
                            'positions_items_to_blanks' => ['int']
                        ]
                    ]
                ]]
            ],

            self::TRANSLATION => [
                'goal' => 'Exercice de traduction',
                'systemMessage' => "Crée un exercice de traduction",
                'outputFormat' => [[
                    'instruction' => 'Consigne pour l\'exercice',
                    'sourceLanguage' => 'string',
                    'targetLanguage' => 'string',
                    'sentences' => [
                        [
                            'source' => 'string',
                            'target' => 'string',
                            'alternatives' => ['string']
                        ]
                    ]
                ]]
            ],

            self::SENTENCE_CORRECTION => [
                'goal' => 'Corriger une phrase',
                'systemMessage' => "Crée un exercice avec un texte dans lequel certains mots sont incorrects et doivent être corrigés par l'utilisateur. 
            Les mots incorrects doivent être entourés par des astérisques `*` et suivis du mot correct séparé par une barre verticale `|`, puis d'un astérisque fermant. 
            Exemple : \"J'ai une *bales|balle* dans mon sac.\". 
            Ajoute également une explication claire pour chaque correction.",
                'outputFormat' => [[
                    'instruction' => 'Consigne pour l\'exercice',
                    'sentences' => [
                        [
                            'text' => 'string',
                            'explanation' => 'string'
                        ]
                    ]
                ]]
            ],

            self::ORAL_QUESTION => [
                'goal' => 'Question orale',
                'systemMessage' => "Crée des questions orales simples et courtes. La réponse principale (`expectedAnswer`) doit être écrite entièrement en lettres (ex. : « quarante-deux »). Le champ `acceptedAnswers` peut contenir des variantes acceptables, y compris des versions numériques (ex. : « 42 ») ou orthographiques.",
                'outputFormat' => [[
                    'question' => 'string',
                    'expectedAnswer' => 'string',
                    'acceptedAnswers' => ['string'],
                    'language' => 'string'
                ]]
            ],

            self::CREATIVE_WRITING => [
                'goal' => 'Production écrite libre',
                'systemMessage' => "Crée un exercice de production écrite créative",
                'outputFormat' => [[
                    'prompt' => 'string',
                    'constraints' => ['string'],
                    'wordCount' => [
                        'min' => 'int',
                        'max' => 'int'
                    ],
                    'criteria' => ['string']
                ]]
            ],

            self::TEXT_ANALYSIS => [
                'goal' => 'Analyse de texte',
                'systemMessage' => "Crée un exercice d'analyse de texte",
                'outputFormat' => [[
                    'text' => 'string',
                    'questions' => [
                        [
                            'question' => 'string',
                            'type' => 'analysis|interpretation|evaluation',
                            'guidelines' => ['string']
                        ]
                    ]
                ]]
            ],

            self::VOCABULARY_DEFINITION => [
                'goal' => 'Définition de vocabulaire',
                'systemMessage' => "Crée un exercice de définition de vocabulaire",
                'outputFormat' => [[
                    'instruction' => 'Consigne pour l\'exercice',
                    'words' => [
                        [
                            'word' => 'string',
                            'definition' => 'string',
                            'synonyms' => ['string']
                        ]
                    ]
                ]]
            ],

            self::SPEED_READING => [
                'goal' => 'Lecture rapide',
                'systemMessage' => "Crée un exercice de lecture rapide",
                'outputFormat' => [[
                    'text' => 'string',
                    'timeLimit' => 'int',
                    'questions' => [
                        [
                            'question' => 'string',
                            'answers' => [
                                [
                                    'text' => 'string',
                                    'correct' => 'boolean'
                                ]
                            ]
                        ]
                    ]
                ]]
            ],

            self::SENTENCE_SELECTION => [
                'goal' => 'Sélection de la bonne phrase',
                'systemMessage' => "Crée un exercice de sélection une expression correct et des propositions incorrects mais similaires",
                'outputFormat' => [[
                    'instruction' => 'Consigne pour l\'exercice',
                    'sentences' => [
                        [
                            'correct' => 'string',
                            'incorrect' => ['string']
                        ]
                    ]
                ]]
            ],

            self::SHORT_ANSWER => [
                'goal' => 'Réponse courte',
                'systemMessage' => "Crée un exercice de réponse courte",
                'outputFormat' => [[
                    'question' => 'string',
                    'expectedAnswer' => 'string',
                    'acceptedAnswers' => ['string'],
                    'maxLength' => 'int'
                ]]
            ],

            self::OPEN_QUESTION => [
                'goal' => 'Question ouverte',
                'systemMessage' => "Crée une question ouverte avec des critères d'évaluation",

                'outputFormat' => [[
                    'question' => 'string',
                    'expectedAnswer' =>  'string',
                    'guidelines' => ['string'],
                    'wordCount' => [
                        'min' => 'int',
                        'max' => 'int'
                    ]
                ]]
            ],

            self::ORDERING => [
                'goal' => 'Classer dans l\'ordre',
                'systemMessage' => "Crée un exercice de classement dans l'ordre",
                'outputFormat' => [[
                    'instruction' => 'Consigne pour l\'exercice',
                    'items' => [
                        [
                            'text' => 'string',
                            'position' => 'int',
                            'explanation' => 'string'
                        ]
                    ]
                ]]
            ],

            self::HOMEWORK => [
                'goal' => 'Aide aux devoirs',
                'systemMessage' => "
            Tu es un assistant pédagogique expert en aide aux devoirs pour les élèves. 
            Ton objectif est d’analyser un devoir et de structurer les exercices comme suit :
            
            - Sépare les contenus **exercice par exercice**.
            - Pour chaque exercice, décompose **question par question**.
            - Pour chaque **question** :
                - Fournis une **reformulation** claire et pédagogique.
                - Donne un **indice** sous forme de **texte simple**, compréhensible à l’oral (langage naturel uniquement).
                - Ajoute un **rappel de cours** (texte court ou schéma, au format texte, HTML, Markdown, Mermaid ou LaTeX).
                - Donne une **réponse étape par étape** avec une explication pour chaque étape.
                - Ajoute un **temps estimé en secondes** pour répondre à la question (`timer`).
            
            Le format attendu est du JSON et est structuré comme suit :",
                
                'outputFormat' => [
                    'exercices' => [
                        [
                            'title' => 'Titre de l’exercice',
                            'statment' => 'Énoncé complet de l’exercice',
                            'questions' => [
                                [
                                    'timer' => 'Temps estimé en secondes pour répondre à la question',
                                    'reformulation_question' => 'Reformulation claire et pédagogique de la question',
                                    'course' => [
                                        'type' => 'text|html|markdown|mermaid|latex',
                                        'content' => 'Petit rappel de cours ou aide contextuelle'
                                    ],
                                    'indice' => [
                                        'type' => 'text',
                                        'content' => 'Indice formulé en langage naturel uniquement (oral possible)'
                                    ],
                                    'answer_step_by_step' => [
                                        [
                                            'type' => 'text|html|markdown|mermaid|latex',
                                            'content' => 'Description détaillée d’une étape de la résolution'
                                        ],
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            

            default => throw new \RuntimeException(sprintf(
                'Unhandled match case of type2 %s: %s',
                self::class,
                $this->value
            )),

        };
    }



    public function getSlot(): string
    {
        return match($this) {
            self::SPEAK_THE_WORDS_SET => 'h5p-speak-the-words-set',
            self::COLUMN => 'h5p-column',
            default => throw new \InvalidArgumentException("Type de module sans slot défini: {$this->value}")
        };
    }

    /**
     * Vérifie si le module doit être affiché dans une colonne H5P
     * 
     * @return bool
     */
    public function isInColumnH5P(): bool 
    {
        return in_array($this, [
            self::ACCORDION,
            self::MULTI_CHOICE,
            self::TRUE_FALSE,
            self::DRAG_WORDS,
            self::QUESTION_SET,
            self::ESSAY,
            self::CHOOSE_CORRECT_SENTENCE,
            self::SINGLE_CHOICE_SET,
            self::BLANKS,
            self::DIALOG_CARDS,
            //self::SORT_PARAGRAPHS,
            self::ADVANCED_TEXT,
            self::INTERACTIVE_VIDEO,
            self::MARK_THE_WORDS,
            self::COURSE,
            //ModuleType::MEMORY_GAME,
            //ModuleType::TIMELINE,

            self::MATCHING_PAIRS,
            self::CATEGORIZATION,
            self::CORRESPONDENCE_GRID,
            self::READING,
            self::REORDERING,
            self::SCALE_SORTING,
            self::TABLE_COMPLETION,
            self::TRANSLATION,
            self::SENTENCE_CORRECTION,
            self::ORAL_QUESTION,
            self::CREATIVE_WRITING,
            self::TEXT_ANALYSIS,
            self::VOCABULARY_DEFINITION,
            self::SPEED_READING,
            self::SENTENCE_SELECTION,
            self::SHORT_ANSWER,
            self::OPEN_QUESTION,
            self::ORDERING
        ]);
    }

    public function getLabel(): string
    {
        return match($this) {
            self::SPEAK_THE_WORDS_SET => 'Parler les mots',
            self::ACCORDION => 'Accordéon',
            self::DIALOG_CARDS => 'Cartes de dialogue',
            self::DRAG_WORDS => 'Glisser-déposer les mots',
            self::ESSAY => 'Dissertation',
            self::ADVANCED_TEXT => 'Texte avancé',
            self::INTERACTIVE_VIDEO => 'Vidéo interactive',
            self::MULTI_CHOICE => 'Choix multiples',
            self::QUESTION_SET => 'Ensemble de questions',
            self::TRUE_FALSE => 'Vrai/Faux',
            self::CHOOSE_CORRECT_SENTENCE => 'Choisir la bonne phrase',
            self::FLASHCARDS => 'Cartes mémoire',
            self::SINGLE_CHOICE_SET => 'Choix unique',
            self::BLANKS => 'Remplir les blancs',
            self::MARK_THE_WORDS => 'Marquer les mots',
            self::TIMELINE => 'Chronologie',
            self::SORT_PARAGRAPHS => 'Trier les paragraphes',
            self::MEMORY_GAME => 'Jeu de mémoire',
            self::DICTATION => 'Dictée',
            self::IMAGE_SEQUENCING => 'Séquencement d\'images',
            self::COLUMN => 'Colonne',
            self::INTERACTIVE_VIDEO_COURSE => 'Cours vidéo interactif',
            self::TEXT => 'Texte',
            self::COURSE_PRESENTATION => 'Présentation de cours',
            self::INTERACTIVE_BOOK => 'Livre interactif',
            self::MATCHING_PAIRS => 'Associer les paires',
            self::CATEGORIZATION => 'Catégorisation',
            self::CORRESPONDENCE_GRID => 'Grille de correspondance',
            self::READING => 'Lecture',
            self::REORDERING => 'Réordonner',
            self::SCALE_SORTING => 'Tri par échelle',
            self::TABLE_COMPLETION => 'Complétion de tableau',
            self::TRANSLATION => 'Traduction',
            self::SENTENCE_CORRECTION => 'Correction de phrase',
            self::ORAL_QUESTION => 'Question orale',
            self::CREATIVE_WRITING => 'Écriture créative',
            self::TEXT_ANALYSIS => 'Analyse de texte',
            self::VOCABULARY_DEFINITION => 'Définition de vocabulaire',
            self::SPEED_READING => 'Lecture rapide',
            self::SENTENCE_SELECTION => 'Sélection de phrase',
            self::SHORT_ANSWER => 'Réponse courte',
            self::OPEN_QUESTION => 'Question ouverte',
            self::ORDERING => 'Classement',
            self::COURSE => 'Cours',
            self::HOMEWORK => 'Aide aux devoirs',
        };
    }

}
