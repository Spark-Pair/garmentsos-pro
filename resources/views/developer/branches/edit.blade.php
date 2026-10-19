@extends('app')

@section('title', 'Edit Branch | ' . $client_company->name)

@section('content')
    <div class="max-w-6xl mx-auto w-full">
        <x-search-header heading="Edit Branch" link linkText="View Branch" linkHref="{{ route('developer.branches.show', $branch) }}" />
    </div>

    <section class="text-center mx-auto">
        <div class="show-box mx-auto w-[90%] h-[70vh] bg-[var(--secondary-bg-color)] border border-[var(--glass-border-color)]/20 rounded-xl shadow pt-8.5 relative">
            <x-form-title-bar title="Branch Business Identity" />
            <div class="details h-full z-40">
                <div class="container-parent h-full">
                    <div class="card_container px-4 h-full flex flex-col">
                        <div class="overflow-y-auto grow my-scrollbar-2  pr-1 text-left">
                            @include('developer.branches._form', ['branch' => $branch])
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
