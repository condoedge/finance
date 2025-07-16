<?php

namespace Tests;

use Condoedge\Finance\Casts\SafeDecimal;
use Condoedge\Finance\CondoedgeFinanceServiceProvider;
use Condoedge\Finance\Models\CustomableTeam;
use Condoedge\Utils\CondoedgeUtilsServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Kompo\Auth\KompoAuthServiceProvider;
use Kompo\KompoServiceProvider;

abstract class TestCase extends \Orchestra\Testbench\TestCase
{
    use RefreshDatabase;
    // use DatabaseTransactions;

    public function setUp(): void
    {
        parent::setUp();

        $this->app->bind('team-model', function () {
            return new CustomableTeam();
        });

        foreach ($this->serviceProviders($this->app) as $class) {
            $provider = new $class($this->app);
            $provider->boot();
            $provider->register();
        }

        Artisan::call('db:seed', [
            '--class' => 'Condoedge\Finance\Database\Seeders\SettingsSeeder',
            '--force' => true,
        ]);
    }

    protected function serviceProviders($app)
    {
        return [
            KompoServiceProvider::class,
            CondoedgeUtilsServiceProvider::class,
            CondoedgeFinanceServiceProvider::class,
            KompoAuthServiceProvider::class,
        ];
    }

    public static function assertEqualsDecimals(float|SafeDecimal|null $expected, float|SafeDecimal|null $actual, string $message = ''): void
    {
        $expected = new SafeDecimal($expected);
        $actual = new SafeDecimal($actual);
        $message = $message ?: sprintf('Failed asserting that %s equals %s.', $expected, $actual);
        self::assertTrue($expected->equals($actual), $message);
    }

}
