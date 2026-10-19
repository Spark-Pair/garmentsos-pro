@extends('app')

@section('title', 'Backup & Restore | ' . $client_company->name)

@section('content')
    @php
        $panel = 'rounded-2xl border border-[var(--h-bg-color)] bg-[var(--secondary-bg-color)] p-5 text-sm shadow-sm';
        $softPanel = 'rounded-2xl border border-[var(--h-bg-color)] bg-[var(--h-bg-color)]/25 p-4';
        $badge = 'inline-flex items-center rounded-xl border px-3 py-1.5 text-xs font-semibold';
        $primaryButton = 'px-4 py-2 bg-[var(--primary-color)] text-[var(--text-color)] font-medium text-nowrap rounded-lg hover:bg-[var(--h-primary-color)] hover:scale-95 transition-all duration-300 ease-in-out cursor-pointer';
        $secondaryButton = 'px-4 py-2 bg-[var(--h-bg-color)] border border-gray-600 text-[var(--secondary-text)] font-medium text-nowrap rounded-lg hover:bg-[var(--secondary-bg-color)] hover:scale-95 transition-all duration-300 ease-in-out cursor-pointer';
        $disabledButton = 'px-4 py-2 bg-[var(--h-bg-color)] border border-gray-600 text-[var(--secondary-text)] font-medium text-nowrap rounded-lg opacity-60 cursor-not-allowed';
        $restoreEnabled = (bool) ($restoreRequirements['restore_enabled'] ?? false);
        $foundationReady = $foundationReady ?? true;
        $missingTables = $missingTables ?? [];
        $restoreJobId = $restoreJobId ?? session('restore_job_id');
        $restoreJobStatus = $restoreJobStatus ?? null;
    @endphp

    <div class="max-w-6xl mx-auto w-full">
        <x-search-header heading="Backup & Restore" />
    </div>

    <section class="text-center mx-auto">
        <div class="show-box mx-auto w-[90%] h-[70vh] bg-[var(--secondary-bg-color)] border border-[var(--glass-border-color)]/20 rounded-xl shadow pt-8.5 relative">
            <x-form-title-bar title="Backup & Restore" />
            <div class="details h-full z-40">
                <div class="container-parent h-full">
                    <div class="card_container px-4 h-full flex flex-col">
                        <div class="overflow-y-auto grow my-scrollbar-2 space-y-4  pr-1 text-left">

        <section class="{{ $panel }}">
            <x-developer-panel-title title="Backup & Restore" description="Private, verified SQLite backups for developer/admin use. Backup files stay in private storage.">
                <form method="POST" action="{{ route('developer.backups.store') }}">
                    @csrf
                    <button type="submit" class="{{ $foundationReady ? $primaryButton : $disabledButton }}" @disabled(!$foundationReady)>
                        <i class="fas fa-database mr-2"></i>Create Backup
                    </button>
                </form>
            </x-developer-panel-title>

            @if (!$foundationReady)
                <div class="mt-4 rounded-lg border border-[var(--border-warning)] bg-[var(--bg-warning)] p-4 text-sm text-[var(--text-warning)]">
                    <div class="font-semibold">Backup setup is pending</div>
                    <p class="mt-1">Backup foundation tables are not available in this local database yet.</p>
                    @if ($missingTables)
                        <p class="mt-2 font-mono text-xs">Missing: {{ implode(', ', $missingTables) }}</p>
                    @endif
                    <form method="POST" action="{{ route('developer.backups.run-migrations') }}" class="mt-4 space-y-3">
                        @csrf
                        <label class="flex items-center justify-between gap-3 rounded-xl border border-[var(--h-bg-color)] bg-[var(--h-bg-color)]/35 px-3 py-2">
                            <span>Run database migrations on this local install. Use this only after confirming this is the intended client/developer database.</span>
                            <x-toggle-switch name="confirm_migrations" />
                        </label>
                        <button type="submit" class="{{ $secondaryButton }}">Run Database Migrations</button>
                    </form>
                </div>
            @endif

            <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-5">
                <div class="{{ $softPanel }} xl:col-span-3">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h2 class="font-semibold">Backup Workflow</h2>
                            <p class="mt-1 text-sm text-[var(--secondary-text)]">Standalone SQLite copy, checksum, metadata, backup log, and audit log.</p>
                        </div>
                        <span class="{{ $badge }} border-[var(--border-success)] bg-[var(--bg-success)] text-[var(--text-success)]">Private storage</span>
                    </div>
                    <div class="mt-4 grid grid-cols-1 gap-3 text-sm md:grid-cols-3">
                        <div class="rounded-lg border border-gray-600 bg-[var(--secondary-bg-color)] p-3">
                            <div class="font-semibold">Create</div>
                            <div class="mt-1 text-[var(--secondary-text)]">Managed backup only.</div>
                        </div>
                        <div class="rounded-lg border border-gray-600 bg-[var(--secondary-bg-color)] p-3">
                            <div class="font-semibold">Verify</div>
                            <div class="mt-1 text-[var(--secondary-text)]">SQLite validity and checksum.</div>
                        </div>
                        <div class="rounded-lg border border-gray-600 bg-[var(--secondary-bg-color)] p-3">
                            <div class="font-semibold">Download</div>
                            <div class="mt-1 text-[var(--secondary-text)]">Developer/admin only.</div>
                        </div>
                    </div>
                </div>

                <div class="{{ $softPanel }} xl:col-span-2">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h2 class="font-semibold">Restore Status</h2>
                            <p class="mt-1 text-sm text-[var(--secondary-text)]">Restore is separate and disabled by default.</p>
                        </div>
                        <span class="{{ $badge }} {{ $restoreEnabled ? 'border-[var(--border-warning)] bg-[var(--bg-warning)] text-[var(--text-warning)]' : 'border-gray-600 bg-[var(--secondary-bg-color)] text-[var(--secondary-text)]' }}">
                            {{ $restoreEnabled ? 'Enabled' : 'Disabled' }}
                        </span>
                    </div>
                    <dl class="mt-4 grid grid-cols-1 gap-2 text-sm">
                        <div class="flex justify-between gap-3">
                            <dt class="text-[var(--secondary-text)]">Typed confirmation</dt>
                            <dd>{{ $restoreRequirements['confirmation_required'] ? 'Required' : 'Not required' }}</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-[var(--secondary-text)]">Staging/copy test</dt>
                            <dd>{{ $restoreRequirements['staging_tested_required'] ? 'Required' : 'Not required' }}</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-[var(--secondary-text)]">Emergency backup</dt>
                            <dd>{{ $restoreRequirements['emergency_backup_before_restore'] ? 'Required' : 'Not required' }}</dd>
                        </div>
                    </dl>
                </div>
            </div>

            @if ($restoreJobId)
                <div id="restoreJobStatus"
                    class="mt-5 rounded-lg border border-[var(--border-warning)] bg-[var(--bg-warning)] p-4 text-sm text-[var(--text-warning)]"
                    data-status-url="{{ route('developer.backups.restore-upload.status', $restoreJobId) }}"
                    data-run-now-url="{{ route('developer.backups.restore-upload.run-now', $restoreJobId) }}">
                    <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                        <div>
                            <div class="font-semibold" data-restore-status-title>
                                {{ ($restoreJobStatus['status'] ?? '') === 'completed' ? 'Restore completed' : 'Restore waiting to start' }}
                            </div>
                            <p class="mt-1" data-restore-status-message>
                                {{ $restoreJobStatus['message'] ?? 'The database file has been uploaded. Restore has not started yet.' }}
                            </p>
                        </div>
                        <span class="{{ $badge }} border-[var(--border-warning)] bg-[var(--bg-warning)] text-[var(--text-warning)]" data-restore-status-badge>
                            {{ ($restoreJobStatus['status'] ?? '') === 'completed' ? 'Completed' : 'Waiting' }}
                        </span>
                    </div>
                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        <span class="text-xs font-semibold" data-restore-progress-label>Waiting to start</span>
                        <button type="button"
                            class="{{ $secondaryButton }} {{ ($restoreJobStatus['can_run_now'] ?? false) ? '' : 'hidden' }}"
                            data-restore-run-now>
                            Run Restore Now
                        </button>
                        <a href="{{ route('home') }}"
                            class="{{ $secondaryButton }} {{ ($restoreJobStatus['status'] ?? '') === 'completed' ? '' : 'hidden' }}"
                            data-restore-refresh>
                            Go to Dashboard
                        </a>
                    </div>
                    <div class="mt-3 h-2 overflow-hidden rounded-full bg-[var(--h-bg-color)]">
                        <div class="h-full bg-[var(--primary-color)] transition-all duration-300" style="width: {{ (int) ($restoreJobStatus['progress'] ?? 10) }}%" data-restore-status-progress></div>
                    </div>
                    <p class="mt-2 text-xs text-[var(--secondary-text)]">You can keep this page open. The restore runs outside the browser request to avoid gateway timeout.</p>
                </div>
            @endif

            <details class="mt-5 {{ $softPanel }}">
                <summary class="cursor-pointer font-semibold">Developer diagnostics</summary>
                <dl class="mt-4 grid grid-cols-1 gap-2 text-xs md:grid-cols-2">
                    @foreach (($diagnostics ?? []) as $key => $value)
                        <div class="flex justify-between gap-3 rounded-md bg-[var(--secondary-bg-color)] p-2">
                            <dt class="text-[var(--secondary-text)]">{{ str_replace('_', ' ', $key) }}</dt>
                            <dd class="max-w-[60%] truncate font-mono">
                                @if (is_bool($value))
                                    {{ $value ? 'yes' : 'no' }}
                                @else
                                    {{ $value ?: '-' }}
                                @endif
                            </dd>
                        </div>
                    @endforeach
                </dl>
            </details>
        </section>

        <section class="{{ $panel }}">
            <x-developer-panel-title title="Database Tools" description="Repair storage permissions and run local migrations when a copied install needs recovery." />
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                <div class="{{ $softPanel }}">
                    <h2 class="font-semibold">Run Database Migrations</h2>
                    <p class="mt-2 text-sm text-[var(--secondary-text)]">
                        Developer/admin recovery action for copied or upgraded installs. This runs Laravel migrations only; it does not restore backups or change license/device identity.
                    </p>
                </div>
                <div class="space-y-4">
                    <form method="POST" action="{{ route('developer.backups.repair-storage') }}">
                        @csrf
                        <button type="submit" class="{{ $primaryButton }}">Repair Storage Permissions</button>
                        <p class="mt-2 text-xs text-[var(--secondary-text)]">
                            Creates required storage folders, clears Laravel cache data safely, and checks backup/restore writability.
                        </p>
                    </form>
                    <form method="POST" action="{{ route('developer.backups.run-migrations') }}" class="space-y-3">
                        @csrf
                        <label class="flex items-center justify-between gap-3 rounded-xl border border-[var(--h-bg-color)] bg-[var(--h-bg-color)]/35 px-3 py-2 text-sm text-[var(--secondary-text)]">
                            <span>I confirm this local database should be migrated now.</span>
                            <x-toggle-switch name="confirm_migrations" />
                        </label>
                        <button type="submit" class="{{ $secondaryButton }}">Run Database Migrations</button>
                    </form>
                </div>
            </div>
        </section>

        <section class="{{ $panel }}">
            <x-developer-panel-title title="Restore Old SQLite Database" description="Restore business data only. License/device identity remains tied to this install." />

            <div class="grid grid-cols-1 gap-5 lg:grid-cols-2">
                <div class="{{ $softPanel }}">
                    <h2 class="font-semibold">Business data only</h2>
                    <p class="mt-2 text-sm text-[var(--secondary-text)]">
                        Upload an old GarmentsOS SQLite database to restore business records. This does not restore .env, install ID, license cache, device approval, request cache, or update markers.
                    </p>
                    <p class="mt-2 text-sm font-semibold text-[var(--text-warning)]">
                        License/device approval remains tied to this installation after restore.
                    </p>
                </div>

                <form method="POST" action="{{ route('developer.backups.restore-upload') }}" enctype="multipart/form-data" class="space-y-4">
                    @csrf
                    <div>
                        <label class="mb-1 block text-sm font-medium">SQLite database file</label>
                        <input type="file" name="sqlite_file" accept=".sqlite,.db" class="w-full rounded-lg border border-[var(--h-bg-color)] bg-[var(--h-bg-color)] px-3 py-2 text-sm text-[var(--text-color)]" @disabled(!$restoreEnabled)>
                        <p class="mt-1 text-xs text-[var(--secondary-text)]">Allowed extensions: .sqlite, .db</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">Type confirmation phrase</label>
                        <input name="confirmation_phrase" placeholder="RESTORE BUSINESS DATA" class="w-full rounded-lg border border-[var(--h-bg-color)] bg-[var(--h-bg-color)] px-3 py-2 text-sm text-[var(--text-color)]" @disabled(!$restoreEnabled)>
                    </div>
                    <label class="flex items-start gap-2 text-sm text-[var(--secondary-text)]">
                        <x-toggle-switch name="staging_tested" :disabled="!$restoreEnabled" class="mt-0.5" />
                        <span>I tested this restore on a staging/copy database and confirmed this old database is correct.</span>
                    </label>
                    <button type="submit" class="{{ $restoreEnabled ? $primaryButton : $disabledButton }}" @disabled(!$restoreEnabled)>
                        Restore Uploaded Database
                    </button>
                </form>
            </div>
        </section>

        <section class="{{ $panel }}">
            <x-developer-panel-title title="Backup Logs" description="Recent backup records and restore audit information." />

            <div class="mb-4 flex items-center justify-between gap-3">
                <p class="text-sm text-[var(--secondary-text)]">Filenames are shown for identification; private storage paths are not shown.</p>
                <span class="{{ $badge }} border-gray-600 bg-[var(--h-bg-color)] text-[var(--secondary-text)]">{{ $logs->count() }} records</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[56rem] text-sm">
                    <thead class="bg-[var(--h-bg-color)]">
                        <tr>
                            <th class="p-3 text-left">Started</th>
                            <th class="p-3 text-left">Action</th>
                            <th class="p-3 text-left">Status</th>
                            <th class="p-3 text-left">File</th>
                            <th class="p-3 text-left">Size</th>
                            <th class="p-3 text-left">Checksum</th>
                            <th class="p-3 text-left">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-600/40">
                        @forelse ($logs as $log)
                            @php
                                $statusClass = match ($log->status) {
                                    'success' => 'border-[var(--border-success)] bg-[var(--bg-success)] text-[var(--text-success)]',
                                    'failed' => 'border-[var(--border-error)] bg-[var(--bg-error)] text-[var(--text-error)]',
                                    default => 'border-[var(--border-warning)] bg-[var(--bg-warning)] text-[var(--text-warning)]',
                                };
                            @endphp
                            <tr>
                                <td class="p-3">{{ $log->started_at?->format('Y-m-d H:i') }}</td>
                                <td class="p-3">{{ str_replace('_', ' ', $log->action) }}</td>
                                <td class="p-3"><span class="{{ $badge }} {{ $statusClass }}">{{ ucfirst($log->status) }}</span></td>
                                <td class="p-3">{{ $log->filename ?? '-' }}</td>
                                <td class="p-3">{{ $log->size_bytes ? number_format($log->size_bytes / 1024, 1) . ' KB' : '-' }}</td>
                                <td class="p-3 font-mono text-xs">{{ $log->checksum ? substr($log->checksum, 0, 16) . '...' : '-' }}</td>
                                <td class="p-3">
                                    <div class="flex flex-wrap gap-2">
                                        <form method="POST" action="{{ route('developer.backups.verify', $log) }}">
                                            @csrf
                                            <button type="submit" class="{{ $secondaryButton }}">Verify</button>
                                        </form>
                                        <a href="{{ route('developer.backups.download', $log) }}" class="{{ $secondaryButton }}" data-download-link="true">Download</a>
                                        <a href="{{ route('developer.backups.restore.show', $log) }}"
                                            class="{{ $restoreEnabled ? $secondaryButton : $disabledButton }}"
                                            @if (!$restoreEnabled) aria-disabled="true" @endif>
                                            Restore
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="p-8 text-center text-[var(--secondary-text)]">
                                    No managed backups yet. Create one before testing restore workflows.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    @if ($restoreJobId)
        <script>
            (() => {
                const panel = document.getElementById('restoreJobStatus');
                if (!panel) return;

                const title = panel.querySelector('[data-restore-status-title]');
                const message = panel.querySelector('[data-restore-status-message]');
                const badge = panel.querySelector('[data-restore-status-badge]');
                const progress = panel.querySelector('[data-restore-status-progress]');
                const progressLabel = panel.querySelector('[data-restore-progress-label]');
                const runNowButton = panel.querySelector('[data-restore-run-now]');
                const refreshButton = panel.querySelector('[data-restore-refresh]');
                const url = panel.dataset.statusUrl;
                const runNowUrl = panel.dataset.runNowUrl;
                let finished = false;
                let runningManualStart = false;

                const notify = (type, title, body) => {
                    if (typeof showNotification === 'function') {
                        showNotification(title, body, type);
                    }
                };

                const statusText = (payload) => {
                    const status = payload.status || 'pending';
                    if (status === 'completed') {
                        return {
                            title: 'Restore completed',
                            message: payload.message || 'Business data restored. License/device approval remains tied to this installation.',
                            badge: 'Completed',
                            label: 'Completed',
                            percent: 100,
                            tone: 'success',
                        };
                    }

                    if (status === 'superseded') {
                        return {
                            title: 'Restore superseded',
                            message: payload.message || 'A newer restore job completed. This older queued job was ignored.',
                            badge: 'Superseded',
                            label: 'Superseded',
                            percent: 100,
                            tone: 'success',
                        };
                    }

                    if (status === 'failed' || status === 'missing') {
                        return {
                            title: 'Restore failed',
                            message: payload.message || 'Restore failed safely.',
                            badge: 'Failed',
                            label: 'Failed',
                            percent: 0,
                            tone: 'error',
                        };
                    }

                    if (status === 'unavailable') {
                        return {
                            title: 'Restore status unavailable',
                            message: payload.message || 'Restore status unavailable. Refresh this page or check restore logs.',
                            badge: 'Unavailable',
                            label: 'Status unavailable',
                            percent: 0,
                            tone: 'warning',
                        };
                    }

                    if (status === 'running') {
                        return {
                            title: 'Restore in progress',
                            message: payload.message || 'Restoring business data. Please do not close or restart the app.',
                            badge: 'Running',
                            label: 'Restoring...',
                            percent: Math.max(0, Math.min(100, Number(payload.progress || 30))),
                            tone: 'warning',
                        };
                    }

                    if (payload.is_stale || payload.can_run_now) {
                        return {
                            title: 'Restore could not start automatically',
                            message: payload.message || 'The file was uploaded, but the restore job did not start. Click Run Restore Now or restart the app.',
                            badge: 'Needs action',
                            label: 'Needs action',
                            percent: 10,
                            tone: 'warning',
                        };
                    }

                    return {
                        title: 'Restore waiting to start',
                        message: payload.message || 'The database file has been uploaded. Restore has not started yet.',
                        badge: 'Waiting',
                        label: 'Waiting to start',
                        percent: 10,
                        tone: 'warning',
                    };
                };

                const render = (payload) => {
                    const status = payload.status || 'pending';
                    const display = statusText(payload);
                    const text = display.message;
                    const percent = Math.max(0, Math.min(100, Number(payload.progress || 0)));

                    if (title) title.textContent = display.title;
                    if (message) message.textContent = text;
                    if (badge) badge.textContent = display.badge;
                    if (progressLabel) progressLabel.textContent = display.label;
                    if (progress) progress.style.width = `${display.percent ?? percent}%`;
                    if (runNowButton) runNowButton.classList.toggle('hidden', !payload.can_run_now || status !== 'queued');
                    if (refreshButton) refreshButton.classList.toggle('hidden', status !== 'completed');

                    panel.classList.remove('border-[var(--border-warning)]', 'bg-[var(--bg-warning)]', 'text-[var(--text-warning)]', 'border-[var(--border-success)]', 'bg-[var(--bg-success)]', 'text-[var(--text-success)]', 'border-[var(--border-error)]', 'bg-[var(--bg-error)]', 'text-[var(--text-error)]');

                    if (display.tone === 'success') {
                        panel.classList.add('border-[var(--border-success)]', 'bg-[var(--bg-success)]', 'text-[var(--text-success)]');
                    } else if (display.tone === 'error') {
                        panel.classList.add('border-[var(--border-error)]', 'bg-[var(--bg-error)]', 'text-[var(--text-error)]');
                    } else {
                        panel.classList.add('border-[var(--border-warning)]', 'bg-[var(--bg-warning)]', 'text-[var(--text-warning)]');
                    }

                    if (!finished && (status === 'completed' || status === 'failed')) {
                        finished = true;
                        notify(status === 'completed' ? 'success' : 'error', 'Restore ' + status, text);
                    }

                    return status === 'completed' || status === 'failed' || status === 'missing' || status === 'superseded';
                };

                const poll = async () => {
                    try {
                        const response = await fetch(url, {headers: {'Accept': 'application/json'}});
                        const payload = await response.json();
                        if (render(payload)) return;
                    } catch (error) {
                        render({status: 'unavailable', message: 'Restore status unavailable. Refresh this page or check restore logs.', progress: 0});
                        return;
                    }

                    window.setTimeout(poll, 3000);
                };

                if (runNowButton) {
                    runNowButton.addEventListener('click', async () => {
                        if (runningManualStart) return;
                        runningManualStart = true;
                        runNowButton.disabled = true;
                        runNowButton.textContent = 'Starting...';

                        try {
                            const response = await fetch(runNowUrl, {
                                method: 'POST',
                                headers: {
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                },
                            });
                            const payload = await response.json();
                            render(payload);
                        } catch (error) {
                            render({status: 'failed', message: 'Restore could not be started. Check logs for details.', progress: 0});
                        } finally {
                            runningManualStart = false;
                            runNowButton.disabled = false;
                            runNowButton.textContent = 'Run Restore Now';
                        }
                    });
                }

                poll();
            })();
        </script>
    @endif
@endsection
