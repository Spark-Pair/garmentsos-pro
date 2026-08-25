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

class ReportsModuleEnforcementTest extends TestCase
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

    public function test_missing_reports_setting_allows_statement_route(): void
    {
        $this->actingAs($this->user('developer'))
            ->get(route('reports.statement'))
            ->assertOk();
    }

    public function test_missing_reports_setting_shows_report_sidebar_links(): void
    {
        $this->actingAs($this->user('developer'))
            ->view('components.sidebar')
            ->assertSee('Manage your reports')
            ->assertSee('Pending Payments')
            ->assertSee('/reports/statement', false);
    }

    public function test_disabled_reports_blocks_statement_route(): void
    {
        $this->disableReports();

        $this->actingAs($this->user('developer'))
            ->get(route('reports.statement'))
            ->assertRedirect(route('home'))
            ->assertSessionHas('error', 'This module is currently disabled.');
    }

    public function test_disabled_reports_blocks_statement_record_details_route(): void
    {
        $this->disableReports();

        $this->actingAs($this->user('developer'))
            ->get(route('reports.statement.record-details', ['type' => 'invoice', 'id' => 1]))
            ->assertRedirect(route('home'))
            ->assertSessionHas('error', 'This module is currently disabled.');
    }

    public function test_disabled_reports_blocks_pending_payments_route(): void
    {
        $this->disableReports();

        $this->actingAs($this->user('developer'))
            ->get(route('reports.pending-payments'))
            ->assertRedirect(route('home'));
    }

    public function test_disabled_reports_blocks_article_report_route(): void
    {
        $this->disableReports();

        $this->actingAs($this->user('developer'))
            ->get(route('reports.article'))
            ->assertRedirect(route('home'));
    }

    public function test_disabled_reports_blocks_physical_quantity_report_route(): void
    {
        $this->disableReports();

        $this->actingAs($this->user('developer'))
            ->get(route('reports.physical-quantity'))
            ->assertRedirect(route('home'));
    }

    public function test_disabled_reports_hides_desktop_reports_group(): void
    {
        $this->disableReports();

        $this->actingAs($this->user('developer'))
            ->view('components.sidebar')
            ->assertDontSee('/reports/article', false)
            ->assertDontSee('/reports/pending-payments', false);
    }

    public function test_disabled_reports_hides_portal_statement_links(): void
    {
        $this->disableReports();

        $this->actingAs($this->user('customer'))
            ->view('components.sidebar')
            ->assertDontSee('View your statement')
            ->assertDontSee('/reports/statement', false);

        $this->actingAs($this->user('supplier'))
            ->view('components.sidebar')
            ->assertDontSee('View your statement')
            ->assertDontSee('/reports/statement', false);
    }

    public function test_license_module_list_does_not_override_enabled_reports_setting(): void
    {
        config(['licensing.enabled' => true]);
        $this->createLicense(['articles', 'customers', 'suppliers']);
        app(ModuleSettingsService::class)->save('reports', true, true);

        $this->actingAs($this->user('developer'))
            ->get(route('reports.statement'))
            ->assertOk();
    }

    public function test_disabled_reports_does_not_block_dashboard_home(): void
    {
        $this->disableReports();

        $this->actingAs($this->user('developer'))
            ->get(route('home'))
            ->assertOk();
    }

    public function test_disabled_reports_does_not_block_existing_module_routes(): void
    {
        $this->disableReports();
        $user = $this->user('developer');

        $this->actingAs($user)->get(route('customers.index'))->assertOk();
        $this->actingAs($user)->get(route('suppliers.index'))->assertOk();
        $this->actingAs($user)->get(route('articles.index'))->assertOk();
    }

    public function test_statement_get_names_helper_remains_unguarded(): void
    {
        $this->disableReports();

        $this->actingAs($this->user('developer'))
            ->postJson(route('reports.statement.get-names'), ['category' => 'customer'])
            ->assertOk()
            ->assertJson([]);
    }

    public function test_report_session_setter_routes_remain_unguarded(): void
    {
        $this->disableReports();
        $user = $this->user('developer');

        $this->actingAs($user)
            ->postJson(route('set-statement-type'), ['statement_type' => 'detailed'])
            ->assertOk()
            ->assertJson(['status' => 'success']);

        $this->actingAs($user)
            ->postJson(route('set-physical-quantity-report-type'), ['physical_quantity_report_type' => 'stock'])
            ->assertOk()
            ->assertJson(['status' => 'success']);
    }

    public function test_guest_auth_behavior_applies_before_reports_module_block(): void
    {
        $this->disableReports();

        $this->get(route('reports.statement'))
            ->assertRedirect(route('login'));
    }

    public function test_guarded_report_json_route_returns_safe_403(): void
    {
        $this->disableReports();

        $this->actingAs($this->user('developer'))
            ->getJson(route('reports.article'))
            ->assertForbidden()
            ->assertJson([
                'status' => 'module_disabled',
                'message' => 'This module is currently disabled.',
            ]);
    }

    protected function disableReports(): void
    {
        app(ModuleSettingsService::class)->save('reports', false, false);
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
