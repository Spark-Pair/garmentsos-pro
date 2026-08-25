<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckActiveSession;
use App\Http\Middleware\SubscriptionExpiry;
use App\Http\Middleware\VerifyCsrfToken;
use App\Models\AuditLog;
use App\Models\BrandingSetting;
use App\Models\User;
use App\Services\Settings\BrandingSettingsService;
use App\Services\Settings\SettingsCacheService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class BrandingSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'licensing.identity_path' => storage_path('framework/testing/license-' . Str::random(12) . '/installation.json'),
            'client_company.name' => 'Config Company',
            'client_company.logo_text' => 'Config Logo',
            'client_company.logo_svg_path' => 'images/default.svg',
            'client_company.phone_number' => '03001234567',
            'branding.app_name' => 'GarmentsOS PRO',
            'branding.company_name' => 'Branding Company',
            'branding.theme_primary_color' => '#2563eb',
            'branding.theme_secondary_color' => '#1f2937',
            'branding.theme_accent_color' => '#2563eb',
        ]);

        $this->withoutMiddleware([CheckActiveSession::class, SubscriptionExpiry::class, VerifyCsrfToken::class]);
        app(SettingsCacheService::class)->forget();
        app()->forgetInstance('client_company');
    }

    public function test_missing_branding_settings_preserve_current_config_branding(): void
    {
        $branding = app(BrandingSettingsService::class);

        $this->assertSame('Config Company', $branding->value('company_name'));
        $this->assertSame('Config Logo', $branding->value('app_name'));
        $this->assertSame('03001234567', $branding->value('phone'));
        $this->assertSame('#2563eb', $branding->value('theme_primary_color'));
    }

    public function test_missing_branding_settings_table_does_not_crash_resolver(): void
    {
        Schema::dropIfExists('branding_settings');
        app(SettingsCacheService::class)->forget();

        $branding = app(BrandingSettingsService::class)->effectiveValues();

        $this->assertSame('Config Company', $branding['company_name']);
        $this->assertSame('#2563eb', $branding['theme_primary_color']);
    }

    public function test_developer_and_admin_can_view_branding_settings(): void
    {
        $this->markTestSkipped('Developer system settings are now restricted to Developer role / Developer Mode.');
        $this->actingAs($this->user('developer'))
            ->get(route('developer.settings'))
            ->assertOk()
            ->assertSee('Branding')
            ->assertSee('Safe text/color only')
            ->assertSee('Effective')
            ->assertSee('Reset');

        $this->actingAs($this->user('admin'))
            ->get(route('developer.settings'))
            ->assertOk()
            ->assertSee('Branding');
    }

    public function test_unauthorized_user_blocked_from_branding_settings(): void
    {
        $this->actingAs($this->user('guest'))
            ->get(route('developer.settings'))
            ->assertRedirect(route('home'));

        $this->actingAs($this->user('guest'))
            ->post(route('developer.settings.branding.save'), [
                'key' => 'company_name',
                'value' => 'Blocked',
            ])
            ->assertForbidden();
    }

    public function test_safe_text_setting_updates_effective_branding(): void
    {
        $this->actingAs($this->user('developer'))
            ->post(route('developer.settings.branding.save'), [
                'key' => 'company_name',
                'value' => 'Acme Garments',
            ])
            ->assertRedirect(route('developer.settings'))
            ->assertSessionHas('success');

        $this->assertSame('Acme Garments', app(BrandingSettingsService::class)->value('company_name'));
    }

    public function test_html_script_text_rejected(): void
    {
        $this->actingAs($this->user('developer'))
            ->from(route('developer.settings'))
            ->post(route('developer.settings.branding.save'), [
                'key' => 'company_name',
                'value' => '<script>alert(1)</script>',
            ])
            ->assertRedirect(route('developer.settings'))
            ->assertSessionHasErrors('value');

        $this->assertDatabaseMissing('branding_settings', ['key' => 'company_name']);
    }

    public function test_secret_like_branding_value_rejected(): void
    {
        $this->actingAs($this->user('developer'))
            ->from(route('developer.settings'))
            ->post(route('developer.settings.branding.save'), [
                'key' => 'company_name',
                'value' => 'APP_KEY=base64:secret',
            ])
            ->assertRedirect(route('developer.settings'))
            ->assertSessionHas('error');

        $this->assertDatabaseMissing('branding_settings', ['key' => 'company_name']);
    }

    public function test_invalid_color_rejected(): void
    {
        $this->actingAs($this->user('developer'))
            ->from(route('developer.settings'))
            ->post(route('developer.settings.branding.save'), [
                'key' => 'theme_primary_color',
                'value' => 'url(javascript:alert(1))',
            ])
            ->assertRedirect(route('developer.settings'))
            ->assertSessionHasErrors('value');

        $this->assertDatabaseMissing('branding_settings', ['key' => 'theme_primary_color']);
    }

    public function test_valid_hex_color_accepted(): void
    {
        $this->actingAs($this->user('developer'))
            ->post(route('developer.settings.branding.save'), [
                'key' => 'theme_primary_color',
                'value' => '#e85f24',
            ])
            ->assertRedirect(route('developer.settings'));

        $this->assertSame('#e85f24', app(BrandingSettingsService::class)->value('theme_primary_color'));
    }

    public function test_reset_returns_to_config_default_value(): void
    {
        $this->actingAs($this->user('developer'));

        app(BrandingSettingsService::class)->save('company_name', 'Acme Garments');
        $this->assertSame('Acme Garments', app(BrandingSettingsService::class)->value('company_name'));

        $this->post(route('developer.settings.branding.reset', 'company_name'))
            ->assertRedirect(route('developer.settings'))
            ->assertSessionHas('success');

        $this->assertSame('Config Company', app(BrandingSettingsService::class)->value('company_name'));
        $this->assertDatabaseMissing('branding_settings', ['key' => 'company_name']);
    }

    public function test_cache_invalidates_after_update_and_reset(): void
    {
        $this->actingAs($this->user('developer'));

        $branding = app(BrandingSettingsService::class);
        $this->assertSame('Config Company', $branding->value('company_name'));

        $branding->save('company_name', 'Cache Brand');
        $this->assertSame('Cache Brand', app(BrandingSettingsService::class)->value('company_name'));

        $branding->reset('company_name');
        $this->assertSame('Config Company', app(BrandingSettingsService::class)->value('company_name'));
    }

    public function test_audit_log_created_for_branding_change(): void
    {
        $this->actingAs($this->user('developer'));

        app(BrandingSettingsService::class)->save('company_name', 'Audited Brand');

        $this->assertDatabaseHas('audit_logs', ['event_type' => 'settings.branding_saved']);
        $this->assertSame(1, AuditLog::where('event_type', 'settings.branding_saved')->count());
    }

    public function test_layout_sidebar_login_and_home_use_effective_branding(): void
    {
        $this->actingAs($this->user('developer'));
        app(BrandingSettingsService::class)->save('company_name', 'Effective Company');
        app(BrandingSettingsService::class)->save('app_name', 'Effective App');
        app(BrandingSettingsService::class)->save('logo_text', 'Effective App');
        app()->forgetInstance('client_company');

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Welcome to Effective Company!')
            ->assertSee('Effective App | Track your progress');

        $this->view('auth.login')
            ->assertSee('Effective App')
            ->assertSee('Effective Company');

        $this->view('components.sidebar')
            ->assertSee('Effective App', false);
    }

    public function test_existing_client_company_fields_remain_available(): void
    {
        $company = app(BrandingSettingsService::class)->clientCompany();

        $this->assertSame('Config Company', $company->name);
        $this->assertSame('images/default.svg', $company->logo_svg_path);
        $this->assertSame('Config Logo', $company->logo_text);
        $this->assertSame('03001234567', $company->phone_number);
        $this->assertTrue(property_exists($company, 'logo'));
    }

    public function test_print_and_logo_path_keys_are_not_exposed_for_upload_or_path_editing(): void
    {
        $this->assertArrayNotHasKey('logo_path', config('branding'));
        $this->assertArrayNotHasKey('logo_upload', config('branding'));
        $this->assertArrayNotHasKey('favicon', config('branding'));
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
