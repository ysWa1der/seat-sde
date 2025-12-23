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

namespace LocalSde\SeatLocalSde\Tests\Unit;

use LocalSde\SeatLocalSde\Parsers\JsonlParser;
use LocalSde\SeatLocalSde\Tests\TestCase;

/**
 * Tests for JsonlParser class.
 */
class JsonlParserTest extends TestCase
{
    /**
     * Test parsing a regular JSONL file.
     */
    public function testParseJsonlFile(): void
    {
        $filePath = $this->getFixturePath('types.jsonl');
        $records = iterator_to_array(JsonlParser::parse($filePath));

        $this->assertCount(3, $records);
        $this->assertEquals(34, $records[0]['_key']);
        $this->assertEquals('Tritanium', $records[0]['name']['en']);
        $this->assertEquals(35, $records[1]['_key']);
        $this->assertEquals('Pyerite', $records[1]['name']['en']);
        $this->assertEquals(36, $records[2]['_key']);
        $this->assertEquals('Mexallon', $records[2]['name']['en']);
    }

    /**
     * Test parsing from a zip file.
     */
    public function testParseFromZip(): void
    {
        $zipPath = $this->getFixturePath('test-sde.zip');
        $records = iterator_to_array(JsonlParser::parse($zipPath));

        $this->assertNotEmpty($records);
        $this->assertArrayHasKey('_key', $records[0]);
    }

    /**
     * Test parsing non-existent file throws exception.
     */
    public function testParseNonExistentFileThrowsException(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('File not found');

        iterator_to_array(JsonlParser::parse('/non/existent/file.jsonl'));
    }

    /**
     * Test getting SDE version from zip file.
     */
    public function testGetSdeVersionFromZip(): void
    {
        $zipPath = $this->getFixturePath('test-sde.zip');
        $version = JsonlParser::getSdeVersion($zipPath);

        $this->assertIsArray($version);
        $this->assertArrayHasKey('version', $version);
        $this->assertArrayHasKey('buildNumber', $version);
        $this->assertArrayHasKey('releaseDate', $version);
        $this->assertEquals('sde-3142455', $version['version']);
        $this->assertEquals(3142455, $version['buildNumber']);
        $this->assertEquals('2024-12-10', $version['releaseDate']);
    }

    /**
     * Test getting SDE version from directory with zip file.
     */
    public function testGetSdeVersionFromDirectory(): void
    {
        $dirPath = $this->getFixturePath('');
        $version = JsonlParser::getSdeVersion($dirPath);

        $this->assertIsArray($version);
        $this->assertEquals('sde-3142455', $version['version']);
        $this->assertEquals(3142455, $version['buildNumber']);
    }

    /**
     * Test listing JSONL files in zip.
     */
    public function testListJsonlFiles(): void
    {
        $zipPath = $this->getFixturePath('test-sde.zip');
        $files = JsonlParser::listJsonlFiles($zipPath);

        $this->assertIsArray($files);
        $this->assertContains('_sde.jsonl', $files);
        $this->assertContains('types.jsonl', $files);
    }

    /**
     * Test parsing empty lines are skipped.
     */
    public function testParseSkipsEmptyLines(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tempFile, "{'_key': 1, 'name': 'Test'}\n\n\n{'_key': 2, 'name': 'Test2'}\n");

        try {
            $records = iterator_to_array(JsonlParser::parse($tempFile));
            $this->assertCount(2, $records);
        } finally {
            @unlink($tempFile);
        }
    }

    /**
     * Test parsing invalid JSON throws exception.
     */
    public function testParseInvalidJsonThrowsException(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tempFile, "{'invalid json without closing brace\n");

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('JSON parse error');

        try {
            iterator_to_array(JsonlParser::parse($tempFile));
        } finally {
            @unlink($tempFile);
        }
    }

    /**
     * Test getting version from invalid path throws exception.
     */
    public function testGetSdeVersionInvalidPathThrowsException(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid data path');

        JsonlParser::getSdeVersion('/invalid/path/without/zip');
    }

    /**
     * Test listing files from non-existent zip throws exception.
     */
    public function testListJsonlFilesNonExistentZipThrowsException(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Cannot open zip file');

        JsonlParser::listJsonlFiles('/non/existent/file.zip');
    }
}
