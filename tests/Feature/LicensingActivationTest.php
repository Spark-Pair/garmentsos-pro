<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\License;
use App\Models\LicenseCheck;
use App\Services\Licensing\CanonicalJsonVerifier;
use App\Services\Licensing\InstallationFingerprintService;
use App\Services\Licensing\InstallationIdentityService;
use App\Services\Licensing\LicenseService;
use App\Services\Licensing\OfflineActivationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class LicensingActivationTest extends TestCase
{
    use RefreshDatabase;

    private string $privateKey = '';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'licensing.enabled' => false,
            'licensing.identity_path' => storage_path('framework/testing/license-' . Str::random(12) . '/installation.json'),
            'licensing.cache_path' => storage_path('framework/testing/license-' . Str::random(12) . '/license.json'),
            'licensing.server_url' => 'https://licenses.example.test',
        ]);

        $keyPair = openssl_pkey_new(array_merge($this->opensslKeyOptions(), [
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]));
        openssl_pkey_export($keyPair, $privateKey, null, $this->opensslKeyOptions());
        $this->privateKey = $privateKey;
        $details = openssl_pkey_get_details($keyPair);
        config(['licensing.public_key' => $details['key']]);
    }

    public function test_online_activation_success_persists_signed_license_without_storing_raw_key(): void
    {
        $document = $this->signedDocument();

        Http::fake([
            'licenses.example.test/api/licenses/activate' => Http::response($document, 200),
        ]);

        $status = app(LicenseService::class)->activate('RAW-LICENSE-KEY');

        $this->assertSame('active', $status->state);
        $this->assertDatabaseHas('licenses', [
            'license_key_hash' => $document['payload']['license_key_hash'],
            'status' => 'active',
            'subscription_status' => 'active',
        ]);
        $this->assertDatabaseMissing('licenses', ['license_key_hash' => 'RAW-LICENSE-KEY']);
        $this->assertStringNotContainsString('RAW-LICENSE-KEY', File::get((string) config('licensing.cache_path')));
        $this->assertDatabaseHas('license_checks', ['check_type' => 'activation', 'result' => 'active']);
        $this->assertDatabaseHas('audit_logs', ['event_type' => 'license.online_activation_succeeded']);

        Http::assertSent(fn ($request) => $request['license_key'] === 'RAW-LICENSE-KEY'
            && isset($request['installation_uuid'], $request['fingerprint_hash'], $request['installation_mode'])
            && !isset($request['raw_machine_details']));
    }

    public function test_online_activation_failure_does_not_create_license(): void
    {
        Http::fake([
            'licenses.example.test/api/licenses/activate' => Http::response(['message' => 'Denied'], 422),
        ]);

        $status = app(LicenseService::class)->activate('RAW-LICENSE-KEY');

        $this->assertSame('activation_failed', $status->state);
        $this->assertSame(0, License::count());
        $this->assertDatabaseHas('license_checks', ['check_type' => 'activation', 'result' => 'activation_failed']);
        $this->assertDatabaseHas('audit_logs', ['event_type' => 'license.online_activation_failed']);
    }

    public function test_offline_import_success_persists_license(): void
    {
        $document = $this->signedDocument(['subscription_expires_at' => now()->addMonths(2)->toIso8601String()]);

        $status = app(LicenseService::class)->importSignedLicense(json_encode($document));

        $this->assertSame('active', $status->state);
        $this->assertSame(1, License::count());
        $this->assertDatabaseHas('license_checks', ['check_type' => 'offline_activation', 'result' => 'active']);
        $this->assertDatabaseHas('audit_logs', ['event_type' => 'license.offline_import_succeeded']);
    }

    public function test_tampered_offline_import_fails(): void
    {
        $document = $this->signedDocument();
        $document['payload']['business_name'] = 'Tampered Business';

        $status = app(LicenseService::class)->importSignedLicense(json_encode($document));

        $this->assertSame('tampered', $status->state);
        $this->assertSame('blocked', $status->enforcement);
        $this->assertSame(0, License::count());
    }

    public function test_installation_uuid_mismatch_fails(): void
    {
        $document = $this->signedDocument(['installation_uuid' => (string) Str::uuid()]);

        $status = app(LicenseService::class)->importSignedLicense(json_encode($document));

        $this->assertSame('installation_mismatch', $status->state);
        $this->assertSame('blocked', $status->enforcement);
    }

    public function test_fingerprint_mismatch_fails(): void
    {
        $document = $this->signedDocument(['fingerprint_hash' => hash('sha256', 'different-installation')]);

        $status = app(LicenseService::class)->importSignedLicense(json_encode($document));

        $this->assertSame('installation_mismatch', $status->state);
        $this->assertSame('blocked', $status->enforcement);
    }

    public function test_fingerprint_mismatch_blocks_before_subscription_expiry_readonly(): void
    {
        $installation = app(InstallationIdentityService::class)->current();
        $license = License::create([
            'app_installation_id' => $installation->id,
            'license_key_hash' => hash('sha256', 'copied-installation'),
            'status' => 'active',
            'subscription_status' => 'expired',
            'subscription_expires_at' => now()->subDay(),
            'offline_grace_until' => now()->addDay(),
            'enforcement_mode' => 'readonly',
        ]);

        $installation->update(['fingerprint_hash' => hash('sha256', 'other-server')]);

        $status = app(LicenseService::class)->statusForLicense($license->fresh('installation'));

        $this->assertSame('blocked', $status->state);
        $this->assertSame('blocked', $status->enforcement);
    }

    public function test_refresh_updates_signed_cache_and_subscription_dates(): void
    {
        app(LicenseService::class)->importSignedLicense(json_encode($this->signedDocument([
            'subscription_expires_at' => now()->addMonth()->toIso8601String(),
        ])));

        $renewedDate = now()->addYear()->toIso8601String();
        $refreshDocument = $this->signedDocument(['subscription_expires_at' => $renewedDate]);
        Http::fake([
            'licenses.example.test/api/licenses/refresh' => Http::response($refreshDocument, 200),
        ]);

        $status = app(LicenseService::class)->refresh();

        $this->assertSame('active', $status->state);
        $this->assertSame(Carbon::parse($renewedDate)->toDateString(), License::first()->subscription_expires_at->toDateString());
        $this->assertStringContainsString($refreshDocument['payload']['payload_hash'], File::get((string) config('licensing.cache_path')));
    }

    public function test_offline_renewal_import_updates_subscription_dates(): void
    {
        app(LicenseService::class)->importSignedLicense(json_encode($this->signedDocument([
            'subscription_expires_at' => now()->addMonth()->toIso8601String(),
        ])));

        $renewedDate = now()->addMonths(8)->toIso8601String();
        $status = app(LicenseService::class)->importSignedLicense(json_encode($this->signedDocument([
            'subscription_expires_at' => $renewedDate,
        ])));

        $this->assertSame('active', $status->state);
        $this->assertSame(Carbon::parse($renewedDate)->toDateString(), License::first()->subscription_expires_at->toDateString());
    }

    public function test_reactivation_request_code_is_generated_but_not_self_approved(): void
    {
        $code = app(OfflineActivationService::class)->reactivationRequestCode('Server motherboard changed.');
        $decoded = json_decode(base64_decode($code), true);

        $this->assertSame('reactivation_request', $decoded['type']);
        $this->assertSame('Server motherboard changed.', $decoded['reason']);
        $this->assertSame(0, License::count());
    }

    public function test_offline_grace_valid_and_expired_statuses(): void
    {
        $installation = app(InstallationIdentityService::class)->current();
        $installation->update(['fingerprint_hash' => app(InstallationFingerprintService::class)->fingerprintHash()]);

        $license = License::create([
            'app_installation_id' => $installation->id,
            'license_key_hash' => hash('sha256', 'grace'),
            'status' => 'active',
            'subscription_status' => 'grace',
            'subscription_expires_at' => now()->subDay(),
            'offline_grace_until' => now()->addDays(2),
            'enforcement_mode' => 'readonly',
        ]);

        $this->assertSame('offline_grace', app(LicenseService::class)->statusForLicense($license)->state);

        $license->offline_grace_until = now()->subMinute();
        $license->save();

        $expired = app(LicenseService::class)->statusForLicense($license->fresh('installation'));
        $this->assertSame('expired_readonly', $expired->state);
        $this->assertSame('readonly', $expired->enforcement);
    }

    public function test_signed_cache_tampering_maps_to_blocked_status(): void
    {
        $document = $this->signedDocument();
        $document['payload']['client_name'] = 'Changed';
        File::ensureDirectoryExists(dirname((string) config('licensing.cache_path')));
        File::put((string) config('licensing.cache_path'), json_encode($document));

        $status = app(LicenseService::class)->statusFromSignedCache();

        $this->assertSame('tampered', $status->state);
        $this->assertSame('blocked', $status->enforcement);
    }

    public function test_ensure_license_is_wired_after_active_session_and_disabled_config_remains_noop(): void
    {
        $routes = file_get_contents(base_path('routes/web.php'));

        $this->assertStringContainsString("'setup.complete', 'auth', 'activeSession', 'ensureLicense', 'readonly', 'blockWhenUpdating'", $routes);
        $this->assertFalse((bool) config('licensing.enabled'));
    }

    private function signedDocument(array $overrides = []): array
    {
        $installation = app(InstallationIdentityService::class)->current();
        $fingerprint = app(InstallationFingerprintService::class)->fingerprintHash();
        $verifier = app(CanonicalJsonVerifier::class);

        $payload = array_merge([
            'server_license_id' => 'lic_test_001',
            'license_key_hash' => hash('sha256', 'RAW-LICENSE-KEY'),
            'client_name' => 'Test Client',
            'business_name' => 'Test Business',
            'installation_uuid' => $installation->installation_uuid,
            'fingerprint_hash' => $fingerprint,
            'installation_mode' => $installation->installation_mode,
            'license_status' => 'active',
            'subscription_status' => 'active',
            'subscription_expires_at' => now()->addMonth()->toIso8601String(),
            'license_expires_at' => null,
            'offline_grace_until' => now()->addMonths(2)->toIso8601String(),
            'cache_until' => now()->addMonths(2)->toIso8601String(),
            'allowed_modules' => ['core'],
            'allowed_features' => ['reports'],
            'allowed_brand_ids' => [],
            'update_channel' => 'stable',
            'issued_at' => now()->toIso8601String(),
            'signature_version' => 'v1',
            'payload_hash' => '',
        ], $overrides);

        $payload['payload_hash'] = $verifier->payloadHash($payload);
        openssl_sign($verifier->canonicalJson($payload), $signature, $this->privateKey, OPENSSL_ALGO_SHA256);

        return [
            'payload' => $payload,
            'signature' => base64_encode($signature),
        ];
    }
}
