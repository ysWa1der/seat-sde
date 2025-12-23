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

namespace LocalSde\SeatLocalSde\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Seat\Services\Settings\Seat;
use Symfony\Component\Yaml\Yaml;

class UpdatePlanetSde extends Command
{
    protected $signature = 'eve:sde:update-planet
                            {build? : Specific build number to update to (optional, defaults to latest)}
                            {--force : Force download and installation even if already up-to-date}
                            {--check-only : Only check for updates without downloading or installing}';

    protected $description = 'Automatically check, download, and install EVE SDE Planet updates';

    private const LATEST_VERSION_URL = 'https://developers.eveonline.com/static-data/tranquility/latest.jsonl';

    public function handle()
    {
        fwrite(STDERR, "╔═══════════════════════════════════════════════════════════════╗\n");
        fwrite(STDERR, "║  EVE SDE Planet Auto-Update                                   ║\n");
        fwrite(STDERR, "╚═══════════════════════════════════════════════════════════════╝\n\n");

        // Step 1: Check version
        fwrite(STDERR, "[1/3] Checking SDE version...\n\n");

        $buildNumber = $this->argument('build');
        $latestInfo = null;

        if (!$buildNumber) {
            $latestInfo = $this->fetchLatestVersion();

            if (!$latestInfo) {
                $output = [
                    'status' => 'error',
                    'message' => 'Failed to fetch latest version information',
                ];
                $this->line(Yaml::dump($output, 2, 2));
                return self::FAILURE;
            }

            $buildNumber = $latestInfo['buildNumber'];
        }

        fwrite(STDERR, "Target version: Build {$buildNumber}\n");

        $installedVersion = Seat::get('installed_sde');
        $installedBuild = $this->extractBuildNumber($installedVersion);

        if ($installedVersion) {
            fwrite(STDERR, "Installed version: {$installedVersion}" . ($installedBuild ? " (Build {$installedBuild})" : "") . "\n");
        } else {
            fwrite(STDERR, "No SDE version currently installed\n");
        }

        fwrite(STDERR, "\n");

        if ($this->option('check-only')) {
            if ($installedBuild < $buildNumber || !$installedBuild) {
                $output = [
                    'status' => 'update_available',
                    'message' => 'Update available',
                    'installed_build' => $installedBuild ?? null,
                    'latest_build' => $buildNumber,
                    'next_step' => 'php artisan eve:sde:update-planet',
                ];
                $this->line(Yaml::dump($output, 2, 2));
            }
            return self::SUCCESS;
        }
        if ($this->input->isInteractive()) {
            $action = $installedBuild ? 'update' : 'install';
            if (!$this->confirm("Do you want to {$action} to build {$buildNumber}?", true)) {
                $output = [
                    'status' => 'cancelled',
                    'message' => 'Update cancelled by user',
                ];
                $this->line(Yaml::dump($output, 2, 2));
                return self::SUCCESS;
            }
        }

        fwrite(STDERR, "\n");

        // Step 2: Download
        fwrite(STDERR, "[2/3] Downloading SDE data...\n\n");

        $downloadOptions = $this->option('force') ? ['--force' => true] : [];
        $exitCode = $this->call('eve:sde:download', array_merge(
            [$buildNumber],
            $downloadOptions
        ));

        if ($exitCode !== self::SUCCESS) {
            $output = [
                'status' => 'error',
                'message' => 'Download failed',
                'step' => 'download',
            ];
            $this->line(Yaml::dump($output, 2, 2));
            return self::FAILURE;
        }

        fwrite(STDERR, "\n");

        // Step 3: Install
        fwrite(STDERR, "[3/3] Installing to database...\n\n");

        $installOptions = $this->option('force') ? ['--force' => true] : [];
        $exitCode = $this->call('eve:sde:install-planet', array_merge(
            [$buildNumber],
            $installOptions
        ));

        if ($exitCode !== self::SUCCESS) {
            $output = [
                'status' => 'error',
                'message' => 'Installation failed',
                'step' => 'install',
            ];
            $this->line(Yaml::dump($output, 2, 2));
            return self::FAILURE;
        }

        fwrite(STDERR, "\n");
        fwrite(STDERR, "╔═══════════════════════════════════════════════════════════════╗\n");
        fwrite(STDERR, "║  SDE Planet Update Complete!                                  ║\n");
        fwrite(STDERR, "╚═══════════════════════════════════════════════════════════════╝\n\n");

        $output = [
            'status' => 'success',
            'message' => 'SDE Planet update complete',
            'build_number' => $buildNumber,
        ];

        $this->line(Yaml::dump($output, 2, 2));
        return self::SUCCESS;
    }

    private function fetchLatestVersion(): ?array
    {
        try {
            $response = Http::timeout(10)->get(self::LATEST_VERSION_URL);

            if (!$response->successful()) {
                return null;
            }

            $content = trim($response->body());
            $lines = explode("\n", $content);
            $data = json_decode($lines[0], true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return null;
            }

            if (!isset($data['buildNumber']) || !isset($data['releaseDate'])) {
                return null;
            }

            return [
                'buildNumber' => $data['buildNumber'],
                'releaseDate' => $data['releaseDate'],
            ];
        } catch (\Exception $e) {
            return null;
        }
    }

    private function extractBuildNumber(?string $version): ?int
    {
        if (!$version) {
            return null;
        }

        if (preg_match('/sde-(\d+)/', $version, $matches)) {
            return (int)$matches[1];
        }

        return null;
    }
}
