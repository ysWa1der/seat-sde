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

use LocalSde\SeatLocalSde\Parsers\SdeTableMapper;
use LocalSde\SeatLocalSde\Tests\TestCase;

/**
 * Tests for SdeTableMapper class.
 */
class SdeTableMapperTest extends TestCase
{
    /**
     * Test mapping types.jsonl record.
     */
    public function testMapTypes(): void
    {
        $record = [
            '_key' => 34,
            'groupID' => 18,
            'name' => ['en' => 'Tritanium'],
            'description' => ['en' => 'A valuable mineral'],
            'mass' => 0.01,
            'volume' => 0.01,
            'capacity' => 0.0,
            'portionSize' => 1,
            'basePrice' => 0.0,
            'published' => true,
            'marketGroupID' => 1857,
            'iconID' => 402,
            'graphicID' => 402,
        ];

        $mapped = SdeTableMapper::map('types.jsonl', $record);

        $this->assertIsArray($mapped);
        $this->assertEquals(34, $mapped['typeID']);
        $this->assertEquals(18, $mapped['groupID']);
        $this->assertEquals('Tritanium', $mapped['typeName']);
        $this->assertEquals('A valuable mineral', $mapped['description']);
        $this->assertEquals(0.01, $mapped['mass']);
        $this->assertEquals(0.01, $mapped['volume']);
        $this->assertTrue($mapped['published']);
    }

    /**
     * Test mapping categories.jsonl record.
     */
    public function testMapCategories(): void
    {
        $record = [
            '_key' => 6,
            'name' => ['en' => 'Ship'],
            'published' => true,
            'iconID' => 123,
        ];

        $mapped = SdeTableMapper::map('categories.jsonl', $record);

        $this->assertIsArray($mapped);
        $this->assertEquals(6, $mapped['categoryID']);
        $this->assertEquals('Ship', $mapped['categoryName']);
        $this->assertTrue($mapped['published']);
    }

    /**
     * Test mapping groups.jsonl record.
     */
    public function testMapGroups(): void
    {
        $record = [
            '_key' => 25,
            'categoryID' => 6,
            'name' => ['en' => 'Frigate'],
            'published' => true,
            'useBasePrice' => false,
            'anchored' => false,
            'anchorable' => false,
            'fittableNonSingleton' => true,
        ];

        $mapped = SdeTableMapper::map('groups.jsonl', $record);

        $this->assertIsArray($mapped);
        $this->assertEquals(25, $mapped['groupID']);
        $this->assertEquals(6, $mapped['categoryID']);
        $this->assertEquals('Frigate', $mapped['groupName']);
        $this->assertTrue($mapped['published']);
        $this->assertTrue($mapped['fittableNonSingleton']);
    }

    /**
     * Test mapping marketGroups.jsonl record.
     */
    public function testMapMarketGroups(): void
    {
        $record = [
            '_key' => 1857,
            'parentGroupID' => 1856,
            'name' => ['en' => 'Minerals'],
            'description' => ['en' => 'Raw minerals'],
            'iconID' => 22,
            'hasTypes' => true,
        ];

        $mapped = SdeTableMapper::map('marketGroups.jsonl', $record);

        $this->assertIsArray($mapped);
        $this->assertEquals(1857, $mapped['marketGroupID']);
        $this->assertEquals(1856, $mapped['parentGroupID']);
        $this->assertEquals('Minerals', $mapped['marketGroupName']);
        $this->assertEquals('Raw minerals', $mapped['description']);
        $this->assertTrue($mapped['hasTypes']);
    }

    /**
     * Test mapping factions.jsonl record.
     */
    public function testMapFactions(): void
    {
        $record = [
            '_key' => 500001,
            'name' => ['en' => 'Caldari State'],
            'description' => ['en' => 'A powerful faction'],
            'solarSystemID' => 30000142,
            'corporationID' => 1000035,
            'sizeFactor' => 5.0,
            'stationCount' => 100,
            'stationSystemCount' => 50,
        ];

        $mapped = SdeTableMapper::map('factions.jsonl', $record);

        $this->assertIsArray($mapped);
        $this->assertEquals(500001, $mapped['factionID']);
        $this->assertEquals('Caldari State', $mapped['factionName']);
        $this->assertEquals('A powerful faction', $mapped['description']);
        $this->assertEquals(30000142, $mapped['solarSystemID']);
    }

    /**
     * Test extracting English names from multilingual fields.
     */
    public function testExtractEnglishName(): void
    {
        $record = [
            '_key' => 1,
            'name' => [
                'en' => 'English Name',
                'de' => 'Deutscher Name',
                'fr' => 'Nom français',
            ],
        ];

        $mapped = SdeTableMapper::map('categories.jsonl', $record);

        $this->assertEquals('English Name', $mapped['categoryName']);
    }

    /**
     * Test handling missing fields with defaults.
     */
    public function testMissingFieldsUseDefaults(): void
    {
        $record = [
            '_key' => 34,
            'name' => ['en' => 'Test Item'],
        ];

        $mapped = SdeTableMapper::map('types.jsonl', $record);

        $this->assertIsArray($mapped);
        $this->assertEquals(34, $mapped['typeID']);
        $this->assertEquals('Test Item', $mapped['typeName']);
        $this->assertNull($mapped['groupID']);
        $this->assertNull($mapped['mass']);
        $this->assertFalse($mapped['published']);
    }

    /**
     * Test mapping dogmaAttributes.jsonl record.
     */
    public function testMapDogmaAttributes(): void
    {
        $record = [
            '_key' => 5,
            'name' => ['en' => 'CPU Output'],
            'description' => ['en' => 'CPU power output'],
            'defaultValue' => 0.0,
            'published' => true,
            'displayName' => ['en' => 'CPU'],
            'unitID' => 114,
            'stackable' => true,
            'highIsGood' => true,
        ];

        $mapped = SdeTableMapper::map('dogmaAttributes.jsonl', $record);

        $this->assertIsArray($mapped);
        $this->assertEquals(5, $mapped['attributeID']);
        $this->assertEquals('CPU Output', $mapped['attributeName']);
        $this->assertEquals('CPU', $mapped['displayName']);
        $this->assertTrue($mapped['stackable']);
        $this->assertTrue($mapped['highIsGood']);
    }

    /**
     * Test mapping dogmaEffects.jsonl record.
     */
    public function testMapDogmaEffects(): void
    {
        $record = [
            '_key' => 10,
            'name' => ['en' => 'Damage Effect'],
            'description' => ['en' => 'Applies damage'],
            'category' => 0,
            'published' => true,
            'displayName' => ['en' => 'Damage'],
            'isOffensive' => true,
            'isAssistance' => false,
            'isWarpSafe' => false,
            'rangeChance' => true,
        ];

        $mapped = SdeTableMapper::map('dogmaEffects.jsonl', $record);

        $this->assertIsArray($mapped);
        $this->assertEquals(10, $mapped['effectID']);
        $this->assertEquals('Damage Effect', $mapped['effectName']);
        $this->assertEquals('Damage', $mapped['displayName']);
        $this->assertTrue($mapped['isOffensive']);
        $this->assertFalse($mapped['isAssistance']);
    }

    /**
     * Test mapping npcStations.jsonl record.
     */
    public function testMapNpcStations(): void
    {
        $record = [
            '_key' => 60000001,
            'name' => ['en' => 'Jita IV - Moon 4 - Caldari Navy Assembly Plant'],
            'typeID' => 52678,
            'solarSystemID' => 30000142,
            'constellationID' => 20000020,
            'regionID' => 10000002,
            'position' => [
                'x' => 1234.5,
                'y' => 6789.0,
                'z' => -4321.5,
            ],
            'reprocessingEfficiency' => 0.5,
            'reprocessingStationsTake' => 0.05,
        ];

        $mapped = SdeTableMapper::map('npcStations.jsonl', $record);

        $this->assertIsArray($mapped);
        $this->assertEquals(60000001, $mapped['stationID']);
        $this->assertEquals('Jita IV - Moon 4 - Caldari Navy Assembly Plant', $mapped['stationName']);
        $this->assertEquals(52678, $mapped['stationTypeID']);
        $this->assertEquals(30000142, $mapped['solarSystemID']);
        $this->assertEquals(1234.5, $mapped['x']);
        $this->assertEquals(0.5, $mapped['reprocessingEfficiency']);
    }

    /**
     * Test default mapping for unknown file types.
     */
    public function testDefaultMapping(): void
    {
        $record = [
            '_key' => 123,
            'someField' => 'value',
            'anotherField' => 456,
        ];

        $mapped = SdeTableMapper::map('unknownFile.jsonl', $record);

        $this->assertIsArray($mapped);
        $this->assertArrayHasKey('id', $mapped);
        $this->assertEquals(123, $mapped['id']);
        $this->assertArrayHasKey('someField', $mapped);
        $this->assertArrayHasKey('anotherField', $mapped);
    }

    /**
     * Test mapping types:meta.jsonl special case.
     */
    public function testMapTypesMeta(): void
    {
        $record = [
            '_key' => 34,
            'metaGroupID' => 1,
        ];

        $mapped = SdeTableMapper::map('types:meta.jsonl', $record);

        $this->assertIsArray($mapped);
        $this->assertEquals(34, $mapped['typeID']);
        $this->assertEquals(1, $mapped['metaGroupID']);
    }

    /**
     * Test mapping map files (regions, constellations, etc.).
     */
    public function testMapRegions(): void
    {
        $record = [
            '_key' => 10000002,
            'name' => ['en' => 'The Forge'],
            'position' => [
                'x' => 1000.0,
                'y' => 2000.0,
                'z' => 3000.0,
            ],
        ];

        $mapped = SdeTableMapper::map('mapRegions.jsonl', $record);

        $this->assertIsArray($mapped);
        $this->assertEquals(10000002, $mapped['itemID']);
        $this->assertEquals(10000002, $mapped['regionID']);
        $this->assertEquals('The Forge', $mapped['itemName']);
        $this->assertEquals(3, $mapped['groupID']); // Region group
        $this->assertEquals(1000.0, $mapped['x']);
    }
}
