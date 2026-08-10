@extends('app')
@section('title', 'Login | ' . $client_company->name)
@section('content')
    @php
        $loginTitle = $branding['app_name'] ?? $client_company->name;
        $loginLogoSvg = '';
        $loginLogoPath = $client_company->logo_svg_path ? public_path($client_company->logo_svg_path) : null;

        if ($loginLogoPath && file_exists($loginLogoPath) && is_readable($loginLogoPath)) {
            $loginLogoSvg = file_get_contents($loginLogoPath);
        }
    @endphp

    <div class="mb-4 max-w-2xl mx-auto">
        <x-search-header :heading="$loginTitle" />
    </div>

    <form id="login-form" method="POST" action="{{ route('login') }}"
        class="bg-[var(--secondary-bg-color)] text-sm rounded-xl shadow-lg px-7 pb-7 pt-13 md:px-8 md:pb-8 border border-[var(--glass-border-color)]/20 max-w-2xl mx-auto relative overflow-hidden">
        @csrf
        <x-form-title-bar title="Account Login" />

        <div class="mx-auto max-w-xl space-y-6">
            <div class="flex flex-col gap-3 rounded-xl border border-[var(--glass-border-color)]/10 bg-[var(--h-bg-color)]/50 p-4 md:flex-row md:items-center md:justify-between">
                <div class="flex min-w-0 items-center gap-3.5">
                    <div class="flex size-13 shrink-0 items-center justify-center rounded-xl border border-[var(--glass-border-color)]/20 bg-[var(--secondary-bg-color)] shadow-sm">
                        @if ($loginLogoSvg)
                            <span class="block size-8 overflow-hidden [&_svg]:h-full [&_svg]:w-full">{!! $loginLogoSvg !!}</span>
                        @else
                            <span class="text-lg font-bold text-[var(--primary-color)]">{{ strtoupper(substr($loginTitle, 0, 1)) }}</span>
                        @endif
                    </div>
                    <div class="min-w-0">
                        <p class="text-xs font-semibold uppercase tracking-wide text-[var(--secondary-text)]">{{ $client_company->name }}</p>
                        <h2 class="mt-1 truncate text-xl font-bold text-[var(--text-color)]">{{ $loginTitle }}</h2>
                    </div>
                </div>
                <div class="flex w-fit items-center gap-2 rounded-lg border border-[var(--glass-border-color)]/10 bg-[var(--secondary-bg-color)] px-3 py-2 text-xs font-semibold text-[var(--secondary-text)]">
                    <i class="fas fa-lock text-[var(--primary-color)]"></i>
                    Account Access
                </div>
            </div>

            <div class="mx-auto max-w-md space-y-4">
                <div class="mb-1">
                    <h3 class="text-lg font-semibold text-[var(--text-color)]">Welcome Back</h3>
                    <p class="mt-1 text-sm text-[var(--secondary-text)]">Enter your credentials to continue.</p>
                </div>

                <x-input
                    label="Username"
                    name="username"
                    id="username"
                    placeholder="Enter username"
                    required
                />

                <x-input
                    label="Password"
                    name="password"
                    id="password"
                    type="password"
                    placeholder="Enter password"
                    required
                />

                <button type="submit" class="mt-1 flex w-full items-center justify-center gap-2 rounded-lg bg-[var(--primary-color)] px-5 py-2.5 font-semibold text-white transition-all duration-300 ease-in-out hover:bg-[var(--h-primary-color)] hover:scale-[0.98] cursor-pointer">
                    <i class="fas fa-right-to-bracket text-xs"></i>
                    Login
                </button>
            </div>
        </div>
    </form>
@endsection
