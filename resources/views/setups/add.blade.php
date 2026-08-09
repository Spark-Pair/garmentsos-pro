@extends('app')
@section('title', 'Add Setups | ' . $client_company->name)
@section('content')
<!-- Main Content -->

    <div class="max-w-lg mx-auto">
        <x-search-header heading="Add Setup" link linkText="Show Setups" linkHref="{{ route('setups.index') }}"/>
    </div>

    <!-- Form -->
    <form id="add-setups-form" action="{{route('setups.store')}}" method="post"
        class="bg-[var(--secondary-bg-color)] rounded-xl shadow-lg p-8 border border-[var(--h-bg-color)] pt-12 max-w-lg mx-auto  relative overflow-hidden">
        @csrf
        <x-form-title-bar title="Add Setups" />

        <!-- Step 1: Basic Information -->
        <div id="step1" class="space-y-4 ">
            <div class="grid grid-cols-1 md:grid-cols-1 gap-4">
                <!-- type -->
                <x-select
                    label="Type"
                    name="type"
                    id="type"
                    :options="[
                        'supplier_category' => ['text' => 'Supplier Category'],
                        'bank_name' => ['text' => 'Bank Name'],
                        'city' => ['text' => 'City'],
                        'fabric' => ['text' => 'Fabric'],
                        'article_master_unit' => ['text' => 'Article Master Unit'],
                        'staff_type' => ['text' => 'Staff Type'],
                        'utility_bill_type' => ['text' => 'Utility Bill Type'],
                        'utility_bill_location' => ['text' => 'Utility Bill Location'],
                        'daily_ledger_method' => ['text' => 'Daily Ledger Method'],
                        'daily_ledger_case' => ['text' => 'Daily Ledger Case'],
                    ]"
                    showDefault
                />

                <!-- title -->
                <x-input
                    label="Title"
                    name="title"
                    id="title"
                    type="text"
                    placeholder="Enter Title"
                    required
                    capitalized
                    data-setup-title
                />

                <!-- title -->
                <x-input
                    label="Short Title / Global Key"
                    name="short_title"
                    id="short_title"
                    type="text"
                    placeholder="Enter Short Title"
                    uppercased
                    maxlength="20"
                    data-setup-short-title
                />
                
                <!-- login Button -->
                <button type="submit"
                    class="w-full bg-[var(--primary-color)] text-[var(--text-color)] px-4 py-2 mt-2 rounded-lg hover:bg-[var(--h-primary-color)] transition-all duration-300 ease-in-out font-medium uppercase cursor-pointer">
                    Add
                </button>
            </div>
        </div>
    </form>
@endsection

@push('page-scripts')
    <script>
        (() => {
            const existingShortTitles = @json($existingShortTitles ?? []);
            const titlesByType = @json($titlesByType ?? []);

            function setFieldError(input, message) {
                const errorEl = document.getElementById(`${input.name}-error`);
                if (!errorEl) return;

                if (message) {
                    input.classList.add('border-[var(--border-error)]');
                    errorEl.classList.remove('hidden');
                    errorEl.textContent = message;
                    return;
                }

                input.classList.remove('border-[var(--border-error)]');
                errorEl.classList.add('hidden');
                errorEl.textContent = '';
            }

            function validateSetupTitle() {
                const typeInput = document.querySelector('input.dbInput[name="type"]');
                const titleInput = document.querySelector('[data-setup-title]');
                if (!typeInput || !titleInput) return true;

                const selectedType = String(typeInput.value || '').trim();
                const titleValue = String(titleInput.value || '').trim().toLowerCase();

                if (!selectedType || !titleValue) {
                    setFieldError(titleInput, '');
                    return true;
                }

                const existingTitles = Array.isArray(titlesByType[selectedType]) ? titlesByType[selectedType] : [];
                const hasDuplicate = existingTitles.includes(titleValue);

                setFieldError(titleInput, hasDuplicate ? 'Is type mein yeh title pehle se mojood hai.' : '');
                return !hasDuplicate;
            }

            function validateSetupShortTitle() {
                const shortTitleInput = document.querySelector('[data-setup-short-title]');
                if (!shortTitleInput) return true;

                const shortTitleValue = String(shortTitleInput.value || '').trim().toUpperCase();
                if (!shortTitleValue) {
                    setFieldError(shortTitleInput, '');
                    return true;
                }

                const hasDuplicate = existingShortTitles.includes(shortTitleValue);
                setFieldError(shortTitleInput, hasDuplicate ? 'Yeh short title pehle se system mein use ho raha hai.' : '');
                return !hasDuplicate;
            }

            document.addEventListener('DOMContentLoaded', () => {
                const typeInput = document.querySelector('input.dbInput[name="type"]');
                const titleInput = document.querySelector('[data-setup-title]');
                const shortTitleInput = document.querySelector('[data-setup-short-title]');
                const form = document.getElementById('add-setups-form');

                titleInput?.addEventListener('input', validateSetupTitle);
                titleInput?.addEventListener('blur', validateSetupTitle);
                shortTitleInput?.addEventListener('input', validateSetupShortTitle);
                shortTitleInput?.addEventListener('blur', validateSetupShortTitle);
                typeInput?.addEventListener('change', validateSetupTitle);

                form?.addEventListener('submit', (event) => {
                    const titleValid = validateSetupTitle();
                    const shortTitleValid = validateSetupShortTitle();

                    if (!titleValid || !shortTitleValid) {
                        event.preventDefault();
                        if (typeof showMessageBox === 'function') {
                            showMessageBox('error', 'Duplicate setup values ko pehle theek karein.');
                        }
                    }
                });
            });
        })();
    </script>
@endpush
