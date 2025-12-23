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

class UpdateAllSde extends Command
{
    protected $signature = 'eve:sde:update-all
                            {build? : Specific build number to update to (optional, defaults to latest)}
                            {--force : Force download and installation even if already up-to-date}';

    protected $description = 'Run all EVE SDE updates (standard and planet)';

    public function handle()
    {
        $build = $this->argument('build');
        $force = $this->option('force');

        $this->info('Running standard SDE update...');
        $this->call('eve:sde:update', [
            'build' => $build,
            '--force' => $force,
        ]);

        $this->info('Running planet SDE update...');
        $this->call('eve:sde:update-planet', [
            'build' => $build,
            '--force' => $force,
        ]);

        $this->info('All SDE updates complete.');
        return self::SUCCESS;
    }
}
