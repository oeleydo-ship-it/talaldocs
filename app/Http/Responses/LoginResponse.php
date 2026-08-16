<?php

namespace App\Http\Responses;

use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

class LoginResponse implements LoginResponseContract
{
    public function toResponse($request)
    {
        $user = $request->user();

        if ($user && ! $user->hasVerifiedEmail()) {
            return redirect()->route('verification.notice');
        }

        if ($user && ! $user->hasCompletedOnboarding()) {
            return redirect()->route('onboarding.create');
        }

        return redirect()->intended(config('fortify.home'));
    }
}
