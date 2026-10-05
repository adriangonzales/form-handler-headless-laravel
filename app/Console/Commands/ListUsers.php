<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('user:list')]
#[Description('List every account holder.')]
class ListUsers extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $users = User::query()->withCount('forms')->orderBy('id')->get();

        if ($users->isEmpty()) {
            $this->components->info('There are no users.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Name', 'Email', 'Forms', 'Created'],
            $users->map(fn (User $user): array => [
                $user->id,
                $user->name,
                $user->email,
                $user->forms_count,
                $user->created_at?->toDateTimeString(),
            ]),
        );

        return self::SUCCESS;
    }
}
