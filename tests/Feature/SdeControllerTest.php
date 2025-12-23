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

namespace LocalSde\SeatLocalSde\Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * SdeControllerTest
 *
 * Feature tests for SdeController HTTP endpoint.
 */
class SdeControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Clear cache before each test
        Cache::forget('live_sde_version');
    }

    /**
     * Test successful API response from CCP.
     */
    public function test_successful_ccp_api_response()
    {
        // Mock successful HTTP response
        Http::fake([
            'developers.eveonline.com/*' => Http::response(
                '{'buildNumber':3142455,'releaseDate':'2024-12-10T00:00:00Z'}\n'
            ),
        ]);

        $response = $this->get(route('seatcore::check.sde'));

        $response->assertStatus(200);
        $response->assertJson(['version' => 'sde-3142455']);
    }

    /**
     * Test HTTP error handling.
     */
    public function test_http_error_handling()
    {
        // Mock failed HTTP response
        Http::fake([
            'developers.eveonline.com/*' => Http::response('', 500),
        ]);

        $response = $this->get(route('seatcore::check.sde'));

        $response->assertStatus(200);
        $response->assertJson(['version' => 'Error fetching latest SDE version']);
    }

    /**
     * Test JSON parsing error handling.
     */
    public function test_json_parsing_error()
    {
        // Mock invalid JSON response
        Http::fake([
            'developers.eveonline.com/*' => Http::response('invalid json'),
        ]);

        $response = $this->get(route('seatcore::check.sde'));

        $response->assertStatus(200);
        $response->assertJson(['version' => 'Error parsing SDE version data']);
    }

    /**
     * Test missing buildNumber handling.
     */
    public function test_missing_build_number()
    {
        // Mock response without buildNumber
        Http::fake([
            'developers.eveonline.com/*' => Http::response(
                '{'releaseDate':'2024-12-10T00:00:00Z'}\n'
            ),
        ]);

        $response = $this->get(route('seatcore::check.sde'));

        $response->assertStatus(200);
        $response->assertJson(['version' => 'Error: missing buildNumber in response']);
    }

    /**
     * Test cache functionality.
     */
    public function test_cache_functionality()
    {
        // Mock successful HTTP response
        Http::fake([
            'developers.eveonline.com/*' => Http::response(
                '{'buildNumber':3142455,'releaseDate':'2024-12-10T00:00:00Z'}\n'
            ),
        ]);

        // First request - should hit API
        $this->get(route('seatcore::check.sde'));

        // Verify cache is set
        $this->assertTrue(Cache::has('live_sde_version'));
        $this->assertEquals('sde-3142455', Cache::get('live_sde_version'));

        // Second request - should use cache
        Http::fake([
            'developers.eveonline.com/*' => Http::response('', 500),
        ]);

        $response = $this->get(route('seatcore::check.sde'));
        $response->assertJson(['version' => 'sde-3142455']);
    }
}
