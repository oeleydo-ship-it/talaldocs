<?php

namespace Tests\Feature\Auth;

use App\Actions\Fortify\CreateNewUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocalRegistrationVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_local_registration_marks_the_email_verified(): void
    {
        $this->app['env'] = 'local';

        $user = app(CreateNewUser::class)->create([
            'name' => 'Local User',
            'email' => 'local@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertTrue($user->hasVerifiedEmail());
    }
}
