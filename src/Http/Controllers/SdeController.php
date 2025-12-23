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

namespace LocalSde\SeatLocalSde\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * SdeController
 *
 * HTTP controller that overrides SeAT's default SDE version check
 * to use CCP's official API instead of Fuzzwork.
 */
class SdeController extends Controller
{
    /**
     * CCP SDE API endpoint.
     */
    private const LATEST_VERSION_URL = 'https://developers.eveonline.com/static-data/tranquility/latest.jsonl';

    /**
     * Check SDE version from CCP official API.
     * This overrides SeAT's default Fuzzwork check.
     *
     * @return JsonResponse
     */
    public function getApprovedSDE(): JsonResponse
    {
        $sde_version = Cache::remember('live_sde_version', 720, function () {
            return $this->fetchLatestVersionFromCCP();
        });

        return response()->json(['version' => $sde_version]);
    }

    /**
     * Fetch latest SDE version from CCP API.
     *
     * @return string SDE version string (e.g., "sde-3142455") or error message
     */
    private function fetchLatestVersionFromCCP(): string
    {
        try {
            $response = Http::timeout(10)->get(self::LATEST_VERSION_URL);

            if (!$response->successful()) {
                Log::error('Failed to fetch SDE version from CCP API', [
                    'status' => $response->status(),
                    'url' => self::LATEST_VERSION_URL,
                ]);
                return 'Error fetching latest SDE version';
            }

            // Parse JSONL (first line only)
            $content = trim($response->body());
            $lines = explode("\n", $content);
            $data = json_decode($lines[0], true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('Failed to parse SDE version JSON', [
                    'error' => json_last_error_msg(),
                    'content' => substr($lines[0], 0, 200),
                ]);
                return 'Error parsing SDE version data';
            }

            if (!isset($data['buildNumber'])) {
                Log::error('Missing buildNumber in SDE API response', [
                    'data' => $data,
                ]);
                return 'Error: missing buildNumber in response';
            }

            // Format as "sde-BUILDNUMBER" to match SeAT's expected format
            return 'sde-' . $data['buildNumber'];

        } catch (\Exception $e) {
            Log::error('Exception while fetching SDE version from CCP API', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return 'Error fetching latest SDE version';
        }
    }
}
