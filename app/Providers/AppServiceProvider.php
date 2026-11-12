<?php

namespace App\Providers;

use App\DemoShop\DemoCatalog;
use App\DemoShop\PriceSimulator;
use App\Jobs\ScrapeListing;
use App\Scraping\Extractors\CssSelectorExtractor;
use App\Scraping\Extractors\JsonLdExtractor;
use App\Scraping\Extractors\OpenGraphExtractor;
use App\Scraping\HtmlPriceExtractor;
use App\Scraping\Http\UrlGuard;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Foundation\Application;
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
        $this->app->singleton(DemoCatalog::class, fn (): DemoCatalog => DemoCatalog::fromConfig(config('demo_shop')));

        $this->app->singleton(PriceSimulator::class, fn (): PriceSimulator => new PriceSimulator(
            CarbonImmutable::parse(config('demo_shop.anchor_date')),
        ));

        // The app's own origin is trusted so the built-in demo shop can be scraped.
        $this->app->singleton(UrlGuard::class, fn (): UrlGuard => new UrlGuard([
            config()->string('app.url'),
            ...array_values(array_filter(config()->array('scraping.trusted_origins'), is_string(...))),
        ]));

        $this->app->singleton(HtmlPriceExtractor::class, fn (Application $app): HtmlPriceExtractor => new HtmlPriceExtractor([
            $app->make(JsonLdExtractor::class),
            $app->make(OpenGraphExtractor::class),
            $app->make(CssSelectorExtractor::class),
        ]));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        // Politeness: a few requests per minute per store, whatever the queue size.
        RateLimiter::for('scraping', fn (object $job): Limit => Limit::perMinute((int) config('scraping.requests_per_minute_per_store'))
            ->by($job instanceof ScrapeListing ? 'store:'.$job->listing->store_id : 'scraping'));
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
