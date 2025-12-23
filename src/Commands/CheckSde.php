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

/**
 * CheckSde Command
 *
 * Checks the latest SDE version available from CCP's official repository
 * and compares it with the currently installed version.
 *
 * Usage: php artisan eve:sde:check
 */
class CheckSde extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'eve:sde:check';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check latest EVE SDE version from CCP';

    /**
     * CCP SDE API endpoint.
     */
    private const LATEST_VERSION_URL = 'https://developers.eveonline.com/static-data/tranquility/latest.jsonl';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Get latest version from CCP
        $latestVersion = $this->fetchLatestVersion();

        if (!$latestVersion) {
            $output = [
                'status' => 'error',
                'message' => 'Failed to fetch latest version information',
            ];
            $this->line(Yaml::dump($output, 2, 2));
            return self::FAILURE;
        }

        // Get installed version
        $installedVersion = Seat::get('installed_sde');
        $installedBuild = $this->extractBuildNumber($installedVersion);

        // Determine status
        $status = 'unknown';
        $updateAvailable = false;
        $message = '';

        if (!$installedBuild) {
            $status = 'not_installed';
            $message = 'No SDE data installed';
            $updateAvailable = true;
        } elseif ($installedBuild == $latestVersion['buildNumber']) {
            $status = 'up_to_date';
            $message = 'Running the latest SDE version';
            $updateAvailable = false;
        } elseif ($installedBuild < $latestVersion['buildNumber']) {
            $status = 'update_available';
            $message = 'A newer version is available';
            $updateAvailable = true;
        } else {
            $status = 'newer_than_latest';
            $message = 'Installed version is newer than latest official release';
            $updateAvailable = false;
        }

        // Build YAML output
        $output = [
            'status' => $status,
            'message' => $message,
            'update_available' => $updateAvailable,
            'latest' => [
                'build_number' => $latestVersion['buildNumber'],
                'release_date' => $latestVersion['releaseDate'],
            ],
            'installed' => [
                'version' => $installedVersion ?? null,
                'build_number' => $installedBuild ?? null,
            ],
        ];

        if ($updateAvailable) {
            $output['next_steps'] = [
                'php artisan eve:sde:download',
                'php artisan eve:sde:install',
            ];
        }

        $this->line(Yaml::dump($output, 3, 2));
        return self::SUCCESS;
    }

    /**
     * Fetch latest SDE version from CCP API.
     *
     * @return array|null ['buildNumber' => int, 'releaseDate' => string]
     */
    private function fetchLatestVersion(): ?array
    {
        try {
            $response = Http::timeout(10)->get(self::LATEST_VERSION_URL);

            if (!$response->successful()) {
                $this->error("HTTP request failed with status: {$response->status()}");
                return null;
            }

            // Parse JSONL (first line only)
            $content = trim($response->body());
            $lines = explode("\n", $content);
            $data = json_decode($lines[0], true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->error('Failed to parse JSON: ' . json_last_error_msg());
                return null;
            }

            if (!isset($data['buildNumber']) || !isset($data['releaseDate'])) {
                $this->error('Invalid response format: missing required fields');
                return null;
            }

            return [
                'buildNumber' => $data['buildNumber'],
                'releaseDate' => $data['releaseDate'],
            ];
        } catch (\Exception $e) {
            $this->error("Exception: {$e->getMessage()}");
            return null;
        }
    }

    /**
     * Extract build number from version string like 'sde-3142455'.
     *
     * @param string|null $version
     * @return int|null
     */
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
