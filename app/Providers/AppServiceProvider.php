<?php

namespace App\Providers;

use App\Services\Settings\BrandingSettingsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Singleton for client_company
        app()->singleton('client_company', function () {
            return app(BrandingSettingsService::class)->clientCompany();
        });

        app()->singleton('branding.effective_values', function () {
            return app(BrandingSettingsService::class)->effectiveValues();
        });

        // Singleton for Pusher flag
        app()->singleton('pusher.enabled', function () {
            return (bool) config('client_company.pusher_enabled')
                && filled(config('broadcasting.connections.pusher.key'))
                && filled(data_get(config('broadcasting.connections.pusher.options'), 'cluster'));
        });

        app()->singleton('pusher.frontend', function () {
            return [
                'enabled' => app('pusher.enabled'),
                'key' => (string) config('broadcasting.connections.pusher.key', ''),
                'cluster' => (string) data_get(config('broadcasting.connections.pusher.options'), 'cluster', ''),
            ];
        });

        app()->singleton('article', function () {
            return (object) [
                'categories' => [
                    '1_pc' => ['text' => '1 Pc'],
                    '1_pc_inner' => ['text' => '1 Pc + Inner'],
                    '1_pc_koti' => ['text' => '1 Pc + Koti'],
                    '2_pc' => ['text' => '2 Pc'],
                    '3_pc' => ['text' => '3 Pc'],
                    'romper' => ['text' => 'Romper'],
                ],
                'seasons' => [
                    'half' => ['text' => 'Half'],
                    'full' => ['text' => 'Full'],
                    'winter' => ['text' => 'Winter'],
                ],
                'sizes' => [
                    'zero' => ['text' => 'Zero'],
                    '1_2' => ['text' => '1-2'],
                    '2_3' => ['text' => '2-3'],
                    'ml' => ['text' => 'ML'],
                    'sm' => ['text' => 'SM'],
                    'sml' => ['text' => 'SML'],
                    'mlxl' => ['text' => 'MLXL'],
                    '18_20' => ['text' => '18-20'],
                    '18_20_22' => ['text' => '18-20-22'],
                    '18_20_22_24' => ['text' => '18-20-22-24'],
                    '20_22' => ['text' => '20-22'],
                    '20_22_24' => ['text' => '20-22-24'],
                    '24_26_28' => ['text' => '24-26-28'],
                ],
                'parts' => [
                    '1_pc_half' => ['shirt'],
                    '1_pc_inner_half' => ['shirt', 'inner'],
                    '1_pc_koti_half' => ['shirt', 'koti'],
                    '2_pc_half' => ['shirt', 'neker'],
                    '3_pc_half' => ['koti', 'inner', 'neker'],
                    'romper_half' => ['romper'],
                    '1_pc_full' => ['shirt'],
                    '1_pc_inner_full' => ['shirt', 'inner'],
                    '1_pc_koti_full' => ['shirt', 'koti'],
                    '2_pc_full' => ['shirt', 'trouser'],
                    '3_pc_full' => ['koti', 'inner', 'neker'],
                    'romper_full' => ['romper'],
                    '1_pc_winter' => ['shirt'],
                    '1_pc_inner_winter' => ['shirt', 'inner'],
                    '1_pc_koti_winter' => ['shirt', 'koti'],
                    '2_pc_winter' => ['shirt', 'trouser'],
                    '3_pc_winter' => ['koti', 'inner', 'trouser'],
                    'romper_winter' => ['romper'],
                ],
            ];
        });

        app()->singleton('defaults', function () {
            return (object) [
                'units' => [
                    'Kgs',
                    'Meter',
                    'Yards',
                    'Cone',
                    'Piece',
                    'Dozen',
                    'Set',
                    'Pair',
                    'Packet',
                    'Carton',
                    'Roll',
                    'Bag',
                    'Box',
                ],
            ];
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        date_default_timezone_set(config('app.timezone', 'Asia/Karachi'));

        // SQLite hardening for production stability (locking, FKs).
        // Applies only when the default connection is sqlite.
        if (config('database.default') === 'sqlite') {
            try {
                DB::statement('PRAGMA foreign_keys = ON');
                DB::statement('PRAGMA journal_mode = WAL');
                DB::statement('PRAGMA synchronous = NORMAL');
                DB::statement('PRAGMA busy_timeout = 5000');
            } catch (\Throwable $e) {
                // If the environment doesn't allow PRAGMA changes, continue without failing boot.
            }
        }

        // Share client company with all views
        View::share('client_company', app('client_company'));
        View::share('branding', app('branding.effective_values'));

        // Share Pusher enabled flag
        View::share('pusherEnabled', app('pusher.enabled'));
        View::share('pusherFrontend', app('pusher.frontend'));
    }
}
