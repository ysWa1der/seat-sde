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

namespace LocalSde\SeatLocalSde\Parsers;

use Generator;
use ZipArchive;

/**
 * JsonlParser
 *
 * Parses CCP's static data JSONL files (line-delimited JSON format).
 * Supports both extracted files and files within zip archives.
 */
class JsonlParser
{
    /**
     * Parse a JSONL file and yield records one by one.
     *
     * @param string $filePath Path to .jsonl file or .jsonl.zip file
     * @return Generator
     * @throws \Exception
     */
    public static function parse(string $filePath): Generator
    {
        if (!file_exists($filePath)) {
            throw new \Exception("File not found: {$filePath}");
        }

        // Check if it's a zip file
        if (str_ends_with($filePath, '.zip')) {
            yield from self::parseFromZip($filePath);
        } else {
            yield from self::parseFromFile($filePath);
        }
    }

    /**
     * Parse JSONL file directly from disk.
     *
     * @param string $filePath
     * @return Generator
     */
    private static function parseFromFile(string $filePath): Generator
    {
        $handle = fopen($filePath, 'r');
        if (!$handle) {
            throw new \Exception("Cannot open file: {$filePath}");
        }

        try {
            while (($line = fgets($handle)) !== false) {
                $line = trim($line);
                if (empty($line)) {
                    continue;
                }

                $record = json_decode($line, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new \Exception("JSON parse error in {$filePath}: " . json_last_error_msg());
                }

                yield $record;
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * Parse JSONL file from within a zip archive.
     *
     * @param string $zipPath Path to zip file
     * @param string|null $filename Specific file to extract (null = auto-detect)
     * @return Generator
     * @throws \Exception
     */
    private static function parseFromZip(string $zipPath, ?string $filename = null): Generator
    {
        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new \Exception("Cannot open zip file: {$zipPath}");
        }

        try {
            // If filename not specified, find the first .jsonl file
            if ($filename === null) {
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $stat = $zip->statIndex($i);
                    if (str_ends_with($stat['name'], '.jsonl')) {
                        $filename = $stat['name'];
                        break;
                    }
                }

                if ($filename === null) {
                    throw new \Exception("No JSONL file found in zip: {$zipPath}");
                }
            }

            // Read file contents
            $contents = $zip->getFromName($filename);
            if ($contents === false) {
                throw new \Exception("Cannot read {$filename} from zip: {$zipPath}");
            }

            // Parse line by line
            $lines = explode("\n", $contents);
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line)) {
                    continue;
                }

                $record = json_decode($line, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new \Exception("JSON parse error in {$filename}: " . json_last_error_msg());
                }

                yield $record;
            }
        } finally {
            $zip->close();
        }
    }

    /**
     * Get SDE version from _sde.jsonl file.
     *
     * @param string $dataPath Path to static data directory or zip file
     * @return array ['version' => string, 'buildNumber' => int, 'releaseDate' => string]
     * @throws \Exception
     */
    public static function getSdeVersion(string $dataPath): array
    {
        if (is_dir($dataPath)) {
            // Find zip file in directory
            $files = glob($dataPath . '/*.zip');
            if (empty($files)) {
                throw new \Exception("No zip file found in directory: {$dataPath}");
            }
            $sdePath = $files[0];
        } elseif (str_ends_with($dataPath, '.zip')) {
            $sdePath = $dataPath;
        } else {
            throw new \Exception("Invalid data path: {$dataPath}");
        }

        // Parse _sde.jsonl from zip
        $zip = new \ZipArchive();
        if ($zip->open($sdePath) !== true) {
            throw new \Exception("Cannot open zip file: {$sdePath}");
        }

        $contents = $zip->getFromName('_sde.jsonl');
        $zip->close();

        if ($contents === false) {
            throw new \Exception("_sde.jsonl not found in zip file");
        }

        $line = trim(explode("\n", $contents)[0]);
        $sdeInfo = json_decode($line, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception("JSON parse error in _sde.jsonl: " . json_last_error_msg());
        }

        return [
            'version' => 'sde-' . $sdeInfo['buildNumber'],
            'buildNumber' => $sdeInfo['buildNumber'],
            'releaseDate' => $sdeInfo['releaseDate'],
        ];
    }

    /**
     * List all JSONL files in a zip archive.
     *
     * @param string $zipPath
     * @return array
     * @throws \Exception
     */
    public static function listJsonlFiles(string $zipPath): array
    {
        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new \Exception("Cannot open zip file: {$zipPath}");
        }

        $files = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            if (str_ends_with($stat['name'], '.jsonl')) {
                $files[] = $stat['name'];
            }
        }

        $zip->close();
        return $files;
    }
}
