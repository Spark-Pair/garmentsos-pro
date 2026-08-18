@extends('app')
@section('title', 'Edit Supplier | ' . $client_company->name)
@section('content')
    <!-- Main Content -->
    <!-- Progress Bar -->
    <div class="mb-5 max-w-3xl mx-auto">
        <x-search-header heading="Edit Supplier" link linkText="Show Suppliers" linkHref="{{ route('suppliers.index') }}"/>
        <x-progress-bar
            :steps="['Enter Details', 'Upload Image']"
            :currentStep="1"
        />
    </div>

    <div class="row max-w-3xl mx-auto flex gap-4">
        <!-- Form -->
        <form id="form" action="{{ route('suppliers.update', ['supplier' => $supplier->id]) }}" method="POST" enctype="multipart/form-data"
            class="bg-[var(--secondary-bg-color)] text-sm rounded-xl shadow-lg p-8 border border-[var(--glass-border-color)]/20 pt-14 grow relative overflow-hidden">
            @csrf
            @method('PUT')
            <x-form-title-bar title="Edit Supplier" />
            <!-- Step 1: Basic Information -->
            <div class="step1 space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- supplier_name -->
                    @if ($isDeveloper)
                        <x-input
                            label="Supplier Name"
                            name="supplier_name"
                            id="supplier_name"
                            value="{{ $supplier->supplier_name }}"
                            placeholder="Enter supplire name"
                            required
                            capitalized
                            dataValidate="required|friendly"
                        />

                        <!-- urdu_title -->
                        <x-input
                            label="Urdu Title"
                            name="urdu_title"
                            id="urdu_title"
                            value="{{ $supplier->urdu_title }}"
                            placeholder="Enter urdu title"
                            required
                            dataValidate="required|urdu"
                        />
                    @else
                        <x-input
                            label="Supplier Name"
                            value="{{ $supplier->supplier_name }}"
                            disabled
                        />

                        {{-- urdu title --}}
                        <x-input
                            label="Urdu Title"
                            value="{{ $supplier->urdu_title }}"
                            disabled
                        />
                    @endif

                    {{-- person name --}}
                    <x-input
                        label="Person Name"
                        name="person_name"
                        id="person_name"
                        value="{{ $supplier->person_name }}"
                        placeholder="Enter person name"
                        required
                    />

                    {{-- supplier_phone_number --}}
                    <x-input
                        label="Phone Number"
                        name="phone_number"
                        id="phone_number"
                        value="{{ $supplier->phone_number }}"
                        placeholder="Enter phone number"
                        required
                        dataValidate="required|phone"
                    />

                    {{-- supplier_joining_date --}}
                    <x-input
                        label="Joining Date"
                        name="date"
                        id="date"
                        value="{{ $supplier->date?->format('Y-m-d') }}"
                        min="2024-01-01"
                        validateMin
                        max="{{ now()->toDateString() }}"
                        validateMax
                        type="date"
                        required
                    />
                </div>
            </div>

            <!-- Step 2: Image -->
            <div class="step2 hidden space-y-4">
                @if ($supplier->user->profile_picture == 'default_avatar.png')
                    <x-file-upload
                        id="image_upload"
                        name="image_upload"
                        placeholder="{{ asset('images/image_icon.png') }}"
                        uploadText="Upload customer image"
                    />
                @else
                    <x-file-upload
                        id="image_upload"
                        name="image_upload"
                        placeholder="{{ asset('storage/uploads/images/' . $supplier->user->profile_picture) }}"
                        uploadText="Preview"
                    />
                @endif
            </div>
        </form>
    </div>

@endsection


@push('page-scripts')
<script defer src="{{ asset('js/pages/suppliers-edit.js') }}"></script>
<script>
        window.__suppliersEdit = {
            supplierHasCustomImage: @json($supplier->user->profile_picture != 'default_avatar.png'),
        };
    </script>
@endpush
