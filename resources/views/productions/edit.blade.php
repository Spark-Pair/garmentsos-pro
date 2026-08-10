@extends('app')
@section('title', 'Edit Production | ' . $client_company->name)
@section('content')
@php
    $isIssue = filled($production->issue_date);
    $dateName = $isIssue ? 'issue_date' : 'receive_date';
    $dateLabel = $isIssue ? 'Issue Date' : 'Receive Date';
    $dateValue = old($dateName, optional($isIssue ? $production->issue_date : $production->receive_date)->format('Y-m-d'));
@endphp

    <div class="max-w-3xl mx-auto">
        <x-search-header heading="Edit Production" link linkText="Show Productions" linkHref="{{ route('productions.index') }}"/>
    </div>

    <form id="form" action="{{ route('productions.update', $production) }}" method="post"
        class="bg-[var(--secondary-bg-color)] text-sm rounded-xl shadow-lg p-8 border border-[var(--glass-border-color)]/20 pt-14 max-w-3xl mx-auto relative overflow-hidden">
        @csrf
        @method('PUT')
        <x-form-title-bar title="Edit Production" />

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <x-input label="Ticket" id="ticket" value="{{ $production->ticket }}" disabled />
            <x-input label="Article" id="article" value="{{ $production->article?->article_no ?? '-' }}" disabled />
            <x-input label="Work" id="work" value="{{ $production->work?->title ?? '-' }}" disabled />
            <x-input label="Worker" id="worker" value="{{ $production->worker?->employee_name ?? '-' }}" disabled />

            <x-input label="{{ $dateLabel }}" name="{{ $dateName }}" id="{{ $dateName }}" type="date" value="{{ $dateValue }}" validateMax max="{{ now()->toDateString() }}" required />
            <x-input label="Rate" name="rate" id="rate" type="number" value="{{ old('rate', $production->rate) }}" placeholder="Enter Rate" dataValidate="numeric" />
            <x-input label="Issued By" name="issued_by_name" id="issued_by_name" value="{{ old('issued_by_name', $production->issued_by_name) }}" placeholder="Supervisor / issuer name" />
            <x-input label="Received By" name="received_by_name" id="received_by_name" value="{{ old('received_by_name', $production->received_by_name) }}" placeholder="Supervisor / receiver name" />

            <div class="col-span-full">
                <x-input label="Amount" name="amount" id="amount" type="amount" value="{{ old('amount', \App\Support\Money::format($production->amount)) }}" placeholder="Enter Amount" dataValidate="amount" />
            </div>
        </div>

        <div class="w-full flex justify-end mt-4">
            <button type="submit"
                class="px-6 py-1 bg-[var(--bg-success)] border border-[var(--bg-success)] text-[var(--text-success)] font-medium text-nowrap rounded-lg hover:bg-[var(--h-bg-success)] transition-all duration-300 ease-in-out cursor-pointer">
                <i class='fas fa-save mr-1'></i> Save
            </button>
        </div>
    </form>
@endsection
