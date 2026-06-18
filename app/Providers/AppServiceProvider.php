<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\ServiceProvider;

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
        // Password reset links point at the couple portal's reset page
        // (this is an API-only backend with no web `password.reset` route).
        ResetPassword::createUrlUsing(function (object $notifiable, string $token): string {
            $base = rtrim(config('services.client.url'), '/');

            return $base.'/reset-password?token='.$token
                .'&email='.urlencode($notifiable->getEmailForPasswordReset());
        });
    }
}
