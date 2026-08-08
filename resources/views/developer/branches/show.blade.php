@extends('app')

@section('title', 'Branch Details | ' . $client_company->name)

@section('content')
    @php
        $panel = 'bg-[var(--secondary-bg-color)] text-sm rounded-xl shadow border border-[var(--h-bg-color)] relative overflow-hidden';
        $input = 'w-full rounded-lg border border-[var(--h-bg-color)] bg-[var(--h-bg-color)] px-3 py-2 text-sm text-[var(--text-color)] outline-none focus:border-[var(--primary-color)]';
        $button = 'px-4 py-2 bg-[var(--primary-color)] text-[var(--text-color)] font-medium text-nowrap rounded-lg hover:bg-[var(--h-primary-color)] hover:scale-95 transition-all duration-300 ease-in-out cursor-pointer';
        $secondary = 'px-3 py-1.5 bg-[var(--h-bg-color)]/60 border border-[var(--h-bg-color)] text-[var(--secondary-text)] font-medium text-nowrap rounded-lg hover:border-[var(--primary-color)]/50 hover:bg-[var(--secondary-bg-color)] transition-all duration-300 ease-in-out cursor-pointer';
        $badge = 'inline-flex items-center rounded-xl border px-3 py-1.5 text-xs font-semibold';
        $toggle = 'inline-flex items-center gap-2 rounded-lg border border-[var(--h-bg-color)] bg-[var(--h-bg-color)]/55 px-3 py-2 text-xs text-[var(--secondary-text)]';
        $miniToggle = 'flex items-center justify-between gap-3 rounded-xl border border-[var(--h-bg-color)] bg-[var(--h-bg-color)]/35 px-3 py-2 text-xs text-[var(--secondary-text)] transition hover:border-[var(--primary-color)]/40';
        $moduleGroups = collect($moduleRegistry)
        ->groupBy(
            fn ($module) => $module['group'] ?? 'Other',
            true // Preserve original module keys
        );
        $branchModuleService = app(\App\Services\Branches\ModuleBranchService::class);
        $enabledCount = $moduleSettings->filter(fn ($setting) => $setting->branch_enabled && $setting->status === 'active')->count();
        $switchingCount = $moduleSettings->filter(fn ($setting) => $setting->allow_user_switching && $setting->status === 'active')->count();
        $filteringCount = $moduleSettings->filter(function ($setting) {
            $metadata = is_array($setting->metadata) ? $setting->metadata : [];
            return (bool) ($metadata['record_filtering_enabled'] ?? false);
        })->count();
        $moduleStatusOptions = [
            'active' => ['text' => 'Active'],
            'inactive' => ['text' => 'Inactive'],
        ];
        $roleOptions = collect($roleLabels)
            ->mapWithKeys(fn ($role) => [$role => ['text' => str_replace('_', ' ', ucfirst($role))]])
            ->all();
        $userOptions = $users
            ->mapWithKeys(fn ($user) => [$user->id => ['text' => ($user->name ?? $user->username) . ' (' . $user->role . ')']])
            ->all();
        $moduleOptions = collect($moduleLabels)
            ->mapWithKeys(fn ($label, $moduleKey) => [$moduleKey => ['text' => $label]])
            ->all();
    @endphp

    <div class="max-w-6xl mx-auto w-full">
        <x-search-header heading="Branch Details" link linkText="Back to Branches" linkHref="{{ route('developer.branches.index') }}" />
    </div>

    <section class="text-center mx-auto">
        <div class="show-box mx-auto w-[86%] h-[70vh] bg-[var(--secondary-bg-color)] border border-[var(--glass-border-color)]/20 rounded-xl shadow pt-8.5 relative">
            <x-form-title-bar title="Branch Details" />
            <div class="details h-full z-40">
                <div class="container-parent h-full">
                    <div class="card_container px-4 h-full flex flex-col">
                        <div class="overflow-y-auto grow my-scrollbar-2 space-y-4  pr-1 text-left">

                            <details class="{{ $panel }} group" open>
                                <summary class="flex cursor-pointer items-center justify-between gap-4 p-4 list-none transition hover:bg-[var(--h-bg-color)]/20">
                                    <div class="flex min-w-0 items-center gap-3">
                                        <span class="h-10 w-1.5 shrink-0 rounded-full bg-[var(--primary-color)]/80"></span>
                                        <div class="min-w-0">
                                            <h2 class="text-sm font-semibold uppercase tracking-wide">Business Identity</h2>
                                            <p class="text-xs text-[var(--secondary-text)]">Logo, branch display name, contact details and document identity.</p>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span class="rounded-xl border border-[var(--h-bg-color)] bg-[var(--h-bg-color)]/55 px-3 py-1.5 text-xs text-[var(--secondary-text)]">{{ $branch->is_main ? 'Main branch' : ucfirst($branch->status) }}</span>
                                    </div>
                                </summary>
                                <div class="mx-5 mb-5 rounded-2xl border border-[var(--h-bg-color)] bg-[var(--h-bg-color)]/25 p-5">
                                    <div class="grid grid-cols-1 gap-5 xl:grid-cols-[1fr_auto]">
                                        <div class="flex items-start gap-4">
                                            <div class="flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-xl border border-[var(--h-bg-color)] bg-white p-1">
                                                @if ($branch->logo_path)
                                                    <img src="{{ route('branch-logos.show', $branch) }}" alt="{{ $branch->name }}" class="max-h-full max-w-full object-contain" onerror="this.style.display='none'; this.nextElementSibling?.classList.remove('hidden');">
                                                    <span class="hidden text-lg font-semibold text-[var(--primary-color)]">{{ strtoupper(substr($branch->prefix ?: $branch->name, 0, 2)) }}</span>
                                                @else
                                                    <span class="text-lg font-semibold text-[var(--primary-color)]">{{ strtoupper(substr($branch->prefix ?: $branch->name, 0, 2)) }}</span>
                                                @endif
                                            </div>
                                            <div class="min-w-0">
                                                <div class="flex flex-wrap items-center gap-2">
                                                    <h2 class="text-lg font-semibold">{{ $branch->display_name ?: $branch->name }}</h2>
                                                    <span class="{{ $badge }} {{ $branch->status === 'active' ? 'border-[var(--border-success)] bg-[var(--bg-success)] text-[var(--text-success)]' : 'border-gray-600 bg-[var(--h-bg-color)] text-[var(--secondary-text)]' }}">
                                                        {{ $branch->is_main ? 'Main Branch' : ucfirst($branch->status) }}
                                                    </span>
                                                </div>
                                                <p class="mt-1 text-xs text-[var(--secondary-text)]">{{ $branch->code }} | Prefix {{ $branch->prefix ?: '-' }} | {{ $branch->city ?: '-' }}</p>
                                                <p class="mt-2 text-sm text-[var(--secondary-text)]">{{ $branch->address ?: 'No address saved.' }}</p>
                                                <div class="mt-4 grid grid-cols-1 gap-2 text-xs text-[var(--secondary-text)] md:grid-cols-2 xl:grid-cols-4">
                                                    <span>Phone: <strong class="text-[var(--text-color)]">{{ $branch->phone ?: '-' }}</strong></span>
                                                    <span>Email: <strong class="text-[var(--text-color)]">{{ $branch->email ?: '-' }}</strong></span>
                                                    <span>NTN/CNIC: <strong class="text-[var(--text-color)]">{{ $branch->ntn_cnic ?: '-' }}</strong></span>
                                                    <span>STRN/SNTN: <strong class="text-[var(--text-color)]">{{ $branch->strn_sntn ?: '-' }}</strong></span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="flex flex-wrap items-start justify-start gap-2 xl:justify-end">
                                            <a href="{{ route('developer.branches.edit', $branch) }}" class="{{ $secondary }}">Edit Branch</a>
                                            @if (!$branch->is_main)
                                                <form method="POST" action="{{ route('developer.branches.status', $branch) }}">
                                                    @csrf
                                                    @method('PATCH')
                                                    <input type="hidden" name="status" value="{{ $branch->status === 'active' ? 'inactive' : 'active' }}">
                                                    <button type="submit" class="{{ $secondary }}">{{ $branch->status === 'active' ? 'Deactivate' : 'Activate' }}</button>
                                                </form>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </details>

                            <details class="{{ $panel }} group" open>
                                <summary class="flex cursor-pointer items-center justify-between gap-4 p-4 list-none transition hover:bg-[var(--h-bg-color)]/20">
                                    <div class="flex min-w-0 items-center gap-3">
                                        <span class="h-10 w-1.5 shrink-0 rounded-full bg-[var(--primary-color)]/80"></span>
                                        <div class="min-w-0">
                                            <h2 class="text-sm font-semibold uppercase tracking-wide">Module Management</h2>
                                            <p class="text-xs text-[var(--secondary-text)]">Per-branch module settings are managed from one module-wise screen.</p>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span class="rounded-xl border border-[var(--h-bg-color)] bg-[var(--h-bg-color)]/55 px-3 py-1.5 text-xs text-[var(--secondary-text)]">{{ $enabledCount }} enabled</span>
                                        <span class="rounded-xl border border-[var(--h-bg-color)] bg-[var(--h-bg-color)]/55 px-3 py-1.5 text-xs text-[var(--secondary-text)]">{{ $switchingCount }} switchers</span>
                                    </div>
                                </summary>
                                <div class="border-t border-[var(--h-bg-color)] p-5">
                                    <div class="rounded-2xl border border-[var(--h-bg-color)] bg-[var(--h-bg-color)]/25 p-5">
                                        <div class="grid grid-cols-1 gap-4 lg:grid-cols-[1fr_auto] lg:items-center">
                                            <div>
                                                <h3 class="text-base font-semibold">Manage modules by module, not by branch</h3>
                                                <p class="mt-1 text-sm text-[var(--secondary-text)]">
                                                    Module settings still save per branch, but editing is now centralized. Open a module once and update all branch rows together.
                                                </p>
                                            </div>
                                            <a href="{{ route('developer.branches.modules.index') }}" class="{{ $button }}">Open Module Management</a>
                                        </div>
                                        <div class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-3">
                                            <div class="rounded-xl border border-[var(--h-bg-color)] bg-[var(--secondary-bg-color)] px-4 py-3">
                                                <div class="text-[11px] uppercase tracking-wide text-[var(--secondary-text)]">Enabled modules</div>
                                                <div class="mt-1 text-xl font-semibold">{{ $enabledCount }}</div>
                                            </div>
                                            <div class="rounded-xl border border-[var(--h-bg-color)] bg-[var(--secondary-bg-color)] px-4 py-3">
                                                <div class="text-[11px] uppercase tracking-wide text-[var(--secondary-text)]">Branch switchers</div>
                                                <div class="mt-1 text-xl font-semibold">{{ $switchingCount }}</div>
                                            </div>
                                            <div class="rounded-xl border border-[var(--h-bg-color)] bg-[var(--secondary-bg-color)] px-4 py-3">
                                                <div class="text-[11px] uppercase tracking-wide text-[var(--secondary-text)]">Record filters</div>
                                                <div class="mt-1 text-xl font-semibold">{{ $filteringCount }}</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <form id="branchModuleSettingsForm" method="POST" action="{{ route('developer.branches.modules') }}" class="hidden space-y-4 border-t border-[var(--h-bg-color)] p-5">
                                    @csrf
                                    <input type="hidden" name="branch_id" value="{{ $branch->id }}">
                                    <input type="hidden" name="modules_payload" id="branchModulesPayload">

                                    <div class="flex flex-col gap-2 rounded-2xl border border-[var(--h-bg-color)] bg-[var(--h-bg-color)]/20 p-3 sm:flex-row sm:items-center sm:justify-between">
                                        <x-input id="branchModuleSearch" name="branch_module_search" type="search" placeholder="Search modules..." autocomplete="off" class="min-w-[15rem]" />
                                        <button type="submit" form="branchModuleSettingsForm" class="{{ $button }}">Save Module Settings</button>
                                    </div>

                                    <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
                                        <div class="rounded-xl border border-[var(--h-bg-color)] bg-[var(--h-bg-color)]/35 px-4 py-3">
                                            <div class="text-[11px] uppercase tracking-wide text-[var(--secondary-text)]">Enabled modules</div>
                                            <div class="mt-1 text-xl font-semibold">{{ $enabledCount }}</div>
                                        </div>
                                        <div class="rounded-xl border border-[var(--h-bg-color)] bg-[var(--h-bg-color)]/35 px-4 py-3">
                                            <div class="text-[11px] uppercase tracking-wide text-[var(--secondary-text)]">Branch switchers</div>
                                            <div class="mt-1 text-xl font-semibold">{{ $switchingCount }}</div>
                                        </div>
                                        <div class="rounded-xl border border-[var(--h-bg-color)] bg-[var(--h-bg-color)]/35 px-4 py-3">
                                            <div class="text-[11px] uppercase tracking-wide text-[var(--secondary-text)]">Record filters</div>
                                            <div class="mt-1 text-xl font-semibold">{{ $filteringCount }}</div>
                                        </div>
                                    </div>

                                    <div class="rounded-xl border border-[var(--h-bg-color)] bg-[var(--h-bg-color)]/35 px-4 py-3">
                                        <div class="font-semibold">Quick rule</div>
                                        <p class="mt-1 text-xs text-[var(--secondary-text)]">Enable module + show switcher controls the UI. Filter records controls whether that module data becomes branch-wise.</p>
                                    </div>

                                    <div class="space-y-3">
                                        @foreach ($moduleGroups as $group => $modules)
                                            <details data-module-group class="rounded-2xl border border-[var(--h-bg-color)] bg-[var(--h-bg-color)]/20" @if($loop->first) open @endif>
                                                <summary class="flex cursor-pointer items-center justify-between gap-3 rounded-2xl px-4 py-3 list-none">
                                                    <span class="font-semibold">{{ $group }}</span>
                                                    <span data-module-group-count class="rounded-lg bg-[var(--secondary-bg-color)]/80 px-2.5 py-1 text-xs text-[var(--secondary-text)]">{{ $modules->count() }} modules</span>
                                                </summary>
                                                <div class="grid grid-cols-1 gap-3 border-t border-[var(--h-bg-color)] p-3 2xl:grid-cols-2">
                                                    @foreach ($modules as $moduleKey => $module)
                                                        @php
                                                            $setting = $moduleSettings->get($moduleKey);
                                                            $runtimeModule = $branchModuleService->runtimeModuleConfig($moduleKey, $branch->id);
                                                            $supportsSwitching = (bool) ($runtimeModule['supports_branch_selector'] ?? false);
                                                            $supportsFiltering = (bool) ($runtimeModule['record_filtering_enabled'] ?? $runtimeModule['supports_record_filtering'] ?? $runtimeModule['can_filter_records'] ?? false);
                                                            $hasBranchIdSupport = (bool) ($runtimeModule['has_branch_id_support'] ?? false);
                                                            $canBrand = (bool) ($runtimeModule['supports_branch_branding'] ?? $runtimeModule['can_use_branch_branding'] ?? false);
                                                            $canMulti = (bool) ($runtimeModule['supports_multi_branch_selector'] ?? false);
                                                            $canSerialPrefix = (bool) ($runtimeModule['supports_serial_prefix'] ?? $runtimeModule['supports_branch_serial_prefix'] ?? false);
                                                            $canDocPrefix = (bool) ($runtimeModule['supports_doc_identity_prefix'] ?? false);
                                                            $docIdentityPrefix = $runtimeModule['doc_identity_prefix'] ?? '';
                                                            $isSystemModule = (bool) ($runtimeModule['is_system_module'] ?? false);
                                                            $enabled = (bool) ($runtimeModule['branch_enabled'] ?? ($setting ? (bool) $setting->branch_enabled : (bool) $branch->is_main));
                                                            $switching = (bool) ($runtimeModule['supports_branch_selector'] ?? ($setting ? (bool) $setting->allow_user_switching : (bool) $branch->is_main));
                                                            $metadata = is_array($setting?->metadata) ? $setting->metadata : [];
                                                            $filtering = array_key_exists('record_filtering_enabled', $metadata)
                                                                ? (bool) $metadata['record_filtering_enabled']
                                                                : (bool) ($runtimeModule['record_filtering_enabled'] ?? $supportsFiltering);
                                                            $defaultOrderDiscount = max(0, min(100, (int) ($metadata['default_order_discount_percent'] ?? 0)));
                                                            $supportsDocumentOptions = in_array($moduleKey, ['orders', 'invoices'], true);
                                                            $discountDisabled = (bool) ($metadata['discount_disabled'] ?? false);
                                                            $documentNote = (string) ($metadata['document_note'] ?? '');
                                                            $displayLabel = (string) ($runtimeModule['label'] ?? $metadata['label_override'] ?? $module['label'] ?? $moduleKey);
                                                            $labelOverride = (string) ($metadata['label_override'] ?? '');
                                                            $filterWarning = $filtering && ! $hasBranchIdSupport;
                                                        @endphp

                                                        <div data-module-card data-module-key="{{ $moduleKey }}" data-module-search="{{ strtolower($displayLabel . ' ' . ($module['label'] ?? '') . ' ' . $moduleKey . ' ' . ($module['page_reference'] ?? '') . ' ' . ($module['group'] ?? '')) }}" class="rounded-2xl border border-[var(--h-bg-color)] bg-[var(--secondary-bg-color)] p-4 shadow-sm transition hover:-translate-y-0.5 hover:border-[var(--primary-color)]/50 hover:shadow">
                                                            <input type="hidden" name="modules[{{ $moduleKey }}][_present]" value="1">
                                                            @php
                                                                $modalId = 'branchModuleSettings_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $moduleKey);
                                                                $statusText = ($setting?->status ?? 'active') === 'active' ? 'Active' : 'Inactive';
                                                            @endphp
                                                            <div class="flex items-start justify-between gap-3">
                                                                <div class="min-w-0">
                                                                    <div class="flex flex-wrap items-center gap-2">
                                                                        <span class="h-2.5 w-2.5 shrink-0 rounded-full {{ $enabled && ($setting?->status ?? 'active') === 'active' ? 'bg-[var(--text-success)]' : 'bg-[var(--secondary-text)]' }}"></span>
                                                                        <div class="font-semibold leading-tight">{{ $displayLabel }}</div>
                                                                    </div>
                                                                    <div class="mt-1.5 flex flex-wrap items-center gap-1.5 pl-5 text-[11px] text-[var(--secondary-text)]">
                                                                        <span>{{ $moduleKey }}</span>
                                                                        <span class="text-[var(--h-bg-color)]">|</span>
                                                                        <span>{{ $module['page_reference'] ?? '-' }}</span>
                                                                    </div>
                                                                </div>
                                                                <button type="button" class="shrink-0 rounded-lg border border-[var(--h-bg-color)] bg-[var(--h-bg-color)]/45 px-3 py-1.5 text-xs font-semibold text-[var(--secondary-text)] transition hover:border-[var(--primary-color)]/60 hover:text-[var(--text-color)]" data-branch-module-open="{{ $modalId }}">Settings</button>
                                                            </div>

                                                            <div class="mt-4 grid grid-cols-1 gap-2 sm:grid-cols-2">
                                                                <label class="{{ $miniToggle }}">
                                                                    <span>Module active</span>
                                                                    <x-toggle-switch name="modules[{{ $moduleKey }}][branch_enabled]" :checked="$enabled" />
                                                                </label>

                                                                <label class="{{ $miniToggle }}">
                                                                    <span>Show switcher</span>
                                                                    <x-toggle-switch name="modules[{{ $moduleKey }}][allow_user_switching]" :checked="$switching" />
                                                                </label>

                                                                <label class="{{ $miniToggle }}">
                                                                    <span>Filter records</span>
                                                                    <x-toggle-switch name="modules[{{ $moduleKey }}][record_filtering_enabled]" :checked="$filtering" />
                                                                </label>

                                                                <label class="{{ $miniToggle }}">
                                                                    <span>Multi-branch</span>
                                                                    <x-toggle-switch name="modules[{{ $moduleKey }}][supports_multi_branch_selector]" :checked="$canMulti" />
                                                                </label>
                                                            </div>

                                                            @if ($filterWarning)
                                                                <div class="mt-3 rounded-lg border border-[var(--border-warning)]/60 bg-[var(--h-bg-color)] px-3 py-2 text-xs text-[var(--border-warning)]">
                                                                    Filtering is on, but this module still needs branch_id support.
                                                                </div>
                                                            @endif

                                                            <div id="{{ $modalId }}" class="fixed inset-0 z-[9999] hidden items-center justify-center bg-black/55 px-4 backdrop-blur-sm" data-branch-module-modal>
                                                                <div class="absolute inset-0" data-branch-module-close></div>
                                                                <div class="relative flex max-h-[82vh] w-full max-w-2xl flex-col overflow-hidden rounded-2xl border border-[var(--h-bg-color)] bg-[var(--secondary-bg-color)] shadow-2xl">
                                                                    <div class="flex items-start justify-between gap-4 border-b border-[var(--h-bg-color)] bg-[var(--secondary-bg-color)] px-5 py-4">
                                                                        <div class="flex min-w-0 items-start gap-3">
                                                                            <span class="mt-1 h-10 w-1.5 shrink-0 rounded-full bg-[var(--primary-color)]/80"></span>
                                                                            <div class="min-w-0">
                                                                                <h3 class="text-base font-semibold">{{ $displayLabel }}</h3>
                                                                                <p class="mt-1 text-xs text-[var(--secondary-text)]">{{ $moduleKey }} | {{ $module['page_reference'] ?? '-' }}</p>
                                                                            </div>
                                                                        </div>
                                                                        <button type="button" class="rounded-lg px-2 py-1 text-[var(--secondary-text)] hover:bg-[var(--h-bg-color)]" data-branch-module-close aria-label="Close module settings">
                                                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-5 w-5">
                                                                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                                                            </svg>
                                                                        </button>
                                                                    </div>
                                                                    <div class="min-h-0 overflow-y-auto my-scrollbar-2 bg-[var(--secondary-bg-color)] p-5">
                                                                        <input type="hidden" name="modules[{{ $moduleKey }}][_advanced_present]" value="1">
                                                                        <div class="space-y-4">
                                                                            <section class="rounded-2xl border border-[var(--h-bg-color)] bg-[var(--h-bg-color)]/20 p-4">
                                                                                <div class="mb-3">
                                                                                    <h4 class="text-sm font-semibold">Display</h4>
                                                                                    <p class="text-xs text-[var(--secondary-text)]">Optional name shown in developer controls and access selectors.</p>
                                                                                </div>
                                                                                <x-input
                                                                                    name="modules[{{ $moduleKey }}][label_override]"
                                                                                    id="module_label_override_{{ $moduleKey }}"
                                                                                    :value="$labelOverride"
                                                                                    placeholder="{{ $module['label'] ?? $moduleKey }}"
                                                                                />
                                                                            </section>

                                                                            <section class="rounded-2xl border border-[var(--h-bg-color)] bg-[var(--h-bg-color)]/20 p-4">
                                                                                <div class="mb-3 flex items-center justify-between gap-3">
                                                                                    <div>
                                                                                        <h4 class="text-sm font-semibold">Capabilities</h4>
                                                                                        <p class="text-xs text-[var(--secondary-text)]">Technical options used by branch filtering and document identity.</p>
                                                                                    </div>
                                                                                    <div class="w-40">
                                                                                        <x-select
                                                                                            name="modules[{{ $moduleKey }}][status]"
                                                                                            id="module_status_{{ $moduleKey }}"
                                                                                            :options="$moduleStatusOptions"
                                                                                            :value="$setting?->status ?? 'active'"
                                                                                        />
                                                                                    </div>
                                                                                </div>
                                                                                <div class="grid grid-cols-1 gap-2 md:grid-cols-3">
                                                                                    <label class="{{ $miniToggle }}">
                                                                                        <span>branch_id support</span>
                                                                                        <x-toggle-switch name="modules[{{ $moduleKey }}][has_branch_id_support]" :checked="$hasBranchIdSupport" />
                                                                                    </label>
                                                                                    <label class="{{ $miniToggle }}">
                                                                                        <span>Branch branding</span>
                                                                                        <x-toggle-switch name="modules[{{ $moduleKey }}][supports_branch_branding]" :checked="$canBrand" />
                                                                                    </label>
                                                                                    <label class="{{ $miniToggle }}">
                                                                                        <span>Serial prefix</span>
                                                                                        <x-toggle-switch name="modules[{{ $moduleKey }}][supports_branch_serial_prefix]" :checked="$canSerialPrefix" />
                                                                                    </label>
                                                                                </div>
                                                                            </section>

                                                                            <section class="rounded-2xl border border-[var(--h-bg-color)] bg-[var(--h-bg-color)]/20 p-4">
                                                                                <div class="mb-3">
                                                                                    <h4 class="text-sm font-semibold">Document options</h4>
                                                                                    <p class="text-xs text-[var(--secondary-text)]">Optional prefixes and notes used on branch documents.</p>
                                                                                </div>
                                                                                <label class="{{ $miniToggle }} mb-3">
                                                                                    <span>Document identity prefix</span>
                                                                                    <x-toggle-switch name="modules[{{ $moduleKey }}][supports_doc_identity_prefix]" :checked="$canDocPrefix" />
                                                                                </label>
                                                                                <x-input
                                                                                    name="modules[{{ $moduleKey }}][doc_identity_prefix]"
                                                                                    id="doc_identity_prefix_{{ $moduleKey }}"
                                                                                    :value="$docIdentityPrefix"
                                                                                    placeholder="Optional prefix"
                                                                                    uppercased
                                                                                />
                                                                            </section>

                                                                            @if ($moduleKey === 'orders' || $supportsDocumentOptions)
                                                                                <section class="rounded-2xl border border-[var(--h-bg-color)] bg-[var(--h-bg-color)]/20 p-4">
                                                                                    <div class="mb-3">
                                                                                        <h4 class="text-sm font-semibold">Print and totals</h4>
                                                                                        <p class="text-xs text-[var(--secondary-text)]">Branch-specific order/invoice print behavior.</p>
                                                                                    </div>
                                                                                    <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                                                                                        @if ($moduleKey === 'orders')
                                                                                            <div>
                                                                                                <span class="mb-1 block text-xs text-[var(--secondary-text)]">Default order discount (%)</span>
                                                                                                <x-input
                                                                                                    name="modules[{{ $moduleKey }}][default_order_discount_percent]"
                                                                                                    id="default_order_discount_percent_{{ $moduleKey }}"
                                                                                                    type="number"
                                                                                                    :value="$defaultOrderDiscount"
                                                                                                    min="0"
                                                                                                    max="100"
                                                                                                    validateMin
                                                                                                    validateMax
                                                                                                />
                                                                                            </div>
                                                                                        @endif
                                                                                        @if ($supportsDocumentOptions)
                                                                                            <div class="md:col-span-2">
                                                                                                <label class="{{ $miniToggle }} mb-3">
                                                                                                    <span>Hide discount and gross amount in print</span>
                                                                                                    <x-toggle-switch name="modules[{{ $moduleKey }}][discount_disabled]" :checked="$discountDisabled" />
                                                                                                </label>
                                                                                                <textarea name="modules[{{ $moduleKey }}][document_note]" rows="3" maxlength="300" placeholder="Optional note shown above totals" class="{{ $input }}">{{ $documentNote }}</textarea>
                                                                                            </div>
                                                                                        @endif
                                                                                    </div>
                                                                                </section>
                                                                            @endif

                                                                            <section class="rounded-2xl border border-[var(--h-bg-color)] bg-[var(--h-bg-color)]/20 px-4 py-3 text-xs text-[var(--secondary-text)]">
                                                                                {{ $module['notes'] ?? 'No extra notes for this module.' }}
                                                                                @if (!empty($module['dependencies']))
                                                                                    <div class="mt-1 text-[var(--border-warning)]">{{ $module['dependencies'] }}</div>
                                                                                @endif
                                                                            </section>
                                                                        </div>
                                                                    </div>
                                                                    <div class="flex justify-end gap-2 border-t border-[var(--h-bg-color)] bg-[var(--secondary-bg-color)] px-5 py-4">
                                                                        <button type="button" class="{{ $secondary }}" data-branch-module-close>Close</button>
                                                                        <button type="button" class="{{ $button }}" data-branch-module-save>Save Module Settings</button>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </details>
                                        @endforeach
                                    </div>

                                    <div class="sticky bottom-0 z-10 flex justify-end border-t border-[var(--h-bg-color)] bg-[var(--secondary-bg-color)]/95 py-3">
                                        <button type="submit" class="{{ $button }}">Save Module Settings</button>
                                    </div>
                                </form>
                            </details>

                            <details class="{{ $panel }} group">
                                <summary class="flex cursor-pointer items-center justify-between gap-4 p-4 list-none transition hover:bg-[var(--h-bg-color)]/20">
                                    <div class="flex min-w-0 items-center gap-3">
                                        <span class="h-10 w-1.5 shrink-0 rounded-full bg-[var(--primary-color)]/80"></span>
                                        <div class="min-w-0">
                                            <h2 class="text-sm font-semibold uppercase tracking-wide">Role / Permission Management</h2>
                                            <p class="text-xs text-[var(--secondary-text)]">Manage role/user permissions from the central system page.</p>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span class="rounded-xl border border-[var(--h-bg-color)] bg-[var(--h-bg-color)]/55 px-3 py-1.5 text-xs text-[var(--secondary-text)]">{{ $accessRows->count() }} access rows</span>
                                    </div>
                                </summary>
                                <div class="border-t border-[var(--h-bg-color)] p-5">
                                    <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-[var(--h-bg-color)] bg-[var(--secondary-bg-color)] p-4 shadow-sm">
                                        <div>
                                            <h3 class="text-sm font-semibold">Open central permissions</h3>
                                            <p class="text-xs text-[var(--secondary-text)]">Role, user, branch, module aur edit/delete permissions ek hi page se manage karen.</p>
                                        </div>
                                        <a href="{{ route('developer.branches.access.index') }}" class="{{ $button }}">Role / Permissions</a>
                                    </div>
                                </div>
                                <div class="hidden grid-cols-1 gap-5 border-t border-[var(--h-bg-color)] p-5 xl:grid-cols-[0.9fr_1.1fr]">
                                    <form method="POST" action="{{ route('developer.branches.access') }}" class="space-y-4 rounded-2xl border border-[var(--h-bg-color)] bg-[var(--secondary-bg-color)] p-4 shadow-sm">
                                        @csrf
                                        <input type="hidden" name="branch_id" value="{{ $branch->id }}">
                                        <div>
                                            <h3 class="text-sm font-semibold">New access rule</h3>
                                            <p class="text-xs text-[var(--secondary-text)]">Choose a role or user, then select the permissions for this branch.</p>
                                        </div>
                                        <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                                            <x-select
                                                label="Role"
                                                name="role"
                                                id="branch_access_role"
                                                :options="$roleOptions"
                                                :value="$roleLabels[0] ?? 'developer'"
                                            />
                                            <x-select
                                                label="User"
                                                name="user_id"
                                                id="branch_access_user"
                                                :options="$userOptions"
                                                showDefault
                                            />
                                            <x-select
                                                label="Module"
                                                name="module_key"
                                                id="branch_access_module"
                                                :options="$moduleOptions"
                                                showDefault
                                                class="md:col-span-2"
                                            />
                                        </div>
                                        <div class="grid grid-cols-2 gap-2 text-sm md:grid-cols-3">
                                            @foreach (['can_view' => 'View', 'can_create' => 'Create', 'can_update' => 'Update', 'can_delete' => 'Delete', 'can_switch' => 'Switch', 'can_manage' => 'Manage'] as $field => $label)
                                                <label class="flex items-center justify-between gap-3 rounded-xl border border-[var(--h-bg-color)] bg-[var(--h-bg-color)]/35 px-3 py-2 text-xs text-[var(--secondary-text)] transition hover:border-[var(--primary-color)]/40">
                                                    <span>{{ $label }}</span>
                                                    <x-toggle-switch :name="$field" :checked="true" />
                                                </label>
                                            @endforeach
                                        </div>
                                        <button type="submit" class="{{ $button }}">Save Permissions</button>
                                    </form>

                                    <div class="overflow-hidden rounded-2xl border border-[var(--h-bg-color)] bg-[var(--secondary-bg-color)] shadow-sm">
                                        <div class="flex items-center justify-between gap-3 border-b border-[var(--h-bg-color)] px-4 py-3">
                                            <div>
                                                <h3 class="text-sm font-semibold">Current access</h3>
                                                <p class="text-xs text-[var(--secondary-text)]">Existing branch permissions for roles and users.</p>
                                            </div>
                                            <span class="rounded-xl bg-[var(--h-bg-color)]/60 px-3 py-1 text-xs text-[var(--secondary-text)]">{{ $accessRows->count() }} rows</span>
                                        </div>
                                        <div class="max-h-80 space-y-2 overflow-y-auto p-3 my-scrollbar-2">
                                            @forelse ($accessRows as $row)
                                                <div class="rounded-2xl border border-[var(--h-bg-color)] bg-[var(--h-bg-color)]/20 px-4 py-3 text-xs">
                                                    <div class="flex flex-wrap items-start justify-between gap-3">
                                                        <div>
                                                            <div class="font-semibold">{{ $row->user ? (($row->user->name ?? $row->user->username) . ' (user)') : ($row->role ?: '-') }}</div>
                                                            <div class="mt-1 text-[var(--secondary-text)]">{{ $row->module_key ? ($moduleLabels[$row->module_key] ?? $row->module_key) : 'All modules' }}</div>
                                                        </div>
                                                        <div class="flex max-w-md flex-wrap justify-end gap-1.5 text-[var(--secondary-text)]">
                                                            @foreach ([
                                                                $row->can_view ? 'view' : null,
                                                                $row->can_create ? 'create' : null,
                                                                $row->can_update ? 'update' : null,
                                                                $row->can_delete ? 'delete' : null,
                                                                $row->can_switch ? 'switch' : null,
                                                                $row->can_manage ? 'manage' : null,
                                                            ] as $permission)
                                                                @if ($permission)
                                                                    <span class="rounded-lg bg-[var(--secondary-bg-color)] px-2 py-0.5">{{ $permission }}</span>
                                                                @endif
                                                            @endforeach
                                                            @unless ($row->can_view || $row->can_create || $row->can_update || $row->can_delete || $row->can_switch || $row->can_manage)
                                                                <span>-</span>
                                                            @endunless
                                                        </div>
                                                    </div>
                                                </div>
                                            @empty
                                                <div class="p-6 text-center text-sm text-[var(--secondary-text)]">No access rows yet.</div>
                                            @endforelse
                                        </div>
                                    </div>
                                </div>
                            </details>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection

@push('page-scripts')
    <script>
        (() => {
            const search = document.getElementById('branchModuleSearch');
            const normalize = (value) => String(value || '').toLowerCase().trim();

            if (search) {
                document.querySelectorAll('[data-module-group-count]').forEach((counter) => {
                    counter.dataset.originalText = counter.textContent;
                });

                search.addEventListener('input', () => {
                    const term = normalize(search.value);

                    document.querySelectorAll('[data-module-group]').forEach((group) => {
                        let visibleCount = 0;

                        group.querySelectorAll('[data-module-card]').forEach((card) => {
                            const visible = !term || normalize(card.dataset.moduleSearch).includes(term);
                            card.classList.toggle('hidden', !visible);
                            if (visible) visibleCount += 1;
                        });

                        group.classList.toggle('hidden', visibleCount === 0);
                        const counter = group.querySelector('[data-module-group-count]');
                        if (counter) {
                            counter.textContent = term ? `${visibleCount} shown` : (counter.dataset.originalText || counter.textContent);
                        }

                        if (term && visibleCount > 0) {
                            group.open = true;
                        }
                    });
                });
            }

            const portalModal = (modal) => {
                if (!modal || modal.dataset.portaled === '1') return;
                const placeholder = document.createComment(`branch-module-modal:${modal.id}`);
                modal.parentNode.insertBefore(placeholder, modal);
                modal._branchModulePlaceholder = placeholder;
                document.body.appendChild(modal);
                modal.dataset.portaled = '1';
            };

            const restoreModal = (modal) => {
                if (!modal || modal.dataset.portaled !== '1') return;
                const placeholder = modal._branchModulePlaceholder;
                if (placeholder?.parentNode) {
                    placeholder.parentNode.insertBefore(modal, placeholder);
                    placeholder.remove();
                }
                modal.dataset.portaled = '';
                modal._branchModulePlaceholder = null;
            };

            const closeModal = (modal) => {
                if (!modal) return;
                modal.classList.add('hidden');
                modal.classList.remove('flex');
                restoreModal(modal);
                document.body.classList.remove('overflow-hidden');
            };

            const setModalFieldsDisabled = (modal, disabled) => {
                modal?.querySelectorAll('input, select, textarea').forEach((field) => {
                    field.disabled = disabled;
                });
            };

            document.querySelectorAll('[data-branch-module-modal]').forEach((modal) => {
                setModalFieldsDisabled(modal, true);
            });

            document.querySelectorAll('[data-branch-module-open]').forEach((button) => {
                button.addEventListener('click', () => {
                    const modal = document.getElementById(button.dataset.branchModuleOpen);
                    if (!modal) return;
                    setModalFieldsDisabled(modal, false);
                    portalModal(modal);
                    modal.classList.remove('hidden');
                    modal.classList.add('flex');
                    document.body.classList.add('overflow-hidden');
                    modal.querySelector('input:not([type="hidden"]), select, textarea, button')?.focus();
                });
            });

            document.querySelectorAll('[data-branch-module-close]').forEach((button) => {
                button.addEventListener('click', () => closeModal(button.closest('[data-branch-module-modal]')));
            });

            document.querySelectorAll('[data-branch-module-save]').forEach((button) => {
                button.addEventListener('click', () => {
                    const modal = button.closest('[data-branch-module-modal]');
                    restoreModal(modal);
                    document.body.classList.remove('overflow-hidden');
                    document.getElementById('branchModuleSettingsForm')?.requestSubmit();
                });
            });

            const moduleFieldName = (name) => {
                const match = String(name || '').match(/^modules\[[^\]]+\]\[([^\]]+)\]$/);
                return match ? match[1] : null;
            };

            const moduleInput = (card, fieldName) => {
                return card?.querySelector(`[name^="modules["][name$="[${fieldName}]"]`);
            };

            const syncToggleVisual = (input) => {
                const toggle = input?.closest('.app-toggle');
                if (!toggle) return;
                toggle.classList.toggle('is-checked', Boolean(input.checked));
                toggle.setAttribute('aria-checked', input.checked ? 'true' : 'false');
            };

            document.querySelectorAll('.app-toggle-input').forEach((input) => {
                syncToggleVisual(input);
                input.addEventListener('change', () => syncToggleVisual(input));
            });

            document.querySelectorAll('[data-module-card]').forEach((card) => {
                const switcherInput = moduleInput(card, 'allow_user_switching');
                const multiInput = moduleInput(card, 'supports_multi_branch_selector');

                multiInput?.addEventListener('change', () => {
                    if (!multiInput.checked) return;
                    if (switcherInput) {
                        switcherInput.checked = true;
                        syncToggleVisual(switcherInput);
                    }
                });
            });

            const collectModuleSettingsPayload = (form) => {
                const payload = {};

                form.querySelectorAll('[data-module-card]').forEach((card) => {
                    const moduleKey = card.dataset.moduleKey;
                    if (!moduleKey) return;

                    payload[moduleKey] = { _present: '1' };

                    card.querySelectorAll('[name^="modules["]:not(:disabled)').forEach((field) => {
                        const fieldName = moduleFieldName(field.name);
                        if (!fieldName || fieldName === '_present') return;

                        if (field.type === 'checkbox') {
                            payload[moduleKey][fieldName] = field.checked ? '1' : '0';
                            return;
                        }

                        payload[moduleKey][fieldName] = field.value ?? '';
                    });
                });

                return payload;
            };

            const moduleForm = document.getElementById('branchModuleSettingsForm');
            moduleForm?.addEventListener('submit', () => {
                const payloadField = document.getElementById('branchModulesPayload');
                if (!payloadField) return;

                payloadField.value = JSON.stringify(collectModuleSettingsPayload(moduleForm));

                moduleForm.querySelectorAll('[name^="modules["]').forEach((field) => {
                    field.disabled = true;
                });
            });

            document.addEventListener('keydown', (event) => {
                if (event.key !== 'Escape') return;
                closeModal(document.querySelector('[data-branch-module-modal]:not(.hidden)'));
            });
        })();
    </script>
@endpush
