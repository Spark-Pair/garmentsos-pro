@extends('app')
@section('title', 'Permissions Report | ' . $client_company->name)
@section('content')
    <div class="w-[90%] mx-auto">
        <x-search-header heading="Permissions Report" />
    </div>

    <section class="text-center mx-auto">
        <div
            class="show-box mx-auto w-[90%] h-[70vh] bg-[var(--secondary-bg-color)] border border-[var(--glass-border-color)]/20 rounded-xl shadow pt-8.5 relative">
            <x-form-title-bar title="Role Wise Permissions" />

            <div class="details h-full z-40">
                <div class="container-parent h-full">
                    <div class="card_container px-3 h-full flex flex-col">
                        <div class="overflow-y-auto grow my-scrollbar-2">
                            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
                                @forelse ($roleMap as $role => $actions)
                                    <div class="bg-[var(--h-bg-color)]/40 border border-[var(--h-bg-color)] rounded-xl p-4 text-left">
                                        <div class="font-semibold capitalize text-[var(--text-color)]">
                                            {{ str_replace('_', ' ', $role) }}
                                        </div>
                                        <div class="mt-3 flex flex-wrap gap-2">
                                            @foreach ($actions as $action)
                                                <span
                                                    class="text-xs px-2 py-1 rounded-lg bg-[var(--secondary-bg-color)] border border-[var(--h-bg-color)] text-[var(--secondary-text)]">
                                                    {{ $action }}
                                                </span>
                                            @endforeach
                                        </div>
                                    </div>
                                @empty
                                    <div class="text-[var(--border-text-color)] text-sm">
                                        No permissions found.
                                    </div>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
