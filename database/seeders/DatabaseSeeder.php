<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Demo data and its known logins exist only on a developer's laptop.
     * On staging or production, `db:seed` does nothing.
     */
    public function run(): void
    {
        if (! app()->environment('local')) {
            return;
        }

        $this->call(DemoSeeder::class);
    }
}
