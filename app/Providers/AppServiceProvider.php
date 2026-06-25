<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
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
        // Auth throttling: 5 attempts per minute, keyed per client IP. Applied
        // to login/register/forgot/reset via the `throttle:auth` middleware.
        RateLimiter::for('auth', function (Request $request): Limit {
            return Limit::perMinute(5)->by($request->ip());
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
