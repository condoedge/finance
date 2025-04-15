<?php

namespace Condoedge\Finance;

use Condoedge\Finance\Billing\PaymentGatewayInterface;
use Condoedge\Finance\Billing\PaymentGatewayResolver;
use Condoedge\Finance\Billing\TempPaymentGateway;
use Condoedge\Finance\Services\Graph;
use Condoedge\Finance\Services\IntegrityChecker;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Kompo\Auth\KompoAuthServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\Factories\Factory as EloquentFactory;

class CondoedgeFinanceServiceProvider extends ServiceProvider
{
    use \Kompo\Routing\Mixins\ExtendsRoutingTrait;

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        $authProvider = new KompoAuthServiceProvider($this->app);
        $authProvider->boot();

        EloquentFactory::guessFactoryNamesUsing(function (string $modelName) {
            return 'Condoedge\\Finance\\Database\\Factories\\'.class_basename($modelName).'Factory';
        });

        $this->loadHelpers();
        $this->loadConfig();

        $this->registerPolicies();

        $this->extendRouting(); //otherwise Route::layout doesn't work

        $this->loadJSONTranslationsFrom(__DIR__.'/../resources/lang');

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->loadRelationsMorphMap();

        $this->loadCommands();

        $this->registerFacades();

        $this->setCronExecutions();
    }

    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        // Best way to load routes. This ensures loading at the very end (after fortifies' routes for ex.)
        $this->booted(function () {
            Route::middleware('web')->group(__DIR__.'/../routes/web.php');
        });

        // Register services for integrity checking
        $this->app->bind('finance.graph', function ($app) {
            return new Graph();
        });
        
        $this->app->singleton('finance.integrity_checker', function ($app) {
            return new IntegrityChecker();
        });

        $this->app->bind(PaymentGatewayInterface::class, function ($app) {
            return PaymentGatewayResolver::resolve();
        });

        $this->app->bind('config-currency', function ($app) {
            return app()->getLocale() === 'en'
                ? config('kompo-finance.currency_preformats.en')
                : config('kompo-finance.currency_preformats.fr');
        });
        
        // Publish configuration
        $this->publishes([
            __DIR__.'/../config/kompo-finance.php' => config_path('kompo-finance.php'),
        ], 'finance-config');
    }

    protected function registerFacades()
    {
        // Model binding facades
        $this->app->bind(CUSTOMER_MODEL_KEY, function () {
            return new (config('kompo-finance.'. CUSTOMER_MODEL_KEY .'-namespace'));
        });

        $this->app->bind(INVOICE_MODEL_KEY, function () {
            return new (config('kompo-finance.'. INVOICE_MODEL_KEY .'-namespace'));
        });

        $this->app->bind(INVOICE_DETAIL_MODEL_KEY, function () {
            return new (config('kompo-finance.'. INVOICE_DETAIL_MODEL_KEY .'-namespace'));
        });

        $this->app->bind(INVOICE_PAYMENT_MODEL_KEY, function () {
            return new (config('kompo-finance.'. INVOICE_PAYMENT_MODEL_KEY .'-namespace'));
        });

        $this->app->bind(TAX_MODEL_KEY, function () {
            return new (config('kompo-finance.'. TAX_MODEL_KEY .'-namespace'));
        });

        $this->app->bind(TAX_GROUP_MODEL_KEY, function () {
            return new (config('kompo-finance.'. TAX_GROUP_MODEL_KEY .'-namespace'));
        });

        $this->app->bind(PAYMENT_TYPE_ENUM_KEY, function () {
            return config('kompo-finance.' . PAYMENT_TYPE_ENUM_KEY . '-namespace');
        });

        $this->app->bind(INVOICE_TYPE_ENUM_KEY, function () {
            return config('kompo-finance.' . INVOICE_TYPE_ENUM_KEY . '-namespace');
        });
    }

    protected function loadHelpers()
    {
        $helpersDir = __DIR__.'/Helpers';

        $autoloadedHelpers = collect(File::allFiles($helpersDir))->map(fn($file) => $file->getRealPath());

        $packageHelpers = [
        ];

        $autoloadedHelpers->concat($packageHelpers)->each(function ($path) {
            if (file_exists($path)) {
                require_once $path;
            }
        });
    }

    protected function registerPolicies()
    {
        $policies = [

        ];

        foreach ($policies as $key => $value) {
            Gate::policy($key, $value);
        }
    }

    protected function loadConfig()
    {
        $dirs = [
            'kompo-finance' => __DIR__.'/../config/kompo-finance.php',
            'global-config' => __DIR__.'/../config/global-config.php',
        ];

        foreach ($dirs as $key => $path) {
            $this->mergeConfigFrom($path, $key);
        }
    }
    
    /**
     * Loads a relations morph map.
     */
    protected function loadRelationsMorphMap()
    {
        Relation::morphMap([

        ]);
    }

    public function loadCommands()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                \Condoedge\Finance\Command\EnsureIntegrityCommand::class,
            ]);
        }
    }

    protected function setCronExecutions()
    {
        $this->app->booted(function () {
            $schedule = $this->app->make(Schedule::class);
            $schedule->command('finance:ensure-integrity')->dailyAt('01:00');
        });
    }

    public function loadRoutes()
    {
        Route::middleware('web')->group(__DIR__.'/../routes/web.php');
    }
}
