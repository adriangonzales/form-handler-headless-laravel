<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Postmark webhooks authenticate with HTTP basic auth embedded in the webhook URL
 * (https://user:pass@host/...). Requests are rejected unless both credentials are configured and match.
 */
class VerifyPostmarkWebhook
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $username = config('services.postmark.webhook_username');
        $password = config('services.postmark.webhook_password');

        $authenticated = is_string($username) && $username !== ''
            && is_string($password) && $password !== ''
            && hash_equals($username, (string) $request->getUser())
            && hash_equals($password, (string) $request->getPassword());

        abort_unless($authenticated, 401, 'Unauthenticated.', ['WWW-Authenticate' => 'Basic']);

        return $next($request);
    }
}
