@extends('app')

@section('title', 'Branch Module Management | ' . $client_company->name)

@section('content')
    @php
        $selectedModule = $selectedModule ?? [];
        $globalSetting = $settings->get('global');
        $globalMetadata = is_array($globalSetting?->metadata) ? $globalSetting->metadata : [];
        $selectedLabel = $globalMetadata['label_override'] ?? ($selectedModule['label'] ?? ($moduleLabels[$selectedModuleKey] ?? $selectedModuleKey));
        $input = 'w-full rounded-lg border border-gray-600 bg-[var(--secondary-bg-color)] px-3 py-2 text-sm text-[var(--text-color)] outline-none transition focus:border-[var(--primary-color)]';
        $button = 'px-4 py-2 bg-[var(--primary-color)] text-white font-medium text-nowrap rounded-lg hover:bg-[var(--h-primary-color)] hover:scale-95 transition-all duration-300 ease-in-out cursor-pointer';
        $secondary = 'px-3 py-1.5 bg-[var(--h-bg-color)]/60 border border-[var(--h-bg-color)] text-[var(--secondary-text)] font-medium text-nowrap rounded-lg hover:border-[var(--primary-color)]/50 hover:bg-[var(--secondary-bg-color)] transition-all duration-300 ease-in-out cursor-pointer';
        $miniToggle = 'flex items-center justify-between gap-3 rounded-xl border border-[var(--h-bg-color)] bg-[var(--h-bg-color)]/35 px-3 py-2 text-xs text-[var(--secondary-text)] transition hover:border-[var(--primary-color)]/40';
        $moduleOptions = collect($moduleRegistry)
            ->mapWithKeys(fn ($module, $moduleKey) => [$moduleKey => ['text' => ($moduleLabels[$moduleKey] ?? $module['label'] ?? $moduleKey)]])
            ->all();
        $branchOptions = $branches
            ->mapWithKeys(fn ($branch) => [$branch->id => ['text' => ($branch->display_name ?: $branch->name) . ($branch->is_main ? ' (Main)' : '')]])
            ->all();
        $supportsDocumentOptions = in_array($selectedModuleKey, ['orders', 'invoices'], true);
        $isRequiredSystemModule = in_array($selectedModuleKey, ['home', 'dashboard'], true);
    @endphp

    <div class="w-[80%] mx-auto">
        <x-search-header heading="Branch Module Management" link linkText="Back to Settings" linkHref="{{ route('developer.settings') }}" />
    </div>

    <section class="text-center mx-auto">
        <div class="show-box mx-auto w-[80%] h-[70vh] bg-[var(--secondary-bg-color)] border border-[var(--glass-border-color)]/20 rounded-xl shadow pt-8.5 relative">
            <x-form-title-bar title="Branch Module Management" />

            <form method="POST" action="{{ route('developer.branches.modules.manage') }}" class="details h-full z-40">
                @csrf
                <div class="container-parent h-full">
                    <div class="card_container px-3 h-full flex flex-col">
                        <div class="mt-4 grid grid-cols-1 gap-3 rounded-2xl border border-[var(--h-bg-color)] bg-[var(--h-bg-color)]/20 p-4 text-left xl:grid-cols-[18rem_1fr_auto] xl:items-end">
                            <div>
                                <x-select
                                    label="Module"
                                    name="module_key"
                                    id="branch_module_picker"
                                    :options="$moduleOptions"
                                    :value="$selectedModuleKey"
                                    onchange="branchModuleChanged(this)"
                                />
                            </div>
                            <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                                <div>
                                    <x-input
                                        label="Display name"
                                        name="label_override"
                                        id="module_label_override"
                                        :value="$globalMetadata['label_override'] ?? ''"
                                        placeholder="{{ $selectedModule['label'] ?? $selectedModuleKey }}"
                                    />
                                </div>
                                <div>
                                    <x-input
                                        label="Effect / dependency note"
                                        name="impact_note"
                                        id="module_impact_note"
                                        :value="$globalMetadata['impact_note'] ?? ''"
                                        placeholder="{{ $selectedModule['dependencies'] ?? $selectedModule['notes'] ?? 'Optional impact note' }}"
                                    />
                                </div>
                            </div>
                            <button type="submit" class="{{ $button }}">Save</button>
                        </div>

                        <div class="mt-3 grid grid-cols-1 gap-2 rounded-xl border {{ $clientModuleEnabled ? 'border-[var(--h-bg-color)] bg-[var(--h-bg-color)]/25' : 'border-[var(--border-error)]/50 bg-[var(--bg-error)]/20' }} px-4 py-3 text-left md:grid-cols-2 xl:grid-cols-[1fr_1fr_auto]">
                            <label class="{{ $miniToggle }} {{ $clientModuleEnabled ? '' : 'border-[var(--border-error)]/60' }}">
                                <span>Module enabled for this client</span>
                                <x-toggle-switch name="module_enabled" :checked="$clientModuleEnabled || $isRequiredSystemModule" :disabled="$isRequiredSystemModule" />
                            </label>
                            <label class="{{ $miniToggle }}">
                                <span>Show in sidebar</span>
                                <x-toggle-switch name="visible_in_sidebar" :checked="(bool) ($clientModuleState['visible_in_sidebar'] ?? true)" />
                            </label>
                            <div class="rounded-xl border border-[var(--h-bg-color)] bg-[var(--secondary-bg-color)] px-3 py-2 text-xs text-[var(--secondary-text)]">
                                <span class="font-semibold {{ $clientModuleEnabled ? 'text-[var(--border-success)]' : 'text-[var(--border-error)]' }}">
                                    {{ $clientModuleEnabled ? 'Enabled' : 'Disabled for client' }}
                                </span>
                                <span class="mx-1">|</span>
                                <span>{{ $isRequiredSystemModule ? 'Required system page cannot be disabled.' : ($clientModuleEnabled ? 'Module can be used where branch settings allow it.' : 'No user can access this module until it is enabled again.') }}</span>
                            </div>
                        </div>

                        <div class="mt-3 rounded-lg border border-[var(--h-bg-color)] bg-[var(--h-bg-color)]/25 px-4 py-2 text-left text-xs text-[var(--secondary-text)]">
                            <span class="font-semibold text-[var(--text-color)]">{{ $selectedLabel }}</span>
                            <span class="mx-1">|</span>
                            <span>{{ $selectedModuleKey }}</span>
                            <span class="mx-1">|</span>
                            <span>{{ $globalMetadata['impact_note'] ?? $selectedModule['dependencies'] ?? $selectedModule['notes'] ?? 'No dependency note configured for this module.' }}</span>
                        </div>

                        <div class="overflow-y-auto grow my-scrollbar-2">
                            <div class="grid grid-cols-1 gap-3 py-3 xl:grid-cols-2">
                                @foreach ($branches as $branch)
                                    @php
                                        $setting = $settings->get($branch->id);
                                        $runtime = app(\App\Services\Branches\ModuleBranchService::class)->runtimeModuleConfig($selectedModuleKey, $branch->id);
                                        $metadata = is_array($setting?->metadata) ? $setting->metadata : [];
                                        $checked = fn ($key, $fallback = false) => (bool) ($metadata[$key] ?? $runtime[$key] ?? $fallback);
                                        $value = fn ($key, $fallback = '') => $metadata[$key] ?? $runtime[$key] ?? $fallback;
                                        $docIdentityPrefix = (string) $value('doc_identity_prefix', '');
                                        $documentNote = (string) $value('document_note', '');
                                        $defaultOrderDiscount = max(0, min(100, (int) $value('default_order_discount_percent', 0)));
                                        $discountDisabled = (bool) $value('discount_disabled', false);
                                        $rowName = "branches[{$branch->id}]";
                                        $modalId = 'branchModuleBranch_' . $branch->id;
                                    @endphp
                                    <article class="rounded-2xl border border-[var(--h-bg-color)] bg-[var(--h-bg-color)]/20 p-4 text-left shadow-sm transition hover:-translate-y-0.5 hover:border-[var(--primary-color)]/50 hover:bg-[var(--secondary-bg-color)]">
                                        <div class="flex items-start justify-between gap-3">
                                            <div class="min-w-0">
                                                <div class="truncate text-sm font-semibold">{{ $branch->display_name ?: $branch->name }}</div>
                                                <div class="mt-1 text-[11px] text-[var(--secondary-text)]">{{ $branch->is_main ? 'Main Branch' : ($branch->code ?: 'Branch') }}</div>
                                            </div>
                                            <button type="button" class="{{ $secondary }}" data-branch-module-modal-open="{{ $modalId }}">Settings</button>
                                        </div>

                                        <div class="mt-4 grid grid-cols-1 gap-2 sm:grid-cols-2">
                                            <label class="{{ $miniToggle }}"><span>Active</span><x-toggle-switch name="{{ $rowName }}[branch_enabled]" :checked="(bool) ($setting?->branch_enabled ?? false)" /></label>
                                            <label class="{{ $miniToggle }}"><span>Switcher</span><x-toggle-switch name="{{ $rowName }}[allow_user_switching]" :checked="(bool) ($setting?->allow_user_switching ?? false)" /></label>
                                            <label class="{{ $miniToggle }}"><span>Multi</span><x-toggle-switch name="{{ $rowName }}[supports_multi_branch_selector]" :checked="$checked('supports_multi_branch_selector')" /></label>
                                            <label class="{{ $miniToggle }}"><span>Filter records</span><x-toggle-switch name="{{ $rowName }}[record_filtering_enabled]" :checked="$checked('record_filtering_enabled')" /></label>
                                        </div>

                                        <div class="mt-3 grid grid-cols-2 gap-2 text-[11px] text-[var(--secondary-text)]">
                                            <span>branch_id: <strong class="text-[var(--text-color)]">{{ $checked('has_branch_id_support') ? 'Yes' : 'No' }}</strong></span>
                                            <span>Status: <strong class="text-[var(--text-color)]">{{ ucfirst($setting?->status ?? 'active') }}</strong></span>
                                        </div>

                                        <div id="{{ $modalId }}" class="fixed inset-0 z-[9999] hidden items-center justify-center bg-black/55 px-4 backdrop-blur-sm" data-branch-module-modal>
                                            <div class="absolute inset-0" data-branch-module-modal-close></div>
                                            <div class="relative flex max-h-[82vh] w-full max-w-2xl flex-col overflow-hidden rounded-2xl border border-[var(--h-bg-color)] bg-[var(--secondary-bg-color)] shadow-2xl">
                                                <div class="flex items-start justify-between gap-4 border-b border-[var(--h-bg-color)] px-5 py-4">
                                                    <div>
                                                        <h3 class="text-base font-semibold">{{ $branch->display_name ?: $branch->name }}</h3>
                                                        <p class="mt-1 text-xs text-[var(--secondary-text)]">{{ $selectedLabel }} | {{ $selectedModuleKey }}</p>
                                                    </div>
                                                    <button type="button" class="rounded-lg px-2 py-1 text-[var(--secondary-text)] hover:bg-[var(--h-bg-color)]" data-branch-module-modal-close aria-label="Close">
                                                        <i class="fas fa-xmark"></i>
                                                    </button>
                                                </div>
                                                <div class="min-h-0 overflow-y-auto p-5 my-scrollbar-2">
                                                    <div class="space-y-4">
                                                        <section class="rounded-2xl border border-[var(--h-bg-color)] bg-[var(--h-bg-color)]/20 p-4">
                                                            <div class="mb-3">
                                                                <h4 class="text-sm font-semibold">Capabilities</h4>
                                                                <p class="text-xs text-[var(--secondary-text)]">Technical options used by branch filtering, switching and document identity.</p>
                                                            </div>
                                                            <div class="grid grid-cols-1 gap-2 md:grid-cols-2">
                                                                <label class="{{ $miniToggle }}"><span>branch_id support</span><x-toggle-switch name="{{ $rowName }}[has_branch_id_support]" :checked="$checked('has_branch_id_support')" /></label>
                                                                <label class="{{ $miniToggle }}"><span>Branch branding</span><x-toggle-switch name="{{ $rowName }}[supports_branch_branding]" :checked="$checked('supports_branch_branding', $checked('can_use_branch_branding'))" /></label>
                                                                <label class="{{ $miniToggle }}"><span>Serial prefix</span><x-toggle-switch name="{{ $rowName }}[supports_branch_serial_prefix]" :checked="$checked('supports_branch_serial_prefix', $checked('supports_serial_prefix'))" /></label>
                                                                <label class="{{ $miniToggle }}"><span>Document prefix</span><x-toggle-switch name="{{ $rowName }}[supports_doc_identity_prefix]" :checked="$checked('supports_doc_identity_prefix')" /></label>
                                                            </div>
                                                        </section>

                                                        <section class="rounded-2xl border border-[var(--h-bg-color)] bg-[var(--h-bg-color)]/20 p-4">
                                                            <div class="mb-3">
                                                                <h4 class="text-sm font-semibold">Defaults</h4>
                                                                <p class="text-xs text-[var(--secondary-text)]">Branch defaults and module status for this branch.</p>
                                                            </div>
                                                            <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                                                                <div>
                                                                    <x-select
                                                                        label="Default branch"
                                                                        name="{{ $rowName }}[default_branch_id]"
                                                                        id="default_branch_{{ $selectedModuleKey }}_{{ $branch->id }}"
                                                                        :options="$branchOptions"
                                                                        :value="$setting?->default_branch_id"
                                                                        showDefault
                                                                    />
                                                                </div>
                                                                <div>
                                                                    <x-select
                                                                        label="Status"
                                                                        name="{{ $rowName }}[status]"
                                                                        id="branch_module_status_{{ $selectedModuleKey }}_{{ $branch->id }}"
                                                                        :options="['active' => ['text' => 'Active'], 'inactive' => ['text' => 'Inactive']]"
                                                                        :value="$setting?->status ?? 'active'"
                                                                    />
                                                                </div>
                                                            </div>
                                                        </section>

                                                        <section class="rounded-2xl border border-[var(--h-bg-color)] bg-[var(--h-bg-color)]/20 p-4">
                                                            <div class="mb-3">
                                                                <h4 class="text-sm font-semibold">Document options</h4>
                                                                <p class="text-xs text-[var(--secondary-text)]">Prefixes and print notes used by branch documents.</p>
                                                            </div>
                                                            <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                                                                <div>
                                                                    <x-input
                                                                        label="Document identity prefix"
                                                                        name="{{ $rowName }}[doc_identity_prefix]"
                                                                        id="doc_identity_prefix_{{ $selectedModuleKey }}_{{ $branch->id }}"
                                                                        :value="$docIdentityPrefix"
                                                                        placeholder="Optional prefix"
                                                                        uppercased
                                                                    />
                                                                </div>
                                                                @if ($selectedModuleKey === 'orders')
                                                                    <div>
                                                                        <x-input
                                                                            label="Default order discount (%)"
                                                                            name="{{ $rowName }}[default_order_discount_percent]"
                                                                            id="default_order_discount_{{ $branch->id }}"
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
                                                                    <label class="{{ $miniToggle }} md:col-span-2"><span>Hide discount and gross amount in print</span><x-toggle-switch name="{{ $rowName }}[discount_disabled]" :checked="$discountDisabled" /></label>
                                                                    <textarea name="{{ $rowName }}[document_note]" rows="3" maxlength="300" placeholder="Optional note shown above totals" class="{{ $input }} md:col-span-2">{{ $documentNote }}</textarea>
                                                                @endif
                                                            </div>
                                                        </section>
                                                    </div>
                                                </div>
                                                <div class="flex justify-end gap-2 border-t border-[var(--h-bg-color)] px-5 py-4">
                                                    <button type="button" class="{{ $secondary }}" data-branch-module-modal-close>Close</button>
                                                    <button type="submit" class="{{ $button }}">Save Module Settings</button>
                                                </div>
                                            </div>
                                        </div>
                                    </article>
                                @endforeach
                            </div>
                        </div>

                        <div class="flex shrink-0 justify-between border-t border-[var(--h-bg-color)] bg-[var(--secondary-bg-color)] py-3">
                            <a href="{{ route('developer.branches.index') }}" class="{{ $secondary }}">Branches</a>
                            <button type="submit" class="{{ $button }}">Save Module Settings</button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </section>
@endsection

@push('page-scripts')
    <script>
        window.branchModuleChanged = function branchModuleChanged(input) {
            const moduleKey = input?.value || '';
            if (!moduleKey) return;
            const url = new URL(@json(route('developer.branches.modules.index')), window.location.origin);
            url.searchParams.set('module', moduleKey);
            if (url.toString() === window.location.href) return;
            window.location.href = url.toString();
        };

        document.addEventListener('DOMContentLoaded', () => {
            const moveModalToBody = (modal) => {
                if (!modal || modal.parentElement === document.body) return;
                const placeholder = document.createComment(`branch-module-modal:${modal.id}`);
                modal.parentNode.insertBefore(placeholder, modal);
                modal._branchModulePlaceholder = placeholder;
                document.body.appendChild(modal);
            };

            const restoreModalPosition = (modal) => {
                const placeholder = modal?._branchModulePlaceholder;
                if (!modal || !placeholder || !placeholder.parentNode) return;
                placeholder.parentNode.insertBefore(modal, placeholder);
                placeholder.remove();
                modal._branchModulePlaceholder = null;
            };

            const openModal = (modal) => {
                if (!modal) return;
                moveModalToBody(modal);
                modal.classList.remove('hidden');
                modal.classList.add('flex');
                document.body.classList.add('overflow-hidden');
            };

            const closeModal = (modal) => {
                if (!modal) return;
                modal.classList.add('hidden');
                modal.classList.remove('flex');
                restoreModalPosition(modal);
                if (!document.querySelector('[data-branch-module-modal]:not(.hidden)')) {
                    document.body.classList.remove('overflow-hidden');
                }
            };

            document.querySelectorAll('[data-branch-module-modal-open]').forEach((button) => {
                button.addEventListener('click', () => {
                    const modal = document.getElementById(button.dataset.branchModuleModalOpen);
                    openModal(modal);
                });
            });

            document.querySelectorAll('[data-branch-module-modal-close]').forEach((button) => {
                button.addEventListener('click', () => {
                    closeModal(button.closest('[data-branch-module-modal]'));
                });
            });

            document.addEventListener('keydown', (event) => {
                if (event.key !== 'Escape') return;
                document.querySelectorAll('[data-branch-module-modal]:not(.hidden)').forEach((modal) => {
                    closeModal(modal);
                });
            });
        });
    </script>
@endpush
