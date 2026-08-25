<?php

namespace App\Services\Updater;

use App\Models\UserSession;
use App\Services\BackupService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class UpdateApplyService
{
    public function __construct(
        protected UpdateManifestService $manifests,
        protected UpdateDownloadService $downloads,
        protected UpdatePackageVerifier $packages,
        protected UpdateLogService $logs,
        protected BackupService $backups,
        protected InstalledVersionService $versions,
    ) {
    }

    public function applyConfigured(): array
    {
        if (!(bool) config('updater.enabled', false)) {
            return $this->result(false, 'disabled', 'Updater is disabled by configuration.');
        }

        $manifest = $this->manifests->checkConfigured();
        if (!($manifest['success'] ?? false) || empty($manifest['manifest'])) {
            return $this->result(false, 'manifest_invalid', 'Update apply was blocked because manifest verification failed.', [
                'manifest_code' => $manifest['code'] ?? 'unknown',
            ]);
        }

        return $this->applyManifest($manifest['manifest']);
    }

    public function applyManifest(array $manifest): array
    {
        if (!(bool) config('updater.enabled', false)) {
            return $this->result(false, 'disabled', 'Updater is disabled by configuration.');
        }

        $manifestResult = $this->manifests->validateManifest($manifest);
        if (!($manifestResult['success'] ?? false)) {
            return $this->result(false, 'manifest_invalid', 'Update apply was blocked because manifest verification failed.', [
                'manifest_code' => $manifestResult['code'] ?? 'unknown',
            ]);
        }

        if (!($manifestResult['update_available'] ?? false)) {
            return $this->result(false, 'up_to_date', 'No newer update is available.');
        }

        $download = $this->downloads->download((string) $manifest['package_url']);
        if (!($download['success'] ?? false)) {
            return $download;
        }

        $verification = $this->packages->verify(
            $download['path'],
            (string) $manifest['package_checksum'],
            (string) ($manifest['package_signature'] ?? ''),
        );

        if (!($verification['success'] ?? false)) {
            return $verification;
        }

        $backup = $this->backups->createManualBackup('pre_update_backup');
        if (!($backup['success'] ?? false)) {
            $this->logs->record('apply_blocked', ['reason' => 'backup_failed']);

            return $this->result(false, 'backup_failed', 'Update apply was blocked because the required database backup failed.');
        }

        $staging = $this->prepareDirectory((string) config('updater.staging_path', 'private/updater/staging'));
        $snapshot = $this->prepareDirectory((string) config('updater.snapshot_path', 'private/updater/snapshots') . '/' . now()->format('Ymd_His') . '_' . Str::lower(Str::random(8)));
        $maintenanceStarted = false;

        try {
            $this->extractZipTo($download['path'], $staging);
            $stagedRoot = $this->detectPackageRoot($staging);
            $this->validateStagedFiles($stagedRoot);

            $plannedFiles = $this->plannedFiles($stagedRoot);
            $this->snapshotExistingFiles($plannedFiles, $stagedRoot, $snapshot);

            if ((bool) config('updater.maintenance_mode', true)) {
                Artisan::call('down');
                $maintenanceStarted = true;
            }

            $this->copyAllowedFiles($plannedFiles, $stagedRoot);
            $this->writeEnvAppVersion((string) ($manifest['latest_version'] ?? ''));

            $migrationCode = $this->runPendingMigrationsOnly();
            if ($migrationCode !== 0) {
                $this->logs->record('apply_migration_failed', ['exit_code' => $migrationCode]);

                return $this->result(false, 'migration_failed', 'Update files were copied, but migrations failed. Use the verified backup and snapshot for manual recovery.', [
                    'backup_log_id' => $backup['backup_log']->id ?? null,
                    'snapshot' => basename($snapshot),
                ]);
            }

            $this->runRequiredSeeders($manifest);
            $maintenance = $this->runPostUpdateMaintenance();

            $versionCheck = $this->verifyInstalledVersion((string) ($manifest['latest_version'] ?? ''));
            if (!$versionCheck['success']) {
                throw new RuntimeException($versionCheck['message']);
            }

            $invalidatedSessions = $this->invalidateAllUserSessions();

            $this->logs->record('apply_succeeded', [
                'version' => $manifest['latest_version'],
                'channel' => $manifest['update_channel'],
                'file_count' => count($plannedFiles),
                'backup_log_id' => $backup['backup_log']->id ?? null,
                'snapshot' => basename($snapshot),
                'migration_required' => (bool) ($manifest['migration_required'] ?? false),
                'migration_exit_code' => $migrationCode,
                'post_update_maintenance' => $maintenance,
                'invalidated_user_sessions' => $invalidatedSessions,
                'database_restore_performed' => false,
            ]);

            return $this->result(true, 'applied', 'Update applied safely. Review the app and keep the pre-update backup/snapshot until verified.', [
                'backup_log_id' => $backup['backup_log']->id ?? null,
                'snapshot' => basename($snapshot),
                'file_count' => count($plannedFiles),
                'post_update_maintenance' => $maintenance,
                'invalidated_user_sessions' => $invalidatedSessions,
                'database_restore_performed' => false,
            ]);
        } catch (Throwable $e) {
            $rollback = $this->rollbackFiles($snapshot);
            $this->logs->record('apply_failed', [
                'reason' => Str::limit($e->getMessage(), 180),
                'rollback' => $rollback['code'],
            ]);

            return $this->result(false, 'apply_failed', 'Update apply failed safely. ' . $rollback['message']);
        } finally {
            if ($maintenanceStarted) {
                Artisan::call('up');
            }
            File::deleteDirectory($staging);
        }
    }

    protected function prepareDirectory(string $relativePath): string
    {
        $path = storage_path('app/' . trim($relativePath, '/'));
        File::deleteDirectory($path);
        File::ensureDirectoryExists($path);

        return $path;
    }

    protected function extractZipTo(string $zipPath, string $target): void
    {
        $data = File::get($zipPath);
        $offset = 0;
        $length = strlen($data);

        while ($offset + 30 <= $length) {
            if (substr($data, $offset, 4) !== "PK\x03\x04") {
                break;
            }

            $method = unpack('v', substr($data, $offset + 8, 2))[1] ?? null;
            $compressedSize = unpack('V', substr($data, $offset + 18, 4))[1] ?? null;
            $uncompressedSize = unpack('V', substr($data, $offset + 22, 4))[1] ?? null;
            $nameLength = unpack('v', substr($data, $offset + 26, 2))[1] ?? 0;
            $extraLength = unpack('v', substr($data, $offset + 28, 2))[1] ?? 0;
            $name = substr($data, $offset + 30, $nameLength);
            $contentOffset = $offset + 30 + $nameLength + $extraLength;
            $compressed = substr($data, $contentOffset, $compressedSize);

            if ($name !== '' && !str_ends_with($name, '/')) {
                $destination = $target . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $name);
                File::ensureDirectoryExists(dirname($destination));

                if ($method === 0) {
                    $contents = $compressed;
                } elseif ($method === 8) {
                    $contents = gzinflate($compressed);
                    if ($contents === false) {
                        throw new RuntimeException('Could not inflate update package entry.');
                    }
                } else {
                    throw new RuntimeException('Unsupported ZIP compression method.');
                }

                if (strlen($contents) !== $uncompressedSize) {
                    throw new RuntimeException('Extracted update package entry size mismatch.');
                }

                File::put($destination, $contents);
            }

            $offset = $contentOffset + $compressedSize;
        }
    }

    protected function detectPackageRoot(string $staging): string
    {
        $children = collect(File::directories($staging));
        $files = File::files($staging);

        if (count($files) === 0 && $children->count() === 1 && File::exists($children->first() . DIRECTORY_SEPARATOR . 'artisan')) {
            return $children->first();
        }

        return $staging;
    }

    protected function validateStagedFiles(string $root): void
    {
        foreach (File::allFiles($root) as $file) {
            $relative = $this->relativePath($root, $file->getPathname());
            $reason = $this->packages->forbiddenReason($relative);

            if ($reason !== null || !$this->isAllowedApplyPath($relative)) {
                throw new RuntimeException('Unsafe update package entry was staged: ' . $relative);
            }
        }
    }

    protected function plannedFiles(string $root): array
    {
        $files = [];
        foreach (File::allFiles($root) as $file) {
            $relative = $this->relativePath($root, $file->getPathname());
            if ($this->isAllowedApplyPath($relative)) {
                $files[] = $relative;
            }
        }

        return $files;
    }

    protected function snapshotExistingFiles(array $files, string $stagedRoot, string $snapshot): void
    {
        foreach ($files as $relative) {
            $destination = base_path($relative);
            if (File::exists($destination) && File::isFile($destination)) {
                File::ensureDirectoryExists(dirname($snapshot . DIRECTORY_SEPARATOR . $relative));
                File::copy($destination, $snapshot . DIRECTORY_SEPARATOR . $relative);
            }
        }

        $envPath = base_path('.env');
        if (File::isFile($envPath)) {
            File::copy($envPath, $snapshot . DIRECTORY_SEPARATOR . '.env');
        }
    }

    protected function copyAllowedFiles(array $files, string $stagedRoot): void
    {
        foreach ($files as $relative) {
            $source = $stagedRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
            $destination = base_path($relative);

            $this->assertInsideBasePath($destination);
            File::ensureDirectoryExists(dirname($destination));
            File::copy($source, $destination);
        }
    }

    protected function rollbackFiles(string $snapshot): array
    {
        try {
            if (!File::isDirectory($snapshot)) {
                return ['code' => 'no_snapshot', 'message' => 'No code snapshot was available for rollback.'];
            }

            foreach (File::allFiles($snapshot) as $file) {
                $relative = $this->relativePath($snapshot, $file->getPathname());
                $destination = base_path($relative);
                $this->assertInsideBasePath($destination);
                File::ensureDirectoryExists(dirname($destination));
                File::copy($file->getPathname(), $destination);
            }

            $envSnapshot = $snapshot . DIRECTORY_SEPARATOR . '.env';
            if (File::isFile($envSnapshot)) {
                File::copy($envSnapshot, base_path('.env'));
            }

            File::deleteDirectory(storage_path('framework/cache'));
            File::deleteDirectory(storage_path('framework/views'));
            File::deleteDirectory(storage_path('framework/sessions'));
            File::ensureDirectoryExists(storage_path('framework/cache'));
            File::ensureDirectoryExists(storage_path('framework/views'));
            File::ensureDirectoryExists(storage_path('framework/sessions'));

            Artisan::call('config:clear');
            Artisan::call('route:clear');
            Artisan::call('view:clear');
            Artisan::call('cache:clear');
            Artisan::call('up');

            return ['code' => 'rollback_succeeded', 'message' => 'Application files, .env, caches, and runtime state were rolled back from the pre-update snapshot.'];
        } catch (Throwable) {
            return ['code' => 'rollback_failed', 'message' => 'Rollback failed. Manual recovery from snapshot is required.'];
        }
    }

    protected function runPostUpdateMaintenance(): array
    {
        return [
            'storage_unlink' => $this->callArtisanSafely('storage:unlink'),
            'storage_link' => $this->callArtisanSafely('storage:link', ['--force' => true], retryWithoutOptions: true),
            'optimize_clear' => $this->callArtisanSafely('optimize:clear'),
            'config_cache' => $this->callArtisanSafely('config:cache'),
            'route_cache' => $this->callArtisanSafely('route:cache'),
            'view_cache' => $this->callArtisanSafely('view:cache'),
        ];
    }

    protected function invalidateAllUserSessions(): int
    {
        if (!Schema::hasTable('user_sessions')) {
            return 0;
        }

        return UserSession::query()
            ->where('is_active', true)
            ->update([
                'is_active' => false,
                'last_activity' => now(),
            ]);
    }

    protected function runPendingMigrationsOnly(): int
    {
        $statusCode = Artisan::call('migrate:status');
        $output = trim(Artisan::output());
        $this->logs->record('migrate_status_checked', ['output' => Str::limit($output, 400)]);

        if ($statusCode !== 0) {
            return $statusCode;
        }

        if (str_contains($output, 'No pending migrations') || !str_contains($output, 'Pending')) {
            $this->logs->record('migrate_skipped', ['message' => 'No pending migrations.']);

            return 0;
        }

        $this->logs->record('migrate_running', ['message' => 'Pending migrations detected; running migrate --force.']);

        return Artisan::call('migrate', ['--force' => true]);
    }

    protected function runRequiredSeeders(array $manifest): void
    {
        $seeders = $manifest['required_seeders'] ?? [];
        if (!is_array($seeders) || $seeders === []) {
            return;
        }

        foreach ($seeders as $class) {
            $class = trim((string) $class);
            if ($class === '' || $class === 'DatabaseSeeder') {
                continue;
            }

            $this->logs->record('seeder_running', ['class' => $class]);
            $exitCode = Artisan::call('db:seed', ['--class' => $class, '--force' => true]);
            if ($exitCode !== 0) {
                throw new RuntimeException('Seeder failed: ' . $class);
            }
        }
    }

    protected function writeEnvAppVersion(string $version): void
    {
        $version = trim($version);
        if ($version === '') {
            return;
        }

        $envPath = base_path('.env');
        if (!File::isFile($envPath)) {
            return;
        }

        $content = File::get($envPath);
        $pattern = '/^APP_VERSION=.*$/m';
        $line = 'APP_VERSION=' . $version;
        $updated = preg_match($pattern, $content)
            ? preg_replace($pattern, $line, $content, 1)
            : rtrim($content, "\r\n") . PHP_EOL . $line . PHP_EOL;

        if ($updated === null) {
            throw new RuntimeException('Could not update APP_VERSION in .env.');
        }

        File::put($envPath, $updated);
    }

    protected function verifyInstalledVersion(string $releaseVersion): array
    {
        $envVersion = trim((string) preg_replace('/^APP_VERSION=/m', '', File::get(base_path('.env'))));
        $installedVersion = trim((string) $this->versions->currentVersion());

        if ($envVersion === '' || $installedVersion === '' || $releaseVersion === '') {
            return $this->result(false, 'app_version_verify_failed', 'APP_VERSION verification could not complete.');
        }

        if ($envVersion !== $releaseVersion || $installedVersion !== $releaseVersion) {
            return $this->result(false, 'app_version_mismatch', 'APP_VERSION mismatch: release manifest version, .env APP_VERSION, and InstalledVersionService::currentVersion() must match.');
        }

        return $this->result(true, 'app_version_verified', 'APP_VERSION verified.');
    }

    protected function callArtisanSafely(string $command, array $parameters = [], bool $retryWithoutOptions = false): array
    {
        try {
            $exitCode = Artisan::call($command, $parameters);

            return [
                'success' => $exitCode === 0,
                'exit_code' => $exitCode,
                'output' => trim(Artisan::output()),
            ];
        } catch (Throwable $exception) {
            if ($retryWithoutOptions && $parameters !== []) {
                try {
                    $exitCode = Artisan::call($command);

                    return [
                        'success' => $exitCode === 0,
                        'exit_code' => $exitCode,
                        'output' => trim(Artisan::output()),
                        'retried_without_options' => true,
                    ];
                } catch (Throwable $retryException) {
                    return [
                        'success' => false,
                        'exit_code' => null,
                        'output' => '',
                        'error' => $retryException->getMessage(),
                        'retried_without_options' => true,
                    ];
                }
            }

            return [
                'success' => false,
                'exit_code' => null,
                'output' => '',
                'error' => $exception->getMessage(),
            ];
        }
    }

    protected function isAllowedApplyPath(string $relative): bool
    {
        $relative = str_replace('\\', '/', ltrim($relative, '/'));
        if ($relative === '' || str_contains($relative, '../')) {
            return false;
        }

        if (in_array($relative, config('updater.allowed_apply_files', []), true)) {
            return true;
        }

        foreach (config('updater.allowed_apply_roots', []) as $root) {
            $root = trim((string) $root, '/');
            if ($relative === $root || str_starts_with($relative, $root . '/')) {
                return true;
            }
        }

        return false;
    }

    protected function relativePath(string $root, string $path): string
    {
        return ltrim(str_replace('\\', '/', Str::after(str_replace('\\', '/', $path), rtrim(str_replace('\\', '/', $root), '/') . '/')), '/');
    }

    protected function assertInsideBasePath(string $path): void
    {
        $base = rtrim(str_replace('\\', '/', base_path()), '/');
        $target = str_replace('\\', '/', $path);

        if ($target !== $base && !str_starts_with($target, $base . '/')) {
            throw new RuntimeException('Refusing to copy outside the application path.');
        }
    }

    protected function result(bool $success, string $code, string $message, array $extra = []): array
    {
        return array_merge([
            'success' => $success,
            'code' => $code,
            'message' => $message,
        ], $extra);
    }
}
