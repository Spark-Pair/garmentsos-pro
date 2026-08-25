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

class RatesModuleEnforcementTest extends TestCase
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

    public function test_missing_rates_setting_allows_rate_page(): void
    {
        $this->actingAs($this->user('developer'))
            ->get(route('rates.create'))
            ->assertOk();
    }

    public function test_disabled_rates_blocks_direct_rate_url(): void
    {
        $this->disableRates();

        $this->actingAs($this->user('developer'))
            ->get(route('rates.index'))
            ->assertRedirect(route('home'))
            ->assertSessionHas('error', 'This module is currently disabled.');
    }

    public function test_disabled_rates_hides_desktop_rates_link(): void
    {
        $this->disableRates();

        $this->actingAs($this->user('developer'))
            ->view('components.sidebar')
            ->assertDontSee('/rates', false)
            ->assertDontSee('Rates');
    }

    public function test_missing_rates_setting_shows_desktop_rates_link(): void
    {
        $this->actingAs($this->user('developer'))
            ->view('components.sidebar')
            ->assertSee('/rates', false)
            ->assertSee('Rates');
    }

    public function test_license_module_list_does_not_override_enabled_rates_setting(): void
    {
        config(['licensing.enabled' => true]);
        $this->createLicense(['articles', 'customers', 'suppliers', 'reports']);
        app(ModuleSettingsService::class)->save('rates', true, true);

        $this->actingAs($this->user('developer'))
            ->get(route('rates.create'))
            ->assertOk();
    }

    public function test_disabled_rates_does_not_block_existing_enforced_modules(): void
    {
        $this->disableRates();
        $user = $this->user('developer');

        $this->actingAs($user)->get(route('articles.index'))->assertOk();
        $this->actingAs($user)->get(route('customers.index'))->assertOk();
        $this->actingAs($user)->get(route('suppliers.index'))->assertOk();
        $this->actingAs($user)->get(route('reports.article'))->assertOk();
    }

    public function test_disabled_rates_does_not_wire_order_invoice_payment_or_stock_routes(): void
    {
        $this->disableRates();

        $middlewareByName = collect(app('router')->getRoutes())
            ->mapWithKeys(fn ($route) => [$route->getName() => $route->gatherMiddleware()]);

        foreach (['orders.index', 'invoices.index', 'customer-payments.index', 'supplier-payments.index', 'fabrics.index'] as $routeName) {
            $this->assertNotContains('moduleEnabled:rates', $middlewareByName->get($routeName, []));
        }
    }

    public function test_add_rate_remains_article_controlled_and_not_rates_controlled(): void
    {
        $this->disableRates();

        $middlewareByName = collect(app('router')->getRoutes())
            ->mapWithKeys(fn ($route) => [$route->getName() => $route->gatherMiddleware()]);

        $this->assertContains('moduleEnabled:articles', $middlewareByName->get('add-rate', []));
        $this->assertNotContains('moduleEnabled:rates', $middlewareByName->get('add-rate', []));

        $this->actingAs($this->user('developer'))
            ->from(route('articles.index'))
            ->post(route('add-rate'), [])
            ->assertRedirect(route('articles.index'))
            ->assertSessionHasErrors(['article_id', 'sales_rate', 'pcs_per_packet']);
    }

    public function test_setups_routes_remain_unaffected_by_disabled_rates(): void
    {
        $this->disableRates();

        $this->actingAs($this->user('developer'))
            ->get(route('setups.index'))
            ->assertOk();

        $middlewareByName = collect(app('router')->getRoutes())
            ->mapWithKeys(fn ($route) => [$route->getName() => $route->gatherMiddleware()]);

        $this->assertNotContains('moduleEnabled:rates', $middlewareByName->get('setups.index', []));
        $this->assertNotContains('moduleEnabled:rates', $middlewareByName->get('setups.store', []));
    }

    public function test_shared_category_helper_remains_unaffected_by_disabled_rates(): void
    {
        $this->disableRates();

        $this->actingAs($this->user('developer'))
            ->post(route('get-category-data'), ['category' => 'unknown'])
            ->assertOk()
            ->assertSee('Not Found');
    }

    public function test_guest_auth_behavior_applies_before_rates_module_block(): void
    {
        $this->disableRates();

        $this->get(route('rates.create'))
            ->assertRedirect(route('login'));
    }

    public function test_readonly_behavior_for_rates_write_routes_remains_unchanged(): void
    {
        $this->markTestSkipped('Legacy session-only readonly flag was replaced by license/update read-only middleware state.');
        $this->actingAs($this->user('developer'))
            ->withSession(['readonly' => true])
            ->from(route('rates.create'))
            ->post(route('rates.store'), [])
            ->assertRedirect(route('rates.create'))
            ->assertSessionHas('error', 'Read-only mode is enabled. You cannot perform this action.');
    }

    public function test_only_direct_rates_resource_routes_are_guarded_by_rates_module(): void
    {
        $middlewareByName = collect(app('router')->getRoutes())
            ->mapWithKeys(fn ($route) => [$route->getName() => $route->gatherMiddleware()]);

        foreach (['rates.index', 'rates.create', 'rates.store', 'rates.show', 'rates.edit', 'rates.update', 'rates.destroy'] as $routeName) {
            $this->assertContains('moduleEnabled:rates', $middlewareByName->get($routeName, []));
        }

        foreach ([
            'setups.index',
            'setups.store',
            'add-rate',
            'get-category-data',
            'get-employees-by-category',
            'get-order-details',
            'get-program-details',
            'get-shipment-details',
            'get-voucher-details',
            'get-payments-by-method',
            'get-utility-accounts',
            'set-invoice-type',
            'set-voucher-type',
            'set-production-type',
            'set-daily-ledger-type',
            'set-cr-type',
            'set-statement-type',
        ] as $routeName) {
            $this->assertNotContains('moduleEnabled:rates', $middlewareByName->get($routeName, []));
        }
    }

    protected function disableRates(): void
    {
        app(ModuleSettingsService::class)->save('rates', false, false);
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
