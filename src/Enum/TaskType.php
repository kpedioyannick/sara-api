<?php

namespace App\Enum;

enum TaskType: string
{
    case TASK = 'task';
    case ACTIVITY_TASK = 'activity_task';
    case SCHOOL_ACTIVITY_TASK = 'school_activity_task';

    public function getLabel(): string
    {
        return match($this) {
            self::TASK => 'Tâche',
            self::ACTIVITY_TASK => 'Tâche activité',
            self::SCHOOL_ACTIVITY_TASK => 'Tâche activité scolaire',
        };
    }

    public static function getChoices(): array
    {
        return [
            self::TASK->value => self::TASK->getLabel(),
            self::ACTIVITY_TASK->value => self::ACTIVITY_TASK->getLabel(),
            self::SCHOOL_ACTIVITY_TASK->value => self::SCHOOL_ACTIVITY_TASK->getLabel(),
        ];
    }
}

