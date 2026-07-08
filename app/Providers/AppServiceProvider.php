<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Symfony\Component\Mailer\Bridge\Brevo\Transport\BrevoApiTransport;

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
        // Auth throttling for login/register/forgot/reset via the
        // `throttle:auth` middleware. Two independent 5/min limits: one per
        // client IP (stops a single noisy host) and one per submitted email
        // (stops credential stuffing against one account from rotating IPs).
        // Either being exceeded returns 429. The email is normalized and
        // hashed so the key is stable and doesn't leak addresses into cache.
        RateLimiter::for('auth', function (Request $request): array {
            $email = Str::lower(trim((string) $request->input('email')));

            return [
                Limit::perMinute(5)->by('auth-ip:'.$request->ip()),
                Limit::perMinute(5)->by('auth-email:'.sha1($email)),
            ];
        });

        // Password policy (single source of truth for register/reset/change).
        // Breach-check via HaveIBeenPwned only outside local dev so offline
        // work and tests aren't blocked on an outbound HTTP call.
        Password::defaults(function (): Password {
            $rule = Password::min(8)->mixedCase()->numbers();

            return $this->app->isProduction() ? $rule->uncompromised() : $rule;
        });

        // Brevo mail transport via its HTTP API (port 443) — works on hosts
        // that block outbound SMTP ports (e.g. Render).
        Mail::extend('brevo', function (): BrevoApiTransport {
            return new BrevoApiTransport((string) config('services.brevo.key'));
        });

        // Password reset links point at the couple portal's reset page
        // (this is an API-only backend with no web `password.reset` route).
        ResetPassword::createUrlUsing(function (object $notifiable, string $token): string {
            $base = rtrim(config('services.client.url'), '/');

            return $base.'/reset-password?token='.$token
                .'&email='.urlencode($notifiable->getEmailForPasswordReset());
        });
    }
}
