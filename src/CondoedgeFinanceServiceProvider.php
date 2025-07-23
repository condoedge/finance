<?php

namespace Condoedge\Finance;

use Condoedge\Finance\Billing\Core\PaymentProviderRegistry;
use Condoedge\Finance\Facades\CustomerService;
use Condoedge\Finance\Facades\InvoiceModel;
use Condoedge\Finance\Models\Invoice;
use Condoedge\Finance\Models\MorphablesEnum;
use Condoedge\Finance\Models\Product;
use Condoedge\Finance\Observers\DatabaseIntegrityObserver;
use Condoedge\Finance\Services\DatabaseQueryInterceptor;
use Condoedge\Finance\Services\Graph;
use Condoedge\Finance\Services\IntegrityChecker;
use Condoedge\Finance\Services\Invoice\InvoiceService;
use Condoedge\Finance\Services\Invoice\InvoiceServiceInterface;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Eloquent\Factories\Factory as EloquentFactory;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class CondoedgeFinanceServiceProvider extends ServiceProvider
{
    use \Kompo\Routing\Mixins\ExtendsRoutingTrait;

    /**
     * Bootstrap services.
     */
    public function boot()
    {
        $this->registerServiceLayer();

        EloquentFactory::guessFactoryNamesUsing(function (string $modelName) {
            return 'Condoedge\\Finance\\Database\\Factories\\'.class_basename($modelName).'Factory';
        });

        $this->loadHelpers();
        $this->loadConfig();

        $this->registerPolicies();

        $this->extendRouting(); //otherwise Route::layout doesn't work

        $this->loadJSONTranslationsFrom(__DIR__.'/../resources/lang');

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->loadCommands();

        $this->registerFacades();

        $this->bootDatabaseIntegritySystem();

        $this->loadRelationsMorphMap();

        $this->setCronExecutions();

        $this->registerPaymentWebhookRoutes();

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'finance');
    }

    /**
     * Register services.
     */
    public function register()
    {
        // Best way to load routes. This ensures loading at the very end (after fortifies' routes for ex.)
        $this->booted(function () {
            Route::middleware('web')->group(__DIR__.'/../routes/web.php');
            Route::prefix('api')->middleware('api')->group(__DIR__.'/../routes/api.php');
        });

        // Register services for integrity checking
        $this->app->bind('finance.graph', function ($app) {
            return new Graph();
        });

        $this->app->singleton('integrity-checker', function ($app) {
            return new IntegrityChecker();
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

        // Publish images
        $this->publishes([
            __DIR__.'/../resources/images' => public_path('images/vendor/kompo-finance'),
        ], 'finance-assets');
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

        $this->app->bind(PAYMENT_METHOD_ENUM_KEY, function () {
            return config('kompo-finance.' . PAYMENT_METHOD_ENUM_KEY . '-namespace');
        });

        $this->app->bind(PRODUCT_TYPE_ENUM_KEY, function () {
            return config('kompo-finance.' . PRODUCT_TYPE_ENUM_KEY . '-namespace');
        });

        $this->app->bind(PRODUCT_MODEL_KEY, function () {
            return new (config('kompo-finance.' . PRODUCT_MODEL_KEY . '-namespace'));
        });

        $this->app->bind(SEGMENT_DEFAULT_HANDLER_ENUM_KEY, function () {
            return config('kompo-finance.' . SEGMENT_DEFAULT_HANDLER_ENUM_KEY . '-namespace');
        });

        $this->app->bind(INVOICE_TYPE_ENUM_KEY, function () {
            return config('kompo-finance.' . INVOICE_TYPE_ENUM_KEY . '-namespace');
        });

        $this->app->bind(CUSTOMER_PAYMENT_MODEL_KEY, function () {
            return new (config('kompo-finance.' . CUSTOMER_PAYMENT_MODEL_KEY . '-namespace'));
        });

        // Register Database Integrity System
        $this->app->singleton(DatabaseIntegrityObserver::class);
        $this->app->singleton(DatabaseQueryInterceptor::class, function ($app) {
            return new DatabaseQueryInterceptor($app->make(DatabaseIntegrityObserver::class));
        });
    }

    protected function loadHelpers()
    {
        $helpersDir = __DIR__.'/Helpers';

        $autoloadedHelpers = collect(File::allFiles($helpersDir))->map(fn ($file) => $file->getRealPath());

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
        Relation::morphMap(collect([
            'product' => Product::class,
            'invoice' => InvoiceModel::getClass(),
        ])->union(CustomerService::getValidCustomableModels())->union(collect(MorphablesEnum::cases())->mapWithKeys(function ($case) {
            return [$case->value => $case->getMorphableClass()];
        }))->all());
    }

    public function loadCommands()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                \Condoedge\Finance\Command\EnsureIntegrityCommand::class,
                \Condoedge\Finance\Command\PreCreateFiscalPeriodsCommand::class,
                \Condoedge\Finance\Command\CleanupWebhookEventsCommand::class,
                \Condoedge\Finance\Command\EnsureInvoiceEventsAreProcessed::class
            ]);
        }
    }

    protected function setCronExecutions()
    {
        $this->app->booted(function () {
            $schedule = $this->app->make(Schedule::class);

            // Daily integrity check
            $schedule->command('finance:ensure-integrity')->dailyAt('01:00');

            $schedule->command('finance:pre-create-periods')
                ->monthlyOn(\Carbon\Carbon::now()->endOfMonth()->day, '23:30')
                ->withoutOverlapping()
                ->appendOutputTo(storage_path('logs/fiscal-periods.log'));

            $schedule->command('finance:ensure-invoice-events-processed')
                ->hourly()
                ->withoutOverlapping()
                ->appendOutputTo(storage_path('logs/invoice-actions.log'));

            // Cleanup old webhook events monthly
            $schedule->command('finance:cleanup-webhook-events --days=90')
                ->monthly()
                ->at('02:00');

            $schedule->command('cleanup:expense-report-drafts')
                ->daily()
                ->at('03:00')
                ->withoutOverlapping()
                ->appendOutputTo(storage_path('logs/expense-report-cleanup.log'));
        });
    }

    /**
     * Register service layer bindings
     */
    protected function registerServiceLayer(): void
    {
        // Invoice Service (existing)
        $this->app->bind(InvoiceServiceInterface::class, InvoiceService::class);

        // Customer Service
        $this->app->bind(
            \Condoedge\Finance\Services\Customer\CustomerServiceInterface::class,
            \Condoedge\Finance\Services\Customer\CustomerService::class
        );

        // Payment Service
        $this->app->bind(
            \Condoedge\Finance\Services\Payment\PaymentServiceInterface::class,
            \Condoedge\Finance\Services\Payment\PaymentService::class
        );

        // Tax Service
        $this->app->singleton(
            \Condoedge\Finance\Services\Tax\TaxServiceInterface::class,
            \Condoedge\Finance\Services\Tax\TaxService::class
        );

        // Account Segment Validator
        $this->app->singleton(\Condoedge\Finance\Services\AccountSegmentValidator::class);

        // Segment Default Handler Service
        $this->app->singleton(\Condoedge\Finance\Services\SegmentDefaultHandlerService::class);

        // Account Segment Service (new segment-based system)
        $this->app->bind(
            \Condoedge\Finance\Services\AccountSegmentServiceInterface::class,
            \Condoedge\Finance\Services\AccountSegmentService::class
        );
        $this->app->singleton(\Condoedge\Finance\Services\AccountSegmentService::class);

        // Fiscal Year Service
        $this->app->singleton(\Condoedge\Finance\Services\FiscalYearService::class);

        // Invoice Detail Service
        $this->app->bind(
            \Condoedge\Finance\Services\InvoiceDetail\InvoiceDetailServiceInterface::class,
            \Condoedge\Finance\Services\InvoiceDetail\InvoiceDetailService::class
        );

        // GL Transaction Service
        $this->app->bind(
            \Condoedge\Finance\Services\GlTransactionServiceInterface::class,
            \Condoedge\Finance\Services\GlTransactionService::class
        );

        // Product Service
        $this->app->bind(
            \Condoedge\Finance\Services\Product\ProductServiceInterface::class,
            \Condoedge\Finance\Services\Product\ProductService::class
        );

        // Payment Term Service
        $this->app->bind(
            \Condoedge\Finance\Services\PaymentTerm\PaymentTermServiceInterface::class,
            \Condoedge\Finance\Services\PaymentTerm\PaymentTermService::class
        );

        // PAYMENT PROCESSORS
        $this->app->bind(
            \Condoedge\Finance\Billing\Contracts\PaymentProcessorInterface::class,
            \Condoedge\Finance\Billing\Core\PaymentProcessor::class
        );

        // Register the Payment Gateway Resolver
        $this->app->bind(
            \Condoedge\Finance\Billing\Contracts\PaymentGatewayResolverInterface::class,
            \Condoedge\Finance\Billing\Core\Resolver\DefaultPaymentGatewayResolver::class
        );

        $this->app->singleton(PaymentProviderRegistry::class);
    }

    /**
     * Boot the database integrity checking system
     */
    protected function bootDatabaseIntegritySystem(): void
    {
        // Only enable in environments where we want database integrity checking
        if (config('kompo-finance.database_integrity_interceptor', true)) {
            $interceptor = $this->app->make(DatabaseQueryInterceptor::class);
            $interceptor->enable();
        }
    }

    /**
     * Register payment webhook routes
     */
    private function registerPaymentWebhookRoutes(): void
    {
        $this->app->booted(function () {
            Route::middleware(['api'])
                ->prefix('api/webhooks')
                ->group(function ($router) {
                    $registry = app(PaymentProviderRegistry::class);

                    foreach ($registry->all() as $provider) {
                        $provider->registerWebhookRoutes($router);
                    }
                });
        });
    }
}
