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
use Symfony\Component\Yaml\Yaml;

/**
 * DownloadSde Command
 *
 * Downloads EVE SDE data from CCP's official repository.
 * Can download either the latest version or a specific build number.
 *
 * Usage:
 *   php artisan eve:sde:download           # Download latest
 *   php artisan eve:sde:download 3142455   # Download specific build
 */
class DownloadSde extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'eve:sde:download
                            {build? : Specific build number to download (optional, defaults to latest)}
                            {--force : Force download even if file already exists}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Download EVE SDE from CCP official repository';

    /**
     * CCP SDE download URLs.
     */
    private const LATEST_VERSION_URL = 'https://developers.eveonline.com/static-data/tranquility/latest.jsonl';
    private const DOWNLOAD_URL_PATTERN = 'https://developers.eveonline.com/static-data/eve-online-static-data-%s-jsonl.zip';
    private const LATEST_DOWNLOAD_URL = 'https://developers.eveonline.com/static-data/eve-online-static-data-latest-jsonl.zip';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Determine which build to download
        $buildNumber = $this->argument('build');

        if (!$buildNumber) {
            // Get latest build number
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

        // Prepare download path
        $dataPath = config('local-sde.data_path');
        $filename = "eve-online-static-data-{$buildNumber}-jsonl.zip";

        if (is_dir($dataPath)) {
            $savePath = rtrim($dataPath, '/') . '/' . $filename;
        } else {
            $savePath = dirname($dataPath) . '/' . $filename;
        }

        // Check if file already exists
        if (file_exists($savePath) && !$this->option('force')) {
            $fileSize = filesize($savePath);
            $output = [
                'status' => 'already_exists',
                'message' => 'File already exists',
                'build_number' => $buildNumber,
                'file' => [
                    'path' => $savePath,
                    'name' => $filename,
                    'size' => $fileSize,
                    'size_human' => $this->formatBytes($fileSize),
                ],
                'hint' => 'Use --force to re-download',
            ];
            $this->line(Yaml::dump($output, 3, 2));
            return self::SUCCESS;
        }

        // Download
        $downloadUrl = $this->argument('build')
            ? sprintf(self::DOWNLOAD_URL_PATTERN, $buildNumber)
            : self::LATEST_DOWNLOAD_URL;

        $downloadPath = $this->downloadSde($downloadUrl, $savePath, $buildNumber);

        if (!$downloadPath) {
            $output = [
                'status' => 'error',
                'message' => 'Download failed',
                'build_number' => $buildNumber,
            ];
            $this->line(Yaml::dump($output, 2, 2));
            return self::FAILURE;
        }

        $fileSize = filesize($downloadPath);
        $output = [
            'status' => 'success',
            'message' => 'Download complete',
            'build_number' => $buildNumber,
            'file' => [
                'path' => $downloadPath,
                'name' => $filename,
                'size' => $fileSize,
                'size_human' => $this->formatBytes($fileSize),
            ],
            'next_step' => "php artisan eve:sde:install {$buildNumber}",
        ];

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
                return null;
            }

            $content = trim($response->body());
            $lines = explode("\n", $content);
            $data = json_decode($lines[0], true);

            if (json_last_error() !== JSON_ERROR_NONE) {
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

    /**
     * Download SDE zip file.
     *
     * @param string $url
     * @param string $savePath
     * @param int $buildNumber
     * @return string|null Path to downloaded file
     */
    private function downloadSde(string $url, string $savePath, int $buildNumber): ?string
    {
        try {
            // Progress to stderr
            fwrite(STDERR, "Downloading build {$buildNumber}...\n");
            fwrite(STDERR, "From: {$url}\n");
            fwrite(STDERR, "To:   {$savePath}\n\n");

            $lastPercent = 0;
            $response = Http::timeout(300)
                ->withOptions([
                    'sink' => $savePath,
                    'progress' => function ($downloadTotal, $downloadedBytes) use (&$lastPercent) {
                        if ($downloadTotal > 0) {
                            $percent = (int)(($downloadedBytes / $downloadTotal) * 100);
                            if ($percent != $lastPercent && $percent % 10 == 0) {
                                fwrite(STDERR, "Progress: {$percent}%\n");
                                $lastPercent = $percent;
                            }
                        }
                    },
                ])
                ->get($url);

            if (!$response->successful()) {
                @unlink($savePath);
                return null;
            }

            if (!file_exists($savePath)) {
                return null;
            }

            fwrite(STDERR, "Download completed\n\n");
            return $savePath;
        } catch (\Exception $e) {
            @unlink($savePath);
            return null;
        }
    }

    /**
     * Format bytes to human-readable size.
     *
     * @param int $bytes
     * @return string
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $factor = floor((strlen($bytes) - 1) / 3);

        return sprintf("%.2f %s", $bytes / pow(1024, $factor), $units[$factor]);
    }
}
