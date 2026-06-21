<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Mail;
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
