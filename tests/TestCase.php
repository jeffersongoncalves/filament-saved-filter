<?php

namespace JeffersonGoncalves\Filament\SavedFilter\Tests;

use BladeUI\Heroicons\BladeHeroiconsServiceProvider;
use BladeUI\Icons\BladeIconsServiceProvider;
use Filament\Actions\ActionsServiceProvider;
use Filament\FilamentServiceProvider;
use Filament\Forms\FormsServiceProvider;
use Filament\Infolists\InfolistsServiceProvider;
use Filament\Notifications\NotificationsServiceProvider;
use Filament\Schemas\SchemasServiceProvider;
use Filament\Support\SupportServiceProvider;
use Filament\Tables\TablesServiceProvider;
use Filament\Widgets\WidgetsServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use JeffersonGoncalves\Filament\SavedFilter\SavedFilterServiceProvider;
use JeffersonGoncalves\Filament\SavedFilter\Tests\Fixtures\TestPanelProvider;
use JeffersonGoncalves\PestPluginDatabaseMigrations\LoadsPackageMigrations;
use JeffersonGoncalves\SavedFilter\SavedFilterServiceProvider as LaravelSavedFilterServiceProvider;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use RyanChandler\BladeCaptureDirective\BladeCaptureDirectiveServiceProvider;

class TestCase extends Orchestra
{
    use LoadsPackageMigrations;

    protected function getPackageProviders($app): array
    {
        // Version-specific providers: Filament 4+ ships the form/schema test helpers in the Schemas provider;
        // Filament 3 views need the @capture directive provider.
        $versionSpecific = array_values(array_filter([
            SchemasServiceProvider::class,
            BladeCaptureDirectiveServiceProvider::class,
        ], 'class_exists'));

        return [
            ...$versionSpecific,
            ActionsServiceProvider::class,
            BladeHeroiconsServiceProvider::class,
            BladeIconsServiceProvider::class,
            FilamentServiceProvider::class,
            FormsServiceProvider::class,
            InfolistsServiceProvider::class,
            LivewireServiceProvider::class,
            NotificationsServiceProvider::class,
            SupportServiceProvider::class,
            TablesServiceProvider::class,
            WidgetsServiceProvider::class,
            LaravelSavedFilterServiceProvider::class,
            SavedFilterServiceProvider::class,
            TestPanelProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('k', 32)));
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', $this->testing_connection());
    }

    /**
     * In-memory SQLite by default; CI (tests.yml) sets SAVED_FILTER_TEST_DB_* to run the suite on MySQL and
     * PostgreSQL too. Not the plain DB_* names: Testbench sets DB_CONNECTION=testing itself.
     *
     * @return array<string, mixed>
     */
    protected function testing_connection(): array
    {
        $driver = env('SAVED_FILTER_TEST_DB_DRIVER', 'sqlite');

        if ($driver === 'sqlite') {
            return ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true];
        }

        return [
            'driver' => $driver,
            'host' => env('SAVED_FILTER_TEST_DB_HOST', '127.0.0.1'),
            'port' => env('SAVED_FILTER_TEST_DB_PORT'),
            'database' => env('SAVED_FILTER_TEST_DB_DATABASE', 'testing'),
            'username' => env('SAVED_FILTER_TEST_DB_USERNAME', 'root'),
            'password' => env('SAVED_FILTER_TEST_DB_PASSWORD', ''),
            'charset' => $driver === 'pgsql' ? 'utf8' : 'utf8mb4',
            'prefix' => '',
        ];
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadPackageMigrations(LaravelSavedFilterServiceProvider::class);

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
            $table->timestamps();
        });

        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('status');
            $table->timestamps();
        });

        // MySQL and PostgreSQL outlive the test, unlike in-memory SQLite.
        $this->beforeApplicationDestroyed(function () {
            Schema::dropIfExists('posts');
            Schema::dropIfExists('users');
        });
    }
}
