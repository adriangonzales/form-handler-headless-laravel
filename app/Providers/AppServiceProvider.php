<?php

namespace App\Providers;

use App\Models\User;
use App\OpenApi\ArrayKeysRuleTransformer;
use Carbon\CarbonImmutable;
use Dedoc\Scramble\Scramble;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configurePasswordResetUrl();
        $this->configureRateLimiting();
        $this->configureApiDocs();
    }

    /**
     * Correct how the OpenAPI document describes some validation rules.
     */
    protected function configureApiDocs(): void
    {
        Scramble::configure()->withRuleTransformers(ArrayKeysRuleTransformer::class);
    }

    /**
     * Cap public submissions to each form per client IP, so one client cannot block a form for others.
     * Throttle middleware runs before route model binding, so the key uses the raw form ID from the URL.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('form-submissions', fn (Request $request): Limit => Limit::perMinute(60)
            ->by($request->route()?->originalParameter('form').'|'.$request->ip()));
    }

    /**
     * Point password reset emails at the client application, since this API has no web pages.
     */
    protected function configurePasswordResetUrl(): void
    {
        ResetPassword::createUrlUsing(fn (User $user, string $token): string => config('auth.passwords.users.reset_url').'?'.http_build_query([
            'token' => $token,
            'email' => $user->getEmailForPasswordReset(),
        ]));
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
