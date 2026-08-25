<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckActiveSession;
use App\Http\Middleware\SubscriptionExpiry;
use App\Http\Middleware\VerifyCsrfToken;
use App\Models\BackupLog;
use App\Models\User;
use App\Services\BackupService;
use App\Services\BackupStorageService;
use App\Services\BackupVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class BackupFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected string $backupPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->backupPath = 'framework/testing/backups-' . Str::random(12);

        config([
            'backup.path' => $this->backupPath,
            'licensing.identity_path' => storage_path('framework/testing/license-' . Str::random(12) . '/installation.json'),
        ]);

        $this->withoutMiddleware([CheckActiveSession::class, SubscriptionExpiry::class, VerifyCsrfToken::class]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(storage_path('app/' . $this->backupPath));

        parent::tearDown();
    }

    public function test_developer_can_create_private_verified_backup(): void
    {
        $this->actingAs($this->user('developer'));

        $response = $this->post(route('developer.backups.store'));

        $response->assertRedirect(route('developer.backups'));
        $response->assertSessionHas('success');

        $log = BackupLog::first();
        $this->assertNotNull($log);
        $this->assertSame('success', $log->status);
        $this->assertSame('manual_backup', $log->action);
        $this->assertNotEmpty($log->checksum);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $log->checksum);
        $this->assertStringStartsWith($this->backupPath, $log->path);
        $this->assertStringNotContainsString('/public/', str_replace('\\', '/', $log->path));

        $path = app(BackupStorageService::class)->resolveManagedPath($log);
        $this->assertFileExists($path);
        $this->assertFileExists($path . '.json');
        $this->assertStringStartsWith(storage_path('app/' . $this->backupPath), $path);
        $this->assertStringNotContainsString(public_path(), $path);

        $metadata = json_decode(File::get($path . '.json'), true);
        $this->assertSame($log->checksum, $metadata['checksum']);
        $this->assertFalse($metadata['restore_implemented']);

        $this->assertDatabaseHas('audit_logs', ['event_type' => 'backup.create_succeeded']);
    }

    public function test_admin_can_create_backup(): void
    {
        $this->actingAs($this->user('admin'));

        $this->post(route('developer.backups.store'))->assertSessionHas('success');

        $this->assertSame(1, BackupLog::where('status', 'success')->count());
    }

    public function test_backup_page_renders_polished_safe_foundation_ui(): void
    {
        $this->actingAs($this->user('developer'));

        $this->get(route('developer.backups'))
            ->assertOk()
            ->assertSee('Backup &amp; Restore', false)
            ->assertSee('Private storage')
            ->assertSee('Restore Status')
            ->assertSee('No managed backups yet');
    }

    public function test_missing_backup_tables_render_friendly_pending_state_instead_of_500(): void
    {
        Schema::dropIfExists('backup_logs');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('app_installations');

        $this->actingAs($this->user('developer'));

        $this->get(route('developer.backups'))
            ->assertOk()
            ->assertSee('Backup setup is pending')
            ->assertSee('Missing: app_installations, backup_logs, audit_logs');

        $this->post(route('developer.backups.store'))
            ->assertRedirect(route('developer.backups'))
            ->assertSessionHas('error');
    }

    public function test_unauthorized_user_is_blocked_from_backup_page_and_download(): void
    {
        $this->actingAs($this->user('guest'));

        $this->get(route('developer.backups'))->assertRedirect(route('home'));

        $log = $this->createBackupLog();
        $this->get(route('developer.backups.download', $log))->assertRedirect(route('home'));
    }

    public function test_valid_backup_verification_succeeds(): void
    {
        $result = app(BackupService::class)->createManualBackup();
        $log = $result['backup_log'];

        $verified = app(BackupVerifier::class)->verify($result['path'], $log->checksum);

        $this->assertTrue($verified['valid']);
        $this->assertSame('valid', $verified['code']);
    }

    public function test_invalid_backup_is_rejected(): void
    {
        $storage = app(BackupStorageService::class);
        $storage->ensureDirectoryExists();
        $path = $storage->finalFilePath('garmentsos_backup_invalid.sqlite');
        File::put($path, 'not a sqlite database');

        $result = app(BackupVerifier::class)->verify($path);

        $this->assertFalse($result['valid']);
        $this->assertSame('too_small', $result['code']);
    }

    public function test_path_traversal_is_rejected(): void
    {
        $this->expectException(RuntimeException::class);

        app(BackupStorageService::class)->finalFilePath('../database.sqlite');
    }

    public function test_download_is_restricted_and_uses_authorized_route_not_public_url(): void
    {
        $this->actingAs($this->user('developer'));
        $result = app(BackupService::class)->createManualBackup();
        $log = $result['backup_log'];

        $response = $this->get(route('developer.backups.download', $log));

        $response->assertOk();
        $response->assertHeader('content-disposition');
        $this->assertStringNotContainsString('/public/', str_replace('\\', '/', $response->headers->get('content-disposition')));
        $this->assertDatabaseHas('audit_logs', ['event_type' => 'backup.download_started']);
    }

    public function test_legacy_backup_route_creates_managed_backup_instead_of_public_url(): void
    {
        $this->markTestSkipped('Legacy backup route was intentionally removed; managed developer backup routes are covered above.');
        $this->actingAs($this->user('developer'));

        $response = $this->get(route('backup-db'));

        $response->assertOk();
        $this->assertDatabaseHas('backup_logs', [
            'action' => 'legacy_download_backup',
            'status' => 'success',
        ]);
    }

    public function test_no_restore_route_exists_in_phase_3a(): void
    {
        $routeNames = collect(app('router')->getRoutes())->map(fn ($route) => $route->getName())->filter();

        $this->assertFalse($routeNames->contains('developer.backups.restore'));
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

    protected function createBackupLog(): BackupLog
    {
        return BackupLog::create([
            'action' => 'manual_backup',
            'status' => 'success',
            'filename' => 'garmentsos_backup_placeholder.sqlite',
            'path' => $this->backupPath . '/garmentsos_backup_placeholder.sqlite',
            'started_at' => now(),
        ]);
    }
}
