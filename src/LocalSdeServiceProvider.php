<?php

// Copyright (C) 2025 kangtong@cloudtemple.cc

// This program is free software; you can redistribute it and/or
// modify it under the terms of the GNU General Public License
// as published by the Free Software Foundation; either version 2
// of the License, or (at your option) any later version.

// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.

// You should have received a copy of the GNU General Public License
// along with this program; if not, write to the Free Software
// Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA. 

namespace LocalSde\SeatLocalSde;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use LocalSde\SeatLocalSde\Commands\CheckSde;
use LocalSde\SeatLocalSde\Commands\DownloadSde;
use LocalSde\SeatLocalSde\Commands\InstallSde;
use LocalSde\SeatLocalSde\Commands\InstallPlanetSde;
use LocalSde\SeatLocalSde\Commands\UpdateAllSde;
use LocalSde\SeatLocalSde\Commands\UpdatePlanetSde;
use LocalSde\SeatLocalSde\Commands\UpdateSde;
use LocalSde\SeatLocalSde\Http\Middleware\InjectSdeUrlScript;
use Seat\Services\Models\Schedule;


/**
 * LocalSdeServiceProvider
 *
 * Provides local CCP static data (JSONL) import functionality as an alternative to Fuzzwork SDE.
 * This plugin automatically:
 * - Removes 'eve:update:sde' from scheduler
 * - Adds 'eve:sde:update-all' to scheduler (weekly on Sunday at 3:00 AM)
 * - Overrides SeAT's SDE version check to use CCP official API instead of Fuzzwork
 * - Replaces Fuzzwork URL with CCP official URL on settings page
 *
 * Safe to remove: Removing this plugin will restore default Fuzzwork behavior.
 * Note: You may need to manually re-add 'eve:update:sde' to scheduler after removal.
 */
class LocalSdeServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register()
    {
        // Merge configuration
        $this->mergeConfigFrom(
            __DIR__ . '/Config/local-sde.php',
            'local-sde'
        );
    }

    /**
     * Bootstrap services.
     */
    public function boot()
    {
        // Load migrations
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        // Register commands (available in both console and web contexts for scheduler UI)
        $this->commands([
            CheckSde::class,
            DownloadSde::class,
            InstallSde::class,
            UpdateSde::class,
            InstallPlanetSde::class,
            UpdateAllSde::class,
            UpdatePlanetSde::class,
        ]);

        // Publish configuration
        $this->publishes([
            __DIR__ . '/Config/local-sde.php' => config_path('local-sde.php'),
        ], 'config');

        // Register middleware to inject URL override script
        $this->app['Illuminate\Contracts\Http\Kernel']
            ->pushMiddleware(InjectSdeUrlScript::class);

        // Automatically manage scheduler: replace eve:update:sde with eve:sde:update-all
        $this->manageScheduler();

        // Register HTTP routes to override SeAT's SDE version check
        // IMPORTANT: This must be called LAST to ensure routes override core routes
        $this->registerRoutes();
    }

    /**
     * Register HTTP routes to override SeAT core functionality.
     */
    private function registerRoutes()
    {
        if (!$this->app->routesAreCached()) {
            include __DIR__ . '/Http/routes.php';
        }
    }

    /**
     * Automatically replace default SDE update scheduler with local SDE update.
     *
     * This method:
     * 1. Removes the default 'eve:update:sde' schedule entry
     * 2. Adds 'eve:sde:update-all' schedule if not exists
     */
    private function manageScheduler()
    {
        try {
            // Check if database connection is available and schedules table exists
            DB::connection();
            if (!Schema::hasTable('schedules')) {
                return;
            }

            // Remove old Fuzzwork SDE updater
            Schedule::where('command', 'eve:update:sde')->delete();

            // Add local SDE updater if not exists
            $exists = Schedule::where('command', 'eve:sde:update-all')->exists();

            if (!$exists) {
                Schedule::create([
                    'command' => 'eve:sde:update-all',
                    'expression' => '0 3 * * 0',  // Every Sunday at 3:00 AM
                    'allow_overlap' => false,
                    'allow_maintenance' => false,
                    'ping_before' => null,
                    'ping_after' => null,
                ]);
            }
        } catch (\Exception $e) {
            // Silently fail if database is not ready (e.g., during initial setup)
            // This is expected behavior during composer install or migrations
        }
    }
}
