<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use App\Services\Socialite\YahooProvider;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Ai\Events\InvokingTool;
use Laravel\Ai\Events\ToolInvoked;
use Laravel\Socialite\Facades\Socialite;

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
        $this->configureAiLogging();
        $this->configureSocialite();
    }

    /**
     * Register the custom socialite providers.
     */
    protected function configureSocialite(): void
    {
        Socialite::extend('yahoo', fn () => Socialite::buildProvider(
            YahooProvider::class,
            config('services.yahoo'),
        ));
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

    /**
     * Log AI tool invocations for observability.
     */
    protected function configureAiLogging(): void
    {
        Event::listen(InvokingTool::class, function (InvokingTool $event): void {
            Log::info('AI tool invoked', [
                'tool' => class_basename($event->tool),
                'arguments' => $event->arguments,
            ]);
        });

        Event::listen(ToolInvoked::class, function (ToolInvoked $event): void {
            Log::info('AI tool result', [
                'tool' => class_basename($event->tool),
                'arguments' => $event->arguments,
                'result' => is_string($event->result) ? mb_substr($event->result, 0, 300) : $event->result,
            ]);
        });
    }
}
