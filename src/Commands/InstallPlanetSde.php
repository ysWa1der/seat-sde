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
use Illuminate\Support\Facades\DB;
use LocalSde\SeatLocalSde\Parsers\JsonlParser;
use LocalSde\SeatLocalSde\Parsers\SdeTableMapper;
use Seat\Services\Settings\Seat;
use Symfony\Component\Yaml\Yaml;

class InstallPlanetSde extends Command
{
    protected $signature = 'eve:sde:install-planet
                            {build? : Specific build number to install (optional, defaults to latest)}
                            {--force : Force re-installation of an existing SDE version}
                            {--path= : Custom path to static data zip file}';

    protected $description = 'Install EVE SDE Planet data into database from JSONL files';

    protected $sdeVersion;

    protected $dataPath;

    public function handle()
    {
        fwrite(STDERR, "Starting SDE Planet installation...\n\n");

        $buildNumber = $this->argument('build');

        if ($this->option('path')) {
            $this->dataPath = $this->option('path');
        } elseif ($buildNumber) {
            $dataPath = config('local-sde.data_path');
            $filename = "eve-online-static-data-{$buildNumber}-jsonl.zip";

            if (is_dir($dataPath)) {
                $this->dataPath = rtrim($dataPath, '/') . '/' . $filename;
            } else {
                $this->dataPath = dirname($dataPath) . '/' . $filename;
            }
        } else {
            $this->dataPath = config('local-sde.data_path');
        }

        if (!$this->validateDataPath()) {
            return self::FAILURE;
        }

        try {
            $this->sdeVersion = JsonlParser::getSdeVersion($this->dataPath);
        } catch (\Exception $e) {
            $output = [
                'status' => 'error',
                'message' => 'Failed to read SDE version',
                'error' => $e->getMessage(),
            ];
            $this->line(Yaml::dump($output, 2, 2));
            return self::FAILURE;
        }

        ini_set('memory_limit', config('local-sde.memory_limit'));

        fwrite(STDERR, "Starting SDE Planet import...\n\n");

        try {
            $stats = $this->importTables();

            $output = [
                'status' => 'success',
                'message' => 'SDE Planet installation complete',
                'version' => $this->sdeVersion['version'],
                'statistics' => $stats,
            ];

            $this->line(Yaml::dump($output, 3, 2));
            return self::SUCCESS;
        } catch (\Exception $e) {
            $output = [
                'status' => 'error',
                'message' => 'Import failed',
                'error' => $e->getMessage(),
                'trace' => explode("\n", $e->getTraceAsString()),
            ];
            $this->line(Yaml::dump($output, 4, 2));
            return self::FAILURE;
        }
    }

    private function validateDataPath(): bool
    {
        if (!file_exists($this->dataPath) && !is_dir($this->dataPath)) {
            $output = [
                'status' => 'error',
                'message' => 'Data path not found',
                'path' => $this->dataPath,
                'hint' => 'Set custom path with: --path=/path/to/data.zip',
            ];
            $this->line(Yaml::dump($output, 2, 2));
            return false;
        }

        fwrite(STDERR, "Data path: {$this->dataPath}\n");
        return true;
    }

    private function importTables(): array
    {
        $zipFile = $this->findZipFile();

        if (!$zipFile) {
            throw new \Exception("No static data zip file found in {$this->dataPath}");
        }

        $jsonlFiles = JsonlParser::listJsonlFiles($zipFile);

        $importOrder = [
            'mapPlanets.jsonl',
            'mapMoons.jsonl',
            'planetSchematics.jsonl',
        ];

        $stats = [];
        $current = 0;
        $total = count($importOrder);

        foreach ($importOrder as $jsonlFile) {
            if (!in_array($jsonlFile, $jsonlFiles)) {
                continue;
            }

            $current++;
            fwrite(STDERR, sprintf("[%d/%d] Importing %s...\n", $current, $total, $jsonlFile));

            $count = $this->importTable($zipFile, $jsonlFile);
            $stats[$jsonlFile] = $count;
        }

        fwrite(STDERR, "\nImport complete!\n\n");
        return $stats;
    }

    private function importTable(string $zipFile, string $jsonlFile): int
    {
        $tableName = $this->getTableName($jsonlFile);
        if (!$tableName) {
            fwrite(STDERR, "  WARNING: No table mapping for {$jsonlFile}, skipping\n");
            return 0;
        }

        if ($tableName !== 'mapDenormalize') {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
            DB::table($tableName)->truncate();
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }

        $chunkSize = config('local-sde.chunk_size');
        $chunk = [];
        $recordCount = 0;

        $actualFile = $jsonlFile;
        if (str_contains($jsonlFile, ':')) {
            $actualFile = explode(':', $jsonlFile)[0] . '.jsonl';
        }

        $tempDir = sys_get_temp_dir();
        $tempFile = "{$tempDir}/{$actualFile}";

        $zip = new \ZipArchive();
        if ($zip->open($zipFile) === true) {
            $contents = $zip->getFromName($actualFile);
            if ($contents === false) {
                fwrite(STDERR, "  WARNING: File {$actualFile} not found in zip\n");
                return 0;
            }
            file_put_contents($tempFile, $contents);
            $zip->close();
        }

        foreach (JsonlParser::parse($tempFile) as $record) {
            $mapped = SdeTableMapper::map($jsonlFile, $record);

            if ($mapped === null) {
                continue;
            }

            if ($tableName === 'mapDenormalize') {
                DB::table($tableName)->updateOrInsert(
                    ['itemID' => $mapped['itemID']],
                    $mapped
                );
                $recordCount++;
                continue;
            }

            $chunk[] = $mapped;
            $recordCount++;

            if (count($chunk) >= $chunkSize) {
                DB::table($tableName)->insert($chunk);
                $chunk = [];
            }
        }

        if (!empty($chunk)) {
            DB::table($tableName)->insert($chunk);
        }

        @unlink($tempFile);

        return $recordCount;
    }

    private function getTableName(string $jsonlFile): ?string
    {
        $mappings = config('local-sde.table_mappings');
        return $mappings[$jsonlFile] ?? null;
    }

    private function findZipFile(): ?string
    {
        if (str_ends_with($this->dataPath, '.zip')) {
            return $this->dataPath;
        }

        if (is_dir($this->dataPath)) {
            $files = glob($this->dataPath . '/*.zip');
            if (!empty($files)) {
                return $files[0];
            }
        }

        return null;
    }
}
