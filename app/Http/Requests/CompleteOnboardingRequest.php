<?php

namespace App\Http\Requests;

use App\Support\Subdomain;
use Illuminate\Foundation\Http\FormRequest;

class CompleteOnboardingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'company_name' => ['required', 'string', 'max:255'],
            'workspace_name' => ['required', 'string', 'max:255'],
            'subdomain' => Subdomain::rules(),
            'website_url' => ['nullable', 'string', 'url', 'max:2048'],
            'product_description' => ['nullable', 'string', 'max:2000'],
            'publish_ai_pages' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('subdomain') && is_string($this->input('subdomain'))) {
            $this->merge([
                'subdomain' => Subdomain::normalize($this->string('subdomain')->toString()),
            ]);
        }
    }
}
