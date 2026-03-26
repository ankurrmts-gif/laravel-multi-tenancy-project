<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use App\Models\Settings;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;

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
        RateLimiter::for('auth-limit', function (Request $request) {
            // Get settings directly (NO helper)
            $perSecond = Settings::where('key','login_attempt_seconds')->first()->value;
            $perMinute = Settings::where('key','login_attempt_minute')->first()->value;
            $perHour   = Settings::where('key','login_attempt_hour')->first()->value;

            return [
                // 1 request every 3 seconds
                Limit::perMinute(intval($perSecond))->by($request->ip())
                    ->response(function () {
                        return response()->json([
                            'message' => 'Please wait a few seconds before retrying.'
                        ], 429);
                    }),

                // Per Minute Limit
                Limit::perMinute(intval($perMinute))
                    ->by($request->ip())
                    ->response(function () use ($perMinute) {
                        return response()->json([
                            'message' => "Too many attempts."
                        ], 429);
                    }),

                // Per Hour Limit
                Limit::perHour(intval($perHour))
                    ->by($request->ip().'-hour')
                    ->response(function () use ($perHour) {
                        return response()->json([
                            'message' => "Hourly limit exceeded."
                        ], 429);
                    }),
            ];
        });

        VerifyEmail::toMailUsing(function ($notifiable, $url) {
            return (new MailMessage)
                ->subject('Verify Your Email Address')
                ->markdown('emails.verify-email', [
                    'url' => $url,
                    'user' => $notifiable
                ]);
        });
    }
}
