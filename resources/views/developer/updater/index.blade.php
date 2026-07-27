@extends('app')

@section('title', 'Updater | ' . $client_company->name)

@section('content')
    @php
        $panel = 'rounded-2xl border border-[var(--h-bg-color)] bg-[var(--secondary-bg-color)] p-5 text-sm shadow-sm';
        $softPanel = 'rounded-2xl border border-[var(--h-bg-color)] bg-[var(--h-bg-color)]/25 p-4';
        $badge = 'inline-flex items-center rounded-xl border px-3 py-1.5 text-xs font-semibold';
        $primaryButton = 'px-4 py-2 bg-[var(--primary-color)] text-[var(--text-color)] font-medium text-nowrap rounded-lg hover:bg-[var(--h-primary-color)] hover:scale-95 transition-all duration-300 ease-in-out cursor-pointer';
        $secondaryButton = 'px-4 py-2 bg-[var(--h-bg-color)] border border-gray-600 text-[var(--secondary-text)] font-medium text-nowrap rounded-lg hover:bg-[var(--secondary-bg-color)] hover:scale-95 transition-all duration-300 ease-in-out cursor-pointer';
        $disabledButton = 'px-4 py-2 bg-[var(--h-bg-color)] border border-gray-600 text-[var(--secondary-text)] font-medium text-nowrap rounded-lg opacity-60 cursor-not-allowed';
        $releaseFeed = $releaseFeedStatus['feed'] ?? [];
        $releaseFeedCode = $releaseFeedStatus['code'] ?? 'feed_not_configured';
        $updateAvailable = !empty($releaseFeedStatus['update_available']);
        $upToDate = !empty($releaseFeedStatus['success']) && $releaseFeedCode === 'up_to_date';
        $feedUnreachable = $releaseFeedCode === 'feed_unreachable';
        $fallbackUsed = !empty($releaseFeedStatus['fallback_used']);
        $primaryFeedFailed = $releaseFeedStatus['primary_feed_failed'] ?? null;
        $activeFeedUrl = $releaseFeedStatus['feed_url'] ?? $updateFeedUrl;
        $feedDiagnostics = $releaseFeedStatus['diagnostics'] ?? $curlDiagnostics ?? [];
        $feedDiagnosticCode = $releaseFeedStatus['diagnostic_code'] ?? null;
        $setupUrl = $releaseFeed['setup_url'] ?? '';
        $setupUrlAvailable = is_string($setupUrl)
            && $setupUrl !== ''
            && !str_starts_with($setupUrl, 'PLACEHOLDER_')
            && filter_var($setupUrl, FILTER_VALIDATE_URL);
        $requestUrl = route('developer.updater.update-request');
        $launcherUpdateUrl = $launcherHandoff['protocol_url'] ?? null;
        $canRunUpdateActions = (bool) ($canRunUpdateActions ?? false);
        $statusClass = $updateAvailable
            ? 'border-[var(--border-warning)] bg-[var(--bg-warning)] text-[var(--text-warning)]'
            : ($upToDate
                ? 'border-[var(--border-success)] bg-[var(--bg-success)] text-[var(--text-success)]'
                : 'border-gray-600 bg-[var(--h-bg-color)] text-[var(--secondary-text)]');
        $statusLabel = $updateAvailable ? 'Update available' : ($upToDate ? 'Up to date' : str_replace('_', ' ', $releaseFeedCode));
        $statusMessage = $updateAvailable
            ? ($canRunUpdateActions
                ? 'A new version is available. Click Update Now to open GarmentsOS PRO Launcher.'
                : 'A new version is available. Update can only be started from the server PC.')
            : ($upToDate
                ? 'This installation is already on the latest version.'
                : ($releaseFeedStatus['message'] ?? 'Update feed status is unavailable.'));
        $canApply = $enabled && !empty($result['success']) && !empty($result['update_available']);
        $manifestReady = $enabled && $manifestUrlConfigured;
        $applyStatus = $manifestReady
            ? 'Available after signed manifest and package validation'
            : 'Advanced signed-manifest apply is not configured';
        $releaseNoteItems = format_release_notes($releaseFeed['notes'] ?? null);
        $manifestReleaseNoteItems = format_release_notes($result['manifest']['release_notes'] ?? null);
    @endphp

    <div class="max-w-6xl mx-auto w-full">
        <x-search-header heading="Updater" />
    </div>

    <section class="text-center mx-auto">
        <div class="show-box mx-auto w-[80%] h-[70vh] bg-[var(--secondary-bg-color)] border border-[var(--glass-border-color)]/20 rounded-xl shadow pt-8.5 relative">
            <x-form-title-bar title="Updater" />
            <div class="details h-full z-40">
                <div class="container-parent h-full">
                    <div class="card_container px-4 h-full flex flex-col">
                        <div class="overflow-y-auto grow my-scrollbar-2 space-y-4  pr-1 text-left">

        <section class="{{ $panel }}">
            <x-developer-panel-title title="Release Feed Update" description="Checks the public latest.json feed and hands update work to the Windows launcher.">
                <span class="{{ $badge }} {{ $statusClass }}">{{ $statusLabel }}</span>
            </x-developer-panel-title>

            @if (!empty($updateLockStatus['updating']))
                <div class="mb-4 rounded-lg border border-[var(--border-warning)] bg-[var(--bg-warning)] p-4 text-sm text-[var(--text-warning)]">
                    <div class="font-semibold">GarmentsOS PRO is updating</div>
                    <div class="mt-1">{{ $updateLockStatus['message'] ?? 'Please wait until the update is complete.' }}</div>
                    <div class="mt-2 text-xs">
                        Target: {{ $updateLockStatus['target_version'] ?? '-' }} |
                        Started: {{ $updateLockStatus['started_at'] ?? '-' }} |
                        Expires: {{ $updateLockStatus['expires_at'] ?? '-' }}
                    </div>
                    <form method="POST" action="{{ route('developer.updater.clear-update-lock') }}" class="mt-3">
                        @csrf
                        <button type="submit" class="{{ $secondaryButton }}">
                            Clear stale update lock
                        </button>
                    </form>
                </div>
            @endif

            <div class="mb-4 flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                <p class="max-w-3xl text-sm text-[var(--secondary-text)]">
                    Checks the configured public `latest.json` feed. Laravel only prepares the handoff; the Windows launcher applies updates outside the running app.
                </p>
            </div>

            <div class="mb-4 rounded-lg border {{ $upToDate ? 'border-[var(--border-success)] bg-[var(--bg-success)] text-[var(--text-success)]' : ($updateAvailable ? 'border-[var(--border-warning)] bg-[var(--bg-warning)] text-[var(--text-warning)]' : 'border-gray-600 bg-[var(--h-bg-color)] text-[var(--secondary-text)]') }} p-4">
                {{ $statusMessage }}
                @if ($fallbackUsed)
                    <div class="mt-2 text-xs">
                        Primary feed failed{{ $primaryFeedFailed ? ': ' . ($primaryFeedFailed['message'] ?? $primaryFeedFailed['code'] ?? 'unreachable') : '' }}. Using fallback feed.
                    </div>
                    <div class="mt-1 break-all font-mono text-xs">
                        Fallback: {{ $releaseFeedStatus['fallback_feed_url'] ?? $activeFeedUrl }}
                    </div>
                @endif
                @if ($feedUnreachable && (($releaseFeedStatus['http_status'] ?? null) === 404))
                    <div class="mt-2 text-xs">
                        If this feed points to a private GitHub release asset, unauthenticated apps may receive 404. Use a public SparkPair update feed URL.
                    </div>
                @endif
                @if (!empty($releaseFeedStatus['http_status']))
                    <div class="mt-2 text-xs">HTTP status: {{ $releaseFeedStatus['http_status'] }}</div>
                @endif
                @if ($feedDiagnosticCode === 'curl_ca_missing')
                    <div class="mt-2 text-xs font-semibold">
                        HTTPS certificate bundle is missing. Please configure PHP curl.cainfo.
                    </div>
                @endif
            </div>

            <dl class="grid grid-cols-1 gap-3 md:grid-cols-2">
                <div class="{{ $softPanel }}">
                    <dt class="text-[var(--secondary-text)]">Current installed version</dt>
                    <dd class="mt-1 font-semibold">{{ $releaseFeedStatus['current_version'] ?? $currentVersion }}</dd>
                    <dd class="mt-1 text-xs text-[var(--secondary-text)]">Source: {{ $currentVersionSourceLabel }}</dd>
                    <dd class="mt-1 text-xs text-[var(--secondary-text)]">Mode: {{ $runtimeModeLabel }}</dd>
                </div>
                <div class="{{ $softPanel }}">
                    <dt class="text-[var(--secondary-text)]">Latest feed version</dt>
                    <dd class="mt-1 font-semibold">{{ $releaseFeed['version'] ?? '-' }}</dd>
                    <dd class="mt-1 text-xs text-[var(--secondary-text)]">Channel: {{ $releaseFeed['channel'] ?? $channel }}</dd>
                </div>
                <div class="{{ $softPanel }} md:col-span-2">
                    <dt class="text-[var(--secondary-text)]">Configured feed URL</dt>
                    <dd class="mt-1 break-all font-mono text-xs">{{ $updateFeedUrlConfigured ? $updateFeedUrl : 'Not configured' }}</dd>
                </div>
                <div class="{{ $softPanel }} md:col-span-2">
                    <dt class="text-[var(--secondary-text)]">Active feed URL</dt>
                    <dd class="mt-1 break-all font-mono text-xs">{{ $activeFeedUrl ?: 'Not configured' }}</dd>
                    @if ($fallbackUsed)
                        <dd class="mt-1 text-xs text-[var(--text-warning)]">Fallback feed is being used for this check.</dd>
                    @endif
                </div>
                <div class="{{ $softPanel }} md:col-span-2">
                    <dt class="text-[var(--secondary-text)]">PHP HTTPS diagnostics</dt>
                    <dd class="mt-1 text-xs text-[var(--secondary-text)]">
                        cURL available: {{ !empty($feedDiagnostics['php_curl_available']) ? 'yes' : 'no' }}
                    </dd>
                    <dd class="mt-1 break-all font-mono text-xs">curl.cainfo: {{ $feedDiagnostics['curl_cainfo'] ?? '' ?: '-' }}</dd>
                    <dd class="mt-1 break-all font-mono text-xs">openssl.cafile: {{ $feedDiagnostics['openssl_cafile'] ?? '' ?: '-' }}</dd>
                    <dd class="mt-1 text-xs text-[var(--secondary-text)]">
                        certificate file exists: {{ !empty($feedDiagnostics['certificate_file_exists']) ? 'yes' : 'no' }}
                    </dd>
                </div>
                <div class="{{ $softPanel }}">
                    <dt class="text-[var(--secondary-text)]">Mandatory</dt>
                    <dd class="mt-1 font-semibold">{{ !empty($releaseFeed['mandatory']) ? 'true' : 'false' }}</dd>
                </div>
                <div class="{{ $softPanel }}">
                    <dt class="text-[var(--secondary-text)]">Released at</dt>
                    <dd class="mt-1 font-semibold">{{ $releaseFeed['released_at'] ?? '-' }}</dd>
                </div>
                <div class="{{ $softPanel }}">
                    <dt class="text-[var(--secondary-text)]">Package file</dt>
                    <dd class="mt-1 break-all font-semibold">{{ $releaseFeed['package_file'] ?? '-' }}</dd>
                </div>
                <div class="{{ $softPanel }}">
                    <dt class="text-[var(--secondary-text)]">Windows updater</dt>
                    @if ($setupUrlAvailable)
                        <dd class="mt-1 break-all font-mono text-xs">{{ $setupUrl }}</dd>
                    @else
                        <dd class="mt-1 font-semibold">Not advertised by feed</dd>
                    @endif
                </div>
                <div class="{{ $softPanel }} md:col-span-2">
                    <dt class="text-[var(--secondary-text)]">Notes</dt>
                    <dd class="mt-2">
                        @if ($releaseNoteItems)
                            <ul class="space-y-1.5">
                                @foreach ($releaseNoteItems as $note)
                                    <li class="flex gap-2 text-sm">
                                        <span class="mt-2 h-1.5 w-1.5 shrink-0 rounded-full bg-[var(--primary-color)]"></span>
                                        <span>{{ $note }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            -
                        @endif
                    </dd>
                </div>
            </dl>

            @if ($updateAvailable)
                <div class="mt-5 rounded-lg border border-[var(--border-warning)] bg-[var(--bg-warning)] p-4 text-sm text-[var(--text-warning)]">
                    @if ($canRunUpdateActions)
                        This opens GarmentsOS PRO Updater. Your Update Now click confirms the update; the Windows updater applies it outside the running app.
                    @else
                        {{ $updateActionDeniedMessage ?? 'Updates can only be started from the server PC.' }}
                    @endif
                </div>

                @if ($canRunUpdateActions)
                    <div class="mt-4 flex flex-wrap items-center gap-3">
                        @if ($launcherUpdateUrl)
                            <a href="#" data-update-start-url="{{ route('developer.updater.launcher-handoff.start') }}" class="{{ $primaryButton }} js-update-handoff">
                                Update Now
                            </a>
                        @else
                            <button type="button" class="{{ $disabledButton }}" disabled>
                                Update Now
                            </button>
                            <span class="text-xs text-[var(--secondary-text)]">
                                GarmentsOS PRO Launcher link is not configured on this PC. Download and run the Windows updater once, then try again.
                            </span>
                        @endif
                    </div>
                    @if (!empty($launcherHandoff['expires_at']))
                        <p class="mt-3 text-xs text-[var(--secondary-text)]">
                            The signed launcher request expires at {{ $launcherHandoff['expires_at'] }}. Protocol handoff requires garmentsos:// registration on the client machine.
                        </p>
                    @endif
                @endif

                @if ($canRunUpdateActions)
                    <details class="mt-5 rounded-lg border border-[var(--h-bg-color)] bg-[var(--h-bg-color)]/50 p-4">
                        <summary class="cursor-pointer font-semibold">Troubleshooting / Manual update</summary>
                        <p class="mt-3 text-sm text-[var(--secondary-text)]">
                            Use this only if Update Now does not open the launcher.
                        </p>
                        <div class="mt-4 flex flex-wrap gap-3">
                            <a href="{{ $requestUrl }}" class="{{ $secondaryButton }}">
                                Download Update Request
                            </a>
                            @if ($setupUrlAvailable)
                                <a href="{{ $setupUrl }}" class="{{ $secondaryButton }}">
                                    Download Windows Updater
                                </a>
                            @endif
                        </div>
                    </details>
                @endif
            @endif

            <div class="mt-5 rounded-lg border border-[var(--h-bg-color)] bg-[var(--h-bg-color)]/50 p-4">
                <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <div>
                        <div class="font-semibold">Update feed repair</div>
                        <p class="mt-1 text-sm text-[var(--secondary-text)]">
                            Use this if the installed `.env` points to an old or private update feed.
                        </p>
                    </div>
                    <form method="POST" action="{{ route('developer.updater.set-stable-feed') }}">
                        @csrf
                        <button type="submit" class="{{ $secondaryButton }}">
                            Set feed to SparkPair stable
                        </button>
                    </form>
                </div>
            </div>

            <div class="mt-5 rounded-lg border border-[var(--h-bg-color)] bg-[var(--h-bg-color)]/50 p-4">
                <div class="font-semibold">Database and backup diagnostics</div>
                <dl class="mt-3 grid grid-cols-1 gap-3 md:grid-cols-2">
                    <div>
                        <dt class="text-xs uppercase text-[var(--secondary-text)]">DB connection</dt>
                        <dd class="mt-1 font-mono text-xs">{{ $databaseDiagnostics['connection'] ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase text-[var(--secondary-text)]">Container DB path</dt>
                        <dd class="mt-1 break-all font-mono text-xs">{{ $databaseDiagnostics['container_database_path'] ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase text-[var(--secondary-text)]">Host DB path</dt>
                        <dd class="mt-1 break-all font-mono text-xs">{{ $databaseDiagnostics['host_database_path'] ?: 'Not bind-mounted / not visible to Laravel container' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase text-[var(--secondary-text)]">Docker volumes</dt>
                        <dd class="mt-1 break-all font-mono text-xs">
                            DB: {{ $databaseDiagnostics['database_volume'] ?? '-' }}<br>
                            Storage: {{ $databaseDiagnostics['storage_volume'] ?? '-' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase text-[var(--secondary-text)]">Last backup path</dt>
                        <dd class="mt-1 break-all font-mono text-xs">{{ $databaseDiagnostics['last_backup_path'] ?: '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase text-[var(--secondary-text)]">Last backup status</dt>
                        <dd class="mt-1 font-mono text-xs">
                            {{ $databaseDiagnostics['last_backup_status'] ?: '-' }}
                            @if (!empty($databaseDiagnostics['last_backup_timestamp']))
                                <span class="text-[var(--secondary-text)]">({{ $databaseDiagnostics['last_backup_timestamp'] }})</span>
                            @endif
                        </dd>
                    </div>
                </dl>
            </div>
        </section>

        <details class="{{ $panel }}">
            <summary class="cursor-pointer list-none">
                <x-developer-panel-title title="Advanced Update Security" description="Advanced signed-manifest apply is separate from the release feed and Windows launcher flow.">
                    <span class="{{ $badge }} border-gray-600 bg-[var(--h-bg-color)] text-[var(--secondary-text)]">
                        {{ $manifestReady ? 'Signed manifest configured' : 'Signed manifest not configured' }}
                    </span>
                </x-developer-panel-title>
            </summary>

            <div class="mt-6 grid grid-cols-1 gap-3 md:grid-cols-2">
                <div class="{{ $softPanel }}">
                    <dt class="text-[var(--secondary-text)]">Manifest configured</dt>
                    <dd class="mt-1 font-semibold">{{ $manifestUrlConfigured ? 'Yes' : 'No' }}</dd>
                </div>
                <div class="{{ $softPanel }}">
                    <dt class="text-[var(--secondary-text)]">Signature required</dt>
                    <dd class="mt-1 font-semibold">{{ $signatureRequired ? 'Yes' : 'No' }}</dd>
                </div>
                <div class="{{ $softPanel }}">
                    <dt class="text-[var(--secondary-text)]">Apply/install</dt>
                    <dd class="mt-1 font-semibold">{{ $applyStatus }}</dd>
                </div>
                <div class="{{ $softPanel }}">
                    <dt class="text-[var(--secondary-text)]">Rollback</dt>
                    <dd class="mt-1 font-semibold">{{ $rollbackAvailable ? 'Available' : 'Not available' }}</dd>
                </div>
            </div>

            @if (!$manifestReady)
                <div class="mt-4 rounded-lg border border-[var(--border-warning)] bg-[var(--bg-warning)] p-3 text-sm text-[var(--text-warning)]">
                    Advanced signed-manifest apply is not configured. Release feed checks and Windows launcher updates are still supported.
                </div>
            @endif

            <form method="POST" action="{{ route('developer.updater.check') }}" class="mt-4">
                @csrf
                <button type="submit" class="{{ $enabled ? $secondaryButton : $disabledButton }}" @disabled(!$enabled)>
                    Check Manifest
                </button>
            </form>

            @if ($result)
                <div class="mt-5 rounded-lg border border-[var(--h-bg-color)] bg-[var(--h-bg-color)]/50 p-4">
                    <div class="mb-4 flex items-start justify-between gap-3">
                        <p class="text-sm text-[var(--secondary-text)]">{{ $result['message'] }}</p>
                        <span class="{{ $badge }} {{ !empty($result['success']) ? 'border-[var(--border-success)] bg-[var(--bg-success)] text-[var(--text-success)]' : 'border-[var(--border-warning)] bg-[var(--bg-warning)] text-[var(--text-warning)]' }}">
                            {{ $result['code'] }}
                        </span>
                    </div>

                    @if (!empty($result['manifest']))
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div class="{{ $softPanel }}">
                                <div class="text-[var(--secondary-text)]">Latest version</div>
                                <div>{{ $result['latest_version'] ?? '-' }}</div>
                            </div>
                            <div class="{{ $softPanel }}">
                                <div class="text-[var(--secondary-text)]">Mandatory</div>
                                <div>{{ !empty($result['mandatory']) ? 'Yes' : 'No' }}</div>
                            </div>
                            <div class="{{ $softPanel }} md:col-span-2">
                                <div class="text-[var(--secondary-text)]">Package checksum</div>
                                <div class="break-all font-mono text-xs">{{ $result['manifest']['package_checksum'] ?? '-' }}</div>
                            </div>
                            <div class="{{ $softPanel }} md:col-span-2">
                                <div class="text-[var(--secondary-text)]">Release notes</div>
                                <div class="mt-2">
                                    @if ($manifestReleaseNoteItems)
                                        <ul class="space-y-1.5">
                                            @foreach ($manifestReleaseNoteItems as $note)
                                                <li class="flex gap-2 text-sm">
                                                    <span class="mt-2 h-1.5 w-1.5 shrink-0 rounded-full bg-[var(--primary-color)]"></span>
                                                    <span>{{ $note }}</span>
                                                </li>
                                            @endforeach
                                        </ul>
                                    @else
                                        -
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="mt-5 rounded-lg border border-[var(--border-warning)] bg-[var(--bg-warning)] p-4 text-sm text-[var(--text-warning)]">
                            Apply creates a verified database backup, validates the package again, stages files privately, snapshots overwritten code files, preserves `.env`, database, backups, logs, and private storage, then runs migrations only when the manifest requires it.
                        </div>

                        @if ($canRunUpdateActions)
                            <form method="POST" action="{{ route('developer.updater.apply') }}" class="mt-4">
                                @csrf
                                <button type="submit" class="{{ $canApply ? $primaryButton : $disabledButton }}" @disabled(!$canApply)>
                                    Apply Verified Update
                                </button>
                            </form>
                        @else
                            <div class="mt-4 rounded-lg border border-[var(--border-warning)] bg-[var(--bg-warning)] p-3 text-sm text-[var(--text-warning)]">
                                Signed manifest updates can only be applied from the server PC.
                            </div>
                        @endif
                    @endif
                </div>
            @endif

            @if ($applyResult)
                <div class="mt-5 rounded-lg border {{ !empty($applyResult['success']) ? 'border-[var(--border-success)] bg-[var(--bg-success)] text-[var(--text-success)]' : 'border-[var(--border-error)] bg-[var(--bg-error)] text-[var(--text-error)]' }} p-4 text-sm">
                    {{ $applyResult['message'] }}
                </div>
            @endif
        </details>

        <section class="rounded-lg border border-[var(--border-warning)] bg-[var(--bg-warning)] p-4 text-sm text-[var(--text-warning)]">
            Laravel never loads Docker images, restarts containers, or replaces the running app. Windows launcher/update tools apply packages outside the app process and preserve client data.
        </section>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
