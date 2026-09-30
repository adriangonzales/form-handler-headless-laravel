<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

#[Signature("user:create {--name= : The account holder's name} {--email= : The login email} {--password= : The password (prompted for when omitted, to keep it out of shell history)}")]
#[Description('Create an account holder. There is no self-service sign-up.')]
class CreateUser extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $attributes = [
            'name' => $this->option('name') ?? text('Name', required: true),
            'email' => Str::lower($this->option('email') ?? text('Email', required: true)),
            'password' => $this->option('password') ?? password('Password', required: true),
        ];

        $validator = Validator::make($attributes, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', Password::defaults()],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        $user = User::create($validator->validated());

        $this->components->info("Created user [{$user->email}] with ID [{$user->id}].");

        return self::SUCCESS;
    }
}
