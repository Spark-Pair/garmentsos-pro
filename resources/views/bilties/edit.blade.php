@extends('app')
@section('title', 'Edit Bilty | ' . $client_company->name)
@section('content')
    <div class="max-w-3xl mx-auto w-full">
        <x-search-header heading="Edit Bilty" link linkText="Show Bilties" linkHref="{{ route('bilties.index') }}" />

        <form id="form" action="{{ route('bilties.update', $bilty) }}" method="post"
            class="bg-[var(--secondary-bg-color)] text-sm rounded-xl shadow-lg p-8 border border-[var(--glass-border-color)]/20 pt-14 max-w-3xl mx-auto relative overflow-hidden">
            @csrf
            @method('PUT')
            <x-form-title-bar title="Edit Bilty" />

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <x-input label="Date" name="date" id="date" type="date"
                    value="{{ old('date', $bilty->date?->format('Y-m-d')) }}" required />

                <x-input label="Bilty No." name="bilty_no" id="bilty_no"
                    value="{{ old('bilty_no', $bilty->bilty_no) }}" required />

                <x-input label="Invoice No." name="invoice_no" id="invoice_no" type="text"
                    value="{{ $bilty->invoice?->invoice_no ?? '-' }}" disabled />

                <x-input label="Customer" name="customer_name" id="customer_name" type="text"
                    value="{{ trim(($bilty->invoice?->customer?->customer_name ?? '-') . ' | ' . ($bilty->invoice?->customer?->city?->title ?? '-')) }}" disabled />

                <x-input label="Cargo Name" name="cargo_name" id="cargo_name" type="text"
                    value="{{ $bilty->invoice?->cargo_name ?? '-' }}" disabled />

                <x-input label="Cartons" name="carton_count" id="carton_count" type="text"
                    value="{{ $bilty->invoice?->carton_count ?? '-' }}" disabled />
            </div>

            <div class="w-full flex justify-end mt-5">
                <button type="submit"
                    class="px-6 py-2 bg-[var(--bg-success)] border border-[var(--bg-success)] text-[var(--text-success)] font-medium text-nowrap rounded-lg hover:bg-[var(--h-bg-success)] hover:border-[var(--border-success)] transition-all duration-300 ease-in-out cursor-pointer">
                    <i class="fas fa-save mr-1"></i> Save
                </button>
            </div>
        </form>
    </div>
@endsection
