@extends('app')
@section('title', 'Edit Physical Quantity | ' . $client_company->name)
@section('content')
@php
    $category_options = [
        'a' => ['text'  => 'A'],
        'b' => ['text'  => 'B'],
        'c' => ['text'  => 'C'],
    ];
    $article = $physicalQuantity->article;
    $articleText = trim(($article?->article_no ?? '-') . ' | ' . ($article?->description ?? ''));
    $canEditArticleMeta = Auth::user()?->role === 'developer' || app_can('physical_quantities', 'override');
@endphp
    <div class="max-w-5xl mx-auto">
        <x-search-header heading="Edit Physical Quantity" link linkText="Show Physical Quantities" linkHref="{{ route('physical-quantities.index') }}"/>
    </div>

    <form id="form" action="{{ route('physical-quantities.update', ['physical_quantity' => $physicalQuantity->id]) }}" method="post"
        class="bg-[var(--secondary-bg-color)] text-sm rounded-xl shadow-lg p-8 border border-[var(--glass-border-color)]/20 pt-14 max-w-5xl mx-auto relative overflow-hidden">
        @csrf
        @method('PUT')
        <x-form-title-bar title="Edit Physical Quantity" />

        <div class="space-y-4">
            <div class="flex justify-between gap-4">
                <div class="grow">
                    <x-input label="Article" id="article" value="{{ $articleText }}" disabled withImg imgUrl="{{ $article?->image ? asset('storage/' . $article->image) : '' }}" />
                </div>

                <div class="w-1/4">
                    <x-input label="Date" name="date" id="date" type="date" max="{{ now()->toDateString() }}" value="{{ old('date', $physicalQuantity->date?->format('Y-m-d')) }}" required />
                </div>

                <div class="w-1/4">
                    <x-input label="Processed By" name="processed_by" id="processed_by" value="{{ old('processed_by', $article?->processed_by) }}" placeholder="Enter processed by" :disabled="!$canEditArticleMeta" :required="$canEditArticleMeta" />
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-1 md:grid-cols-3 gap-4">
                <x-select
                    label="Master Unit"
                    name="pcs_per_packet"
                    id="pcs_per_packet"
                    :options="$masterUnitOptions"
                    :value="old('pcs_per_packet', $article?->pcs_per_packet ?: '')"
                    showDefault
                    :dataClearable="$canEditArticleMeta"
                    :disabled="!$canEditArticleMeta"
                    addBtnLink="{{ $canEditArticleMeta ? route('setups.create') : '' }}"
                />
                <x-input label="Packets" name="packets" id="packets" type="number" value="{{ old('packets', $physicalQuantity->packets) }}" placeholder="Enter packet count" required />
                <x-select label="Category" name="category" id="category" :options="$category_options" :value="old('category', $physicalQuantity->category)" required />
            </div>
        </div>

        <div class="w-full flex justify-end gap-3 mt-4">
            <a href="{{ route('physical-quantities.index') }}"
                class="px-5 py-1 border border-gray-600 bg-[var(--h-bg-color)] rounded-lg hover:bg-[var(--h-secondary-bg-color)] transition-all">
                Cancel
            </a>
            <button type="submit"
                class="px-6 py-1 bg-[var(--bg-success)] border border-[var(--bg-success)] text-[var(--text-success)] font-medium text-nowrap rounded-lg hover:bg-[var(--h-bg-success)] transition-all 0.3s ease-in-out cursor-pointer">
                <i class='fas fa-save mr-1'></i> Save
            </button>
        </div>
    </form>
@endsection
