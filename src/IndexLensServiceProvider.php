<?php

namespace IndexLens\IndexLens;

use Illuminate\Support\ServiceProvider;
use IndexLens\IndexLens\Commands\CiBudgetCommand;
use IndexLens\IndexLens\Commands\RegressionCommand;
use IndexLens\IndexLens\Commands\ReportCommand;
use IndexLens\IndexLens\Commands\RoutesCommand;
use IndexLens\IndexLens\Commands\ScanCommand;
use IndexLens\IndexLens\Contracts\IndexLensContract;
use IndexLens\IndexLens\Repositories\ProfileRepository;
use IndexLens\IndexLens\Services\ExplainAnalyzer;
use IndexLens\IndexLens\Services\IndexRecommendationService;
use IndexLens\IndexLens\Services\QueryCaptureService;
use IndexLens\IndexLens\Services\RegressionService;
use IndexLens\IndexLens\Services\ReportBuilder;
use IndexLens\IndexLens\Services\RouteProfiler;
use IndexLens\IndexLens\Analyzers\NPlusOneAnalyzer;

class IndexLensServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/indexlens.php', 'indexlens');

        $this->app->singleton(ProfileRepository::class);
        $this->app->singleton(QueryCaptureService::class);
        $this->app->singleton(NPlusOneAnalyzer::class);
        $this->app->singleton(IndexRecommendationService::class);
        $this->app->singleton(ExplainAnalyzer::class);
        $this->app->singleton(RouteProfiler::class);
        $this->app->singleton(RegressionService::class);
        $this->app->singleton(ReportBuilder::class);

        $this->app->singleton(IndexLensContract::class, function ($app) {
            return new IndexLensManager(
                $app->make(QueryCaptureService::class),
                $app->make(NPlusOneAnalyzer::class),
                $app->make(IndexRecommendationService::class),
                $app->make(ExplainAnalyzer::class),
                $app->make(RouteProfiler::class),
                $app->make(RegressionService::class),
                $app->make(ReportBuilder::class)
            );
        });

        $this->app->alias(IndexLensContract::class, 'indexlens');
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'indexlens');

        $this->publishes([
            __DIR__ . '/../config/indexlens.php' => config_path('indexlens.php'),
        ], 'indexlens-config');

        $this->publishes([
            __DIR__ . '/../database/migrations/' => database_path('migrations'),
        ], 'indexlens-migrations');

        $this->publishes([
            __DIR__ . '/../resources/views/' => resource_path('views/vendor/indexlens'),
        ], 'indexlens-views');

        if ($this->app->runningInConsole()) {
            $this->commands([
                ScanCommand::class,
                RoutesCommand::class,
                RegressionCommand::class,
                CiBudgetCommand::class,
                ReportCommand::class,
            ]);
        }

        $this->app->make(QueryCaptureService::class)->boot();
    }
}
