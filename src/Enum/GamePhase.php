<?php

declare(strict_types=1);

namespace App\Enum;

enum GamePhase: string
{
    case PROLOGUE           = 'prologue';
    case CHAPTER_1          = 'chapter_1';
    case CHAPTER_2          = 'chapter_2';
    case CHAPTER_3          = 'chapter_3';
    case EPILOGUE_ACTION_1  = 'epilogue_action_1';
    case EPILOGUE_ACTION_2  = 'epilogue_action_2';
    case EPILOGUE_ACTION_3  = 'epilogue_action_3';
    case EPILOGUE_FINAL     = 'epilogue_final';
    case COMPLETED          = 'completed';

    public function isChapter(): bool
    {
        return in_array($this, [self::CHAPTER_1, self::CHAPTER_2, self::CHAPTER_3], true);
    }

    public function isEpilogueAction(): bool
    {
        return in_array($this, [self::EPILOGUE_ACTION_1, self::EPILOGUE_ACTION_2, self::EPILOGUE_ACTION_3], true);
    }

    public function next(): self
    {
        return match ($this) {
            self::PROLOGUE          => self::CHAPTER_1,
            self::CHAPTER_1         => self::CHAPTER_2,
            self::CHAPTER_2         => self::CHAPTER_3,
            self::CHAPTER_3         => self::EPILOGUE_ACTION_1,
            self::EPILOGUE_ACTION_1 => self::EPILOGUE_ACTION_2,
            self::EPILOGUE_ACTION_2 => self::EPILOGUE_ACTION_3,
            self::EPILOGUE_ACTION_3 => self::EPILOGUE_FINAL,
            self::EPILOGUE_FINAL    => self::COMPLETED,
            self::COMPLETED         => self::COMPLETED,
        };
    }
}
