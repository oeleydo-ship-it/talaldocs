<?php

namespace App\Enums;

enum AiProvider: string
{
    case OpenAi = 'openai';
    case Kimi = 'kimi';
    case Custom = 'custom';

    public function defaultBaseUrl(): string
    {
        return match ($this) {
            self::OpenAi => 'https://api.openai.com/v1',
            self::Kimi => 'https://api.moonshot.ai/v1',
            self::Custom => '',
        };
    }

    /**
     * @return array<string, string> base URL => label
     */
    public function regionalBaseUrls(): array
    {
        return match ($this) {
            self::Kimi => [
                'https://api.moonshot.ai/v1' => 'International (platform.kimi.ai)',
                'https://api.moonshot.cn/v1' => 'China (moonshot.cn)',
            ],
            default => [],
        };
    }
}
