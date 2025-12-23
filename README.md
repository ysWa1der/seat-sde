# SeAT Local SDE Plugin

This plugin allows SeAT to import SDE (Static Data Export) from **local CCP static data files** in JSONL format, instead of relying on Fuzzwork's MySQL dumps.

## Why This Plugin?

Fuzzwork SDE stopped updating on July 7, 2025. This plugin provides an alternative by using CCP's official static data exports directly.

## Features

- ✅ **Auto-scheduler management**: Automatically replaces `eve:update:sde` with `eve:sde:update-all` in scheduler
- ✅ **Self-contained**: All data stored within plugin's `storage/sde` directory
- ✅ **Safe rollback**: Remove plugin to revert to Fuzzwork SDE
- ✅ **One-command update**: `eve:sde:update-all` for automatic check + download + install
- ✅ **Modular commands**: Optional separate check, download, and install steps
- ✅ **Version control**: Download and install specific SDE builds
- ✅ **YAML output**: Machine-readable output for easy parsing and automation
- ✅ **Pipeable**: Progress to stderr, results to stdout for seamless piping
- ✅ **JSONL support**: Parses CCP's official static data format
- ✅ **Memory efficient**: Streams large files instead of loading into memory

## Installation

### 1. Register Plugin

Edit `composer.json`:

```json
{
    "repositories": {
        "local-sde": {
            "type": "path",
            "url": "/var/www/seat/packages/local-sde/seat-local-sde"
        }
    },
    "require": {
        "local-sde/seat-local-sde": "@dev"
    }
}
```

### 2. Install Plugin

Run inside the SeAT container:

```bash
docker exec -it seat50-basic-front bash    # if you use docker
cd /var/www/seat
composer update local-sde/seat-local-sde
php artisan package:discover
```

**Note:** The plugin will automatically:
- Remove `eve:update:sde` from the scheduler
- Add `eve:sde:update-all` to the scheduler (weekly on Sunday at 3:00 AM)

To revert, simply remove the plugin and manually re-add `eve:update:sde` to the scheduler.

## Usage

### Quick Start (Recommended)

For most users, simply run:

```bash
# Automatically check, download, and install latest SDE (all data, including planet-related)
php artisan eve:sde:update-all
```

**Note:** `eve:sde:update-all` performs a full SDE update. If you only want to update non-planet related data, use `php artisan eve:sde:update`. If you only want to update planet related data, use `php artisan eve:sde:update-planet`.

### Advanced Usage

The plugin also provides modular commands for fine-grained control:

### 1. Check SDE Version

Check the latest SDE version available from CCP and compare with installed version:

```bash
php artisan eve:sde:check
```

**Output (YAML):**
```yaml
status: up_to_date
message: Running the latest SDE version
update_available: false
latest:
  build_number: 3142455
  release_date: '2025-12-15T11:14:02Z'
installed:
  version: sde-3142455
  build_number: 3142455
```

**If update is available:**
```yaml
status: update_available
message: A newer version is available
update_available: true
latest:
  build_number: 3142500
  release_date: '2025-12-22T10:00:00Z'
installed:
  version: sde-3142455
  build_number: 3142455
next_steps:
  - 'php artisan eve:sde:download'
  - 'php artisan eve:sde:install'
```

### 2. Download SDE

Download SDE data from CCP's official repository:

```bash
# Download latest version
php artisan eve:sde:download

# Download specific build
php artisan eve:sde:download 3142455

# Force re-download if file exists
php artisan eve:sde:download --force
```

Downloaded files are saved to the plugin's `storage/sde/` directory.

### 3. Install SDE

Install downloaded SDE data into the database:

```bash
# Install non-planet related data
php artisan eve:sde:install

# Install planet-related data
php artisan eve:sde:install-planet
```

### Manual Workflow Example

If you prefer to control each step manually:

```bash
# 1. Check if update is available
docker exec seat50-basic-front php artisan eve:sde:check

# 2. Download latest SDE
docker exec seat50-basic-front php artisan eve:sde:download

# 3. Install to database (non-planet related data only)
docker exec seat50-basic-front php artisan eve:sde:install

# 4. Install planet-related data to database
docker exec seat50-basic-front php artisan eve:sde:install-planet
```

Or use the automatic update commands:

```bash
# All-in-one update (standard data only)
docker exec seat50-basic-front php artisan eve:sde:update

# All-in-one update (planet data only)
docker exec seat50-basic-front php artisan eve:sde:update-planet

# All-in-one update (standard and planet data)
docker exec seat50-basic-front php artisan eve:sde:update-all
```

### Automated Updates

**The plugin automatically manages the scheduler!** On installation, it:
1. Removes `eve:update:sde` from the scheduler
2. Adds `eve:sde:update-all` to scheduler (weekly on Sunday at 3:00 AM)

You can customize the schedule via SeAT's web UI (Configuration → Schedule) or database:

```sql
-- Update schedule expression (e.g., daily instead of weekly)
-- Recommended for a full SDE update including planet data
UPDATE schedules
SET expression = '0 3 * * *'  -- Daily at 3:00 AM
WHERE command = 'eve:sde:update-all';
```

### Pipeline Integration

All commands output YAML to stdout and progress to stderr, making them perfect for automation:

```bash
# Check and parse result with yq
php artisan eve:sde:check | yq '.update_available'
# Output: true

# Get installed build number
php artisan eve:sde:check | yq '.installed.build_number'
# Output: 3142455

# Conditional update in shell script
result=$(php artisan eve:sde:check)
if echo "$result" | yq -e '.update_available == true' > /dev/null; then
    echo "Update available, downloading..."
    php artisan eve:sde:update-all
fi

# Download and capture file info
php artisan eve:sde:download | yq '.file.path'
# Output: /var/www/seat/vendor/local-sde/seat-local-sde/storage/sde/eve-online-static-data-3142455-jsonl.zip

# Silent mode (suppress stderr)
php artisan eve:sde:install 2>/dev/null | yq '.status'
# Output: success
```

## Configuration

Publish configuration (optional):

```bash
php artisan vendor:publish --provider="LocalSde\SeatLocalSde\LocalSdeServiceProvider"
```

Edit `config/local-sde.php`:

```php
return [
    // Default: Plugin's storage/sde directory
    // Override: Set LOCAL_SDE_PATH for custom location
    'data_path' => env('LOCAL_SDE_PATH', dirname(__DIR__, 2) . '/storage/sde'),
    'chunk_size' => env('LOCAL_SDE_CHUNK_SIZE', 1000),
    'memory_limit' => env('LOCAL_SDE_MEMORY_LIMIT', '2048M'),
];
```

## Uninstallation

To remove this plugin and revert to Fuzzwork SDE:

```bash
composer remove local-sde/seat-local-sde
```

The original `php artisan eve:update:sde` command will continue to work unchanged.

## SDE File Support Status

The CCP SDE contains **54 JSONL files**. The plugin separates the import process into two commands:
- `eve:sde:install`: For general game data.
- `eve:sde:install-planet`: For large planetary and moon datasets.

This table shows all files and their mapping status to SeAT database tables.

| SeAT Table | JSONL File | Category | Status | Notes |
|------------|------------|----------|--------|-------|
| `invCategories` | `categories.jsonl` | Inventory | ✅ Supported | Top-level item categories |
| `invContrabandTypes` | `contrabandTypes.jsonl` | Inventory | ✅ Supported | Contraband by faction (nested) |
| `invControlTowerResources` | `controlTowerResources.jsonl` | Structures | ✅ Supported | POS fuel/resources (nested) |
| `ramActivities` | `corporationActivities.jsonl` | Industry | ✅ Supported | Manufacturing activities |
| `chrFactions` | `factions.jsonl` | Factions | ✅ Supported | Empire factions (standings, LP) |
| `invGroups` | `groups.jsonl` | Inventory | ✅ Supported | Item groups |
| `invMarketGroups` | `marketGroups.jsonl` | Market | ✅ Supported | Market categories |
| `invMetaGroups` | `metaGroups.jsonl` | Inventory | ✅ Supported | T1/T2/Officer/Faction meta |
| `invMetaTypes`  | `types.jsonl`, `metaGroups.jsonl` | Inventory | ✅ Supported | Type meta relationships (simplified) |
| `staStations` | `npcStations.jsonl` | Universe | ✅ Supported | NPC station data |
| `dgmAttributeCategories` | `dogmaAttributeCategories.jsonl` | Dogma | ✅ Supported | Attribute categorization metadata. |
| `dgmAttributeTypes` | `dogmaAttributes.jsonl` | Dogma | ✅ Supported | Attributes definitions |
| `dgmEffects` | `dogmaEffects.jsonl` | Dogma | ✅ Supported | Effects definitions |
| `dgmTypeAttributes` | `typeDogma.jsonl` | Dogma | ✅ Supported | Item attributes (nested) |
| `dgmTypeEffects` | `typeDogma.jsonl` (effects) | Dogma | ✅ Supported | Item effects (nested in `typeDogma`) |
| `dgmUnits` | `dogmaUnits.jsonl` | Dogma | ✅ Supported | Unit definitions for attributes. |
| `invTypeMaterials` | `typeMaterials.jsonl` | Industry | ✅ Supported | Reprocessing materials (nested) |
| `invTypes` | `types.jsonl` | Inventory | ✅ Supported | Core item/type definitions |
| `mapDenormalize` | `mapConstellations.jsonl` | Universe | ✅ Supported | Constellations (merged) |
| `mapDenormalize` | `mapRegions.jsonl` | Universe | ✅ Supported | Regions (merged) |
| `mapDenormalize` | `mapSolarSystems.jsonl` | Universe | ✅ Supported | Solar systems (merged) |
| `mapDenormalize` | `mapStars.jsonl` | Universe | ✅ Supported | Stars (merged) |
| `universe_schematics` | `planetSchematics.jsonl` | Planetary | ✅ Supported | PI schematics. Via `eve:sde:install-planet`. |
| `mapDenormalize` | `mapMoons.jsonl` | Universe | ✅ Supported | Moons (merged). Via `eve:sde:install-planet`. |
| `mapDenormalize` | `mapPlanets.jsonl` | Universe | ✅ Supported | Planets (merged). Via `eve:sde:install-planet`. |
| - | `blueprints.jsonl` | Industry | 🔍 Future | Blueprint manufacturing recipes. |
| - | `planetResources.jsonl` | Planetary | 🔍 Future | Planet resource richness. |
| - | `npcCorporations.jsonl` | NPCs | 🔍 Future | NPC corp details for missions/LP. |
| - | `mapStargates.jsonl` | Universe | 🔍 Future | Stargate connections for route planning. |
| - | `mapAsteroidBelts.jsonl` | Universe | 🔍 Future | Asteroid belt locations. |
| - | `stationOperations.jsonl` | Stations | 🔍 Future | Station operation types. |
| - | `stationServices.jsonl` | Stations | 🔍 Future | Station service types. |
| - | `sovereigntyUpgrades.jsonl` | Sovereignty | 🔍 Future | Sovereignty upgrade structures. |
| - | `landmarks.jsonl` | Universe | 🔍 Future | Special universe landmarks. |
| - | `compressibleTypes.jsonl` | Industry | 🔍 Future | Ore compression data. |
| - | `certificates.jsonl` | Character | 🔍 Future | Skill certificates. |
| - | `masteries.jsonl` | Character | 🔍 Future | Ship mastery levels. |
| - | `agentTypes.jsonl` | Agents | 🔍 Future | Agent type definitions. |
| - | `agentsInSpace.jsonl` | Agents | 🔍 Future | Agent location data. |
| - | `typeBonus.jsonl` | Items | 🔍 Future | Item bonus text/descriptions. |
| - | `npcCharacters.jsonl` | NPCs | 🔍 Future | Named NPC characters. |
| - | `npcCorporationDivisions.jsonl` | NPCs | 🔍 Future | NPC corp division structure. |
| - | `freelanceJobSchemas.jsonl` | Career | 🔍 Future | Career agent job schemas. |
| ❌ N/A | `_sde.jsonl` | Meta | ⛔ Not Needed | SDE version metadata only. |
| ❌ N/A | `ancestries.jsonl` | Character | ⛔ Not Needed | Character creation data. |
| ❌ N/A | `bloodlines.jsonl` | Character | ⛔ Not Needed | Character creation data. |
| ❌ N/A | `races.jsonl` | Character | ⛔ Not Needed | Character creation data. |
| ❌ N/A | `characterAttributes.jsonl` | Character | ⛔ Not Needed | Character creation attributes. |
| ❌ N/A | `graphics.jsonl` | Graphics | ⛔ Not Needed | 3D graphics references. |
| ❌ N/A | `icons.jsonl` | Graphics | ⛔ Not Needed | Icon file references (uses CDN). |
| ❌ N/A | `skins.jsonl` | Graphics | ⛔ Not Needed | Ship skin definitions (visual only). |
| ❌ N/A | `skinMaterials.jsonl` | Graphics | ⛔ Not Needed | Skin material definitions (visual only). |
| ❌ N/A | `skinLicenses.jsonl` | Graphics | ⛔ Not Needed | Skin license items (visual only). |
| ❌ N/A | `translationLanguages.jsonl` | Meta | ⛔ Not Needed | Translation language metadata. |
| ❌ N/A | `dynamicItemAttributes.jsonl` | Runtime | ⛔ Not Needed | Dynamic runtime attributes (abyssal items). |
| ❌ N/A | `dbuffCollections.jsonl` | Runtime | ⛔ Not Needed | Dynamic buff collections (runtime only). |

### Other SeAT Tables (Not from SDE)

This plugin focuses on static data from the SDE. The following tables are populated dynamically from the EVE ESI API and are not handled by this plugin.

| SeAT Table | Source | Notes |
|------------|--------|-------|
| `invItems` | ESI | Dynamic item data (assets) |
| `invNames` | ESI | Resolved character/corporation/etc. names |
| `invPositions` | ESI | In-space item locations |
| `invUniqueNames` | ESI | Custom names for ships, structures, etc. |
| `constellations` | SDE | Redundant; data integrated into `mapDenormalize` |
| `moons` | SDE | Redundant; data integrated into `mapDenormalize` |
| `planets` | SDE | Redundant; data integrated into `mapDenormalize` |
| `regions` | SDE | Redundant; data integrated into `mapDenormalize` |
| `solar_systems` | SDE | Redundant; data integrated into `mapDenormalize` |
| `stars` | SDE | Redundant; data integrated into `mapDenormalize` |
| `universe_names` | ESI | Resolved universe item names |
| `invVolumes` | SDE | Redundant; `volume` data is in `invTypes` table |
| `universe_stations` | SDE | Redundant; plugin uses `staStations` |
| `invFlags` | SDE | Missing `flags.jsonl` in SDE zip |
| `invTypeReactions` | SDE | Missing `typeReactions.jsonl` in SDE zip |
| `invControlTowerResourcePurposes` | SDE | Missing `controlTowerResourcePurposes.jsonl` in SDE zip |

**Status Legend**

- ✅ **Supported** (24 files): Fully implemented and working.
- 🔍 **Future** (17 files): Potentially useful or partially implemented.
- ⛔ **Not Needed** (13 files): Not applicable to SeAT's use case.

### 💡 Adding Custom Mappings

Ready-to-implement mappings for existing SeAT tables:

```php
// config/local-sde.php
'table_mappings' => [
    // Core Inventory
    'types.jsonl' => 'invTypes',
    'groups.jsonl' => 'invGroups',
    'categories.jsonl' => 'invCategories',          // ✅ Supported
    'marketGroups.jsonl' => 'invMarketGroups',
    'typeMaterials.jsonl' => 'invTypeMaterials',
    'controlTowerResources.jsonl' => 'invControlTowerResources',
    'metaGroups.jsonl' => 'invMetaGroups',          // ✅ Supported
    'contrabandTypes.jsonl' => 'invContrabandTypes', // ✅ Supported (nested)

    // Factions
    'factions.jsonl' => 'chrFactions',              // ✅ Supported

    // Stations & Structures
    'npcStations.jsonl' => 'staStations',

    // Dogma
    'dogmaAttributeCategories.jsonl' => 'dgmAttributeCategories', // ✅ Supported
    'dogmaAttributes.jsonl' => 'dgmAttributeTypes',
    'dogmaEffects.jsonl' => 'dgmEffects',
    'dogmaUnits.jsonl' => 'dgmUnits',                // ✅ Supported
    'typeDogma.jsonl' => 'dgmTypeAttributes',       // ✅ Supported (dogmaAttributes field)
    'typeDogma:effects.jsonl' => 'dgmTypeEffects',  // ✅ Supported

    // Industry
    'corporationActivities.jsonl' => 'ramActivities',

    // ... add more as needed
],
```

**Implementation Notes:**
- Simple 1:1 mappings work out-of-box
- `typeDogma.jsonl` contains both `dogmaAttributes` and `dogmaEffects` arrays
- `types.jsonl` has embedded `metaGroupID` field
- Complex structures need custom parsing in `JsonlParser.php`

## Troubleshooting

### "No table mapping" Warnings During Installation

If you see warnings like:
```
WARNING: No table mapping for categories.jsonl, skipping
WARNING: No table mapping for metaGroups.jsonl, skipping
WARNING: No table mapping for dogmaAttributes.jsonl, skipping
```

This means the configuration file is not published or cached. The plugin uses `mergeConfigFrom()` which is ignored when config is cached.

**Solution:**

Publish the configuration file with force flag:

```bash
php artisan vendor:publish --provider="LocalSde\SeatLocalSde\LocalSdeServiceProvider" --tag=config --force
```

Then clear all caches:

```bash
php artisan optimize:clear
```

**Why this happens:**
- The plugin's config file in `vendor/local-sde/seat-local-sde/src/Config/local-sde.php` is only loaded when config is NOT cached
- When Laravel caches config, it only reads from published config files in `config/` directory
- Publishing creates/updates `config/local-sde.php` which is used when config is cached

### "Data path not found" Error

Ensure the static data zip file exists in the plugin's storage directory:

```bash
ls -lh /var/www/seat/vendor/local-sde/seat-local-sde/storage/sde/
```

### Memory Limit Errors

Increase memory limit in `.env`:

```
LOCAL_SDE_MEMORY_LIMIT=4096M
```

### Plugin Not Found

Run package discovery:

```bash
php artisan package:discover
php artisan clear-compiled
```

## License

GPL-2.0 (same as SeAT)

## Credits

- Based on SeAT's original SDE import system
- Uses CCP's official EVE Online static data exports
