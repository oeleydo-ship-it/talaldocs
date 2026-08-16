<?php

namespace App\Support;

class BlockContentValidator
{
    public const MAX_LENGTH = 50000;

    /**
     * @return array<string, list<mixed>>
     */
    public static function rules(bool $requireContent = true): array
    {
        $markdownRules = ['string', 'max:'.self::MAX_LENGTH];

        if ($requireContent) {
            array_unshift($markdownRules, 'required');
        } else {
            array_unshift($markdownRules, 'nullable');
        }

        return [
            'name' => ['required', 'string', 'max:120'],
            'markdown' => $markdownRules,
        ];
    }

    public static function looksLikeApiDump(string $content): bool
    {
        $trimmed = trim($content);

        if ($trimmed === '') {
            return false;
        }

        if (! str_starts_with($trimmed, '{') && ! str_starts_with($trimmed, '[')) {
            return false;
        }

        json_decode($trimmed);

        return json_last_error() === JSON_ERROR_NONE;
    }
}
