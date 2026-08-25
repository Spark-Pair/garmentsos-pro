<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckActiveSession;
use App\Http\Middleware\SubscriptionExpiry;
use App\Http\Middleware\VerifyCsrfToken;
use App\Models\License;
use App\Models\User;
use App\Services\Licensing\InstallationFingerprintService;
use App\Services\Licensing\InstallationIdentityService;
use App\Services\Settings\ModuleSettingsService;
use App\Services\Settings\SettingsCacheService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class ModuleRouteMapExpansionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'licensing.identity_path' => storage_path('framework/testing/license-' . Str::random(12) . '/installation.json'),
            'licensing.enabled' => false,
        ]);

        $this->withoutMiddleware([CheckActiveSession::class, SubscriptionExpiry::class, VerifyCsrfToken::class]);
        app(SettingsCacheService::class)->forget();
    }

    public function test_missing_customers_setting_allows_customer_route(): void
    {
        $this->actingAs($this->user('developer'))
            ->get(route('customers.index'))
            ->assertOk();
    }

    public function test_disabled_customers_blocks_customer_direct_url(): void
    {
        app(ModuleSettingsService::class)->save('customers', false, false);

        $this->actingAs($this->user('developer'))
            ->get(route('customers.index'))
            ->assertRedirect(route('home'))
            ->assertSessionHas('error', 'This module is currently disabled.');
    }

    public function test_disabled_customers_hides_customer_sidebar_link(): void
    {
        app(ModuleSettingsService::class)->save('customers', false, false);

        $this->actingAs($this->user('developer'))
            ->view('components.sidebar')
            ->assertDontSee('Show Customers')
            ->assertDontSee('Add Customer')
            ->assertDontSee('Manage your customers');
    }

    public function test_license_module_list_does_not_override_enabled_customers_setting(): void
    {
        config(['licensing.enabled' => true]);
        $this->createLicense(['suppliers', 'articles']);
        app(ModuleSettingsService::class)->save('customers', true, true);

        $this->actingAs($this->user('developer'))
            ->get(route('customers.index'))
            ->assertOk();
    }

    public function test_missing_suppliers_setting_allows_supplier_route(): void
    {
        $this->actingAs($this->user('developer'))
            ->get(route('suppliers.index'))
            ->assertOk();
    }

    public function test_disabled_suppliers_blocks_supplier_direct_url(): void
    {
        app(ModuleSettingsService::class)->save('suppliers', false, false);

        $this->actingAs($this->user('developer'))
            ->get(route('suppliers.index'))
            ->assertRedirect(route('home'))
            ->assertSessionHas('error', 'This module is currently disabled.');
    }

    public function test_disabled_suppliers_hides_supplier_sidebar_link(): void
    {
        app(ModuleSettingsService::class)->save('suppliers', false, false);

        $this->actingAs($this->user('developer'))
            ->view('components.sidebar')
            ->assertDontSee('Show Suppliers')
            ->assertDontSee('Add Supplier')
            ->assertDontSee('Manage your suppliers');
    }

    public function test_license_module_list_does_not_override_enabled_suppliers_setting(): void
    {
        config(['licensing.enabled' => true]);
        $this->createLicense(['customers', 'articles']);
        app(ModuleSettingsService::class)->save('suppliers', true, true);

        $this->actingAs($this->user('developer'))
            ->get(route('suppliers.index'))
            ->assertOk();
    }

    public function test_disabling_customers_does_not_block_suppliers(): void
    {
        app(ModuleSettingsService::class)->save('customers', false, false);

        $this->actingAs($this->user('developer'))
            ->get(route('suppliers.index'))
            ->assertOk();
    }

    public function test_disabling_suppliers_does_not_block_customers(): void
    {
        app(ModuleSettingsService::class)->save('suppliers', false, false);

        $this->actingAs($this->user('developer'))
            ->get(route('customers.index'))
            ->assertOk();
    }

    public function test_disabling_customers_and_suppliers_does_not_affect_articles_default_behavior(): void
    {
        app(ModuleSettingsService::class)->save('customers', false, false);
        app(ModuleSettingsService::class)->save('suppliers', false, false);

        $this->actingAs($this->user('developer'))
            ->get(route('articles.index'))
            ->assertOk();
    }

    public function test_customer_supplier_expansion_does_not_wire_risky_business_routes(): void
    {
        app(ModuleSettingsService::class)->save('customers', false, false);
        app(ModuleSettingsService::class)->save('suppliers', false, false);

        $middlewareByName = collect(app('router')->getRoutes())
            ->mapWithKeys(fn ($route) => [$route->getName() => $route->gatherMiddleware()]);

        foreach (['orders.index', 'invoices.index', 'customer-payments.index', 'reports.article'] as $routeName) {
            $middleware = $middlewareByName->get($routeName, []);
            $this->assertNotContains('moduleEnabled:customers', $middleware);
            $this->assertNotContains('moduleEnabled:suppliers', $middleware);
        }
    }

    public function test_developer_settings_route_remains_accessible(): void
    {
        app(ModuleSettingsService::class)->save('customers', false, false);
        app(ModuleSettingsService::class)->save('suppliers', false, false);

        $this->actingAs($this->user('developer'))
            ->get(route('developer.settings'))
            ->assertOk();
    }

    public function test_guest_auth_behavior_still_applies_before_customer_module_block(): void
    {
        app(ModuleSettingsService::class)->save('customers', false, false);

        $this->get(route('customers.index'))
            ->assertRedirect(route('login'));
    }

    public function test_route_middleware_is_limited_to_reviewed_customer_supplier_routes(): void
    {
        $middlewareByName = collect(app('router')->getRoutes())
            ->mapWithKeys(fn ($route) => [$route->getName() => $route->gatherMiddleware()]);

        foreach (['customers.index', 'customers.create', 'customers.store', 'customers.edit', 'customers.update', 'suppliers.index', 'suppliers.create', 'suppliers.store', 'suppliers.edit', 'suppliers.update', 'update-supplier-category'] as $routeName) {
            $expected = str_starts_with($routeName, 'customers.') ? 'moduleEnabled:customers' : 'moduleEnabled:suppliers';
            $this->assertContains($expected, $middlewareByName->get($routeName, []));
        }

        foreach (['orders.index', 'invoices.index', 'customer-payments.index', 'reports.article'] as $routeName) {
            $this->assertNotContains('moduleEnabled:customers', $middlewareByName->get($routeName, []));
            $this->assertNotContains('moduleEnabled:suppliers', $middlewareByName->get($routeName, []));
        }
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

    protected function createLicense(array $modules = []): License
    {
        $installation = app(InstallationIdentityService::class)->current();
        $installation->update([
            'fingerprint_hash' => app(InstallationFingerprintService::class)->fingerprintHash(),
        ]);

        return License::create([
            'app_installation_id' => $installation->id,
            'license_key_hash' => hash('sha256', 'license-' . Str::random(20)),
            'client_name' => 'Test Client',
            'business_name' => 'Test Business',
            'status' => 'active',
            'subscription_status' => 'active',
            'subscription_expires_at' => now()->addMonth(),
            'license_expires_at' => now()->addYear(),
            'offline_grace_days' => 7,
            'offline_grace_until' => now()->addDays(7),
            'enforcement_mode' => 'readonly',
            'allowed_modules' => $modules,
            'allowed_features' => [],
            'allowed_brand_ids' => [],
            'update_channel' => 'stable',
            'last_verified_at' => now(),
            'signed_payload_hash' => hash('sha256', Str::random(20)),
            'metadata' => ['server_license_id' => 'lic_' . Str::random(8)],
        ]);
    }
}
