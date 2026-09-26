<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\FormNotification;
use Illuminate\Database\Seeder;

class FormNotificationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        FormNotification::factory()->count(5)->create();
    }
}
