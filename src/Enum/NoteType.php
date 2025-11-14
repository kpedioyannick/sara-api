<?php

namespace App\Enum;

enum NoteType: string
{
    case DRAFT = 'draft';
    case ASSESSMENT = 'assessment';
    case REMARK = 'remark';
    case VIGILANCE = 'vigilance';

    public function getLabel(): string
    {
        return match($this) {
            self::DRAFT => 'Brouillon',
            self::ASSESSMENT => 'Bilan',
            self::REMARK => 'Remarque',
            self::VIGILANCE => 'Vigilance',
        };
    }

    public function getColor(): string
    {
        return match($this) {
            self::DRAFT => 'gray',
            self::ASSESSMENT => 'blue',
            self::REMARK => 'green',
            self::VIGILANCE => 'red',
        };
    }
}

