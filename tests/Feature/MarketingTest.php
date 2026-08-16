<?php

namespace Tests\Feature;

use App\Mail\ContactMessageMail;
use App\Models\PlatformSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MarketingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, string>
     */
    public static function marketingPagesProvider(): array
    {
        return [
            'home' => ['home', 'marketing/home'],
            'features' => ['marketing.features', 'marketing/features'],
            'pricing' => ['marketing.pricing', 'marketing/pricing'],
            'examples' => ['marketing.examples', 'marketing/examples'],
            'faq' => ['marketing.faq', 'marketing/faq'],
            'privacy' => ['marketing.privacy', 'marketing/privacy'],
            'terms' => ['marketing.terms', 'marketing/terms'],
            'contact' => ['marketing.contact', 'marketing/contact'],
        ];
    }

    #[DataProvider('marketingPagesProvider')]
    public function test_marketing_pages_load(string $routeName, string $component): void
    {
        $this->get(route($routeName))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component($component));
    }

    public function test_contact_page_includes_support_email_when_configured(): void
    {
        PlatformSetting::instance()->update([
            'support_email' => 'support@example.com',
        ]);

        $this->get(route('marketing.contact'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('marketing/contact')
                ->where('supportEmail', 'support@example.com')
            );
    }

    public function test_contact_form_sends_mail_to_support_address(): void
    {
        Mail::fake();

        PlatformSetting::instance()->update([
            'support_email' => 'support@example.com',
        ]);

        $this->from(route('marketing.contact'))
            ->post(route('marketing.contact.submit'), [
                'name' => 'Taylor Docs',
                'email' => 'taylor@example.com',
                'message' => 'We would like to discuss Enterprise.',
            ])
            ->assertRedirect(route('marketing.contact'))
            ->assertSessionHas('status');

        Mail::assertSent(ContactMessageMail::class, function (ContactMessageMail $mail): bool {
            return $mail->hasTo('support@example.com')
                && $mail->contact['name'] === 'Taylor Docs'
                && $mail->contact['email'] === 'taylor@example.com'
                && $mail->contact['message'] === 'We would like to discuss Enterprise.';
        });
    }

    public function test_contact_form_validates_required_fields(): void
    {
        Mail::fake();

        $this->post(route('marketing.contact.submit'), [])
            ->assertSessionHasErrors(['name', 'email', 'message']);

        Mail::assertNothingSent();
    }
}
