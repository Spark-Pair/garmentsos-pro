@extends('app')

@section('title', 'Create Branch | ' . $client_company->name)

@section('content')
    <div class="max-w-6xl mx-auto w-full">
        <x-search-header heading="Create Branch" link linkText="Back to Branches" linkHref="{{ route('developer.branches.index') }}" />
    </div>

    <section class="text-center mx-auto">
        <div class="show-box mx-auto w-[90%] h-[70vh] bg-[var(--secondary-bg-color)] border border-[var(--glass-border-color)]/20 rounded-xl shadow pt-8.5 relative">
            <x-form-title-bar title="Branch Business Identity" />
            <div class="details h-full z-40">
                <div class="container-parent h-full">
                    <div class="card_container px-4 h-full flex flex-col">
                        <div class="overflow-y-auto grow my-scrollbar-2  pr-1 text-left">
                            @include('developer.branches._form')
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
