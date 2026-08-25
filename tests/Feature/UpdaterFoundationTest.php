<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckActiveSession;
use App\Http\Middleware\SubscriptionExpiry;
use App\Http\Middleware\VerifyCsrfToken;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\Updater\UpdateDownloadService;
use App\Services\Updater\UpdateApplyService;
use App\Services\Updater\UpdateLogService;
use App\Services\Updater\UpdateManifestService;
use App\Services\Updater\UpdatePackageVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class UpdaterFoundationTest extends TestCase
{
    use RefreshDatabase;

    private string $privateKey = '';

    protected string $tempPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempPath = 'framework/testing/updater-' . Str::random(12);

        config([
            'licensing.enabled' => false,
            'updater.enabled' => false,
            'updater.current_version' => '1.0.0',
            'updater.channel' => 'stable',
            'updater.installation_mode' => 'local_lan',
            'updater.temp_path' => $this->tempPath,
            'updater.manifest_url' => 'https://updates.example.test/manifest.json',
            'licensing.identity_path' => storage_path('framework/testing/license-' . Str::random(12) . '/installation.json'),
        ]);

        $keyPair = openssl_pkey_new(array_merge($this->opensslKeyOptions(), [
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]));
        openssl_pkey_export($keyPair, $privateKey, null, $this->opensslKeyOptions());
        $this->privateKey = $privateKey;
        $details = openssl_pkey_get_details($keyPair);
        config(['updater.public_key' => $details['key']]);

        $this->withoutMiddleware([CheckActiveSession::class, SubscriptionExpiry::class, VerifyCsrfToken::class]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(storage_path('app/' . $this->tempPath));

        parent::tearDown();
    }

    public function test_updater_disabled_by_default_and_does_not_fetch_manifest(): void
    {
        Http::fake();

        $result = app(UpdateManifestService::class)->checkConfigured();

        $this->assertFalse($result['success']);
        $this->assertSame('disabled', $result['code']);
        Http::assertNothingSent();
    }

    public function test_updater_page_renders_disabled_verification_only_ui(): void
    {
        $this->actingAs($this->user('developer'));

        $this->get(route('developer.updater'))
            ->assertOk()
            ->assertSee('Updater disabled')
            ->assertSee('Apply/install')
            ->assertSee('Disabled by configuration')
            ->assertSee('No manifest request is made');
    }

    public function test_valid_manifest_is_accepted(): void
    {
        $result = app(UpdateManifestService::class)->validateManifest($this->signedManifest());

        $this->assertTrue($result['success']);
        $this->assertSame('update_available', $result['code']);
        $this->assertTrue($result['update_available']);
    }

    public function test_invalid_signature_is_rejected_when_required(): void
    {
        $manifest = $this->signedManifest();
        $manifest['latest_version'] = '9.9.9';

        $result = app(UpdateManifestService::class)->validateManifest($manifest);

        $this->assertFalse($result['success']);
        $this->assertSame('invalid_signature', $result['code']);
    }

    public function test_expired_wrong_channel_and_wrong_app_manifests_are_rejected(): void
    {
        $expired = app(UpdateManifestService::class)->validateManifest($this->signedManifest([
            'expires_at' => now()->subDay()->toIso8601String(),
        ]));
        $wrongChannel = app(UpdateManifestService::class)->validateManifest($this->signedManifest([
            'update_channel' => 'beta',
        ]));
        $wrongApp = app(UpdateManifestService::class)->validateManifest($this->signedManifest([
            'app' => 'other-app',
        ]));

        $this->assertSame('expired', $expired['code']);
        $this->assertSame('wrong_channel', $wrongChannel['code']);
        $this->assertSame('wrong_app', $wrongApp['code']);
    }

    public function test_mandatory_and_optional_updates_are_recognized(): void
    {
        $optional = app(UpdateManifestService::class)->validateManifest($this->signedManifest([
            'mandatory' => false,
            'minimum_required_version' => '0.9.0',
        ]));
        $mandatory = app(UpdateManifestService::class)->validateManifest($this->signedManifest([
            'mandatory' => true,
        ]));
        $minimumRequired = app(UpdateManifestService::class)->validateManifest($this->signedManifest([
            'mandatory' => false,
            'minimum_required_version' => '1.1.0',
        ]));

        $this->assertFalse($optional['mandatory']);
        $this->assertTrue($mandatory['mandatory']);
        $this->assertTrue($minimumRequired['mandatory']);
    }

    public function test_package_checksum_mismatch_is_rejected(): void
    {
        $path = $this->zipPath(['app/Services/Safe.php' => '<?php']);

        $result = app(UpdatePackageVerifier::class)->verify($path, hash('sha256', 'wrong'));

        $this->assertFalse($result['success']);
        $this->assertSame('checksum_mismatch', $result['code']);
    }

    public function test_package_forbidden_files_are_rejected(): void
    {
        $cases = [
            '.env' => 'forbidden_file',
            'config/.env.production' => 'forbidden_file',
            'database/database.sqlite' => 'forbidden_file',
            'database/database.sqlite-wal' => 'forbidden_file',
            'database/database.sqlite-shm' => 'forbidden_file',
            'database/client.dump' => 'forbidden_file',
            'logs/laravel.log' => 'forbidden_directory',
            'keys/signing.key' => 'forbidden_file',
            '../evil.php' => 'path_traversal',
            '/absolute.php' => 'absolute_path',
            'C:/absolute.php' => 'absolute_path',
        ];

        foreach ($cases as $entry => $code) {
            $path = $this->zipPath([$entry => 'x']);
            $result = app(UpdatePackageVerifier::class)->verify($path, hash_file('sha256', $path));
            $this->assertFalse($result['success'], $entry);
            $this->assertSame($code, $result['code'], $entry);
        }
    }

    public function test_safe_package_requires_and_accepts_signature(): void
    {
        $path = $this->zipPath(['app/Services/Safe.php' => '<?php']);
        $checksum = hash_file('sha256', $path);

        $missing = app(UpdatePackageVerifier::class)->verify($path, $checksum);
        $valid = app(UpdatePackageVerifier::class)->verify($path, $checksum, $this->signatureFor($checksum));

        $this->assertFalse($missing['success']);
        $this->assertSame('missing_signature', $missing['code']);
        $this->assertTrue($valid['success']);
        $this->assertSame('valid', $valid['code']);
    }

    public function test_package_download_uses_private_storage_only(): void
    {
        config(['updater.enabled' => true]);
        Http::fake([
            'https://updates.example.test/package.zip' => Http::response($this->zipBytes(['app/Safe.php' => '<?php']), 200),
        ]);

        $result = app(UpdateDownloadService::class)->download('https://updates.example.test/package.zip');

        $this->assertTrue($result['success']);
        $this->assertFileExists($result['path']);
        $this->assertStringStartsWith(storage_path('app/' . $this->tempPath), $result['path']);
        $this->assertStringNotContainsString(public_path(), $result['path']);
    }

    public function test_download_refuses_when_updater_disabled(): void
    {
        Http::fake();

        $result = app(UpdateDownloadService::class)->download('https://updates.example.test/package.zip');

        $this->assertFalse($result['success']);
        $this->assertSame('disabled', $result['code']);
        Http::assertNothingSent();
    }

    public function test_unauthorized_users_are_blocked_from_updater_routes(): void
    {
        $this->actingAs($this->user('guest'));

        $this->get(route('developer.updater'))->assertRedirect(route('home'));
        $this->post(route('developer.updater.check'))->assertRedirect(route('home'));
        $this->post(route('developer.updater.apply'))->assertRedirect(route('home'));
    }

    public function test_only_guarded_apply_route_exists_no_install_route(): void
    {
        $routeNames = collect(app('router')->getRoutes())
            ->map(fn ($route) => (string) $route->getName())
            ->filter(fn ($name) => str_contains($name, 'updater'))
            ->values()
            ->all();

        $this->assertSame([
            'developer.updater',
            'developer.updater.check',
            'developer.updater.apply',
        ], $routeNames);
    }

    public function test_disabled_updater_cannot_apply(): void
    {
        Http::fake();

        $result = app(UpdateApplyService::class)->applyConfigured();

        $this->assertFalse($result['success']);
        $this->assertSame('disabled', $result['code']);
        Http::assertNothingSent();
    }

    public function test_apply_rejects_forbidden_package_before_backup(): void
    {
        config(['updater.enabled' => true]);
        $package = $this->zipBytes(['.env' => 'APP_KEY=base64:unsafe']);
        $checksum = hash('sha256', $package);

        Http::fake([
            'https://updates.example.test/manifest.json' => Http::response($this->signedManifest([
                'package_checksum' => $checksum,
                'package_signature' => $this->signatureFor($checksum),
            ]), 200),
            'https://updates.example.test/package.zip' => Http::response($package, 200),
        ]);

        $result = app(UpdateApplyService::class)->applyConfigured();

        $this->assertFalse($result['success']);
        $this->assertSame('forbidden_file', $result['code']);
        $this->assertDatabaseMissing('backup_logs', ['action' => 'pre_update_backup']);
    }

    public function test_apply_creates_backup_before_copying_allowed_update_and_preserves_env_and_database(): void
    {
        config([
            'updater.enabled' => true,
            'updater.maintenance_mode' => false,
            'updater.run_migrations' => false,
        ]);

        $target = base_path('docs/__updater_apply_test.md');
        File::delete($target);
        $envBefore = File::exists(base_path('.env')) ? File::get(base_path('.env')) : null;
        $databasePath = config('database.connections.sqlite.database');
        $dbBefore = is_string($databasePath) && File::exists($databasePath) ? hash_file('sha256', $databasePath) : null;

        $package = $this->zipBytes(['docs/__updater_apply_test.md' => 'safe updater test']);
        $checksum = hash('sha256', $package);

        Http::fake([
            'https://updates.example.test/manifest.json' => Http::response($this->signedManifest([
                'package_checksum' => $checksum,
                'package_signature' => $this->signatureFor($checksum),
                'migration_required' => false,
            ]), 200),
            'https://updates.example.test/package.zip' => Http::response($package, 200),
        ]);

        try {
            $result = app(UpdateApplyService::class)->applyConfigured();

            $this->assertTrue($result['success']);
            $this->assertSame('applied', $result['code']);
            $this->assertFileExists($target);
            $this->assertSame('safe updater test', File::get($target));
            $this->assertDatabaseHas('backup_logs', ['action' => 'pre_update_backup', 'status' => 'success']);
            if ($envBefore !== null) {
                $this->assertSame($envBefore, File::get(base_path('.env')));
            }
            if ($dbBefore !== null) {
                $this->assertSame($dbBefore, hash_file('sha256', $databasePath));
            }
        } finally {
            File::delete($target);
        }
    }

    public function test_apply_logs_migration_guard_when_manifest_requires_migration(): void
    {
        config([
            'updater.enabled' => true,
            'updater.maintenance_mode' => false,
            'updater.run_migrations' => false,
        ]);

        $target = base_path('docs/__updater_no_migration_test.md');
        File::delete($target);
        $package = $this->zipBytes(['docs/__updater_no_migration_test.md' => 'safe updater test']);
        $checksum = hash('sha256', $package);

        Http::fake([
            'https://updates.example.test/manifest.json' => Http::response($this->signedManifest([
                'package_checksum' => $checksum,
                'package_signature' => $this->signatureFor($checksum),
                'migration_required' => true,
            ]), 200),
            'https://updates.example.test/package.zip' => Http::response($package, 200),
        ]);

        try {
            $result = app(UpdateApplyService::class)->applyConfigured();

            $this->assertTrue($result['success']);
            $this->assertDatabaseHas('audit_logs', ['event_type' => 'updater.apply_succeeded']);
            $log = AuditLog::where('event_type', 'updater.apply_succeeded')->latest('id')->first();
            $this->assertTrue($log->context['migration_required']);
            $this->assertNull($log->context['migration_exit_code']);
        } finally {
            File::delete($target);
        }
    }

    public function test_updater_logs_are_sanitized(): void
    {
        app(UpdateLogService::class)->record('check_failed', [
            'token' => 'secret-token',
            'safe' => 'value',
        ]);

        $log = AuditLog::where('event_type', 'updater.check_failed')->first();

        $this->assertSame('[redacted]', $log->context['token']);
        $this->assertSame('value', $log->context['safe']);
    }

    protected function signedManifest(array $overrides = []): array
    {
        $manifest = array_merge([
            'app' => 'garmentsos-pro',
            'latest_version' => '1.1.0',
            'minimum_required_version' => '1.0.0',
            'update_channel' => 'stable',
            'mandatory' => false,
            'release_notes' => 'Safe updater foundation test release.',
            'package_url' => 'https://updates.example.test/package.zip',
            'package_checksum' => hash('sha256', 'package'),
            'package_signature' => '',
            'migration_required' => false,
            'backup_required' => true,
            'supported_installation_modes' => ['local_lan'],
            'created_at' => now()->subHour()->toIso8601String(),
            'expires_at' => now()->addDay()->toIso8601String(),
            'signature_version' => 'v1',
        ], $overrides);

        openssl_sign(app(UpdateManifestService::class)->canonicalJson($manifest), $signature, $this->privateKey, OPENSSL_ALGO_SHA256);
        $manifest['signature'] = base64_encode($signature);

        return $manifest;
    }

    protected function signatureFor(string $value): string
    {
        openssl_sign($value, $signature, $this->privateKey, OPENSSL_ALGO_SHA256);

        return base64_encode($signature);
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

    protected function zipPath(array $files): string
    {
        $directory = storage_path('app/' . $this->tempPath);
        File::ensureDirectoryExists($directory);
        $path = $directory . DIRECTORY_SEPARATOR . 'package_' . Str::random(8) . '.zip';
        File::put($path, $this->zipBytes($files));

        return $path;
    }

    protected function zipBytes(array $files): string
    {
        $local = '';
        $central = '';
        $offset = 0;

        foreach ($files as $name => $contents) {
            $crc = crc32($contents);
            $size = strlen($contents);
            $nameLength = strlen($name);

            $localHeader = "PK\x03\x04"
                . pack('v', 20)
                . pack('v', 0)
                . pack('v', 0)
                . pack('v', 0)
                . pack('v', 0)
                . pack('V', $crc)
                . pack('V', $size)
                . pack('V', $size)
                . pack('v', $nameLength)
                . pack('v', 0)
                . $name
                . $contents;

            $central .= "PK\x01\x02"
                . pack('v', 20)
                . pack('v', 20)
                . pack('v', 0)
                . pack('v', 0)
                . pack('v', 0)
                . pack('v', 0)
                . pack('V', $crc)
                . pack('V', $size)
                . pack('V', $size)
                . pack('v', $nameLength)
                . pack('v', 0)
                . pack('v', 0)
                . pack('v', 0)
                . pack('v', 0)
                . pack('V', 0)
                . pack('V', $offset)
                . $name;

            $local .= $localHeader;
            $offset += strlen($localHeader);
        }

        return $local
            . $central
            . "PK\x05\x06"
            . pack('v', 0)
            . pack('v', 0)
            . pack('v', count($files))
            . pack('v', count($files))
            . pack('V', strlen($central))
            . pack('V', strlen($local))
            . pack('v', 0);
    }
}
