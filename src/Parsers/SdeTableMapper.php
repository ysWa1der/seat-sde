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

/**
 * SdeTableMapper
 *
 * Maps CCP JSONL records to SeAT database table format.
 * Handles field name conversions and data transformations.
 */
class SdeTableMapper
{
    /**
     * Map a CCP JSONL record to SeAT table format.
     *
     * @param string $jsonlFile The JSONL filename (e.g., 'types.jsonl')
     * @param array $record The parsed JSONL record
     * @return array|null Mapped record for database insertion, or null to skip
     */
    public static function map(string $jsonlFile, array $record): ?array
    {
        if ($jsonlFile === 'types:meta.jsonl') {
            return self::mapTypesMeta($record);
        }

        if (str_starts_with($jsonlFile, 'map')) {
            return self::mapMapDenormalize($jsonlFile, $record);
        }

        $method = 'map' . str_replace('.jsonl', '', ucfirst($jsonlFile));
        $method = str_replace(['Map', 'Npc', ':'], ['map', 'npc', ''], $method);

        if (method_exists(self::class, $method)) {
            return self::$method($record);
        }

        // Default: pass through with minimal transformation
        return self::defaultMapping($record);
    }

    /**
     * Map types.jsonl to invTypes table.
     */
    private static function mapTypes(array $record): ?array
    {
        // Skip unpublished types if needed
        // if (!($record['published'] ?? false)) {
        //     return null;
        // }

        return [
            'typeID' => $record['_key'],
            'groupID' => $record['groupID'] ?? null,
            'typeName' => self::extractEnglishName($record['name'] ?? []),
            'description' => self::extractEnglishName($record['description'] ?? []),
            'mass' => $record['mass'] ?? null,
            'volume' => $record['volume'] ?? null,
            'capacity' => $record['capacity'] ?? null,
            'portionSize' => $record['portionSize'] ?? 1,
            'raceID' => $record['raceID'] ?? null,
            'basePrice' => $record['basePrice'] ?? null,
            'published' => $record['published'] ?? false,
            'marketGroupID' => $record['marketGroupID'] ?? null,
            'iconID' => $record['iconID'] ?? null,
            'soundID' => $record['soundID'] ?? null,
            'graphicID' => $record['graphicID'] ?? null,
        ];
    }

    /**
     * Map groups.jsonl to invGroups table.
     */
    private static function mapGroups(array $record): ?array
    {
        return [
            'groupID' => $record['_key'],
            'categoryID' => $record['categoryID'] ?? null,
            'groupName' => self::extractEnglishName($record['name'] ?? []),
            'iconID' => $record['iconID'] ?? null,
            'useBasePrice' => $record['useBasePrice'] ?? false,
            'anchored' => $record['anchored'] ?? false,
            'anchorable' => $record['anchorable'] ?? false,
            'fittableNonSingleton' => $record['fittableNonSingleton'] ?? false,
            'published' => $record['published'] ?? false,
        ];
    }

    /**
     * Map marketGroups.jsonl to invMarketGroups table.
     */
    private static function mapMarketGroups(array $record): ?array
    {
        return [
            'marketGroupID' => $record['_key'],
            'parentGroupID' => $record['parentGroupID'] ?? null,
            'marketGroupName' => self::extractEnglishName($record['name'] ?? []),
            'description' => self::extractEnglishName($record['description'] ?? []),
            'iconID' => $record['iconID'] ?? null,
            'hasTypes' => $record['hasTypes'] ?? false,
        ];
    }

    /**
     * Map typeMaterials.jsonl to invTypeMaterials table.
     *
     * NOTE: This returns NULL because typeMaterials has nested structure
     * and must be handled specially in the import command.
     */
    private static function mapTypeMaterials(array $record): ?array
    {
        // typeMaterials.jsonl has structure: {"_key": typeID, "materials": [{materialTypeID, quantity}, ...]}
        // This must be exploded into multiple rows - handled in importTable()
        return null;
    }

    /**
     * Map controlTowerResources.jsonl to invControlTowerResources table.
     */
    private static function mapControlTowerResources(array $record): ?array
    {
        return [
            'controlTowerTypeID' => $record['_key'],
            'resourceTypeID' => $record['resourceTypeID'] ?? null,
            'purpose' => $record['purpose'] ?? null,
            'quantity' => $record['quantity'] ?? null,
            'minSecurityLevel' => $record['minSecurityLevel'] ?? null,
            'factionID' => $record['factionID'] ?? null,
        ];
    }

    /**
     * Map npcStations.jsonl to staStations table.
     */
    private static function mapNpcStations(array $record): ?array
    {
        return [
            'stationID' => $record['_key'],
            'security' => $record['security'] ?? null,
            'dockingCostPerVolume' => $record['dockingCostPerVolume'] ?? null,
            'maxShipVolumeDockable' => $record['maxShipVolumeDockable'] ?? null,
            'officeRentalCost' => $record['officeRentalCost'] ?? null,
            'operationID' => $record['operationID'] ?? null,
            'stationTypeID' => $record['stationTypeID'] ?? null,
            'corporationID' => $record['corporationID'] ?? null,
            'solarSystemID' => $record['solarSystemID'] ?? null,
            'constellationID' => $record['constellationID'] ?? null,
            'regionID' => $record['regionID'] ?? null,
            'stationName' => self::extractEnglishName($record['name'] ?? []),
            'x' => $record['position']['x'] ?? null,
            'y' => $record['position']['y'] ?? null,
            'z' => $record['position']['z'] ?? null,
            'reprocessingEfficiency' => $record['reprocessingEfficiency'] ?? null,
            'reprocessingStationsTake' => $record['reprocessingStationsTake'] ?? null,
            'reprocessingHangarFlag' => $record['reprocessingHangarFlag'] ?? null,
        ];
    }

    /**
     * Map typeDogma.jsonl to dgmTypeAttributes table.
     */
    private static function mapTypeDogma(array $record): ?array
    {
        // typeDogma has nested structure: typeID with array of attributes
        // Need to explode this into multiple rows
        // This will be handled in the command, not here
        return null;
    }

    /**
     * Map corporationActivities.jsonl to ramActivities table.
     */
    private static function mapCorporationActivities(array $record): ?array
    {
        return [
            'activityID' => $record['_key'],
            'activityName' => self::extractEnglishName($record['name'] ?? []),
            'iconNo' => $record['iconNo'] ?? null,
            'description' => self::extractEnglishName($record['description'] ?? []),
            'published' => $record['published'] ?? false,
        ];
    }

    /**
     * Map factions.jsonl to chrFactions table.
     */
    private static function mapFactions(array $record): ?array
    {
        return [
            'factionID' => $record['_key'],
            'factionName' => self::extractEnglishName($record['name'] ?? []),
            'description' => self::extractEnglishName($record['description'] ?? []),
            'solarSystemID' => $record['solarSystemID'] ?? null,
            'corporationID' => $record['corporationID'] ?? null,
            'sizeFactor' => $record['sizeFactor'] ?? null,
            'stationCount' => $record['stationCount'] ?? null,
            'stationSystemCount' => $record['stationSystemCount'] ?? null,
            'militiaCorporationID' => $record['militiaCorporationID'] ?? null,
            'iconID' => $record['iconID'] ?? null,
        ];
    }

    /**
     * Map categories.jsonl to invCategories table.
     */
    private static function mapCategories(array $record): ?array
    {
        return [
            'categoryID' => $record['_key'],
            'categoryName' => self::extractEnglishName($record['name'] ?? []),
            'iconID' => $record['iconID'] ?? null,
            'published' => $record['published'] ?? false,
        ];
    }

    /**
     * Map metaGroups.jsonl to invMetaGroups table.
     */
    private static function mapMetaGroups(array $record): ?array
    {
        return [
            'metaGroupID' => $record['_key'],
            'metaGroupName' => self::extractEnglishName($record['name'] ?? []),
            'description' => self::extractEnglishName($record['description'] ?? []),
            'iconID' => $record['iconID'] ?? null,
        ];
    }

    /**
     * Map contrabandTypes.jsonl to invContrabandTypes table.
     *
     * NOTE: This has nested structure and needs special handling.
     * Format: {"_key": typeID, "factions": [{"_key": factionID, ...}, ...]}
     */
    private static function mapContrabandTypes(array $record): ?array
    {
        // Nested structure - handled in import command
        return null;
    }

    /**
     * Map typeReactions.jsonl to invTypeReactions table.
     *
     * NOTE: This has a nested structure (inputs and outputs) and needs special handling.
     */
    private static function mapTypeReactions(array $record): ?array
    {
        // Nested structure - handled in import command
        return null;
    }

    /**
     * Map typeDogma.jsonl (dogmaEffects field) to dgmTypeEffects table.
     *
     * Special handler for extracting dogmaEffects from typeDogma.jsonl
     * Format: {"_key": typeID, "dogmaEffects": [{"effectID": ..., "isDefault": ...}, ...]}
     */
    private static function mapTypeDogmaEffects(array $record): ?array
    {
        // Extract dogmaEffects field - handled in import command
        return null;
    }

    /**
     * Map types.jsonl (metaGroupID field) to invMetaTypes table.
     */
    private static function mapTypesMeta(array $record): ?array
    {
        if (!isset($record['metaGroupID'])) {
            return null;
        }

        return [
            'typeID' => $record['_key'],
            'parentTypeID' => $record['_key'], // Simplified: parent is self
            'metaGroupID' => $record['metaGroupID'],
        ];
    }

    /**
     * Map flags.jsonl to invFlags table.
     */
    private static function mapFlags(array $record): ?array
    {
        return [
            'flagID' => $record['_key'],
            'flagName' => self::extractEnglishName($record['name'] ?? []),
            'flagText' => self::extractEnglishName($record['text'] ?? []),
            'orderID' => $record['order'] ?? null,
        ];
    }

    /**
     * Map dogmaAttributes.jsonl to dgmAttributeTypes table.
     */
    private static function mapDogmaAttributes(array $record): ?array
    {
        return [
            'attributeID' => $record['_key'],
            'attributeName' => self::extractEnglishName($record['name'] ?? []),
            'description' => self::extractEnglishName($record['description'] ?? []),
            'iconID' => $record['iconID'] ?? null,
            'defaultValue' => $record['defaultValue'] ?? null,
            'published' => $record['published'] ?? false,
            'displayName' => self::extractEnglishName($record['displayName'] ?? []),
            'unitID' => $record['unitID'] ?? null,
            'stackable' => $record['stackable'] ?? false,
            'highIsGood' => $record['highIsGood'] ?? false,
            'categoryID' => $record['categoryID'] ?? null,
        ];
    }

    /**
     * Map dogmaEffects.jsonl to dgmEffects table.
     */
    private static function mapDogmaEffects(array $record): ?array
    {
        return [
            'effectID' => $record['_key'],
            'effectName' => self::extractEnglishName($record['name'] ?? []),
            'effectCategory' => $record['category'] ?? null,
            'preExpression' => $record['preExpression'] ?? null,
            'postExpression' => $record['postExpression'] ?? null,
            'description' => self::extractEnglishName($record['description'] ?? []),
            'guid' => $record['guid'] ?? null,
            'iconID' => $record['iconID'] ?? null,
            'isOffensive' => $record['isOffensive'] ?? false,
            'isAssistance' => $record['isAssistance'] ?? false,
            'durationAttributeID' => $record['durationAttributeID'] ?? null,
            'trackingSpeedAttributeID' => $record['trackingSpeedAttributeID'] ?? null,
            'dischargeAttributeID' => $record['dischargeAttributeID'] ?? null,
            'rangeAttributeID' => $record['rangeAttributeID'] ?? null,
            'falloffAttributeID' => $record['falloffAttributeID'] ?? null,
            'disallowAutoRepeat' => $record['disallowAutoRepeat'] ?? false,
            'published' => $record['published'] ?? false,
            'displayName' => self::extractEnglishName($record['displayName'] ?? []),
            'isWarpSafe' => $record['isWarpSafe'] ?? false,
            'rangeChance' => $record['rangeChance'] ?? false,
            'electronicChance' => $record['electronicChance'] ?? false,
            'propulsionChance' => $record['propulsionChance'] ?? false,
            'distribution' => $record['distribution'] ?? null,
            'sfxName' => $record['sfxName'] ?? null,
            'npcUsageChanceAttributeID' => $record['npcUsageChanceAttributeID'] ?? null,
            'npcActivationChanceAttributeID' => $record['npcActivationChanceAttributeID'] ?? null,
            'fittingUsageChanceAttributeID' => $record['fittingUsageChanceAttributeID'] ?? null,
            'modifierInfo' => json_encode($record['modifierInfo'] ?? null), // Assuming this is JSON or null
        ];
    }

    /**
     * Map controlTowerResourcePurposes.jsonl to invControlTowerResourcePurposes table.
     */
    private static function mapControlTowerResourcePurposes(array $record): ?array
    {
        return [
            'purpose' => $record['_key'],
            'purposeText' => self::extractEnglishName($record['name'] ?? []),
        ];
    }

    /**
     * Map planetSchematics.jsonl to universe_schematics table.
     *
     * PARTIAL IMPLEMENTATION: Only maps basic schematic info (id, cycle time, name, output type).
     * Does NOT map: pins array, input materials (types with isInput:true)
     *
     * Format: {"_key": schematicID, "cycleTime": int, "name": {...}, "types": [...], "pins": [...]}
     */
    private static function mapPlanetSchematics(array $record): ?array
    {
        // Extract output type (isInput: false)
        $outputTypeId = null;
        if (isset($record['types']) && is_array($record['types'])) {
            foreach ($record['types'] as $type) {
                if (isset($type['isInput']) && $type['isInput'] === false) {
                    $outputTypeId = $type['_key'] ?? null;
                    break;
                }
            }
        }

        return [
            'schematic_id' => $record['_key'],
            'cycle_time' => $record['cycleTime'] ?? null,
            'schematic_name' => self::extractEnglishName($record['name'] ?? []),
            'type_id' => $outputTypeId,
        ];
    }

    /**
     * Default mapping: convert _key to ID and extract English names.
     */
    private static function defaultMapping(array $record): array
    {
        $mapped = [];

        foreach ($record as $key => $value) {
            if ($key === '_key') {
                // Try to detect the ID field name from context
                $mapped['id'] = $value;
            } elseif (is_array($value) && isset($value['en'])) {
                // Multi-language field, extract English
                $mapped[$key] = self::extractEnglishName($value);
            } else {
                $mapped[$key] = $value;
            }
        }

        return $mapped;
    }

    /**
     * Extract English name from multi-language field.
     *
     * @param array|string $nameField
     * @return string|null
     */
    private static function extractEnglishName($nameField): ?string
    {
        if (is_string($nameField)) {
            return $nameField;
        }

        if (is_array($nameField)) {
            return $nameField['en'] ?? $nameField['de'] ?? $nameField['fr'] ?? null;
        }

        return null;
    }

    private static function mapMapDenormalize(string $jsonlFile, array $record): ?array
    {
        switch ($jsonlFile) {
            case 'mapRegions.jsonl':
                return self::mapMapRegions($record);
            case 'mapConstellations.jsonl':
                return self::mapMapConstellations($record);
            case 'mapSolarSystems.jsonl':
                return self::mapMapSolarSystems($record);
            case 'mapStars.jsonl':
                return self::mapMapStars($record);
            case 'mapPlanets.jsonl':
                return self::mapMapPlanets($record);
            case 'mapMoons.jsonl':
                return self::mapMapMoons($record);
            default:
                return null;
        }
    }

    private static function mapMapRegions(array $record): array
    {
        $region_id = $record['_key'];
        return [
            'itemID' => $region_id,
            'typeID' => $region_id,
            'groupID' => 3, // Region
            'regionID' => $region_id,
            'itemName' => self::extractEnglishName($record['name'] ?? []),
            'x' => $record['position']['x'] ?? 0.0,
            'y' => $record['position']['y'] ?? 0.0,
            'z' => $record['position']['z'] ?? 0.0,
        ];
    }

    private static function mapMapConstellations(array $record): array
    {
        $constellation_id = $record['_key'];
        return [
            'itemID' => $constellation_id,
            'typeID' => $constellation_id,
            'groupID' => 4, // Constellation
            'regionID' => $record['regionID'] ?? null,
            'constellationID' => $constellation_id,
            'itemName' => self::extractEnglishName($record['name'] ?? []),
            'x' => $record['position']['x'] ?? 0.0,
            'y' => $record['position']['y'] ?? 0.0,
            'z' => $record['position']['z'] ?? 0.0,
        ];
    }

    private static function mapMapSolarSystems(array $record): array
    {
        $system_id = $record['_key'];
        return [
            'itemID' => $system_id,
            'typeID' => $record['star']['typeID'] ?? $system_id,
            'groupID' => 5, // Solar System
            'regionID' => $record['regionID'] ?? null,
            'constellationID' => $record['constellationID'] ?? null,
            'solarSystemID' => $system_id,
            'itemName' => self::extractEnglishName($record['name'] ?? []),
            'x' => $record['position']['x'] ?? 0.0,
            'y' => $record['position']['y'] ?? 0.0,
            'z' => $record['position']['z'] ?? 0.0,
            'security' => $record['security'] ?? 0.0,
        ];
    }

    private static function mapMapStars(array $record): array
    {
        $star_id = $record['_key'];
        return [
            'itemID' => $star_id,
            'typeID' => $record['typeID'] ?? null,
            'groupID' => 6, // Star
            'solarSystemID' => $record['solarSystemID'] ?? null,
            'itemName' => self::extractEnglishName($record['name'] ?? []),
            'radius' => $record['radius'] ?? null,
        ];
    }

    private static function mapMapPlanets(array $record): array
    {
        $planet_id = $record['_key'];
        return [
            'itemID' => $planet_id,
            'typeID' => $record['typeID'] ?? null,
            'groupID' => 7, // Planet
            'solarSystemID' => $record['solarSystemID'] ?? null,
            'itemName' => self::extractEnglishName($record['name'] ?? []),
        ];
    }

    private static function mapMapMoons(array $record): array
    {
        $moon_id = $record['_key'];
        return [
            'itemID' => $moon_id,
            'typeID' => $record['typeID'] ?? null,
            'groupID' => 8, // Moon
            'solarSystemID' => $record['solarSystemID'] ?? null,
            'itemName' => self::extractEnglishName($record['name'] ?? []),
            'x' => $record['position']['x'] ?? 0.0,
            'y' => $record['position']['y'] ?? 0.0,
            'z' => $record['position']['z'] ?? 0.0,
        ];
    }
}