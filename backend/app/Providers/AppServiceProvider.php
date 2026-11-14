<?php

namespace App\Providers;

use App\Models\Event;
use App\Models\Round;
use App\Models\Score;
use App\Policies\EventPolicy;
use App\Policies\RoundPolicy;
use App\Policies\ScorePolicy;
use Illuminate\Support\Facades\Gate;
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
        Gate::policy(Event::class, EventPolicy::class);
        Gate::policy(Round::class, RoundPolicy::class);
        Gate::policy(Score::class, ScorePolicy::class);
        Gate::define('mutateRound', [ScorePolicy::class, 'mutateRound']);
    }
}
