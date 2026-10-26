@extends('app')

@section('title', 'License Activation | ' . $client_company->name)

@section('content')
    @php
        $panel = 'rounded-2xl border border-[var(--h-bg-color)] bg-[var(--secondary-bg-color)] p-5 text-sm shadow-sm';
        $softPanel = 'rounded-2xl border border-[var(--h-bg-color)] bg-[var(--h-bg-color)]/25 p-4';
        $input = 'w-full rounded-lg border border-[var(--h-bg-color)] bg-[var(--h-bg-color)] px-3 py-2 text-sm text-[var(--text-color)] outline-none focus:border-[var(--primary-color)]';
        $textarea = $input . ' min-h-[86px]';
        $primaryButton = 'px-4 py-2 bg-[var(--primary-color)] text-[var(--text-color)] font-medium text-nowrap rounded-lg hover:bg-[var(--h-primary-color)] hover:scale-95 transition-all duration-300 ease-in-out cursor-pointer';
        $secondaryButton = 'px-4 py-2 bg-[var(--h-bg-color)] border border-gray-600 text-[var(--secondary-text)] font-medium text-nowrap rounded-lg hover:bg-[var(--secondary-bg-color)] hover:scale-95 transition-all duration-300 ease-in-out cursor-pointer';
        $badge = 'inline-flex items-center rounded-xl border px-3 py-1.5 text-xs font-semibold';
        $foundationReady = $foundationReady ?? true;
        $missingTables = $missingTables ?? [];
        $requestCache = $requestCache ?? null;
        $canManageLicense = $canManageLicense ?? false;
        $isActive = $status->state === 'active';
        $isPending = in_array($status->state, ['pending', 'activation_required'], true) || (($licenseConfig['device_status'] ?? '') === 'pending');
        $isApprovedButInvalid = in_array(strtolower((string) ($licenseConfig['device_status'] ?? '')), ['active', 'approved', 'grace'], true)
            && !$isActive
            && in_array($status->state, ['invalid_readonly', 'installation_mismatch', 'blocked'], true);
        $hasLatestCheck = !empty($licenseConfig['last_check_at']);
        $needsRefresh = !$isActive && in_array($status->state, ['invalid_readonly', 'installation_mismatch', 'activation_required', 'blocked'], true);
        $statusMessage = $status->message;
        if ($needsRefresh && !$hasLatestCheck) {
            $statusMessage = 'License has not been refreshed after this update. Click Refresh/Rebind License Approval.';
        } elseif (($licenseConfig['latest_response_status'] ?? '') === 'pending') {
            $statusMessage = 'This installation is pending approval on SparkPair.';
        } elseif (in_array(($licenseConfig['latest_response_status'] ?? ''), ['identity_mismatch', 'installation_mismatch', 'fingerprint_mismatch'], true)) {
            $statusMessage = 'This installation needs rebind approval. Click Refresh/Rebind or approve it in SparkPair admin.';
        } elseif (!empty($licenseConfig['latest_response_message'])) {
            $statusMessage = $licenseConfig['latest_response_message'];
        }
        $isDevelopmentBypass = !$licensingEnabled || ($licenseConfig['development_bypass'] ?? false);
        $isReadonlyRecovery = session('license_readonly', false);
        $bannerClass = $isActive
            ? 'border-[var(--border-success)] bg-[var(--bg-success)] text-[var(--text-success)]'
            : ($isDevelopmentBypass
                ? 'border-[var(--border-warning)] bg-[var(--bg-warning)] text-[var(--text-warning)]'
                : 'border-[var(--border-error)] bg-[var(--bg-error)] text-[var(--text-error)]');
        $stateLabel = ucfirst(str_replace('_', ' ', $status->state));
        $requestType = old('request_type', $requestCache['request_type'] ?? 'demo_trial');
    @endphp

    <div class="max-w-6xl mx-auto w-full">
        <x-search-header heading="License Activation" />
    </div>

    <section class="text-center mx-auto">
        <div class="show-box mx-auto w-[90%] h-[70vh] bg-[var(--secondary-bg-color)] border border-[var(--glass-border-color)]/20 rounded-xl shadow pt-8.5 relative">
            <x-form-title-bar title="License Activation" />
            <div class="details h-full z-40">
                <div class="container-parent h-full">
                    <div class="card_container px-4 h-full flex flex-col">
                        <div class="overflow-y-auto grow my-scrollbar-2 space-y-4  pr-1 text-left">

        <section class="{{ $panel }}">
            <x-developer-panel-title title="License Activation" description="GarmentsOS PRO is activated by SparkPair using this installation ID and device fingerprint.">
                <span class="{{ $badge }} {{ $isActive ? 'border-[var(--border-success)] bg-[var(--bg-success)] text-[var(--text-success)]' : 'border-[var(--border-warning)] bg-[var(--bg-warning)] text-[var(--text-warning)]' }}">
                    {{ $isActive ? 'License active' : 'Activation required' }}
                </span>
            </x-developer-panel-title>

            <div class="mb-4 flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                <div>
                    <p class="max-w-3xl text-sm text-[var(--secondary-text)]">
                        No license key is entered in the app.
                    </p>
                    @if ($isDevelopmentBypass)
                        <p class="mt-2 text-sm font-semibold text-[var(--text-warning)]">
                            Licensing enforcement is disabled for this build. This should only be used for development or approved demo testing.
                        </p>
                    @endif
                </div>
            </div>

            @if (!$foundationReady)
                <div class="mb-4 rounded-lg border border-[var(--border-warning)] bg-[var(--bg-warning)] p-4 text-sm text-[var(--text-warning)]">
                    <div class="font-semibold">Licensing setup is pending</div>
                    <p class="mt-1">The licensing foundation tables are not available in this local database yet.</p>
                    @if ($missingTables)
                        <p class="mt-2 font-mono text-xs">Missing: {{ implode(', ', $missingTables) }}</p>
                    @endif
                </div>
            @endif

            @if ($isReadonlyRecovery)
                <div class="mb-4 rounded-lg border border-[var(--border-warning)] bg-[var(--bg-warning)] p-4 text-sm text-[var(--text-warning)]">
                    <div class="font-semibold">Readonly recovery mode</div>
                    <p class="mt-1">
                        Business actions are readonly, but license refresh, activation, updater, and repair actions are allowed for recovery.
                    </p>
                </div>
            @endif

            @if ($errors->any())
                <div class="mb-4 rounded-lg border border-[var(--border-error)] bg-[var(--bg-error)] p-4 text-sm text-[var(--text-error)]">
                    <div class="font-semibold">Please fix the highlighted fields before continuing.</div>
                    <ul class="mt-2 list-disc space-y-1 pl-5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="mb-5 rounded-lg border p-4 {{ $bannerClass }}">
                @if ($isActive)
                    <div class="font-semibold">License active</div>
                    <p class="mt-1">
                        {{ $licenseConfig['customer_name'] ?: 'This device is approved by SparkPair.' }}
                        @if ($status->expiresAt)
                            Valid until {{ $status->expiresAt->format('Y-m-d') }}.
                        @endif
                    </p>
                @elseif ($isPending)
                    <div class="font-semibold">Waiting for SparkPair approval</div>
                    <p class="mt-1">{{ $statusMessage ?: 'This device or demo request is pending approval.' }}</p>
                @elseif ($isApprovedButInvalid)
                    <div class="font-semibold">License approval needs refresh</div>
                    <p class="mt-1">
                        This device is approved, but the stored approval still references the older Docker-based fingerprint.
                        Refresh approval to rebind this same install ID to the stable install identity.
                    </p>
                @else
                    <div class="font-semibold">License activation is required</div>
                    <p class="mt-1">{{ $statusMessage ?: 'Request a demo/trial or register this device with SparkPair.' }}</p>
                @endif
                @if ($canManageLicense && $needsRefresh)
                    <form method="POST" action="{{ route('developer.license.check') }}" class="mt-3">
                        @csrf
                        <button type="submit" class="{{ $primaryButton }}">Refresh/Rebind License Approval</button>
                    </form>
                @endif
            </div>

            @if ($needsRefresh || $isApprovedButInvalid || !empty($licenseConfig['previous_machine_hash_preview']))
                <div class="mb-5 rounded-lg border border-[var(--border-warning)] bg-[var(--bg-warning)] p-4 text-sm text-[var(--text-warning)]">
                    <div class="font-semibold">Stable fingerprint rebind</div>
                    <p class="mt-1">
                        This installation keeps the same install ID, but GarmentsOS now verifies with a stable install identity instead of Docker runtime values.
                        SparkPair can safely rebind an already approved install ID to this new stable fingerprint.
                    </p>
                    @if ($canManageLicense)
                        <form method="POST" action="{{ route('developer.license.check') }}" class="mt-3">
                            @csrf
                            <button type="submit" class="{{ $primaryButton }}">Refresh/Rebind License Approval</button>
                        </form>
                    @endif
                </div>
            @endif

            <div class="mb-5 rounded-lg border border-[var(--h-bg-color)] bg-[var(--h-bg-color)]/50 p-4 text-sm text-[var(--secondary-text)]">
                <div class="font-semibold text-[var(--text-color)]">Latest license refresh response</div>
                @if ($hasLatestCheck)
                    <div class="mt-2 grid grid-cols-1 gap-2 md:grid-cols-4">
                        <div>HTTP: <span class="font-semibold">{{ $licenseConfig['latest_response_http_status'] ?: '-' }}</span></div>
                        <div>Status: <span class="font-semibold">{{ $licenseConfig['latest_response_status'] ?: '-' }}</span></div>
                        <div>Allowed: <span class="font-semibold">{{ $licenseConfig['latest_response_allowed'] ? 'yes' : 'no' }}</span></div>
                        <div>Rebind: <span class="font-semibold">{{ $licenseConfig['latest_response_rebind_performed'] ? 'done' : 'not reported' }}</span></div>
                    </div>
                    <p class="mt-2">{{ $licenseConfig['latest_response_message'] ?: 'No message returned.' }}</p>
                @else
                    <p class="mt-2">No license refresh response is recorded after this update. Click Refresh/Rebind License Approval.</p>
                @endif
            </div>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
                <div class="{{ $softPanel }}">
                    <div class="text-xs uppercase text-[var(--secondary-text)]">Current state</div>
                    <div class="mt-1 text-lg font-semibold">{{ $stateLabel }}</div>
                </div>
                <div class="{{ $softPanel }}">
                    <div class="text-xs uppercase text-[var(--secondary-text)]">Device approval</div>
                    <div class="mt-1 text-lg font-semibold">{{ $licenseConfig['device_status'] ? ucfirst(str_replace('_', ' ', $licenseConfig['device_status'])) : $stateLabel }}</div>
                </div>
                <div class="{{ $softPanel }}">
                    <div class="text-xs uppercase text-[var(--secondary-text)]">Customer</div>
                    <div class="mt-1 text-lg font-semibold">{{ $licenseConfig['customer_name'] ?: '-' }}</div>
                </div>
                <div class="{{ $softPanel }}">
                    <div class="text-xs uppercase text-[var(--secondary-text)]">Valid until</div>
                    <div class="mt-1 text-lg font-semibold">{{ $status->expiresAt?->format('Y-m-d') ?? '-' }}</div>
                </div>
                <div class="{{ $softPanel }}">
                    <div class="text-xs uppercase text-[var(--secondary-text)]">Install ID</div>
                    <div class="mt-1 break-all text-sm font-semibold" id="license-install-id">{{ $licenseConfig['install_id'] ?: '-' }}</div>
                </div>
                <div class="{{ $softPanel }}">
                    <div class="text-xs uppercase text-[var(--secondary-text)]">Machine name</div>
                    <div class="mt-1 break-all text-lg font-semibold">{{ $licenseConfig['machine_name'] ?: '-' }}</div>
                </div>
                <div class="{{ $softPanel }}">
                    <div class="text-xs uppercase text-[var(--secondary-text)]">Machine hash</div>
                    <div class="mt-1 break-all text-lg font-semibold">{{ $licenseConfig['machine_hash_preview'] ?: '-' }}</div>
                </div>
                <div class="{{ $softPanel }}">
                    <div class="text-xs uppercase text-[var(--secondary-text)]">Previous hash</div>
                    <div class="mt-1 break-all text-lg font-semibold">{{ $licenseConfig['previous_machine_hash_preview'] ?: '-' }}</div>
                </div>
                <div class="{{ $softPanel }}">
                    <div class="text-xs uppercase text-[var(--secondary-text)]">Fingerprint source</div>
                    <div class="mt-1 break-all text-sm font-semibold">{{ $licenseConfig['fingerprint_source'] ?: '-' }}</div>
                </div>
                <div class="{{ $softPanel }}">
                    <div class="text-xs uppercase text-[var(--secondary-text)]">App version</div>
                    <div class="mt-1 text-lg font-semibold">{{ $licenseConfig['app_version'] ?: '-' }}</div>
                </div>
                <div class="{{ $softPanel }}">
                    <div class="text-xs uppercase text-[var(--secondary-text)]">Last request</div>
                    <div class="mt-1 text-lg font-semibold">{{ $licenseConfig['last_request_at'] ?: '-' }}</div>
                </div>
                <div class="{{ $softPanel }}">
                    <div class="text-xs uppercase text-[var(--secondary-text)]">Last check</div>
                    <div class="mt-1 text-lg font-semibold">{{ $licenseConfig['last_check_at'] ?: '-' }}</div>
                </div>
                <div class="{{ $softPanel }}">
                    <div class="text-xs uppercase text-[var(--secondary-text)]">Check URL</div>
                    <div class="mt-1 break-all text-sm font-semibold">{{ $licenseConfig['check_url_configured'] ? $licenseConfig['check_url'] : 'Not configured' }}</div>
                </div>
                <div class="{{ $softPanel }}">
                    <div class="text-xs uppercase text-[var(--secondary-text)]">Request URL</div>
                    <div class="mt-1 break-all text-sm font-semibold">{{ $licenseConfig['request_demo_url'] ?: '-' }}</div>
                </div>
            </div>
        </section>

        @if (!$canManageLicense && !$isActive)
            <section class="{{ $panel }}">
                <x-developer-panel-title title="Activation Required" description="This device needs SparkPair approval before normal app access can continue." />
                <p class="text-sm text-[var(--secondary-text)]">
                    This device needs SparkPair approval before normal app access can continue. Please contact your administrator or developer to register/check this device.
                </p>
            </section>
        @endif

        @if ($canManageLicense && !$isActive)
            <section class="{{ $panel }}">
                <x-developer-panel-title title="Request Demo / Trial" description="Send business and device details to SparkPair for approval." />
                <form method="POST" action="{{ route('developer.license.request-demo') }}" class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    @csrf
                    <div>
                        <label class="mb-1 block text-sm font-medium">Business / customer name</label>
                        <input name="business_name" value="{{ old('business_name', $requestCache['business_name'] ?? '') }}" class="{{ $input }}" required>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">Owner name</label>
                        <input name="owner_name" value="{{ old('owner_name', $requestCache['owner_name'] ?? '') }}" class="{{ $input }}" required>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">Phone</label>
                        <input name="phone" value="{{ old('phone', $requestCache['phone'] ?? '') }}" class="{{ $input }}" required>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">Email optional</label>
                        <input type="email" name="email" value="{{ old('email', $requestCache['email'] ?? '') }}" class="{{ $input }}">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">City</label>
                        <input name="city" value="{{ old('city', $requestCache['city'] ?? '') }}" class="{{ $input }}" required>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">Request type</label>
                        <select name="request_type" class="{{ $input }}" required>
                            <option value="demo_trial" @selected($requestType === 'demo_trial')>Demo / trial request</option>
                            <option value="paid_activation" @selected($requestType === 'paid_activation')>Paid activation</option>
                        </select>
                    </div>
                    <div class="md:col-span-2">
                        <label class="mb-1 block text-sm font-medium">Address optional</label>
                        <textarea name="address" class="{{ $textarea }}">{{ old('address', $requestCache['address'] ?? '') }}</textarea>
                    </div>
                    <div class="flex flex-wrap gap-3 md:col-span-2">
                        <button type="submit" class="{{ $primaryButton }}">Request Demo / Trial</button>
                    </div>
                </form>
            </section>
        @endif

        @if ($canManageLicense)
            <section class="{{ $panel }}">
                <x-developer-panel-title title="Device Actions" description="Register, check, or refresh this installation approval." />
                <div class="flex flex-wrap gap-3">
                    <form method="POST" action="{{ route('developer.license.register') }}">
                        @csrf
                        <button type="submit" class="{{ $primaryButton }}">Activate Existing License / Register Device</button>
                    </form>
                    <form method="POST" action="{{ route('developer.license.check') }}">
                        @csrf
                        <button type="submit" class="{{ $secondaryButton }}">Check Status</button>
                    </form>
                    <form method="POST" action="{{ route('developer.license.check') }}">
                        @csrf
                        <button type="submit" class="{{ $secondaryButton }}">Refresh/Rebind License Approval</button>
                    </form>
                    <button type="button" class="{{ $secondaryButton }}" onclick="navigator.clipboard && navigator.clipboard.writeText(document.getElementById('license-install-id').innerText)">
                        Copy Install ID
                    </button>
                    <a href="{{ route('developer.audit-logs') }}" class="{{ $secondaryButton }}">Audit Logs</a>
                </div>
            </section>
        @endif

        @if ($canManageLicense)
            <section class="{{ $panel }}">
                <x-developer-panel-title title="Developer Maintenance" description="Recovery actions for migrated or restored local installs." />
                <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                    <p class="max-w-3xl text-sm text-[var(--secondary-text)]">
                        Run database migrations only after a verified update or restore. This does not edit license/device identity files.
                    </p>
                    <form method="POST" action="{{ route('developer.license.run-migrations') }}" class="space-y-3">
                        @csrf
                        <label class="flex items-center justify-between gap-3 rounded-xl border border-[var(--h-bg-color)] bg-[var(--h-bg-color)]/35 px-3 py-2 text-sm text-[var(--secondary-text)]">
                            <span>I understand this will run pending database migrations on this installation.</span>
                            <x-toggle-switch name="confirm_migrations" />
                        </label>
                        <button type="submit" class="{{ $secondaryButton }}">
                            Run Database Migrations
                        </button>
                    </form>
                </div>
            </section>
        @endif

        @if ($canManageLicense)
            <details class="{{ $panel }}">
                <summary class="cursor-pointer list-none">
                    <x-developer-panel-title title="License Diagnostics" description="Safe local diagnostics for developer/admin troubleshooting." />
                </summary>
                <div class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
                    @foreach (($diagnostics ?? []) as $key => $value)
                        <div class="{{ $softPanel }}">
                            <div class="text-xs uppercase text-[var(--secondary-text)]">{{ str_replace('_', ' ', $key) }}</div>
                            <div class="mt-1 break-all text-sm font-semibold">
                                @if (is_bool($value))
                                    {{ $value ? 'yes' : 'no' }}
                                @else
                                    {{ $value ?: '-' }}
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </details>
        @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
