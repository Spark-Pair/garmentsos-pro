<?php

namespace App\Services\Updater;

use App\Models\UserSession;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class UpdateLockService
{
    public function __construct(protected InstalledVersionService $versions)
    {
    }

    public function path(): string
    {
        return storage_path('app/update-lock.json');
    }

    public function failedPath(): string
    {
        return storage_path('app/update-lock-failed.json');
    }

    public function activeLock(): ?array
    {
        $this->pruneStaleState();

        $lock = $this->read();
        if ($lock === null) {
            return null;
        }

        if ($this->targetVersionInstalled($lock)) {
            $this->invalidateAllUserSessions();
            $this->clear();
            return null;
        }

        if ($this->isExpired($lock)) {
            $this->clear();
            return null;
        }

        return $lock;
    }

    public function status(): array
    {
        $this->pruneStaleState();
        $lock = $this->activeLock();

        return [
            'updating' => $lock !== null,
            'failed' => $lock === null ? $this->recentFailure() : null,
            'message' => $lock['message'] ?? 'GarmentsOS PRO is updating. Please wait until the update is complete.',
            'started_at' => $lock['started_at'] ?? null,
            'expires_at' => $lock['expires_at'] ?? null,
            'target_version' => $lock['target_version'] ?? null,
            'request_id' => $lock['request_id'] ?? null,
        ];
    }

    public function start(array $context = []): array
    {
        $this->pruneStaleState();

        $now = now()->utc();
        $ttl = max(1, (int) config('updater.update_lock_ttl_minutes', 30));

        $lock = [
            'locked' => true,
            'reason' => 'system_update_in_progress',
            'message' => 'GarmentsOS PRO is updating. Please wait until the update is complete.',
            'started_at' => $now->toIso8601String(),
            'expires_at' => $now->copy()->addMinutes($ttl)->toIso8601String(),
            'started_by' => $context['started_by'] ?? null,
            'target_version' => $context['target_version'] ?? null,
            'request_id' => $context['request_id'] ?? (string) Str::uuid(),
        ];

        File::ensureDirectoryExists(dirname($this->path()));
        if (File::exists($this->failedPath())) {
            File::delete($this->failedPath());
        }
        File::put($this->path(), json_encode($lock, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $lock;
    }

    public function clear(): void
    {
        if (File::exists($this->path())) {
            File::delete($this->path());
        }
    }

    public function pruneStaleState(): void
    {
        $lock = $this->read();
        if ($lock === null) {
            return;
        }

        if ($this->targetVersionInstalled($lock)) {
            $this->invalidateAllUserSessions();
            $this->clear();
            return;
        }

        if ($this->isExpired($lock)) {
            $this->clear();
        }
    }

    public function fail(?string $requestId = null, string $message = 'Launcher handoff failed before update started.'): array
    {
        $lock = $this->read();
        if ($lock !== null && $requestId && !hash_equals((string) ($lock['request_id'] ?? ''), $requestId)) {
            return $this->status();
        }

        $failure = [
            'updating' => false,
            'failed' => true,
            'message' => $message,
            'failed_at' => now()->utc()->toIso8601String(),
            'target_version' => $lock['target_version'] ?? null,
            'request_id' => $requestId ?: ($lock['request_id'] ?? null),
        ];

        $this->clear();
        File::ensureDirectoryExists(dirname($this->failedPath()));
        File::put($this->failedPath(), json_encode($failure, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $failure;
    }

    public function read(): ?array
    {
        if (!File::exists($this->path())) {
            return null;
        }

        try {
            $lock = json_decode(File::get($this->path()), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            $this->clear();
            return null;
        }

        if (!is_array($lock) || empty($lock['locked'])) {
            $this->clear();
            return null;
        }

        return $lock;
    }

    protected function isExpired(array $lock): bool
    {
        if (empty($lock['expires_at'])) {
            return true;
        }

        try {
            return Carbon::parse($lock['expires_at'])->isPast();
        } catch (\Throwable) {
            return true;
        }
    }

    protected function targetVersionInstalled(array $lock): bool
    {
        $target = trim((string) ($lock['target_version'] ?? ''));
        if ($target === '') {
            return false;
        }

        return version_compare($this->versions->currentVersion(), $target, '>=');
    }

    protected function invalidateAllUserSessions(): void
    {
        if (!Schema::hasTable('user_sessions')) {
            return;
        }

        UserSession::query()
            ->where('is_active', true)
            ->update([
                'is_active' => false,
                'last_activity' => now(),
            ]);
    }

    protected function recentFailure(): ?array
    {
        if (!File::exists($this->failedPath())) {
            return null;
        }

        try {
            $failure = json_decode(File::get($this->failedPath()), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return null;
        }

        return is_array($failure) ? $failure : null;
    }
}
