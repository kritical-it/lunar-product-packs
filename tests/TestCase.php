<?php

namespace Gongarce\ProductPacks\Tests;

use Gongarce\ProductPacks\ProductPacksServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Lunar\Admin\Models\Staff;
use Lunar\Models\Currency;
use Lunar\Models\Language;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The users table, which carts reference.
        if (! \Illuminate\Support\Facades\Schema::hasTable('users')) {
            \Illuminate\Support\Facades\Schema::create('users', function (\Illuminate\Database\Schema\Blueprint $table) {
                $table->id();
                $table->string('name')->nullable();
                $table->string('email')->nullable();
                $table->timestamps();
            });
        }

        Language::factory()->create(['code' => 'en', 'default' => true]);
        Currency::factory()->create(['default' => true]);
    }

    protected function getPackageProviders($app)
    {
        return [
            \Livewire\LivewireServiceProvider::class,
            \BladeUI\Icons\BladeIconsServiceProvider::class,
            \BladeUI\Heroicons\BladeHeroiconsServiceProvider::class,
            \Technikermathe\LucideIcons\BladeLucideIconsServiceProvider::class,
            \RyanChandler\BladeCaptureDirective\BladeCaptureDirectiveServiceProvider::class,
            \Filament\Support\SupportServiceProvider::class,
            \Filament\Actions\ActionsServiceProvider::class,
            \Filament\Forms\FormsServiceProvider::class,
            \Filament\Infolists\InfolistsServiceProvider::class,
            \Filament\Notifications\NotificationsServiceProvider::class,
            \Filament\Tables\TablesServiceProvider::class,
            \Filament\Widgets\WidgetsServiceProvider::class,
            \Filament\FilamentServiceProvider::class,
            \Awcodes\Shout\ShoutServiceProvider::class,
            \Awcodes\FilamentBadgeableColumn\BadgeableColumnServiceProvider::class,
            \Leandrocfe\FilamentApexCharts\FilamentApexChartsServiceProvider::class,
            \Stephenjude\FilamentTwoFactorAuthentication\TwoFactorAuthenticationServiceProvider::class,
            \Cartalyst\Converter\Laravel\ConverterServiceProvider::class,
            \Kalnoy\Nestedset\NestedSetServiceProvider::class,
            \Kirschbaum\PowerJoins\PowerJoinsServiceProvider::class,
            \Laravel\Scout\ScoutServiceProvider::class,
            \Spatie\Activitylog\ActivitylogServiceProvider::class,
            \Spatie\LaravelBlink\BlinkServiceProvider::class,
            \Spatie\MediaLibrary\MediaLibraryServiceProvider::class,
            \Spatie\Permission\PermissionServiceProvider::class,
            \Spatie\StructureDiscoverer\StructureDiscovererServiceProvider::class,
            \Lunar\LunarServiceProvider::class,
            \Lunar\Admin\LunarPanelProvider::class,
            ProductPacksServiceProvider::class,
            TestPanelServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app)
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing.foreign_key_constraints', true);
        $app['config']->set('lunar.database.table_prefix', $this->tablePrefix());
        $app['config']->set('lunar.urls.generator', null);
        $app['config']->set('scout.driver', 'null');
    }

    protected function tablePrefix(): string
    {
        return 'lunar_';
    }

    protected function asStaff(): Staff
    {
        $staff = Staff::create([
            'first_name' => 'Test',
            'last_name' => 'Admin',
            'email' => 'admin@example.test',
            'password' => bcrypt('password'),
            'admin' => true,
        ]);

        $this->actingAs($staff, 'staff');

        return $staff;
    }
}
