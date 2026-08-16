<?php

namespace App\Http\Controllers;

use App\Models\PlatformSetting;
use App\Models\User;
use App\Support\Installer;
use App\Support\PlatformConfig;
use Database\Seeders\LanguageSeeder;
use Database\Seeders\PlanSeeder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Throwable;

class InstallController extends Controller
{
    public function show(): View|RedirectResponse
    {
        if (Installer::enabled() && ! Installer::needsInstall()) {
            return redirect()->route('home');
        }

        $status = Installer::status();
        $driver = $status['driver'];
        $driverLabel = match ($driver) {
            'pgsql' => 'PostgreSQL',
            'mysql', 'mariadb' => 'MySQL',
            'sqlite' => 'SQLite',
            default => $driver,
        };

        return view('install', [
            'status' => $status,
            'supportedDrivers' => Installer::supportedDrivers(),
            'appName' => (string) config('app.name', 'Docs'),
            'driverLabel' => $driverLabel,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(Installer::enabled() && Installer::needsInstall(), 404);

        $status = Installer::status();

        if (! $status['connected']) {
            return back()->withErrors([
                'database' => 'Database connection failed. Set DB_CONNECTION to mysql or pgsql in your .env, then retry. '.$status['error'],
            ]);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'app_name' => ['nullable', 'string', 'max:120'],
        ]);

        try {
            if (! Installer::migrationsReady()) {
                Artisan::call('migrate', ['--force' => true]);
            }

            if (! DB::table('plans')->exists()) {
                (new PlanSeeder)->run();
            }

            if (! DB::table('languages')->exists()) {
                (new LanguageSeeder)->run();
            }
        } catch (Throwable $e) {
            report($e);

            return back()->withErrors([
                'database' => 'Could not prepare the database: '.$e->getMessage(),
            ])->withInput();
        }

        if (User::query()->where('email', $data['email'])->exists()) {
            return back()->withErrors([
                'email' => 'This email is already registered.',
            ])->withInput();
        }

        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'is_platform_admin' => true,
        ]);

        $user->forceFill(['email_verified_at' => now()])->save();

        $appName = filled($data['app_name'] ?? null)
            ? trim((string) $data['app_name'])
            : (string) config('app.name', 'Docs');

        if (PlatformConfig::tableAvailable()) {
            $settings = PlatformSetting::instance();
            $settings->forceFill(['app_name' => $appName])->save();
            PlatformConfig::apply();
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()
            ->route('platform.dashboard')
            ->with('status', 'Platform superadmin created. Welcome!');
    }
}
