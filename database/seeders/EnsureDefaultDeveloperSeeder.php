<?php

namespace Database\Seeders;

use App\Support\DefaultDeveloperManager;
use Illuminate\Database\Seeder;

class EnsureDefaultDeveloperSeeder extends Seeder
{
    public function run(): void
    {
        DefaultDeveloperManager::ensure();
    }
}
