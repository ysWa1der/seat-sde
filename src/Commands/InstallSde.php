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

/**
 * InstallSde Command
 *
 * Installs SDE data into the database from downloaded JSONL files.
 * Can install either the latest downloaded version or a specific build.
 *
 * Usage:
 *   php artisan eve:sde:install           # Install latest downloaded
 *   php artisan eve:sde:install 3142455   # Install specific build
 */
class InstallSde extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'eve:sde:install
                            {build? : Specific build number to install (optional, defaults to latest)}
                            {--force : Force re-installation of an existing SDE version}
                            {--path= : Custom path to static data zip file}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Install EVE SDE data into database from JSONL files';

    /**
     * SDE version information.
     *
     * @var array
     */
    protected $sdeVersion;

    /**
     * Data path (zip file or directory).
     *
     * @var string
     */
    protected $dataPath;

    /**
     * Execute the console command.
     */
    public function handle()
    {
        fwrite(STDERR, "Starting SDE installation...\n\n");

        // Get data path
        $buildNumber = $this->argument('build');

        if ($this->option('path')) {
            $this->dataPath = $this->option('path');
        } elseif ($buildNumber) {
            // Use specific build number file
            $dataPath = config('local-sde.data_path');
            $filename = "eve-online-static-data-{$buildNumber}-jsonl.zip";

            if (is_dir($dataPath)) {
                $this->dataPath = rtrim($dataPath, '/') . '/' . $filename;
            } else {
                $this->dataPath = dirname($dataPath) . '/' . $filename;
            }
        } else {
            // Use default path (will auto-detect latest)
            $this->dataPath = config('local-sde.data_path');
        }

        if (!$this->validateDataPath()) {
            return self::FAILURE;
        }

        // Get SDE version
        try {
            $this->sdeVersion = JsonlParser::getSdeVersion($this->dataPath);
            fwrite(STDERR, "SDE Version: {$this->sdeVersion['version']}\n");
            fwrite(STDERR, "Build Number: {$this->sdeVersion['buildNumber']}\n");
            fwrite(STDERR, "Release Date: {$this->sdeVersion['releaseDate']}\n\n");
        } catch (\Exception $e) {
            $output = [
                'status' => 'error',
                'message' => 'Failed to read SDE version',
                'error' => $e->getMessage(),
            ];
            $this->line(Yaml::dump($output, 2, 2));
            return self::FAILURE;
        }

        // Check if already installed
        $installedVersion = Seat::get('installed_sde');
        if ($installedVersion === $this->sdeVersion['version'] && !$this->option('force')) {
            $output = [
                'status' => 'already_installed',
                'message' => 'This SDE version is already installed',
                'version' => $installedVersion,
                'hint' => 'Use --force to reinstall',
            ];
            $this->line(Yaml::dump($output, 2, 2));
            return self::SUCCESS;
        }

        // Increase memory limit
        ini_set('memory_limit', config('local-sde.memory_limit'));

        // Import tables
        fwrite(STDERR, "Starting SDE import...\n\n");

        try {
            $stats = $this->importTables();

            // Update installed version
            Seat::set('installed_sde', $this->sdeVersion['version']);

            $output = [
                'status' => 'success',
                'message' => 'SDE installation complete',
                'version' => $this->sdeVersion['version'],
                'build_number' => $this->sdeVersion['buildNumber'],
                'release_date' => $this->sdeVersion['releaseDate'],
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

    /**
     * Validate that the data path exists and is accessible.
     */
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

    /**
     * Import all SDE tables.
     */
    private function importTables(): array
    {
        // Find the zip file if dataPath is a directory
        $zipFile = $this->findZipFile();

        if (!$zipFile) {
            throw new \Exception("No static data zip file found in {$this->dataPath}");
        }

        fwrite(STDERR, "Using file: " . basename($zipFile) . "\n");
        // Get list of JSONL files
        $jsonlFiles = JsonlParser::listJsonlFiles($zipFile);
        $jsonlFiles[] = 'types:meta.jsonl';
        $jsonlFiles[] = 'typeDogma:effects.jsonl';
        fwrite(STDERR, "Found " . count($jsonlFiles) . " JSONL files\n\n");

        // Import priority order (some tables depend on others)
        $importOrder = [
            // Core inventory
            'categories.jsonl',
            'groups.jsonl',
            'metaGroups.jsonl',
            'types:meta.jsonl',
            'types.jsonl',
            'marketGroups.jsonl',
            'typeMaterials.jsonl',

            // Universe Map Data (Order is important)
            'mapRegions.jsonl',
            'mapConstellations.jsonl',
            'mapSolarSystems.jsonl',
            'mapStars.jsonl',

            // Factions & contraband
            'factions.jsonl',
            'contrabandTypes.jsonl',

            // Stations & structures
            'npcStations.jsonl',
            'controlTowerResources.jsonl',

            // Dogma (attributes & effects)
            'dogmaAttributes.jsonl',
            'dogmaEffects.jsonl',
            'typeDogma.jsonl',
            'typeDogma:effects.jsonl',

            // Industry & Planetary Interaction
            'corporationActivities.jsonl',
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

    /**
     * Import a single JSONL file into its corresponding table.
     */
    private function importTable(string $zipFile, string $jsonlFile): int
    {
        $tableName = $this->getTableName($jsonlFile);
        if (!$tableName) {
            fwrite(STDERR, "  WARNING: No table mapping for {$jsonlFile}, skipping\n");
            return 0;
        }

        // Special handling for mapDenormalize to avoid truncating on every file
        if ($tableName !== 'mapDenormalize' || $jsonlFile === 'mapRegions.jsonl') {
            // Truncate existing data (disable foreign key checks temporarily)
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
            DB::table($tableName)->truncate();
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }


        // Parse and insert in chunks
        $chunkSize = config('local-sde.chunk_size');
        $chunk = [];
        $recordCount = 0;

        // Handle special keys like 'typeDogma:effects.jsonl' → 'typeDogma.jsonl'
        $actualFile = $jsonlFile;
        if (str_contains($jsonlFile, ':')) {
            $actualFile = explode(':', $jsonlFile)[0] . '.jsonl';
        }

        // Create a temporary file to extract from zip
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
            // Special handling for typeMaterials (nested structure)
            if ($jsonlFile === 'typeMaterials.jsonl' && isset($record['materials'])) {
                foreach ($record['materials'] as $material) {
                    $chunk[] = [
                        'typeID' => $record['_key'],
                        'materialTypeID' => $material['materialTypeID'],
                        'quantity' => $material['quantity'],
                    ];
                    $recordCount++;

                    if (count($chunk) >= $chunkSize) {
                        DB::table($tableName)->insert($chunk);
                        $chunk = [];
                    }
                }
                continue;
            }

            // Special handling for typeReactions (nested inputs and outputs)
            if ($jsonlFile === 'typeReactions.jsonl') {
                if (isset($record['inputs'])) {
                    foreach ($record['inputs'] as $input) {
                        $chunk[] = [
                            'reactionTypeID' => $record['_key'],
                            'input' => true,
                            'typeID' => $input['_key'],
                            'quantity' => $input['quantity'],
                        ];
                        $recordCount++;
                    }
                }
                if (isset($record['outputs'])) {
                    foreach ($record['outputs'] as $output) {
                        $chunk[] = [
                            'reactionTypeID' => $record['_key'],
                            'input' => false,
                            'typeID' => $output['_key'],
                            'quantity' => $output['quantity'],
                        ];
                        $recordCount++;
                    }
                }

                if (count($chunk) >= $chunkSize) {
                    DB::table($tableName)->insert($chunk);
                    $chunk = [];
                }
                continue;
            }

            // Special handling for controlTowerResources (nested structure)
            if ($jsonlFile === 'controlTowerResources.jsonl' && isset($record['resources'])) {
                foreach ($record['resources'] as $resource) {
                    $chunk[] = [
                        'controlTowerTypeID' => $record['_key'],
                        'resourceTypeID' => $resource['resourceTypeID'] ?? null,
                        'purpose' => $resource['purpose'] ?? null,
                        'quantity' => $resource['quantity'] ?? null,
                        'minSecurityLevel' => $resource['minSecurityLevel'] ?? null,
                        'factionID' => $resource['factionID'] ?? null,
                    ];
                    $recordCount++;

                    if (count($chunk) >= $chunkSize) {
                        DB::table($tableName)->insert($chunk);
                        $chunk = [];
                    }
                }
                continue;
            }

            // Special handling for typeDogma (nested structure - dgmTypeAttributes)
            if ($jsonlFile === 'typeDogma.jsonl' && isset($record['dogmaAttributes'])) {
                foreach ($record['dogmaAttributes'] as $attribute) {
                    $value = $attribute['value'] ?? null;

                    // Determine if value is int or float
                    // MySQL INT range: -2147483648 to 2147483647
                    $valueInt = null;
                    $valueFloat = null;
                    if ($value !== null) {
                        // Check if value fits in MySQL INT range
                        if ((is_int($value) || (is_float($value) && $value == (int)$value))
                            && $value >= -2147483648 && $value <= 2147483647) {
                            $valueInt = (int)$value;
                        } else {
                            $valueFloat = (float)$value;
                        }
                    }

                    $chunk[] = [
                        'typeID' => $record['_key'],
                        'attributeID' => $attribute['attributeID'],
                        'valueInt' => $valueInt,
                        'valueFloat' => $valueFloat,
                    ];
                    $recordCount++;

                    if (count($chunk) >= $chunkSize) {
                        DB::table($tableName)->insert($chunk);
                        $chunk = [];
                    }
                }
                continue;
            }

            // 🆕 Special handling for typeDogma:effects (extract dogmaEffects field)
            if ($jsonlFile === 'typeDogma:effects.jsonl' && isset($record['dogmaEffects'])) {
                foreach ($record['dogmaEffects'] as $effect) {
                    $chunk[] = [
                        'typeID' => $record['_key'],
                        'effectID' => $effect['effectID'],
                        'isDefault' => $effect['isDefault'] ?? false,
                    ];
                    $recordCount++;

                    if (count($chunk) >= $chunkSize) {
                        DB::table($tableName)->insert($chunk);
                        $chunk = [];
                    }
                }
                continue;
            }

            // 🆕 Special handling for contrabandTypes (nested factions structure)
            if ($jsonlFile === 'contrabandTypes.jsonl' && isset($record['factions'])) {
                foreach ($record['factions'] as $faction) {
                    $chunk[] = [
                        'typeID' => $record['_key'],
                        'factionID' => $faction['_key'],
                        'standingLoss' => $faction['standingLoss'] ?? null,
                        'confiscateMinSec' => $faction['confiscateMinSec'] ?? null,
                        'fineByValue' => $faction['fineByValue'] ?? null,
                        'attackMinSec' => $faction['attackMinSec'] ?? null,
                    ];
                    $recordCount++;

                    if (count($chunk) >= $chunkSize) {
                        DB::table($tableName)->insert($chunk);
                        $chunk = [];
                    }
                }
                continue;
            }

            $mapped = SdeTableMapper::map($jsonlFile, $record);

            if ($mapped === null) {
                continue;  // Skip this record
            }

            // If we are dealing with mapDenormalize, we need to update or insert.
            if ($tableName === 'mapDenormalize') {
                DB::table($tableName)->updateOrInsert(
                    ['itemID' => $mapped['itemID']], // Condition to find the record
                    $mapped // Values to update or insert
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

        // Insert remaining records
        if (!empty($chunk)) {
            DB::table($tableName)->insert($chunk);
        }

        // Clean up temp file
        @unlink($tempFile);

        return $recordCount;
    }

    /**
     * Get the SeAT table name for a JSONL file.
     */
    private function getTableName(string $jsonlFile): ?string
    {
        $mappings = config('local-sde.table_mappings');
        return $mappings[$jsonlFile] ?? null;
    }

    /**
     * Find the static data zip file.
     */
    private function findZipFile(): ?string
    {
        if (str_ends_with($this->dataPath, '.zip')) {
            return $this->dataPath;
        }

        // Search directory for zip file
        if (is_dir($this->dataPath)) {
            $files = glob($this->dataPath . '/*.zip');
            if (!empty($files)) {
                return $files[0];
            }
        }

        return null;
    }
}
