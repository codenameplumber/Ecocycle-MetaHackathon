<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * EcoCycleSeeder lays down the reference data — areas, categories, the
     * facility, the named demo accounts — and a small hand-written history you
     * can read end to end. NetworkSeeder then grows that into a pilot-sized
     * network so every list, chart and pagination control has real weight
     * behind it.
     */
    public function run(): void
    {
        $this->call([
            EcoCycleSeeder::class,
            VerifiedHistorySeeder::class,
            NetworkSeeder::class,
            ProcessingRunSeeder::class,
            PayoutAccountSeeder::class,
            NotificationSeeder::class,
        ]);
    }
}
