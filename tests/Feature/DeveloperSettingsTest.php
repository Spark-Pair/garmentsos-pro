<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckActiveSession;
use App\Http\Middleware\SubscriptionExpiry;
use App\Http\Middleware\VerifyCsrfToken;
use App\Models\AuditLog;
use App\Models\LabelOverride;
use App\Models\ModuleSetting;
use App\Models\User;
use App\Services\Settings\LabelSettingsService;
use App\Services\Settings\SettingsCacheService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class DeveloperSettingsTest extends TestCase
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

    public function test_default_label_behavior_unchanged_without_settings(): void
    {
        $this->assertSame('Articles', label_text('article.plural', 'Articles'));
        $this->assertSame('Fallback Value', label_text('missing.key', 'Fallback Value'));
    }

    public function test_developer_and_admin_can_view_settings(): void
    {
        $this->markTestSkipped('Developer system settings are now restricted to Developer role / Developer Mode.');
        $this->actingAs($this->user('developer'))
            ->get(route('developer.settings'))
            ->assertOk()
            ->assertSee('Developer Settings')
            ->assertSee('Branding')
            ->assertSee('Modules')
            ->assertSee('Labels')
            ->assertSee('Feature Flags');

        $this->actingAs($this->user('admin'))
            ->get(route('developer.settings'))
            ->assertOk()
            ->assertSee('Developer Settings');
    }

    public function test_unauthorized_user_blocked_from_settings_page(): void
    {
        $this->actingAs($this->user('guest'))
            ->get(route('developer.settings'))
            ->assertRedirect(route('home'));
    }

    public function test_label_override_resolves_safe_text(): void
    {
        $this->actingAs($this->user('developer'));

        app(LabelSettingsService::class)->save('article.plural', 'Designs');

        $this->assertSame('Designs', label_text('article.plural', 'Articles'));
    }

    public function test_missing_label_override_falls_back(): void
    {
        $this->assertSame('Add Article', label_text('article.add', 'Add Article'));
        $this->assertSame('Fallback', label_text('custom.missing', 'Fallback'));
    }

    public function test_reset_label_override_returns_default(): void
    {
        $this->actingAs($this->user('developer'));

        $labels = app(LabelSettingsService::class);
        $labels->save('article.plural', 'Designs');
        $labels->reset('article.plural');

        $this->assertSame('Articles', label_text('article.plural', 'Articles'));
    }

    public function test_invalid_label_key_rejected(): void
    {
        $this->actingAs($this->user('developer'))
            ->from(route('developer.settings'))
            ->post(route('developer.settings.labels.save'), [
                'label_key' => 'not.registered',
                'override_text' => 'Anything',
            ])
            ->assertRedirect(route('developer.settings'))
            ->assertSessionHas('error');

        $this->assertDatabaseMissing('label_overrides', ['label_key' => 'not.registered']);
    }

    public function test_html_label_input_rejected(): void
    {
        $this->actingAs($this->user('developer'))
            ->from(route('developer.settings'))
            ->post(route('developer.settings.labels.save'), [
                'label_key' => 'article.plural',
                'override_text' => '<script>alert(1)</script>',
            ])
            ->assertRedirect(route('developer.settings'))
            ->assertSessionHasErrors('override_text');

        $this->assertDatabaseMissing('label_overrides', ['label_key' => 'article.plural']);
    }

    public function test_secret_like_label_value_rejected(): void
    {
        $this->actingAs($this->user('developer'))
            ->from(route('developer.settings'))
            ->post(route('developer.settings.labels.save'), [
                'label_key' => 'article.plural',
                'override_text' => 'token=ghp_12345678901234567890',
            ])
            ->assertRedirect(route('developer.settings'))
            ->assertSessionHas('error');

        $this->assertDatabaseMissing('label_overrides', ['label_key' => 'article.plural']);
    }

    public function test_secret_like_branding_value_rejected(): void
    {
        $this->actingAs($this->user('developer'))
            ->from(route('developer.settings'))
            ->post(route('developer.settings.branding.save'), [
                'key' => 'client_name',
                'value' => 'APP_KEY=base64:secret',
            ])
            ->assertRedirect(route('developer.settings'))
            ->assertSessionHas('error');

        $this->assertDatabaseMissing('branding_settings', ['key' => 'client_name']);
    }

    public function test_settings_cache_invalidates_after_update(): void
    {
        $this->actingAs($this->user('developer'));

        $this->assertSame('Articles', label_text('article.plural', 'Articles'));
        app(LabelSettingsService::class)->save('article.plural', 'Designs');

        $this->assertSame('Designs', label_text('article.plural', 'Articles'));
    }

    public function test_audit_log_created_for_label_change(): void
    {
        $this->actingAs($this->user('developer'));

        app(LabelSettingsService::class)->save('article.plural', 'Designs');

        $this->assertDatabaseHas('audit_logs', ['event_type' => 'settings.label_saved']);
        $this->assertSame(1, AuditLog::where('event_type', 'settings.label_saved')->count());
    }

    public function test_sidebar_article_labels_use_override(): void
    {
        $this->actingAs($this->user('developer'));

        $labels = app(LabelSettingsService::class);
        $labels->save('article.plural', 'Designs');
        $labels->save('article.show', 'Show Designs');
        $labels->save('article.add', 'Add Design');

        $this->view('components.sidebar')
            ->assertSee('Designs')
            ->assertSee('Show Designs')
            ->assertSee('Add Design');
    }

    public function test_developer_system_navigation_is_visible_only_to_developer_and_admin(): void
    {
        $this->markTestSkipped('Legacy navigation labels and admin access expectations were replaced by Developer Mode navigation.');
        $this->actingAs($this->user('developer'));

        $this->view('components.sidebar')
            ->assertSee(route('developer.settings'))
            ->assertSee(route('developer.license.status'))
            ->assertSee(route('developer.backups'))
            ->assertSee(route('developer.updater'))
            ->assertSee('System')
            ->assertSee('Developer Settings')
            ->assertSee('License Status')
            ->assertSee('Backups')
            ->assertSee('Updater');

        $this->actingAs($this->user('admin'));

        $this->view('components.sidebar')
            ->assertSee(route('developer.settings'))
            ->assertSee(route('developer.backups'));

        $this->actingAs($this->user('guest'));

        $this->view('components.sidebar')
            ->assertDontSee(route('developer.settings'))
            ->assertDontSee(route('developer.license.status'))
            ->assertDontSee(route('developer.backups'))
            ->assertDontSee(route('developer.updater'));
    }

    public function test_no_module_route_blocking_active_in_phase_5a(): void
    {
        $this->markTestSkipped('Obsolete phase-5A expectation; dynamic module route enforcement is now active.');
        ModuleSetting::create([
            'module_key' => 'articles',
            'enabled' => false,
            'visible_in_sidebar' => false,
        ]);

        $routeMiddleware = collect(app('router')->getRoutes())
            ->map(fn ($route) => $route->gatherMiddleware())
            ->flatten()
            ->unique()
            ->values()
            ->all();

        $this->assertNotContains('ensureModule', $routeMiddleware);
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
