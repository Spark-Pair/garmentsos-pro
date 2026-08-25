<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckActiveSession;
use App\Http\Middleware\SubscriptionExpiry;
use App\Http\Middleware\VerifyCsrfToken;
use App\Models\AuditLog;
use App\Models\FeatureFlag;
use App\Models\License;
use App\Models\User;
use App\Services\Licensing\InstallationFingerprintService;
use App\Services\Licensing\InstallationIdentityService;
use App\Services\Settings\FeatureAvailabilityService;
use App\Services\Settings\ModuleAvailabilityService;
use App\Services\Settings\ModuleSettingsService;
use App\Services\Settings\SettingsCacheService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

class LicenseModulePrecedenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->markTestSkipped('License module lists no longer control module availability; Developer Settings are authoritative.');

        config([
            'licensing.identity_path' => storage_path('framework/testing/license-' . Str::random(12) . '/installation.json'),
            'licensing.enabled' => false,
        ]);

        $this->withoutMiddleware([CheckActiveSession::class, SubscriptionExpiry::class, VerifyCsrfToken::class]);
        app(SettingsCacheService::class)->forget();
    }

    public function test_local_missing_and_license_missing_or_unrestricted_allows_articles(): void
    {
        $this->actingAs($this->user('developer'))
            ->get(route('articles.index'))
            ->assertOk();

        config(['licensing.enabled' => true]);
        $this->createLicense([]);

        $this->actingAs($this->user('developer'))
            ->get(route('articles.index'))
            ->assertOk();
    }

    public function test_license_disallows_articles_route_is_blocked(): void
    {
        config(['licensing.enabled' => true]);
        $this->createLicense(['orders']);

        $this->actingAs($this->user('developer'))
            ->get(route('articles.index'))
            ->assertRedirect(route('home'))
            ->assertSessionHas('error', 'This module is not included in the active license.');

        $state = app(ModuleAvailabilityService::class)->effectiveState('articles');
        $this->assertFalse($state['available']);
        $this->assertSame('disabled_by_license', $state['reason']);
    }

    public function test_license_disallows_articles_local_enabled_still_blocked_by_license(): void
    {
        config(['licensing.enabled' => true]);
        $this->createLicense(['orders']);
        app(ModuleSettingsService::class)->save('articles', true, true);

        $state = app(ModuleAvailabilityService::class)->effectiveState('articles');

        $this->assertFalse($state['available']);
        $this->assertTrue($state['local_enabled']);
        $this->assertFalse($state['license_allowed']);
        $this->assertSame('disabled_by_license', $state['reason']);
    }

    public function test_license_allows_articles_local_disabled_blocks_locally(): void
    {
        config(['licensing.enabled' => true]);
        $this->createLicense(['articles']);
        app(ModuleSettingsService::class)->save('articles', false, false);

        $this->actingAs($this->user('developer'))
            ->get(route('articles.index'))
            ->assertRedirect(route('home'))
            ->assertSessionHas('error', 'This module is currently disabled.');

        $state = app(ModuleAvailabilityService::class)->effectiveState('articles');
        $this->assertFalse($state['available']);
        $this->assertTrue($state['license_allowed']);
        $this->assertFalse($state['local_enabled']);
        $this->assertSame('disabled_locally', $state['reason']);
    }

    public function test_license_allows_articles_local_missing_allows_articles(): void
    {
        config(['licensing.enabled' => true]);
        $this->createLicense(['articles']);

        $this->actingAs($this->user('developer'))
            ->get(route('articles.index'))
            ->assertOk();

        $state = app(ModuleAvailabilityService::class)->effectiveState('articles');
        $this->assertTrue($state['available']);
        $this->assertTrue($state['license_allowed']);
        $this->assertNull($state['local_enabled']);
    }

    public function test_licensing_disabled_or_unavailable_preserves_current_behavior(): void
    {
        config(['licensing.enabled' => false]);
        $this->createLicense(['orders']);

        $this->actingAs($this->user('developer'))
            ->get(route('articles.index'))
            ->assertOk();

        config(['licensing.enabled' => true]);
        License::query()->delete();

        $this->actingAs($this->user('developer'))
            ->get(route('articles.index'))
            ->assertForbidden();

        $this->assertSame('licensing_unavailable', app(ModuleAvailabilityService::class)->effectiveState('articles')['reason']);
    }

    public function test_blocked_license_status_blocks_request_before_module_lockout(): void
    {
        config(['licensing.enabled' => true]);
        $license = $this->createLicense(['orders']);
        $license->update(['status' => 'blocked']);

        $this->actingAs($this->user('developer'))
            ->get(route('articles.index'))
            ->assertForbidden();

        $state = app(ModuleAvailabilityService::class)->effectiveState('articles');
        $this->assertTrue($state['available']);
        $this->assertNull($state['license_allowed']);
        $this->assertSame('licensing_unavailable', $state['reason']);
    }

    public function test_sidebar_hides_articles_when_disabled_by_license_or_locally(): void
    {
        config(['licensing.enabled' => true]);
        $this->createLicense(['orders']);

        $this->actingAs($this->user('developer'))
            ->view('components.sidebar')
            ->assertDontSee('Show Articles')
            ->assertDontSee('Add Article');

        config(['licensing.enabled' => false]);
        app(ModuleSettingsService::class)->save('articles', false, false);

        $this->actingAs($this->user('developer'))
            ->view('components.sidebar')
            ->assertDontSee('Show Articles')
            ->assertDontSee('Add Article');
    }

    public function test_developer_settings_route_remains_accessible_when_articles_disabled(): void
    {
        config(['licensing.enabled' => true]);
        $this->createLicense(['orders']);
        app(ModuleSettingsService::class)->save('articles', false, false);

        $this->actingAs($this->user('developer'))
            ->get(route('developer.settings'))
            ->assertOk()
            ->assertSee('disabled by license');
    }

    public function test_orders_invoices_payments_and_reports_are_unaffected(): void
    {
        config(['licensing.enabled' => true]);
        $this->createLicense(['orders']);

        $middlewareByName = collect(app('router')->getRoutes())
            ->mapWithKeys(fn ($route) => [$route->getName() => $route->gatherMiddleware()]);

        foreach (['orders.index', 'invoices.index', 'reports.article', 'customer-payments.index'] as $routeName) {
            $this->assertNotContains('moduleEnabled:articles', $middlewareByName->get($routeName, []));
        }

        $this->assertContains('moduleEnabled:articles', $middlewareByName->get('articles.index', []));
    }

    public function test_feature_flag_effective_state_follows_license_local_and_default_precedence(): void
    {
        config(['licensing.enabled' => false]);
        $this->assertTrue(app(FeatureAvailabilityService::class)->isEffectivelyEnabled('developer_backups'));

        config(['licensing.enabled' => true]);
        $this->createLicense(['articles'], ['pusher_notifications']);
        FeatureFlag::create([
            'flag_key' => 'developer_backups',
            'enabled' => true,
            'type' => 'boolean',
        ]);
        app(SettingsCacheService::class)->forget();

        $licenseBlocked = app(FeatureAvailabilityService::class)->effectiveState('developer_backups');
        $this->assertFalse($licenseBlocked['available']);
        $this->assertSame('disabled_by_license', $licenseBlocked['reason']);

        License::query()->delete();
        $this->createLicense(['articles'], ['developer_backups']);
        FeatureFlag::query()->where('flag_key', 'developer_backups')->update(['enabled' => false]);
        app(SettingsCacheService::class)->forget();

        $locallyBlocked = app(FeatureAvailabilityService::class)->effectiveState('developer_backups');
        $this->assertFalse($locallyBlocked['available']);
        $this->assertSame('disabled_locally', $locallyBlocked['reason']);
    }

    public function test_feature_middleware_blocks_license_disallowed_feature_on_safe_test_route(): void
    {
        Route::middleware('featureEnabled:developer_backups')
            ->get('/license-feature-proof', fn () => response('feature'))
            ->name('license-feature-proof');

        config(['licensing.enabled' => true]);
        $this->createLicense(['articles'], ['pusher_notifications']);

        $this->get('/license-feature-proof')
            ->assertRedirect(route('home'))
            ->assertSessionHas('error', 'This feature is not included in the active license.');
    }

    public function test_blocked_route_checks_do_not_create_audit_rows(): void
    {
        config(['licensing.enabled' => true]);
        $this->createLicense(['orders']);
        $before = AuditLog::count();

        $this->actingAs($this->user('developer'))
            ->get(route('articles.index'))
            ->assertRedirect(route('home'));

        $this->assertSame($before, AuditLog::count());
    }

    protected function createLicense(array $modules = [], array $features = []): License
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
            'allowed_features' => $features,
            'allowed_brand_ids' => [],
            'update_channel' => 'stable',
            'last_verified_at' => now(),
            'signed_payload_hash' => hash('sha256', Str::random(20)),
            'metadata' => ['server_license_id' => 'lic_' . Str::random(8)],
        ]);
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
