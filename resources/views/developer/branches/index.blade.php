@extends('app')

@section('title', 'Branches | ' . $client_company->name)

@section('content')
    @php
        $searchFields = [
            'Branch' => [
                'id' => 'name',
                'type' => 'text',
                'placeholder' => 'Enter branch name',
                'dataFilterPath' => 'name',
            ],
            'Code' => [
                'id' => 'code',
                'type' => 'text',
                'placeholder' => 'Enter branch code',
                'dataFilterPath' => 'details.Code',
            ],
            'Status' => [
                'id' => 'status',
                'type' => 'select',
                'options' => [
                    'active' => ['text' => 'Active'],
                    'inactive' => ['text' => 'Inactive'],
                ],
                'dataFilterPath' => 'status',
            ],
        ];

        $branchRows = $branches->map(function ($branch) {
            $logoUrl = $branch->logo_path
                ? route('branch-logos.show', $branch)
                : null;

            return [
                'id' => $branch->id,
                'name' => $branch->display_name ?: $branch->name,
                'code' => $branch->code,
                'status' => $branch->status,
                'image' => $logoUrl,
                'manage_url' => route('developer.branches.show', $branch),
                'edit_url' => route('developer.branches.edit', $branch),
                'details' => [
                    'Code' => $branch->code,
                    'Prefix' => $branch->prefix ?: '-',
                    'Main Branch' => $branch->is_main ? 'Yes' : 'No',
                    'Business Name' => $branch->display_name ?: $branch->name,
                    'Phone' => $branch->phone ?: '-',
                    'City' => $branch->city ?: '-',
                    'Address' => $branch->address ?: '-',
                    'Status' => ucfirst($branch->status),
                ],
            ];
        })->values();
    @endphp

    <div>
        <div class="w-[80%] mx-auto">
            <x-search-header heading="Branches" :search_fields="$searchFields" />
        </div>

        <section class="text-center mx-auto">
            <div class="show-box mx-auto w-[80%] h-[70vh] bg-[var(--secondary-bg-color)] border border-[var(--glass-border-color)]/20 rounded-xl shadow pt-8.5 relative">
                <x-form-title-bar title="Show Branches" />

                <div class="absolute bottom-0 right-0 flex items-center justify-between gap-2 w-fll z-50 p-3 w-full pointer-events-none">
                    <span></span>
                    <x-section-navigation-button link="{{ route('developer.branches.create') }}" title="Add New Branch" icon="fa-plus" />
                </div>

                <div class="details h-full z-40">
                    <div class="container-parent h-full">
                        <div class="card_container px-3 h-full flex flex-col">
                            <div id="table-head" class="grid grid-cols-6 bg-[var(--h-bg-color)] rounded-lg font-medium py-2 mt-4">
                                <div class="cursor-pointer text-left pl-5 col-span-2">Branch</div>
                                <div class="cursor-pointer text-left pl-5">Code</div>
                                <div class="cursor-pointer text-center">Prefix</div>
                                <div class="cursor-pointer text-center">Contact</div>
                                <div class="cursor-pointer text-right pr-5">Status</div>
                            </div>
                            <p id="noItemsError" style="display: none" class="text-sm text-[var(--border-error)] mt-3">No items found</p>
                            <div class="overflow-y-auto grow my-scrollbar-2">
                                <div class="search_container grid grid-cols-1 gap-0 grow">
                                    @foreach ($branchRows as $row)
                                        <div
                                            id="branch-{{ $row['id'] }}"
                                            class="branch-item item row relative group grid grid-cols-6 border-b border-[var(--h-bg-color)] items-center py-2 cursor-pointer hover:bg-[var(--h-secondary-bg-color)] transition-all fade-in ease-in-out"
                                            data-json='@json($row)'
                                            onclick="generateBranchModal(this)"
                                        >
                                            <span class="text-left pl-5 col-span-2">
                                                <span class="font-medium">{{ $row['name'] }}</span>
                                                @if (($row['details']['Main Branch'] ?? 'No') === 'Yes')
                                                    <span class="ml-2 text-xs text-[var(--border-success)]">Main</span>
                                                @endif
                                            </span>
                                            <span class="text-left pl-5">{{ $row['code'] }}</span>
                                            <span class="text-center font-mono text-xs">{{ $row['details']['Prefix'] }}</span>
                                            <span class="text-center text-[var(--secondary-text)]">{{ $row['details']['Phone'] }}</span>
                                            <span class="text-right pr-5 capitalize {{ $row['status'] === 'active' ? 'text-[var(--border-success)]' : 'text-[var(--border-error)]' }}">{{ $row['status'] }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
@endsection

@push('page-scripts')
<script defer src="{{ asset('js/pages/developer-branches-index.js') }}"></script>
@endpush
