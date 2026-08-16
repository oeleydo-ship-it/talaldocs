<?php

namespace App\Support;

class FrontMatter
{
    /**
     * @return array{data: array<string, string>, body: string}
     */
    public static function parse(string $contents): array
    {
        $normalized = str_replace(["\r\n", "\r"], "\n", $contents);
        $trimmed = ltrim($normalized, "\u{FEFF} \t\n");

        if (! str_starts_with($trimmed, "---\n")) {
            return ['data' => [], 'body' => $normalized];
        }

        $end = strpos($trimmed, "\n---", 4);

        if ($end === false) {
            return ['data' => [], 'body' => $normalized];
        }

        $block = substr($trimmed, 4, $end - 4);
        $body = ltrim(substr($trimmed, $end + 4), "\n");
        $data = [];

        foreach (explode("\n", $block) as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#') || ! str_contains($line, ':')) {
                continue;
            }

            [$key, $value] = explode(':', $line, 2);
            $key = strtolower(trim($key));

            if ($key === '') {
                continue;
            }

            $data[$key] = self::unquote(trim($value));
        }

        return ['data' => $data, 'body' => $body];
    }

    private static function unquote(string $value): string
    {
        if (strlen($value) >= 2) {
            $first = $value[0];
            $last = $value[strlen($value) - 1];

            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                return substr($value, 1, -1);
            }
        }

        return $value;
    }
}
