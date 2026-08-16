<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class HeaderMenuUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            $fail('The :attribute must be a valid URL or path.');

            return;
        }

        if (str_starts_with($value, '/')) {
            return;
        }

        if (! filter_var($value, FILTER_VALIDATE_URL)) {
            $fail('The :attribute must be a valid http/https URL or a path starting with /.');

            return;
        }

        $scheme = parse_url($value, PHP_URL_SCHEME);

        if (! in_array($scheme, ['http', 'https'], true)) {
            $fail('The :attribute must use http or https.');
        }
    }
}
