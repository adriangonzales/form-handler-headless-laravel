<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password;

#[Signature('user:password {email : The login email of the user} {--password= : The new password (prompted for when omitted, to keep it out of shell history)}')]
#[Description("Set a user's password and revoke every token issued to them.")]
class UpdateUserPassword extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $user = User::firstWhere('email', Str::lower($this->argument('email')));

        if ($user === null) {
            $this->components->error("No user found with email [{$this->argument('email')}].");

            return self::FAILURE;
        }

        $validator = Validator::make(
            ['password' => $this->option('password') ?? password('New password', required: true)],
            ['password' => ['required', 'string', Password::defaults()]],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        DB::transaction(function () use ($user, $validator): void {
            $user->forceFill([
                'password' => $validator->validated()['password'],
                'remember_token' => Str::random(60),
            ])->save();

            $user->revokeTokens();
        });

        $this->components->info("Updated the password for [{$user->email}] and revoked their tokens.");

        return self::SUCCESS;
    }
}
