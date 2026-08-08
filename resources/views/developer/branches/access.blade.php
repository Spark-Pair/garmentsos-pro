@extends('app')

@section('title', 'Role / Permission Management | ' . $client_company->name)

@section('content')
    @php
        $input = 'w-full rounded-lg border border-gray-600 bg-[var(--secondary-bg-color)] px-3 py-2 text-sm text-[var(--text-color)] outline-none transition focus:border-[var(--primary-color)]';
        $button = 'px-4 py-2 bg-[var(--primary-color)] text-white font-medium text-nowrap rounded-lg hover:bg-[var(--h-primary-color)] hover:scale-95 transition-all duration-300 ease-in-out cursor-pointer';
        $secondary = 'px-3 py-1.5 bg-[var(--h-bg-color)]/60 border border-[var(--h-bg-color)] text-[var(--secondary-text)] font-medium text-nowrap rounded-lg hover:border-[var(--primary-color)]/50 hover:bg-[var(--secondary-bg-color)] transition-all duration-300 ease-in-out cursor-pointer';
        $toggle = 'flex items-center justify-between gap-3 rounded-xl border border-[var(--h-bg-color)] bg-[var(--h-bg-color)]/35 px-3 py-2 text-xs text-[var(--secondary-text)] transition hover:border-[var(--primary-color)]/40';
        $roleOptions = collect($roleLabels)->mapWithKeys(fn ($role) => [$role => ['text' => str_replace('_', ' ', ucfirst($role))]])->all();
        $userOptions = $users->mapWithKeys(fn ($user) => [$user->id => ['text' => ($user->name ?? $user->username) . ' (' . $user->role . ')']])->all();
    @endphp

    <div class="w-[80%] mx-auto">
        <x-search-header heading="Role / Permission Management" link linkText="Back to Settings" linkHref="{{ route('developer.settings') }}" />
    </div>

    <section class="text-center mx-auto">
        <div class="show-box mx-auto w-[80%] h-[70vh] bg-[var(--secondary-bg-color)] border border-[var(--glass-border-color)]/20 rounded-xl shadow pt-8.5 relative">
            <x-form-title-bar title="Role / Permission Management" />

            <div class="details h-full z-40">
                <div class="container-parent h-full">
                    <div class="card_container px-3 h-full flex flex-col">
                        <form method="POST" action="{{ route('developer.branches.access') }}" class="mt-4 rounded-2xl border border-[var(--h-bg-color)] bg-[var(--h-bg-color)]/20 p-4 text-left">
                            @csrf
                            <div class="grid grid-cols-1 gap-3 xl:grid-cols-4">
                                <x-select label="Role" name="role" id="branch_access_role_page" :options="$roleOptions" :value="$roleLabels[0] ?? 'developer'" />
                                <x-select label="User" name="user_id" id="branch_access_user_page" :options="$userOptions" showDefault />
                                <x-select label="Module" name="module_key" id="branch_access_module_page" :options="$moduleOptions" showDefault class="xl:col-span-2" />
                            </div>

                            <div class="mt-4 grid grid-cols-1 gap-4 xl:grid-cols-[1fr_auto] xl:items-start">
                                <div class="grid grid-cols-2 gap-2 md:grid-cols-3">
                                    @foreach (['can_view' => 'View', 'can_create' => 'Create', 'can_update' => 'Edit', 'can_delete' => 'Delete', 'can_override' => 'Developer Mode'] as $field => $label)
                                        <label class="{{ $toggle }}">
                                            <span>{{ $label }}</span>
                                            <x-toggle-switch :name="$field" :checked="in_array($field, ['can_view', 'can_create', 'can_update'], true)" />
                                        </label>
                                    @endforeach
                                </div>

                                <button type="submit" class="{{ $button }}">Save Permissions</button>
                            </div>
                        </form>

                        <div id="table-head" class="grid grid-cols-[1.2fr_1fr_1.3fr] bg-[var(--h-bg-color)] rounded-lg font-medium py-2 mt-4 text-xs">
                            <div class="text-left pl-5">Role / User</div>
                            <div class="text-left">Module</div>
                            <div class="text-right pr-5">Permissions</div>
                        </div>

                        <div class="overflow-y-auto grow my-scrollbar-2">
                            <div class="search_container grid grid-cols-1 gap-0 grow text-sm">
                                @forelse ($accessRows as $row)
                                    <div class="item row grid grid-cols-[1.2fr_1fr_1.3fr] items-center border-b border-[var(--h-bg-color)] py-2 transition-all hover:bg-[var(--h-secondary-bg-color)]">
                                        <div class="text-left pl-5">
                                            <div class="font-medium">{{ $row->user ? (($row->user->name ?? $row->user->username) . ' (user)') : ($row->role ?: '-') }}</div>
                                            <div class="text-[11px] text-[var(--secondary-text)]">{{ $row->user?->username ?: 'Role rule' }}</div>
                                        </div>
                                        <div class="text-left">{{ $row->module_key ? ($moduleLabels[$row->module_key] ?? $row->module_key) : 'All modules' }}</div>
                                        <div class="flex flex-wrap justify-end gap-1 pr-5 text-[11px] text-[var(--secondary-text)]">
                                            @foreach ([
                                                $row->can_view ? 'view' : null,
                                                $row->can_create ? 'create' : null,
                                                $row->can_update ? 'edit' : null,
                                                $row->can_delete ? 'delete' : null,
                                                $row->can_override ? 'developer mode' : null,
                                            ] as $permission)
                                                @if ($permission)
                                                    <span class="rounded-lg border border-[var(--h-bg-color)] bg-[var(--h-bg-color)]/40 px-2 py-1">{{ $permission }}</span>
                                                @endif
                                            @endforeach
                                        </div>
                                    </div>
                                @empty
                                    <div class="py-8 text-center text-sm text-[var(--secondary-text)]">No permission rules yet.</div>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
