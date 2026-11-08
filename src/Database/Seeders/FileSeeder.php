<?php

namespace Nfgarching\Files\Database\Seeders;

use Illuminate\Database\Seeder;
use Nfgarching\Files\Models\File;

class FileSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        File::factory(10)
            ->create();
    }
}
