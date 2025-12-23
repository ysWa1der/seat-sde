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

return [
    /*
    |--------------------------------------------------------------------------
    | Local SDE Data Path
    |--------------------------------------------------------------------------
    |
    | Path to the CCP static data zip file or extracted directory.
    | This should point to the eve-online-static-data-*.jsonl.zip file.
    |
    | Default: Plugin's storage/sde directory in vendor
    | Override: Set LOCAL_SDE_PATH environment variable for custom path
    |
    */
    'data_path' => env('LOCAL_SDE_PATH', base_path('vendor/local-sde/seat-local-sde/storage/sde')),

    /*
    |--------------------------------------------------------------------------
    | SDE Table Mappings
    |--------------------------------------------------------------------------
    |
    | Mapping between CCP JSONL files and SeAT database tables.
    | Format: 'jsonl_filename' => 'seat_table_name'
    |
    */
    'table_mappings' => [
        // Inventory
        'types.jsonl' => 'invTypes',
        'groups.jsonl' => 'invGroups',
        'categories.jsonl' => 'invCategories',  // 🆕 Top-level item categories
        'marketGroups.jsonl' => 'invMarketGroups',
        'typeMaterials.jsonl' => 'invTypeMaterials',
        'controlTowerResources.jsonl' => 'invControlTowerResources',
        'controlTowerResourcePurposes.jsonl' => 'invControlTowerResourcePurposes',
        'metaGroups.jsonl' => 'invMetaGroups',  // 🆕 T1/T2/Officer/etc
        'types:meta.jsonl' => 'invMetaTypes',
        'flags.jsonl' => 'invFlags',
        'contrabandTypes.jsonl' => 'invContrabandTypes',  // 🆕 Contraband by faction
        'typeReactions.jsonl' => 'invTypeReactions',

        // Character/Factions
        'factions.jsonl' => 'chrFactions',  // 🆕 Empire factions

        // Map/Universe
        'mapRegions.jsonl' => 'mapDenormalize',
        'mapConstellations.jsonl' => 'mapDenormalize',
        'mapSolarSystems.jsonl' => 'mapDenormalize',
        'mapStars.jsonl' => 'mapDenormalize',
        'mapPlanets.jsonl' => 'mapDenormalize',
        'mapMoons.jsonl' => 'mapDenormalize',

        // Stations
        'npcStations.jsonl' => 'staStations',

        // Dogma (attributes/effects)
        'dogmaAttributes.jsonl' => 'dgmAttributeTypes',
        'dogmaEffects.jsonl' => 'dgmEffects',
        'typeDogma.jsonl' => 'dgmTypeAttributes',
        'typeDogma:effects.jsonl' => 'dgmTypeEffects',  // 🆕 Extract dogmaEffects field

        // Industry
        'corporationActivities.jsonl' => 'ramActivities',

        // Planetary Interaction
        'planetSchematics.jsonl' => 'universe_schematics',  // 🆕 PI schematics (partial)
    ],

    /*
    |--------------------------------------------------------------------------
    | Import Options
    |--------------------------------------------------------------------------
    */
    'chunk_size' => env('LOCAL_SDE_CHUNK_SIZE', 1000),  // Records per batch insert
    'memory_limit' => env('LOCAL_SDE_MEMORY_LIMIT', '2048M'),  // PHP memory limit during import
];
