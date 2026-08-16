<?php

namespace App\Support;

use App\Models\Project;
use App\Models\Workspace;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class Subdomain
{
    public static function normalize(?string $value): string
    {
        return Str::lower(trim((string) $value));
    }

    public static function isReserved(string $subdomain): bool
    {
        /** @var list<string> $reserved */
        $reserved = config('anytdocs.reserved_subdomains', []);

        return in_array(self::normalize($subdomain), $reserved, true);
    }

    public static function isValidFormat(string $subdomain): bool
    {
        return (bool) preg_match((string) config('anytdocs.subdomain_pattern'), $subdomain);
    }

    public static function isAvailable(string $subdomain, ?int $ignoreProjectId = null): bool
    {
        $subdomain = self::normalize($subdomain);

        if ($subdomain === '' || ! self::isValidFormat($subdomain) || self::isReserved($subdomain)) {
            return false;
        }

        return ! Project::query()
            ->withoutGlobalScopes()
            ->where('subdomain', $subdomain)
            ->when($ignoreProjectId, fn ($query) => $query->where('id', '!=', $ignoreProjectId))
            ->exists();
    }

    /**
     * @return array{subdomain: string, available: bool, reserved: bool, valid: bool, message: string|null}
     */
    public static function availability(string $value): array
    {
        $subdomain = self::normalize($value);
        $reserved = self::isReserved($subdomain);
        $valid = self::isValidFormat($subdomain);
        $available = self::isAvailable($subdomain);

        $message = null;

        if ($reserved) {
            $message = 'That subdomain is reserved. Please choose another.';
        } elseif (! $valid) {
            $message = 'Use 3–63 lowercase letters, numbers, and hyphens. It must start and end with a letter or number.';
        } elseif (! $available) {
            $message = 'That subdomain is already taken.';
        }

        return [
            'subdomain' => $subdomain,
            'available' => $available,
            'reserved' => $reserved,
            'valid' => $valid,
            'message' => $message,
        ];
    }

    /**
     * @return array<int, mixed>
     */
    public static function rules(): array
    {
        return [
            'required',
            'string',
            'min:3',
            'max:63',
            'regex:/^[a-z0-9](?:[a-z0-9-]{1,61}[a-z0-9])$/',
            function (string $attribute, mixed $value, \Closure $fail): void {
                if (! is_string($value) || self::isReserved($value)) {
                    $fail('That subdomain is reserved. Please choose another.');
                }
            },
            Rule::unique('projects', 'subdomain'),
        ];
    }

    public static function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'workspace';
        $slug = $base;
        $suffix = 1;

        while (Workspace::query()->withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
