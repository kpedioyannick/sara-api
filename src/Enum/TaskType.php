<?php

namespace App\Enum;

enum TaskType: string
{
    case TASK = 'task';
    case ACTIVITY_TASK = 'activity_task';
    case SCHOOL_ACTIVITY_TASK = 'school_activity_task';
    case WORKSHOP = 'workshop';
    case ASSESSMENT = 'assessment';
    case INDIVIDUAL_WORK = 'individual_work';
    case INDIVIDUAL_WORK_REMOTE = 'individual_work_remote';
    case INDIVIDUAL_WORK_ON_SITE = 'individual_work_on_site';


    public function getLabel(): string
    {
        return match($this) {
            self::TASK => 'Tâche',
            self::ACTIVITY_TASK => 'Tâche activité',
            self::SCHOOL_ACTIVITY_TASK => 'Tâche activité scolaire',
            self::WORKSHOP => 'Atelier',
            self::ASSESSMENT => 'Bilan',
            self::INDIVIDUAL_WORK => 'Travail individuel',
            self::INDIVIDUAL_WORK_REMOTE => 'Travail individuel à distance',
            self::INDIVIDUAL_WORK_ON_SITE => 'Travail individuel dans un lieu',
        };
    }

    public static function getChoices(): array
    {
        return [
            self::TASK->value => self::TASK->getLabel(),
            self::ACTIVITY_TASK->value => self::ACTIVITY_TASK->getLabel(),
            self::SCHOOL_ACTIVITY_TASK->value => self::SCHOOL_ACTIVITY_TASK->getLabel(),
            self::WORKSHOP->value => self::WORKSHOP->getLabel(),
            self::ASSESSMENT->value => self::ASSESSMENT->getLabel(),
            self::INDIVIDUAL_WORK->value => self::INDIVIDUAL_WORK->getLabel(),
            self::INDIVIDUAL_WORK_REMOTE->value => self::INDIVIDUAL_WORK_REMOTE->getLabel(),
            self::INDIVIDUAL_WORK_ON_SITE->value => self::INDIVIDUAL_WORK_ON_SITE->getLabel(),
        ];
    }
}

