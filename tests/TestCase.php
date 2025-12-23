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

namespace LocalSde\SeatLocalSde\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use LocalSde\SeatLocalSde\LocalSdeServiceProvider;

/**
 * Base TestCase for all tests.
 */
abstract class TestCase extends Orchestra
{
    /**
     * Setup the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();
    }

    /**
     * Get package providers.
     */
    protected function getPackageProviders($app): array
    {
        return [
            LocalSdeServiceProvider::class,
        ];
    }

    /**
     * Define environment setup.
     */
    protected function getEnvironmentSetUp($app): void
    {
        // Setup test configuration
        $app['config']->set('local-sde.data_path', __DIR__ . '/fixtures');
        $app['config']->set('local-sde.chunk_size', 100);
        $app['config']->set('local-sde.memory_limit', '256M');
    }

    /**
     * Get fixture path.
     */
    protected function getFixturePath(string $filename): string
    {
        return __DIR__ . '/fixtures/' . $filename;
    }
}
