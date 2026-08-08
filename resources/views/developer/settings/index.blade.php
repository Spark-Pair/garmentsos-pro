@extends('app')

@section('title', 'Developer Settings | ' . $client_company->name)

@section('content')
    @php
        $panel = 'rounded-2xl border border-[var(--h-bg-color)] bg-[var(--secondary-bg-color)] p-5 text-sm shadow-sm';
        $softPanel = 'rounded-2xl border border-[var(--h-bg-color)] bg-[var(--h-bg-color)]/25 p-4';
        $badge = 'inline-flex items-center rounded-xl border px-3 py-1.5 text-xs font-semibold';
        $primaryButton = 'px-4 py-2 bg-[var(--primary-color)] text-[var(--text-color)] font-medium text-nowrap rounded-lg hover:bg-[var(--h-primary-color)] hover:scale-95 transition-all duration-300 ease-in-out cursor-pointer';
        $secondaryButton = 'px-4 py-2 bg-[var(--h-bg-color)] border border-gray-600 text-[var(--secondary-text)] font-medium text-nowrap rounded-lg hover:bg-[var(--secondary-bg-color)] hover:scale-95 transition-all duration-300 ease-in-out cursor-pointer';
        $inputClass = 'w-full rounded-lg bg-[var(--h-bg-color)] border border-gray-600 text-[var(--text-color)] px-3 py-2 transition-all duration-300 ease-in-out focus:ring-1 focus:ring-primary focus:border-transparent';
    @endphp

    <div class="max-w-6xl mx-auto w-full">
        <x-search-header heading="Developer Settings" />
    </div>

    <section class="text-center mx-auto">
        <div class="show-box mx-auto w-[80%] h-[70vh] bg-[var(--secondary-bg-color)] border border-[var(--glass-border-color)]/20 rounded-xl shadow pt-8.5 relative">
            <x-form-title-bar title="Developer Settings" />

            <div class="details h-full z-40">
                <div class="container-parent h-full">
                    <div class="card_container px-4 h-full flex flex-col">
                        <div class="overflow-y-auto grow my-scrollbar-2 space-y-4  pr-1 text-left">
        <section id="system-actions" class="{{ $panel }}">
            <x-developer-panel-title title="System Actions" description="Developer navigation for setup, branches, access, licensing, and updates." />

            <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
                @foreach ([
                    ['href' => route('developer.branches.index'), 'icon' => 'fa-code-branch', 'title' => 'Branches', 'text' => 'Create and edit branch identity.'],
                    ['href' => route('developer.branches.modules.index'), 'icon' => 'fa-sliders-h', 'title' => 'Branch Modules', 'text' => 'Manage module behavior for all branches.'],
                    ['href' => route('developer.branches.access.index'), 'icon' => 'fa-user-shield', 'title' => 'Role / Permissions', 'text' => 'Manage role and user permissions by branch and module.'],
                    ['href' => route('developer.license.status'), 'icon' => 'fa-key', 'title' => 'License', 'text' => 'Activation, rebind, and diagnostics.'],
                    ['href' => route('developer.updater'), 'icon' => 'fa-shield-alt', 'title' => 'Updater', 'text' => 'Check, download, and apply updates.'],
                ] as $action)
                    <a href="{{ $action['href'] }}" class="rounded-xl border border-[var(--h-bg-color)] bg-[var(--h-bg-color)]/25 p-4 transition hover:-translate-y-0.5 hover:border-[var(--primary-color)]/60 hover:bg-[var(--secondary-bg-color)]">
                        <div class="flex items-start gap-3">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-[var(--primary-color)] text-white">
                                <i class="fas {{ $action['icon'] }}"></i>
                            </span>
                            <span>
                                <span class="block font-semibold">{{ $action['title'] }}</span>
                                <span class="mt-1 block text-xs text-[var(--secondary-text)]">{{ $action['text'] }}</span>
                            </span>
                        </div>
                    </a>
                @endforeach
            </div>
        </section>

        <section id="branding" class="{{ $panel }}">
            <x-developer-panel-title title="Branding" description="Text and color values use current config defaults when no override exists.">
                <span class="{{ $badge }} border-[var(--border-success)] bg-[var(--bg-success)] text-[var(--text-success)]">Safe text/color only</span>
            </x-developer-panel-title>

            <div class="grid grid-cols-1 gap-4 xl:grid-cols-[minmax(0,1fr)_18rem]">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    @foreach ($branding as $item)
                        @php
                            $isColor = str_contains($item['key'], 'color');
                        @endphp
                        <div class="{{ $softPanel }}">
                            <div class="mb-3 flex items-start justify-between gap-3">
                                <div>
                                    <h3 class="font-semibold capitalize">{{ str_replace('_', ' ', $item['key']) }}</h3>
                                    <p class="font-mono text-xs text-[var(--secondary-text)]">{{ $item['key'] }}</p>
                                </div>
                                <span class="{{ $badge }} border-gray-600 bg-[var(--secondary-bg-color)] text-[var(--secondary-text)]">{{ str_replace('_', ' ', $item['source']) }}</span>
                            </div>

                            <div class="mb-3 grid grid-cols-2 gap-3 text-xs">
                                <div>
                                    <div class="text-[var(--secondary-text)]">Default</div>
                                    <div>{{ $item['default'] ?: '-' }}</div>
                                </div>
                                <div>
                                    <div class="text-[var(--secondary-text)]">Effective</div>
                                    <div class="flex items-center gap-2">
                                        @if ($isColor)
                                            <span class="inline-block h-4 w-4 rounded border border-gray-600" style="background: {{ $item['effective_value'] ?: '#000000' }}"></span>
                                        @endif
                                        <span>{{ $item['effective_value'] ?: '-' }}</span>
                                    </div>
                                </div>
                            </div>

                            <div class="flex flex-col gap-2 sm:flex-row">
                                <form method="POST" action="{{ route('developer.settings.branding.save') }}" class="flex flex-1 flex-col gap-2 sm:flex-row">
                                    @csrf
                                    <input type="hidden" name="key" value="{{ $item['key'] }}">
                                    <input
                                        type="{{ $isColor ? 'color' : 'text' }}"
                                        name="value"
                                        value="{{ $item['value'] }}"
                                        maxlength="120"
                                        class="{{ $inputClass }} {{ $isColor ? 'h-10 w-20 p-1' : '' }}"
                                    >
                                    <button type="submit" class="{{ $primaryButton }}">Save</button>
                                </form>
                                <form method="POST" action="{{ route('developer.settings.branding.reset', $item['key']) }}">
                                    @csrf
                                    <button type="submit" class="{{ $secondaryButton }}">Reset</button>
                                </form>
                            </div>
                        </div>
                    @endforeach
                </div>

                <aside class="{{ $softPanel }}">
                    <h3 class="font-semibold">Preview</h3>
                    <p class="mt-1 text-xs text-[var(--secondary-text)]">Safe text/color overrides only. Logo upload and print templates are separate phases.</p>
                    <div class="mt-4 rounded-lg border border-gray-600 bg-[var(--secondary-bg-color)] p-4">
                        <div class="font-semibold">{{ $branding['app_name']['effective_value'] ?? 'GarmentsOS PRO' }}</div>
                        <div class="text-sm text-[var(--secondary-text)]">{{ $branding['company_name']['effective_value'] ?? $client_company->name }}</div>
                        <div class="mt-4 flex gap-2">
                            <span class="inline-block h-6 w-6 rounded-lg border border-gray-600" style="background: {{ $branding['theme_primary_color']['effective_value'] ?? '#2563eb' }}"></span>
                            <span class="inline-block h-6 w-6 rounded-lg border border-gray-600" style="background: {{ $branding['theme_secondary_color']['effective_value'] ?? '#1f2937' }}"></span>
                            <span class="inline-block h-6 w-6 rounded-lg border border-gray-600" style="background: {{ $branding['theme_accent_color']['effective_value'] ?? '#2563eb' }}"></span>
                        </div>
                    </div>
                </aside>
            </div>
        </section>

        <section id="modules" class="{{ $panel }}">
            <x-developer-panel-title title="Modules" description="Developer can control module visibility and local enablement from here." />

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                @foreach ($modules as $module)
                    @php
                        $enabledClass = $module['effective_enabled']
                            ? 'border-[var(--border-success)] bg-[var(--bg-success)] text-[var(--text-success)]'
                            : 'border-[var(--border-error)] bg-[var(--bg-error)] text-[var(--text-error)]';
                    @endphp
                    <article class="{{ $softPanel }}">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h3 class="font-semibold">{{ $module['label'] }}</h3>
                                <p class="font-mono text-xs text-[var(--secondary-text)]">{{ $module['key'] }}</p>
                            </div>
                            <span class="{{ $badge }} {{ $enabledClass }}">{{ $module['effective_enabled'] ? 'Enabled' : 'Disabled' }}</span>
                        </div>

                        <p class="mt-3 text-sm text-[var(--secondary-text)]">{{ $module['description'] }}</p>

                        <dl class="mt-4 grid grid-cols-2 gap-2 text-xs">
                            <div>
                                <dt class="text-[var(--secondary-text)]">Sidebar</dt>
                                <dd>{{ $module['effective_visible_in_sidebar'] ? 'Visible' : 'Hidden' }}</dd>
                            </div>
                            <div>
                                <dt class="text-[var(--secondary-text)]">License</dt>
                                <dd>{{ is_null($module['license_allowed']) ? 'Unrestricted' : ($module['license_allowed'] ? 'Allowed' : 'Restricted') }}</dd>
                            </div>
                            <div>
                                <dt class="text-[var(--secondary-text)]">Local</dt>
                                <dd>{{ is_null($module['local_enabled']) ? 'Default' : ($module['local_enabled'] ? 'Enabled' : 'Disabled') }}</dd>
                            </div>
                            <div>
                                <dt class="text-[var(--secondary-text)]">Control</dt>
                                <dd>Developer controlled</dd>
                            </div>
                        </dl>

                        <p class="mt-3 text-xs text-[var(--secondary-text)]">Reason: {{ str_replace('_', ' ', $module['reason']) }}</p>

                        <form method="POST" action="{{ route('developer.settings.modules.save') }}" class="mt-4 space-y-3">
                            @csrf
                            <input type="hidden" name="module_key" value="{{ $module['key'] }}">
                            <input type="hidden" name="enabled" value="0">
                            <input type="hidden" name="visible_in_sidebar" value="0">
                            <label class="flex items-center justify-between gap-3 rounded-xl border border-[var(--h-bg-color)] bg-[var(--h-bg-color)]/35 px-3 py-2 text-sm">
                                <span>Enabled</span>
                                <x-toggle-switch name="enabled" :checked="$module['enabled']" />
                            </label>
                            <label class="flex items-center justify-between gap-3 rounded-xl border border-[var(--h-bg-color)] bg-[var(--h-bg-color)]/35 px-3 py-2 text-sm">
                                <span>Show in sidebar</span>
                                <x-toggle-switch name="visible_in_sidebar" :checked="$module['visible_in_sidebar']" />
                            </label>
                            <button type="submit" class="{{ $primaryButton }}">Save {{ $module['label'] }}</button>
                        </form>
                    </article>
                @endforeach
            </div>
        </section>

        <section id="labels" class="{{ $panel }}">
            <x-developer-panel-title title="Labels" description="Adjust app wording without changing code." />

            <div class="overflow-x-auto">
                <table class="w-full min-w-[48rem] text-sm">
                    <thead class="bg-[var(--h-bg-color)]">
                        <tr>
                            <th class="p-3 text-left">Key</th>
                            <th class="p-3 text-left">Default</th>
                            <th class="p-3 text-left">Current</th>
                            <th class="p-3 text-left">Override</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-600/40">
                        @foreach ($labels as $key => $default)
                            <tr>
                                <td class="p-3 font-mono text-xs">{{ $key }}</td>
                                <td class="p-3">{{ $default }}</td>
                                <td class="p-3">{{ label_text($key, $default) }}</td>
                                <td class="p-3">
                                    <div class="flex flex-col gap-2 sm:flex-row">
                                        <form method="POST" action="{{ route('developer.settings.labels.save') }}" class="flex flex-1 flex-col gap-2 sm:flex-row">
                                            @csrf
                                            <input type="hidden" name="label_key" value="{{ $key }}">
                                            <input type="text" name="override_text" value="{{ label_text($key, $default) }}" maxlength="80" class="{{ $inputClass }}">
                                            <button type="submit" class="{{ $primaryButton }}">Save</button>
                                        </form>
                                        <form method="POST" action="{{ route('developer.settings.labels.reset', $key) }}">
                                            @csrf
                                            <button type="submit" class="{{ $secondaryButton }}">Reset</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <section id="features" class="{{ $panel }}">
            <x-developer-panel-title title="Feature Flags" description="Feature flags are foundation-only unless a route/action explicitly uses them." />

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                @foreach ($features as $feature)
                    @php
                        $featureClass = $feature['effective_enabled']
                            ? 'border-[var(--border-success)] bg-[var(--bg-success)] text-[var(--text-success)]'
                            : 'border-[var(--border-warning)] bg-[var(--bg-warning)] text-[var(--text-warning)]';
                    @endphp
                    <article class="{{ $softPanel }}">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h3 class="font-semibold">{{ $feature['label'] }}</h3>
                                <p class="font-mono text-xs text-[var(--secondary-text)]">{{ $feature['key'] }}</p>
                            </div>
                            <span class="{{ $badge }} {{ $featureClass }}">{{ $feature['effective_enabled'] ? 'Enabled' : 'Off' }}</span>
                        </div>
                        <p class="mt-3 text-sm text-[var(--secondary-text)]">{{ $feature['description'] }}</p>
                        <dl class="mt-4 grid grid-cols-3 gap-2 text-xs">
                            <div>
                                <dt class="text-[var(--secondary-text)]">License</dt>
                                <dd>{{ is_null($feature['license_allowed']) ? 'Unrestricted' : ($feature['license_allowed'] ? 'Allowed' : 'Restricted') }}</dd>
                            </div>
                            <div>
                                <dt class="text-[var(--secondary-text)]">Local</dt>
                                <dd>{{ is_null($feature['local_enabled']) ? 'Default' : ($feature['local_enabled'] ? 'Enabled' : 'Disabled') }}</dd>
                            </div>
                            <div>
                                <dt class="text-[var(--secondary-text)]">Reason</dt>
                                <dd>{{ str_replace('_', ' ', $feature['reason']) }}</dd>
                            </div>
                        </dl>
                    </article>
                @endforeach
            </div>
        </section>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
