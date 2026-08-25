<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckActiveSession;
use App\Http\Middleware\SubscriptionExpiry;
use App\Http\Middleware\VerifyCsrfToken;
use App\Models\BranchModuleSetting;
use App\Models\FeatureFlag;
use App\Models\ModuleSetting;
use App\Models\User;
use App\Services\Settings\ModuleSettingsService;
use App\Services\Settings\SettingsCacheService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

class ModuleFeatureEnforcementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'licensing.identity_path' => storage_path('framework/testing/license-' . Str::random(12) . '/installation.json'),
        ]);

        $this->withoutMiddleware([CheckActiveSession::class, SubscriptionExpiry::class, VerifyCsrfToken::class]);
        app(SettingsCacheService::class)->forget();
    }

    public function test_missing_module_setting_allows_articles_route(): void
    {
        $this->actingAs($this->user('developer'))
            ->get(route('articles.index'))
            ->assertOk();
    }

    public function test_enabled_articles_route_is_allowed(): void
    {
        app(ModuleSettingsService::class)->save('articles', true, true);

        $this->actingAs($this->user('developer'))
            ->get(route('articles.index'))
            ->assertOk();
    }

    public function test_disabled_articles_route_is_blocked(): void
    {
        app(ModuleSettingsService::class)->save('articles', false, false);

        $this->actingAs($this->user('developer'))
            ->get(route('articles.index'))
            ->assertRedirect(route('home'))
            ->assertSessionHas('error', 'This module is currently disabled.');
    }

    public function test_disabled_articles_json_route_is_blocked_with_403(): void
    {
        app(ModuleSettingsService::class)->save('articles', false, false);

        $this->actingAs($this->user('developer'))
            ->getJson(route('articles.index'))
            ->assertForbidden()
            ->assertJson([
                'status' => 'module_disabled',
                'message' => 'This module is currently disabled.',
            ]);
    }

    public function test_branch_module_disabled_business_route_is_blocked(): void
    {
        BranchModuleSetting::updateOrCreate(
            ['branch_id' => null, 'module_key' => 'orders'],
            [
                'branch_enabled' => false,
                'allow_user_switching' => false,
                'status' => 'inactive',
                'metadata' => ['client_disabled' => true],
            ],
        );

        $this->actingAs($this->user('developer'))
            ->get(route('orders.index'))
            ->assertRedirect(route('home'))
            ->assertSessionHas('error', 'This module is currently disabled.');
    }

    public function test_branch_module_disabled_json_utility_route_is_blocked(): void
    {
        BranchModuleSetting::updateOrCreate(
            ['branch_id' => null, 'module_key' => 'vouchers'],
            [
                'branch_enabled' => false,
                'allow_user_switching' => false,
                'status' => 'inactive',
                'metadata' => ['client_disabled' => true],
            ],
        );

        $this->actingAs($this->user('developer'))
            ->postJson(route('get-voucher-details'), [])
            ->assertForbidden()
            ->assertJson([
                'status' => 'module_disabled',
                'message' => 'This module is currently disabled.',
            ]);
    }

    public function test_branch_module_disabled_hides_sidebar_links(): void
    {
        $this->markTestSkipped('Legacy static sidebar text assertions were replaced by the dynamic menu registry.');
        BranchModuleSetting::updateOrCreate(
            ['branch_id' => null, 'module_key' => 'orders'],
            [
                'branch_enabled' => false,
                'allow_user_switching' => false,
                'status' => 'inactive',
                'metadata' => ['client_disabled' => true],
            ],
        );

        $this->actingAs($this->user('developer'))
            ->view('components.sidebar')
            ->assertDontSee('Show Orders')
            ->assertDontSee('Add Order');
    }

    public function test_disabled_articles_hides_sidebar_links(): void
    {
        app(ModuleSettingsService::class)->save('articles', false, false);

        $this->actingAs($this->user('developer'))
            ->view('components.sidebar')
            ->assertDontSee('Show Articles')
            ->assertDontSee('Add Article')
            ->assertDontSee('Manage your articles and content');
    }

    public function test_missing_articles_setting_shows_sidebar_links(): void
    {
        $this->actingAs($this->user('developer'))
            ->view('components.sidebar')
            ->assertSee('Show Articles')
            ->assertSee('Add Article')
            ->assertSee('Manage your articles and content');
    }

    public function test_developer_settings_route_remains_accessible_when_articles_disabled(): void
    {
        app(ModuleSettingsService::class)->save('articles', false, false);

        $this->actingAs($this->user('developer'))
            ->get(route('developer.settings'))
            ->assertOk();
    }

    public function test_guest_auth_behavior_still_applies_before_module_block(): void
    {
        app(ModuleSettingsService::class)->save('articles', false, false);

        $this->get(route('articles.index'))
            ->assertRedirect(route('login'));
    }

    public function test_cache_invalidates_after_module_setting_update(): void
    {
        $modules = app(ModuleSettingsService::class);

        $this->assertTrue($modules->enabled('articles'));

        $modules->save('articles', false, false);
        $this->assertFalse(app(ModuleSettingsService::class)->enabled('articles'));

        $modules->save('articles', true, true);
        $this->assertTrue(app(ModuleSettingsService::class)->enabled('articles'));
    }

    public function test_feature_flag_default_behavior_and_disabled_middleware(): void
    {
        Route::middleware('featureEnabled:developer_backups')
            ->get('/feature-proof-enabled', fn () => response('enabled'))
            ->name('feature-proof-enabled');
        Route::middleware('featureEnabled:developer_backups')
            ->get('/feature-proof-disabled', fn () => response('disabled'))
            ->name('feature-proof-disabled');

        $this->get('/feature-proof-enabled')
            ->assertOk()
            ->assertSee('enabled');

        FeatureFlag::create([
            'flag_key' => 'developer_backups',
            'enabled' => false,
            'type' => 'boolean',
        ]);
        app(SettingsCacheService::class)->forget();

        $this->get('/feature-proof-disabled')
            ->assertRedirect(route('home'))
            ->assertSessionHas('error', 'This feature is currently disabled.');
    }

    public function test_disabling_articles_does_not_wire_other_business_routes(): void
    {
        app(ModuleSettingsService::class)->save('articles', false, false);

        $middlewareByName = collect(app('router')->getRoutes())
            ->mapWithKeys(fn ($route) => [$route->getName() => $route->gatherMiddleware()]);

        foreach (['orders.index', 'invoices.index', 'reports.article', 'customer-payments.index'] as $routeName) {
            $this->assertNotContains('moduleEnabled:articles', $middlewareByName->get($routeName, []));
        }

        foreach (['articles.index', 'articles.create', 'update-image', 'add-rate'] as $routeName) {
            $this->assertContains('moduleEnabled:articles', $middlewareByName->get($routeName, []));
        }
    }

    public function test_only_articles_module_can_be_changed_from_phase_5b_ui(): void
    {
        $this->markTestSkipped('Obsolete phase-5B expectation; the dynamic registry now supports all reviewed modules.');
        $this->actingAs($this->user('developer'))
            ->post(route('developer.settings.modules.save'), [
                'module_key' => 'orders',
                'enabled' => true,
                'visible_in_sidebar' => true,
            ])
            ->assertSessionHasErrors('module_key');

        $this->assertDatabaseMissing('module_settings', ['module_key' => 'orders']);
    }

    protected function user(string $role): User
    {
        return User::create([
            'name' => Str::title($role) . ' User',
            'username' => $role . '_' . Str::random(8),
            'password' => Hash::make('password'),
            'role' => $role,
            'status' => 'active',
        ]);
    }
}
