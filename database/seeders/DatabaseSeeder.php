<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Permissions and system roles are always synced. Demo schools and accounts
     * (with a known password) are only created outside production.
     */
    public function run(): void
    {
        Artisan::call('rbac:sync');

        if (! app()->isProduction()) {
            $this->call(DemoSeeder::class);
        }
    }
}
