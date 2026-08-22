<?php

namespace Ayvazyan10\Imagic;

use Ayvazyan10\Imagic\Http\Middleware\EnsureMediaLibraryAuthorized;
use Ayvazyan10\Imagic\Services\ImageTransformer;
use Ayvazyan10\Imagic\Services\MediaStorage;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;
use Laravel\Nova\Events\ServingNova;
use Laravel\Nova\Http\Middleware\Authenticate;
use Laravel\Nova\Http\Middleware\Authorize;
use Laravel\Nova\Nova;

class FieldServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/imagic.php' => config_path('imagic.php'),
        ], 'imagic-config');

        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'imagic-migrations');

        if ((bool) config('imagic.media_library.enabled', true)) {
            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
            $this->registerMediaRoutes();
            $this->registerMediaPage();
        }

        Nova::serving(function (ServingNova $event) {
            Nova::script('imagic', __DIR__.'/../dist/js/field.js');
            Nova::style('imagic', __DIR__.'/../dist/css/field.css');

            if ((bool) config('imagic.media_library.enabled', true)) {
                Nova::tools([new MediaLibraryTool()]);
            }
        });
    }

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/imagic.php', 'imagic');

        $this->app->singleton(ImageTransformer::class);
        $this->app->singleton(MediaStorage::class);
    }

    private function registerMediaRoutes(): void
    {
        RateLimiter::for('imagic', function (Request $request) {
            return Limit::perMinute((int) config('imagic.media_library.rate_limit', 120))
                ->by((string) optional($request->user())->getAuthIdentifier() ?: $request->ip());
        });

        Route::group([
            'domain' => config('nova.domain'),
            'prefix' => trim((string) config('imagic.media_library.api_path', 'nova-vendor/imagic'), '/'),
            'as' => 'imagic.',
            'middleware' => array_merge((array) config('nova.api_middleware', ['nova']), [EnsureMediaLibraryAuthorized::class, 'throttle:imagic']),
        ], function (): void {
            $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
        });
    }

    private function registerMediaPage(): void
    {
        Nova::router(['nova', Authenticate::class, Authorize::class, EnsureMediaLibraryAuthorized::class])
            ->get(trim((string) config('imagic.media_library.page_path', 'imagic-media'), '/'), function () {
                return Inertia::render('imagic-media-manager', [
                    'apiBase' => '/'.trim((string) config('imagic.media_library.api_path', 'nova-vendor/imagic'), '/'),
                ]);
            })
            ->name('imagic.media-library');
    }
}
