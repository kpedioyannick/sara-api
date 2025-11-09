<?php

namespace App\Service\Path;

use App\Enum\ModuleType;

/**
 * Service pour mapper les types de prompts (depuis Chapter/SubChapter) vers ModuleType
 */
class PromptTypeMappingService
{
    /**
     * Mapping entre les types de prompts et les ModuleType
     */
    private const PROMPT_TO_MODULE_TYPE_MAP = [
        'multiple_choice' => ModuleType::MULTI_CHOICE,
        'true_false' => ModuleType::TRUE_FALSE,
        'fill_in_the_blank' => ModuleType::BLANKS,
        'short_answer' => ModuleType::SHORT_ANSWER,
        'open_question' => ModuleType::OPEN_QUESTION,
        'matching_pairs' => ModuleType::MATCHING_PAIRS,
        'categorization' => ModuleType::CATEGORIZATION,
        'correspondence_grid' => ModuleType::CORRESPONDENCE_GRID,
        'table_completion' => ModuleType::TABLE_COMPLETION,
        'translation' => ModuleType::TRANSLATION,
        'sentence_correction' => ModuleType::SENTENCE_CORRECTION,
        'oral_question' => ModuleType::ORAL_QUESTION,
        'creative_writing' => ModuleType::CREATIVE_WRITING,
        'text_analysis' => ModuleType::TEXT_ANALYSIS,
        'vocabulary_definition' => ModuleType::VOCABULARY_DEFINITION,
        'speed_reading' => ModuleType::SPEED_READING,
        'sentence_selection' => ModuleType::SENTENCE_SELECTION,
        'ordering' => ModuleType::ORDERING,
        'reordering' => ModuleType::REORDERING,
        'scale_sorting' => ModuleType::SCALE_SORTING,
    ];

    /**
     * Convertit un type de prompt en ModuleType
     */
    public function mapPromptTypeToModuleType(string $promptType): ?ModuleType
    {
        return self::PROMPT_TO_MODULE_TYPE_MAP[$promptType] ?? null;
    }

    /**
     * Vérifie si un type de prompt est mappable
     */
    public function isMappable(string $promptType): bool
    {
        return isset(self::PROMPT_TO_MODULE_TYPE_MAP[$promptType]);
    }

    /**
     * Retourne tous les types de prompts mappables
     */
    public function getMappableTypes(): array
    {
        return array_keys(self::PROMPT_TO_MODULE_TYPE_MAP);
    }

    /**
     * Filtre les prompts pour ne garder que ceux qui sont mappables
     */
    public function filterMappablePrompts(array $prompts): array
    {
        return array_filter($prompts, function($prompt) {
            if (!is_array($prompt) || !isset($prompt['type'])) {
                return false;
            }
            return $this->isMappable($prompt['type']);
        });
    }
}

