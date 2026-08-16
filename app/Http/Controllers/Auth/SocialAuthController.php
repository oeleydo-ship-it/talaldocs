<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\User;
use App\Support\OAuthProviders;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;

class SocialAuthController extends Controller
{
    /**
     * @return list<string>
     */
    public static function enabledProviders(): array
    {
        return OAuthProviders::enabled();
    }

    public function redirect(string $provider): SymfonyRedirectResponse
    {
        abort_unless(OAuthProviders::isEnabled($provider), 404);

        return Socialite::driver($provider)->redirect();
    }

    public function callback(string $provider): RedirectResponse
    {
        abort_unless(OAuthProviders::isEnabled($provider), 404);

        /** @var SocialiteUser $socialUser */
        $socialUser = Socialite::driver($provider)->user();

        if (! filled($socialUser->getEmail())) {
            return redirect()
                ->route('login')
                ->with('status', 'Your '.$provider.' account does not have an email address we can use.');
        }

        $account = Account::query()
            ->where('provider', $provider)
            ->where('provider_id', $socialUser->getId())
            ->first();

        $user = $account?->user ?? User::query()->where('email', $socialUser->getEmail())->first();

        if ($user === null) {
            $user = User::query()->create([
                'name' => $socialUser->getName() ?: ($socialUser->getNickname() ?: config('app.name', 'Docs').' user'),
                'email' => $socialUser->getEmail(),
                'password' => Hash::make(Str::random(40)),
                'email_verified_at' => now(),
            ]);
        } elseif ($user->email_verified_at === null) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        Account::query()->updateOrCreate(
            [
                'provider' => $provider,
                'provider_id' => $socialUser->getId(),
            ],
            [
                'user_id' => $user->id,
                'token' => $socialUser->token,
                'refresh_token' => $socialUser->refreshToken,
                'token_expires_at' => $socialUser->expiresIn
                    ? now()->addSeconds($socialUser->expiresIn)
                    : null,
            ],
        );

        Auth::login($user, true);

        return redirect()->intended(
            $user->hasCompletedOnboarding()
                ? route('dashboard')
                : route('onboarding.create'),
        );
    }
}
