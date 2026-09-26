<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\FormEntry;
use Illuminate\Database\Seeder;

class FormEntrySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        FormEntry::factory()->count(5)->create();
    }
}
