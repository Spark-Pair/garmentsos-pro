<?php

namespace App\Services\Licensing;

use App\Models\License;
use App\Models\LicenseCheck;
use App\Services\AuditLogService;
use App\Services\Updater\InstalledVersionService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class LicenseService
{
    public function __construct(
        protected InstallationIdentityService $identity,
        protected InstallationFingerprintService $fingerprints,
        protected SignedLicenseFileService $signedFiles,
        protected LicenseActivationClient $activationClient,
        protected LicensePayloadValidator $payloadValidator,
        protected AuditLogService $auditLogs,
        protected InstalledVersionService $versions,
        protected MachineFingerprintService $machine,
    ) {
    }

    public function enabled(): bool
    {
        return (bool) config('licensing.enabled', false);
    }

    public function developmentBypass(): bool
    {
        return (bool) config('licensing.development_bypass', false);
    }

    public function currentStatus(): LicenseStatus
    {
        if (!$this->enabled()) {
            if (!$this->developmentBypass()) {
                return LicenseStatus::problem(
                    'activation_required',
                    'blocked',
                    'License activation is required. Request a demo/trial or register this device with SparkPair.',
                    ['source' => 'config'],
                );
            }

            return LicenseStatus::notEnforced();
        }

        return $this->statusForDeviceLicense(false);

        $installation = $this->identity->current();
        $license = License::query()
            ->where('app_installation_id', $installation->id)
            ->latest('id')
            ->first();

        if (!$license) {
            return LicenseStatus::problem(
                'unactivated',
                'blocked',
                'No license has been activated.',
            );
        }

        return $this->statusForLicense($license);
    }

    public function usesConfiguredLicense(): bool
    {
        return trim((string) config('licensing.license_key', '')) !== ''
            || trim((string) config('licensing.client_id', '')) !== '';
    }

    public function installId(): string
    {
        return $this->identity->installId();
    }

    public function machineName(): string
    {
        return $this->machine->machineName();
    }

    public function machineHash(): string
    {
        return $this->machine->machineHash();
    }

    public function machineHashPreview(): string
    {
        return $this->machine->shortHash();
    }

    public function registerInstall(): array
    {
        if ($this->hasLocalLicenseApprovalState()) {
            Log::warning('License registration skipped because local license/device identity already exists. Verifying existing identity instead.', [
                'install_id_hash' => $this->shortHash($this->identity->existingInstallId()),
                'verify_cache_exists' => File::exists((string) config('licensing.verify_cache_path')),
                'registration_cache_exists' => File::exists((string) config('licensing.registration_cache_path')),
                'request_cache_exists' => File::exists((string) config('licensing.request_cache_path')),
            ]);

            $status = $this->verifyNow();

            return [
                'ok' => !$status->shouldBlock(),
                'message' => $status->message ?: 'Existing license/device identity was checked. Registration was not repeated.',
                'body' => [
                    'status' => $status->state,
                    'message' => $status->message,
                    'registration_skipped' => true,
                ],
            ];
        }

        $result = $this->activationClient->registerInstall([
            'product' => config('updater.app_id', 'garmentsos-pro'),
            'install_id' => $this->identity->installId(),
            'machine_hash' => $this->machine->machineHash(),
            'machine_name' => $this->machine->machineName(),
            'app_version' => $this->versions->currentVersion(),
        ]);

        if (($result['ok'] ?? false) && is_array($result['body'] ?? null)) {
            $this->safeCacheWrite('registration', fn () => $this->writeRegistrationCache($result['body']));
        }

        return $result;
    }

    public function requestDemo(array $customer): array
    {
        $payload = array_merge($customer, [
            'product' => config('updater.app_id', 'garmentsos-pro'),
            'install_id' => $this->identity->installId(),
            'machine_hash' => $this->machine->machineHash(),
            'machine_name' => $this->machine->machineName(),
            'app_version' => $this->versions->currentVersion(),
            'license_check_url' => (string) config('licensing.server_url', ''),
            'license_register_url' => (string) config('licensing.register_url', ''),
            'license_enforcement_enabled' => (bool) config('licensing.enforcement_enabled', false),
            'license_auto_register' => (bool) config('licensing.auto_register', true),
            'license_development_bypass' => (bool) config('licensing.development_bypass', false),
        ]);

        $result = $this->activationClient->requestDemo($payload);
        if (($result['ok'] ?? false) && is_array($result['body'] ?? null)) {
            $body = $result['body'];
            $this->safeCacheWrite('request', fn () => $this->writeRequestCache(array_merge($payload, $body)));
            $this->safeCacheWrite('registration', fn () => $this->writeRegistrationCache([
                'status' => $body['status'] ?? 'pending',
                'message' => $body['message'] ?? 'Waiting for SparkPair approval.',
                'client_name' => $customer['business_name'] ?? '',
            ]));
        }

        return $result;
    }

    public function autoRegisterIfEnabled(): ?array
    {
        if (!(bool) config('licensing.auto_register', true)) {
            return null;
        }

        if ($this->hasExistingIdentityOrCache()) {
            Log::info('License auto-registration skipped; existing identity/cache will be verified instead.', [
                'install_id_hash' => $this->identity->existingInstallId()
                    ? $this->shortHash($this->identity->existingInstallId())
                    : null,
                'identity_exists' => $this->identity->hasPersistedIdentity(),
                'verify_cache_exists' => File::exists((string) config('licensing.verify_cache_path')),
                'registration_cache_exists' => File::exists((string) config('licensing.registration_cache_path')),
                'request_cache_exists' => File::exists((string) config('licensing.request_cache_path')),
            ]);

            return null;
        }

        return $this->registerInstall();
    }

    public function verifyNow(): LicenseStatus
    {
        return $this->statusForDeviceLicense(true);
    }

    protected function statusForDeviceLicense(bool $forceOnlineCheck = false): LicenseStatus
    {
        $installId = $this->identity->existingInstallId();
        if ($installId === null) {
            return LicenseStatus::problem(
                'activation_required',
                $this->shouldEnforceUsage() ? 'blocked' : 'none',
                'License activation is required. Request a demo/trial or register this device with SparkPair.',
                ['source' => 'missing_install_id'],
            );
        }

        if (!$forceOnlineCheck) {
            $cachedStatus = Cache::get($this->licenseStatusCacheKey($installId));
            if ($cachedStatus instanceof LicenseStatus) {
                return $cachedStatus;
            }
        }

        $machineHash = $this->machine->machineHash();
        $previousMachineHash = $this->previousMachineHash($machineHash);
        $previousMachineHashes = $this->previousMachineHashes($machineHash);
        $verifyPayload = [
            'product' => config('updater.app_id', 'garmentsos-pro'),
            'install_id' => $installId,
            'machine_hash' => $machineHash,
            'machine_name' => $this->machine->machineName(),
            'app_version' => $this->versions->currentVersion(),
            'client_id' => (string) ($this->readVerifyCache()['client_id'] ?? config('licensing.client_id', '')),
            'client_name' => (string) ($this->readVerifyCache()['client_name'] ?? config('licensing.client_name', '')),
            'fingerprint_source' => $this->machine->source(),
            'fingerprint_version' => 2,
            'stable_fingerprint_migration' => true,
            'rebind_requested' => true,
            'fingerprint_rebind_reason' => 'stable_install_identity_after_update',
        ];

        if ($previousMachineHash !== null) {
            $verifyPayload['previous_machine_hash'] = $previousMachineHash;
            $verifyPayload['previous_machine_hashes'] = $previousMachineHashes;
        }

        $result = $this->activationClient->verify($verifyPayload);
        $this->safeCacheWrite('last_response', fn () => $this->writeLastResponseCache($result, $verifyPayload));

        if (($result['ok'] ?? false) && is_array($result['body'] ?? null)) {
            $body = $result['body'];
            return $this->rememberLicenseStatus($installId, $this->statusFromVerifyResponse($body, 'server_verify'));
        }

        if ($this->isUpdateCausedIdentityMismatch($result) && $this->hasApprovedLocalStateForInstall($installId)) {
            Log::warning('License server reported device identity mismatch after update; using approved local cache while approval is refreshed.', [
                'install_id_hash' => $this->shortHash($installId),
                'machine_hash_preview' => $this->machine->shortHash(),
                'previous_machine_hash_preview' => $previousMachineHash ? substr($previousMachineHash, 0, 12) : null,
                'fingerprint_source' => $this->machine->source(),
                'server_status' => $result['status'] ?? null,
            ]);

            return $this->rememberLicenseStatus($installId, $this->statusFromApprovedStableMigration(
                'This approved installation is being rebound from the old Docker fingerprint to the stable install identity. Refresh approval from SparkPair when internet is available.',
                $result['message'] ?? $result['error'] ?? null,
            ));
        }

        return $this->rememberLicenseStatus($installId, $this->statusFromVerifyCache(
            $result['message'] ?? 'License server is not reachable.',
            $result['error'] ?? null,
        ));
    }

    protected function licenseStatusCacheKey(string $installId): string
    {
        return 'licensing.current_status.' . sha1($installId . '|' . $this->machine->machineHash());
    }

    protected function rememberLicenseStatus(string $installId, LicenseStatus $status): LicenseStatus
    {
        $seconds = max(0, (int) config('licensing.status_cache_seconds', 900));

        if ($seconds > 0) {
            Cache::put($this->licenseStatusCacheKey($installId), $status, $seconds);
        }

        return $status;
    }

    protected function statusFromVerifyResponse(array $body, string $source): LicenseStatus
    {
        $status = strtolower(trim((string) ($body['status'] ?? $body['state'] ?? 'invalid')));
        $allowed = ($body['allowed'] ?? false) === true
            || ($body['valid'] ?? false) === true
            || in_array($status, ['active', 'approved'], true);
        $message = trim((string) ($body['message'] ?? 'License verification completed.'));
        $expiresAt = $this->parseDate($body['expires_at'] ?? null);
        $graceDays = (int) ($body['grace_days'] ?? config('licensing.offline_grace_days', 7));
        $graceUntil = $expiresAt ? $expiresAt->copy()->addDays(max(0, $graceDays)) : null;

        if ($allowed && $status !== 'grace') {
            $this->safeCacheWrite('verify', fn () => $this->writeVerifyCache($body));

            return LicenseStatus::valid($source, [
                'message' => $message !== '' ? $message : 'License active',
                'expires_at' => $expiresAt,
                'grace_until' => $graceUntil,
            ]);
        }

        if (($body['valid'] ?? false) === true && $status === 'grace') {
            $this->safeCacheWrite('verify', fn () => $this->writeVerifyCache($body));

            return LicenseStatus::problem(
                'grace_period',
                'none',
                $message !== '' ? $message : 'License grace period is active.',
                [
                    'expires_at' => $expiresAt,
                    'grace_until' => $graceUntil,
                    'source' => $source,
                ],
            );
        }

        if ($status === 'pending') {
            return LicenseStatus::problem(
                'pending',
                $this->shouldEnforceUsage() ? 'blocked' : 'none',
                $message !== '' ? $message : 'This device is registered and waiting for approval from SparkPair.',
                [
                    'expires_at' => $expiresAt,
                    'grace_until' => $graceUntil,
                    'source' => $source,
                ],
            );
        }

        if (in_array($status, ['suspended', 'blocked', 'tampered', 'installation_mismatch', 'security_issue'], true)) {
            if ($this->isServerBodyIdentityMismatch($body) && $this->hasApprovedLocalStateForInstall((string) ($body['install_id'] ?? $this->identity->existingInstallId() ?? ''))) {
                Log::warning('License verify response reported device identity mismatch; using approved local cache while approval is refreshed.', [
                    'install_id_hash' => $this->shortHash((string) ($body['install_id'] ?? $this->identity->existingInstallId() ?? '')),
                    'machine_hash_preview' => $this->machine->shortHash(),
                    'fingerprint_source' => $this->machine->source(),
                    'server_status' => $status,
                ]);

                return $this->statusFromApprovedStableMigration(
                    'This approved installation is being rebound from the old Docker fingerprint to the stable install identity. Refresh approval from SparkPair when internet is available.',
                    $message,
                );
            }

            return LicenseStatus::problem(
                $status === 'suspended' ? 'suspended' : $status,
                'blocked',
                $message !== '' ? $message : 'License verification failed.',
                [
                    'expires_at' => $expiresAt,
                    'grace_until' => $graceUntil,
                    'source' => $source,
                ],
            );
        }

        if ($this->isServerBodyIdentityMismatch($body) && $this->hasApprovedLocalStateForInstall((string) ($body['install_id'] ?? $this->identity->existingInstallId() ?? ''))) {
            Log::warning('License verify response reported invalid device identity; using approved local cache while approval is refreshed.', [
                'install_id_hash' => $this->shortHash((string) ($body['install_id'] ?? $this->identity->existingInstallId() ?? '')),
                'machine_hash_preview' => $this->machine->shortHash(),
                'fingerprint_source' => $this->machine->source(),
                'server_status' => $status,
            ]);

            return $this->statusFromApprovedStableMigration(
                'This approved installation is being rebound from the old Docker fingerprint to the stable install identity. Refresh approval from SparkPair when internet is available.',
                $message,
            );
        }

        return LicenseStatus::problem(
            $status === 'expired' ? 'expired_readonly' : 'invalid_readonly',
            $this->shouldEnforceUsage() ? $this->defaultEnforcementMode() : 'none',
            $message !== '' ? $message : 'License is not valid.',
            [
                'expires_at' => $expiresAt,
                'grace_until' => $graceUntil,
                'source' => $source,
            ],
        );
    }

    protected function statusFromVerifyCache(string $message, ?string $error = null): LicenseStatus
    {
        $cache = $this->readVerifyCache();
        if (!$cache) {
            return LicenseStatus::problem(
                'activation_required',
                $this->shouldEnforceUsage() ? 'blocked' : 'none',
                'License activation is required. Request a demo/trial or register this device with SparkPair.',
                ['source' => 'server_unreachable'],
            );
        }

        $expiresAt = $this->parseDate($cache['expires_at'] ?? null);
        $graceDays = (int) ($cache['grace_days'] ?? config('licensing.offline_grace_days', 7));
        $graceUntil = $expiresAt ? $expiresAt->copy()->addDays(max(0, $graceDays)) : null;

        if (!$expiresAt || Carbon::now()->lessThanOrEqualTo($graceUntil ?? $expiresAt)) {
            return LicenseStatus::problem(
                'offline_grace',
                'none',
                $message !== '' ? $message : 'License server is unreachable. Using cached license status.',
                [
                    'expires_at' => $expiresAt,
                    'grace_until' => $graceUntil,
                    'source' => 'verify_cache',
                    'error' => $error,
                ],
            );
        }

        return LicenseStatus::problem(
            'expired_readonly',
            $this->defaultEnforcementMode(),
            'Cached license grace has expired. App is in read-only mode.',
            [
                'expires_at' => $expiresAt,
                'grace_until' => $graceUntil,
                'source' => 'verify_cache',
            ],
        );
    }

    protected function statusFromApprovedStableMigration(string $message, ?string $error = null): LicenseStatus
    {
        $cache = $this->approvedLocalStateForInstall((string) ($this->identity->existingInstallId() ?? ''));
        $expiresAt = $this->parseDate($cache['expires_at'] ?? null);
        $graceDays = (int) ($cache['grace_days'] ?? config('licensing.offline_grace_days', 7));
        $graceUntil = $expiresAt ? $expiresAt->copy()->addDays(max(0, $graceDays)) : null;

        if ($expiresAt && Carbon::now()->greaterThan($graceUntil ?? $expiresAt)) {
            return LicenseStatus::problem(
                'expired_readonly',
                $this->defaultEnforcementMode(),
                'Cached license grace has expired. App is in read-only mode.',
                [
                    'expires_at' => $expiresAt,
                    'grace_until' => $graceUntil,
                    'source' => 'stable_fingerprint_migration_cache',
                    'error' => $error,
                ],
            );
        }

        return LicenseStatus::valid('stable_fingerprint_migration_cache', [
            'state' => 'active',
            'message' => $message,
            'expires_at' => $expiresAt,
            'grace_until' => $graceUntil,
        ]);
    }

    protected function writeVerifyCache(array $body): void
    {
        $path = (string) config('licensing.verify_cache_path');
        $status = strtolower(trim((string) ($body['status'] ?? $body['state'] ?? 'active')));
        $allowed = ($body['allowed'] ?? false) === true
            || ($body['valid'] ?? false) === true
            || in_array($status, ['active', 'approved'], true);

        if ($allowed && $status !== 'grace') {
            $status = 'active';
        }

        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode([
            'valid' => true,
            'allowed' => true,
            'status' => $status !== '' ? $status : 'active',
            'device_approval' => (string) ($body['device_approval'] ?? $body['device_status'] ?? $body['status'] ?? $body['state'] ?? 'active'),
            'install_id' => (string) ($body['install_id'] ?? $this->identity->existingInstallId() ?? ''),
            'client_id' => (string) ($body['client_id'] ?? ''),
            'client_name' => (string) ($body['client_name'] ?? $body['customer_name'] ?? ''),
            'customer_name' => (string) ($body['customer_name'] ?? $body['client_name'] ?? ''),
            'expires_at' => (string) ($body['expires_at'] ?? ''),
            'grace_days' => (int) ($body['grace_days'] ?? config('licensing.offline_grace_days', 7)),
            'message' => (string) ($body['message'] ?? ''),
            'rebind_performed' => (bool) ($body['rebind_performed'] ?? false),
            'machine_hash' => $this->machine->machineHash(),
            'fingerprint_source' => $this->machine->source(),
            'checked_at' => now()->utc()->toIso8601String(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    protected function writeLastResponseCache(array $result, array $payload): void
    {
        $path = (string) config('licensing.last_response_cache_path');
        $body = is_array($result['body'] ?? null) ? $result['body'] : [];
        $status = strtolower(trim((string) ($body['status'] ?? $body['state'] ?? $result['status_text'] ?? '')));
        $allowed = ($body['allowed'] ?? false) === true
            || ($body['valid'] ?? false) === true
            || in_array($status, ['active', 'approved'], true);

        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode([
            'checked_at' => now()->utc()->toIso8601String(),
            'verify_url' => (string) ($result['url'] ?? config('licensing.server_url', '')),
            'http_status' => $result['http_status'] ?? $result['status'] ?? null,
            'json_parsed' => (bool) ($result['json_parsed'] ?? is_array($result['body'] ?? null)),
            'ok' => (bool) ($result['ok'] ?? false),
            'allowed' => $allowed,
            'valid' => (bool) ($body['valid'] ?? $allowed),
            'status' => $status !== '' ? $status : 'network_error',
            'device_approval' => (string) ($body['device_approval'] ?? $body['device_status'] ?? $status),
            'rebind_requested' => (bool) ($payload['rebind_requested'] ?? false),
            'rebind_performed' => (bool) ($body['rebind_performed'] ?? false),
            'message' => (string) ($body['message'] ?? $result['message'] ?? ''),
            'error' => (string) ($result['error'] ?? ''),
            'install_id' => (string) ($payload['install_id'] ?? ''),
            'machine_hash_preview' => substr((string) ($payload['machine_hash'] ?? ''), 0, 12),
            'previous_machine_hash_preview' => substr((string) ($payload['previous_machine_hash'] ?? ''), 0, 12),
            'fingerprint_source' => (string) ($payload['fingerprint_source'] ?? ''),
            'fingerprint_version' => (int) ($payload['fingerprint_version'] ?? 0),
            'app_version' => (string) ($payload['app_version'] ?? ''),
            'client_id' => (string) ($body['client_id'] ?? $payload['client_id'] ?? ''),
            'client_name' => (string) ($body['client_name'] ?? $body['customer_name'] ?? $payload['client_name'] ?? ''),
            'customer_name' => (string) ($body['customer_name'] ?? $body['client_name'] ?? $payload['client_name'] ?? ''),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    protected function safeCacheWrite(string $cacheName, callable $callback): void
    {
        try {
            $callback();
        } catch (\Throwable $e) {
            Log::warning('License cache write failed after server response.', [
                'cache' => $cacheName,
                'error' => $e->getMessage(),
                'type' => $e::class,
            ]);
        }
    }

    protected function readVerifyCache(): ?array
    {
        $path = (string) config('licensing.verify_cache_path');
        try {
            if (!File::exists($path)) {
                return null;
            }

            $decoded = json_decode((string) File::get($path), true);

            return is_array($decoded) && ($decoded['valid'] ?? false) === true ? $decoded : null;
        } catch (\Throwable $e) {
            Log::warning('License verify cache could not be read.', [
                'path' => $path,
                'error' => $e->getMessage(),
                'type' => $e::class,
            ]);

            return null;
        }
    }

    protected function hasExistingIdentityOrCache(): bool
    {
        return $this->identity->hasPersistedIdentity()
            || File::exists((string) config('licensing.verify_cache_path'))
            || File::exists((string) config('licensing.registration_cache_path'))
            || File::exists((string) config('licensing.request_cache_path'));
    }

    protected function hasLocalLicenseApprovalState(): bool
    {
        return File::exists((string) config('licensing.verify_cache_path'))
            || File::exists((string) config('licensing.registration_cache_path'))
            || File::exists((string) config('licensing.request_cache_path'));
    }

    protected function shortHash(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        return substr(hash('sha256', $value), 0, 12);
    }

    public function registrationCache(): ?array
    {
        $path = (string) config('licensing.registration_cache_path');
        try {
            if (!File::exists($path)) {
                return null;
            }

            $decoded = json_decode((string) File::get($path), true);

            return is_array($decoded) ? $decoded : null;
        } catch (\Throwable $e) {
            Log::warning('License registration cache could not be read.', [
                'path' => $path,
                'error' => $e->getMessage(),
                'type' => $e::class,
            ]);

            return null;
        }
    }

    public function verifyCache(): ?array
    {
        return $this->readVerifyCache();
    }

    public function lastResponseCache(): ?array
    {
        $path = (string) config('licensing.last_response_cache_path');
        try {
            if (!File::exists($path)) {
                return null;
            }

            $decoded = json_decode((string) File::get($path), true);

            return is_array($decoded) ? $decoded : null;
        } catch (\Throwable $e) {
            Log::warning('License last response cache could not be read.', [
                'path' => $path,
                'error' => $e->getMessage(),
                'type' => $e::class,
            ]);

            return null;
        }
    }

    public function requestCache(): ?array
    {
        $path = (string) config('licensing.request_cache_path');
        try {
            if (!File::exists($path)) {
                return null;
            }

            $decoded = json_decode((string) File::get($path), true);

            return is_array($decoded) ? $decoded : null;
        } catch (\Throwable $e) {
            Log::warning('License request cache could not be read.', [
                'path' => $path,
                'error' => $e->getMessage(),
                'type' => $e::class,
            ]);

            return null;
        }
    }

    public function diagnostics(): array
    {
        $previousMachineHash = $this->previousMachineHash($this->machine->machineHash());

        return [
            'install_id_exists' => File::exists((string) config('licensing.install_id_path')),
            'identity_exists' => File::exists((string) config('licensing.identity_path')),
            'license_dir_exists' => File::exists(dirname((string) config('licensing.verify_cache_path'))),
            'license_dir_writable' => is_writable(dirname((string) config('licensing.verify_cache_path'))),
            'verify_cache_exists' => File::exists((string) config('licensing.verify_cache_path')),
            'verify_cache_readable' => is_readable((string) config('licensing.verify_cache_path')),
            'verify_cache_writable' => File::exists((string) config('licensing.verify_cache_path'))
                ? is_writable((string) config('licensing.verify_cache_path'))
                : is_writable(dirname((string) config('licensing.verify_cache_path'))),
            'registration_cache_exists' => File::exists((string) config('licensing.registration_cache_path')),
            'request_cache_exists' => File::exists((string) config('licensing.request_cache_path')),
            'last_response_cache_exists' => File::exists((string) config('licensing.last_response_cache_path')),
            'last_verified_at' => (string) ($this->verifyCache()['checked_at'] ?? ''),
            'last_response_at' => (string) ($this->lastResponseCache()['checked_at'] ?? ''),
            'install_id_hash' => $this->shortHash($this->identity->existingInstallId()),
            'machine_hash_preview' => $this->machine->shortHash(),
            'previous_machine_hash_preview' => $previousMachineHash ? substr($previousMachineHash, 0, 12) : null,
            'fingerprint_source' => $this->machine->source(),
            'installation_file_path' => (string) config('licensing.identity_path'),
            'app_version' => $this->versions->currentVersion(),
        ];
    }

    protected function previousMachineHash(string $currentMachineHash): ?string
    {
        return $this->previousMachineHashes($currentMachineHash)[0] ?? null;
    }

    protected function previousMachineHashes(string $currentMachineHash): array
    {
        $hashes = [];
        foreach ([$this->readVerifyCache(), $this->registrationCache(), $this->requestCache()] as $cache) {
            $hash = trim((string) ($cache['machine_hash'] ?? ''));
            if ($hash !== '' && hash_equals($currentMachineHash, $hash) === false) {
                $hashes[] = $hash;
            }
        }

        return array_values(array_unique($hashes));
    }

    protected function isUpdateCausedIdentityMismatch(array $result): bool
    {
        $message = strtolower((string) ($result['message'] ?? $result['error'] ?? ''));
        $status = strtolower((string) ($result['body']['status'] ?? $result['status_text'] ?? ''));

        return str_contains($message, 'identity changed')
            || str_contains($message, 'fingerprint')
            || str_contains($message, 'machine hash')
            || str_contains($message, 'device identity')
            || in_array($status, ['installation_mismatch', 'fingerprint_mismatch', 'identity_mismatch'], true);
    }

    protected function isServerBodyIdentityMismatch(array $body): bool
    {
        $message = strtolower((string) ($body['message'] ?? $body['reason'] ?? ''));
        $status = strtolower((string) ($body['status'] ?? $body['state'] ?? ''));

        return str_contains($message, 'identity changed')
            || str_contains($message, 'fingerprint')
            || str_contains($message, 'machine hash')
            || str_contains($message, 'device identity')
            || in_array($status, ['installation_mismatch', 'fingerprint_mismatch', 'identity_mismatch'], true);
    }

    protected function hasApprovedCacheForInstall(string $installId): bool
    {
        return $this->approvedVerifyCacheForInstall($installId) !== null;
    }

    protected function hasApprovedLocalStateForInstall(string $installId): bool
    {
        return $this->approvedLocalStateForInstall($installId) !== null;
    }

    protected function approvedLocalStateForInstall(string $installId): ?array
    {
        return $this->approvedVerifyCacheForInstall($installId)
            ?? $this->approvedRegistrationCacheForInstall($installId);
    }

    protected function approvedVerifyCacheForInstall(string $installId): ?array
    {
        $cache = $this->readVerifyCache();
        if (!$cache || ($cache['valid'] ?? false) !== true) {
            return null;
        }

        $cachedInstallId = trim((string) ($cache['install_id'] ?? ''));
        $cachedStatus = strtolower(trim((string) ($cache['status'] ?? '')));

        return $cachedInstallId !== ''
            && hash_equals($installId, $cachedInstallId)
            && in_array($cachedStatus, ['active', 'grace'], true)
                ? $cache
                : null;
    }

    protected function approvedRegistrationCacheForInstall(string $installId): ?array
    {
        $cache = $this->registrationCache();
        if (!$cache) {
            return null;
        }

        $cachedInstallId = trim((string) ($cache['install_id'] ?? ''));
        $cachedStatus = strtolower(trim((string) ($cache['status'] ?? $cache['device_status'] ?? '')));

        return $cachedInstallId !== ''
            && hash_equals($installId, $cachedInstallId)
            && in_array($cachedStatus, ['active', 'approved', 'grace'], true)
                ? array_merge(['valid' => true], $cache, ['status' => $cachedStatus])
                : null;
    }

    protected function writeRegistrationCache(array $body): void
    {
        $path = (string) config('licensing.registration_cache_path');
        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode(array_merge($body, [
            'install_id' => (string) ($body['install_id'] ?? $this->identity->existingInstallId() ?? ''),
            'registered_at' => now()->utc()->toIso8601String(),
        ]), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    protected function writeRequestCache(array $body): void
    {
        $path = (string) config('licensing.request_cache_path');
        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode(array_merge($body, [
            'status' => $body['status'] ?? 'pending',
            'requested_at' => now()->utc()->toIso8601String(),
        ]), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    protected function parseDate(mixed $date): ?Carbon
    {
        $date = trim((string) $date);
        if ($date === '') {
            return null;
        }

        try {
            return Carbon::parse($date);
        } catch (\Throwable) {
            return null;
        }
    }

    protected function enforcementEnabled(): bool
    {
        return (bool) config('licensing.enforcement_enabled', false);
    }

    protected function shouldEnforceUsage(): bool
    {
        return $this->enforcementEnabled() || !$this->developmentBypass();
    }

    public function statusForLicense(License $license): LicenseStatus
    {
        $payload = [
            'expires_at' => $license->subscription_expires_at,
            'grace_until' => $license->offline_grace_until,
            'modules' => $license->allowed_modules ?? [],
            'features' => $license->allowed_features ?? [],
            'brands' => $license->allowed_brand_ids ?? [],
            'update_channel' => $license->update_channel,
            'source' => 'database',
        ];

        if ($license->status === 'unactivated') {
            return LicenseStatus::problem('unactivated', 'blocked', 'No license has been activated.', $payload);
        }

        if (in_array($license->status, ['suspended', 'blocked'], true)) {
            return LicenseStatus::problem(
                $license->status === 'suspended' ? 'revoked' : 'blocked',
                'blocked',
                'License is ' . $license->status . '.',
                $payload,
            );
        }

        if ($license->license_expires_at && Carbon::now()->greaterThan($license->license_expires_at)) {
            return LicenseStatus::problem(
                'expired_readonly',
                $license->enforcement_mode ?: $this->defaultEnforcementMode(),
                'License has expired. App is in read-only mode.',
                $payload,
            );
        }

        $installation = $license->installation;

        if (!$installation || $installation->fingerprint_hash !== $this->fingerprints->fingerprintHash()) {
            return LicenseStatus::problem(
                'blocked',
                'blocked',
                'This app installation does not match the activated license.',
                $payload,
            );
        }

        if ($license->subscription_status === 'grace' && $license->offline_grace_until) {
            if (Carbon::now()->lessThanOrEqualTo($license->offline_grace_until)) {
                return LicenseStatus::problem(
                    'offline_grace',
                    'none',
                    'Offline grace is active. License server refresh is recommended.',
                    $payload,
                );
            }

            return LicenseStatus::problem(
                'expired_readonly',
                $license->enforcement_mode ?: $this->defaultEnforcementMode(),
                'Offline grace has expired.',
                $payload,
            );
        }

        if (
            $license->subscription_status === 'expired'
            || ($license->subscription_expires_at && Carbon::now()->greaterThan($license->subscription_expires_at))
        ) {
            return LicenseStatus::problem(
                'expired_readonly',
                $license->enforcement_mode ?: $this->defaultEnforcementMode(),
                'Subscription has expired. Read-only mode should be used when enforcement is enabled.',
                $payload,
            );
        }

        if ($license->subscription_expires_at) {
            $days = (int) config('licensing.expiring_soon_days', 14);
            $daysLeft = Carbon::now()->startOfDay()->diffInDays($license->subscription_expires_at->copy()->startOfDay(), false);
            if ($daysLeft >= 0 && $daysLeft <= $days) {
                return LicenseStatus::valid('database', array_merge($payload, [
                    'state' => 'expiring_soon',
                    'message' => 'License is active and expiring soon.',
                ]));
            }
        }

        return LicenseStatus::valid('database', $payload);
    }

    public function activate(string $licenseKey): LicenseStatus
    {
        $installation = $this->identity->current();
        $fingerprintHash = $this->fingerprints->fingerprintHash();

        $this->auditLogs->record('license.online_activation_attempted', [
            'installation_uuid' => $this->identity->maskedUuid($installation),
            'fingerprint_preview' => $this->fingerprints->fingerprintPreview(),
        ]);

        $result = $this->activationClient->activate($licenseKey, [
            'installation_uuid' => $installation->installation_uuid,
            'fingerprint_hash' => $fingerprintHash,
            'installation_mode' => $installation->installation_mode,
        ]);

        if (!($result['ok'] ?? false)) {
            $status = LicenseStatus::problem(
                'activation_failed',
                'none',
                $result['message'] ?? 'License activation failed.',
            );
            $this->recordCheck($installation, null, 'activation', $status);
            $this->auditLogs->record('license.online_activation_failed', [
                'reason' => $status->message,
            ]);

            return $status;
        }

        return $this->persistSignedDocument([
            'payload' => $result['payload'] ?? null,
            'signature' => $result['signature'] ?? null,
        ], 'activation', 'server');
    }

    public function importSignedLicense(string $signedLicense, string $checkType = 'offline_activation'): LicenseStatus
    {
        $this->auditLogs->record('license.offline_import_attempted');

        $decoded = $this->signedFiles->decodeDocument($signedLicense);
        if (!($decoded['valid'] ?? false)) {
            $status = LicenseStatus::problem('tampered', 'blocked', 'Signed license payload is invalid.');
            $this->recordCheck($this->identity->current(), null, $checkType, $status);
            $this->auditLogs->record('license.offline_import_failed', [
                'reason' => $decoded['reason'] ?? 'invalid',
            ]);

            return $status;
        }

        return $this->persistSignedDocument($decoded['document'], $checkType, 'signed_import');
    }

    public function refresh(): LicenseStatus
    {
        $installation = $this->identity->current();
        $license = License::query()
            ->where('app_installation_id', $installation->id)
            ->latest('id')
            ->first();

        $this->auditLogs->record('license.refresh_attempted');

        if (!$license) {
            $status = LicenseStatus::problem('unactivated', 'blocked', 'No license has been activated.');
            $this->recordCheck($installation, null, 'online', $status);
            $this->auditLogs->record('license.refresh_failed', ['reason' => 'unactivated']);

            return $status;
        }

        $result = $this->activationClient->refresh([
            'server_license_id' => $license->metadata['server_license_id'] ?? null,
            'license_key_hash' => $license->license_key_hash,
            'installation_uuid' => $installation->installation_uuid,
            'fingerprint_hash' => $this->fingerprints->fingerprintHash(),
            'signed_payload_hash' => $license->signed_payload_hash,
            'app' => 'garmentsos-pro',
            'app_version' => config('app.version', 'local'),
        ]);

        if (!($result['ok'] ?? false)) {
            $status = LicenseStatus::problem(
                'network_error',
                'none',
                $result['message'] ?? 'License refresh failed.',
            );
            $this->recordCheck($installation, $license, 'online', $status);
            $this->auditLogs->record('license.refresh_failed', [
                'reason' => $status->message,
            ]);

            return $status;
        }

        return $this->persistSignedDocument([
            'payload' => $result['payload'] ?? null,
            'signature' => $result['signature'] ?? null,
        ], 'online', 'server');
    }

    public function statusFromSignedCache(): LicenseStatus
    {
        $read = $this->signedFiles->read();

        if (($read['valid'] ?? false) !== true) {
            $reason = (string) ($read['reason'] ?? 'invalid');

            if ($reason === 'missing') {
                return LicenseStatus::problem(
                    'unactivated',
                    'blocked',
                    'No signed license cache is present.',
                    ['source' => 'signed_cache'],
                );
            }

            return LicenseStatus::problem(
                'tampered',
                'blocked',
                'Signed license cache is invalid or tampered.',
                ['source' => 'signed_cache'],
            );
        }

        $result = $this->payloadValidator->validatePayload($read['payload']);
        if (($result['valid'] ?? false) === true) {
            return LicenseStatus::valid('signed_cache', [
                'message' => 'Signed license cache is valid.',
            ]);
        }

        $reason = (string) ($result['reason'] ?? 'invalid');
        return LicenseStatus::problem(
            $this->statusStateForValidationFailure($reason),
            'blocked',
            'Signed license cache is invalid or tampered.',
            ['source' => 'signed_cache'],
        );
    }

    public function persistSignedDocument(array $document, string $checkType, string $source): LicenseStatus
    {
        $installation = $this->identity->current();
        $result = $this->payloadValidator->validateDocument($document);

        if (!($result['valid'] ?? false)) {
            $status = LicenseStatus::problem(
                $this->statusStateForValidationFailure((string) ($result['reason'] ?? 'invalid')),
                'blocked',
                'Signed license payload could not be verified.',
                ['source' => $source],
            );
            $this->recordCheck($installation, null, $checkType, $status);
            $this->auditLogs->record($this->auditEventForCheckType($checkType, false), [
                'reason' => $result['reason'] ?? 'invalid',
            ]);

            return $status;
        }

        $payload = $result['payload'];
        $installation->update([
            'installation_mode' => $payload['installation_mode'],
            'fingerprint_hash' => $payload['fingerprint_hash'],
            'last_seen_at' => now(),
            'metadata' => [
                'signature_version' => $payload['signature_version'],
            ],
        ]);

        $existingLicense = License::query()
            ->where('app_installation_id', $installation->id)
            ->latest('id')
            ->first();

        $license = License::updateOrCreate(
            ['app_installation_id' => $installation->id],
            [
                'license_key_hash' => $payload['license_key_hash'],
                'client_name' => $payload['client_name'],
                'business_name' => $payload['business_name'],
                'status' => $payload['license_status'],
                'subscription_status' => $payload['subscription_status'],
                'subscription_expires_at' => $payload['subscription_expires_at'],
                'license_expires_at' => $payload['license_expires_at'],
                'offline_grace_days' => (int) config('licensing.offline_grace_days', 7),
                'offline_grace_until' => $payload['offline_grace_until'] ?? $payload['cache_until'],
                'enforcement_mode' => 'readonly',
                'allowed_modules' => $payload['allowed_modules'] ?? [],
                'allowed_features' => $payload['allowed_features'] ?? [],
                'allowed_brand_ids' => $payload['allowed_brand_ids'] ?? [],
                'update_channel' => $payload['update_channel'],
                'last_verified_at' => now(),
                'last_online_check_at' => in_array($checkType, ['activation', 'online'], true)
                    ? now()
                    : $existingLicense?->last_online_check_at,
                'signed_payload_hash' => $result['payload_hash'],
                'metadata' => [
                    'server_license_id' => $payload['server_license_id'],
                    'signature_version' => $payload['signature_version'],
                    'payload_hash' => $payload['payload_hash'],
                ],
            ],
        );

        $this->signedFiles->write($payload, (string) $document['signature']);

        $status = $this->statusForLicense($license->fresh('installation'));
        $this->recordCheck($installation, $license, $checkType, $status);
        $this->auditLogs->record($this->auditEventForCheckType($checkType, true), [
            'server_license_id' => $payload['server_license_id'],
            'subscription_status' => $payload['subscription_status'],
            'license_status' => $payload['license_status'],
        ]);

        return $status;
    }

    public function recordCheck(mixed $installation, ?License $license, string $type, LicenseStatus $status): void
    {
        LicenseCheck::create([
            'app_installation_id' => $installation?->id,
            'license_id' => $license?->id,
            'check_type' => $type,
            'result' => $status->state,
            'enforcement' => $status->enforcement,
            'checked_at' => now(),
            'message' => $status->message,
            'context' => [
                'source' => $status->source,
            ],
            'user_id' => Auth::id(),
        ]);
    }

    protected function defaultEnforcementMode(): string
    {
        return (string) config('licensing.default_enforcement_mode', 'readonly');
    }

    protected function statusStateForValidationFailure(string $reason): string
    {
        return match ($reason) {
            'installation_uuid_mismatch', 'fingerprint_mismatch' => 'installation_mismatch',
            default => 'tampered',
        };
    }

    protected function auditEventForCheckType(string $checkType, bool $success): string
    {
        $suffix = $success ? 'succeeded' : 'failed';

        return match ($checkType) {
            'activation' => 'license.online_activation_' . $suffix,
            'online' => 'license.refresh_' . $suffix,
            default => 'license.offline_import_' . $suffix,
        };
    }
}
