<?php

namespace App\Message;

class GeneratePathMessage
{
    public function __construct(
        private readonly int $pathId,
        private readonly array $modules,
        private readonly ?array $chapterPrompts = null,
        private readonly ?array $subChapterPrompts = null
    ) {
    }

    public function getPathId(): int
    {
        return $this->pathId;
    }

    public function getModules(): array
    {
        return $this->modules;
    }

    public function getChapterPrompts(): ?array
    {
        return $this->chapterPrompts;
    }

    public function getSubChapterPrompts(): ?array
    {
        return $this->subChapterPrompts;
    }
}

