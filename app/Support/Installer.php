<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class Installer
{
    public static function enabled(): bool
    {
        if (app()->runningUnitTests()) {
            return (bool) config('app.installer_enabled_in_tests', false);
        }

        return (bool) config('app.installer_enabled', true);
    }

    public static function databaseConnected(): bool
    {
        try {
            DB::connection()->getPdo();

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    public static function migrationsReady(): bool
    {
        try {
            return Schema::hasTable('users') && Schema::hasTable('plans');
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Fresh install: no platform admin yet (and usually no users).
     */
    public static function needsInstall(): bool
    {
        if (! self::enabled()) {
            return false;
        }

        if (! self::databaseConnected()) {
            return true;
        }

        try {
            if (! Schema::hasTable('users')) {
                return true;
            }

            return ! User::query()->where('is_platform_admin', true)->exists();
        } catch (Throwable) {
            return true;
        }
    }

    /**
     * @return array{connected: bool, driver: string, migrations_ready: bool, needs_install: bool, error: string|null}
     */
    public static function status(): array
    {
        $driver = (string) config('database.default');
        $error = null;
        $connected = false;

        try {
            DB::connection()->getPdo();
            $connected = true;
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }

        return [
            'connected' => $connected,
            'driver' => $driver,
            'migrations_ready' => $connected && self::migrationsReady(),
            'needs_install' => self::needsInstall(),
            'error' => $error,
        ];
    }

    public static function supportedDrivers(): array
    {
        return ['pgsql', 'mysql', 'mariadb', 'sqlite'];
    }
}
