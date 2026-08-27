@php
    $preferredTheme = Auth::check()
        ? Auth::user()->theme
        : (request()->cookie('theme')
            ?? (isset($_COOKIE['theme']) ? $_COOKIE['theme'] : (str_contains($_SERVER['HTTP_USER_AGENT'] ?? '', 'Dark') ? 'dark' : 'light')));
    $rawBranding = $branding ?? [];
    $effectiveBranding = collect($rawBranding)
        ->mapWithKeys(fn ($value, $key) => [$key => is_array($value) ? ($value['effective_value'] ?? $value['value'] ?? null) : $value])
        ->all();
    $appName = $effectiveBranding['app_name'] ?? $client_company->logo_text ?? 'GarmentsOS PRO';
    $primaryColor = $effectiveBranding['theme_primary_color'] ?? '#2563eb';
    $secondaryColor = $effectiveBranding['theme_secondary_color'] ?? '#1f2937';
    $accentColor = $effectiveBranding['theme_accent_color'] ?? '#2563eb';
    $menuShortcuts = [];
    if (Auth::check()) {
        $rawMenuShortcuts = Auth::user()->menu_shortcuts;
        $menuShortcuts = is_array($rawMenuShortcuts)
            ? $rawMenuShortcuts
            : (json_decode((string) $rawMenuShortcuts, true) ?: []);
    }
    $activeUpdateLock = null;
    $clearBrowserCacheAfterLogin = (bool) session()->pull('clear_browser_cache_after_login', false);
    try {
        $activeUpdateLock = app(\App\Services\Updater\UpdateLockService::class)->activeLock();
    } catch (\Throwable) {
        $activeUpdateLock = null;
    }

    $appConfig = [
        'authenticated' => Auth::check(),
        'clearBrowserCache' => $clearBrowserCacheAfterLogin,
        'homeUrl' => route('home'),
        'menuShortcuts' => $menuShortcuts,
        'maxShortcutsLimit' => 7,
        'pusherEnabled' => $pusherEnabled,
        'pusherKey' => $pusherFrontend['key'] ?? null,
        'pusherCluster' => $pusherFrontend['cluster'] ?? null,
        'authUserId' => Auth::check() ? Auth::user()->id : null,
        'authUserRole' => Auth::check() ? Auth::user()->role : null,
        'routeIsLogin' => request()->is('login'),
        'routeIsSetup' => request()->is('setup'),
        'routeIsSubscriptionExpired' => request()->is('subscription-expired'),
        'routeIsOrdersCreate' => request()->is('orders/create'),
        'changeLayoutUrl' => request()->route()?->getActionMethod() === 'index' || request()->route()?->getActionMethod() === 'summary'
            ? route('change-data-layout')
            : null,
        'routeName' => request()->route()?->getName(),
        'companyLogoBase' => url('/') . '/',
        'branding' => $effectiveBranding,
        'notificationsUrl' => Auth::check() ? route('notifications.index') : null,
        'readonlySession' => !request()->is('login') && !request()->is('setup') && ((bool) session('license_readonly')),
        'updating' => $activeUpdateLock !== null,
    ];
    $developerUpdateStatus = null;
    $developerLauncherHandoff = null;
    $canRunUpdateActions = false;
    $updateActionDeniedMessage = '';
    $canManageUpdates = Auth::check() && in_array(Auth::user()->role, ['developer', 'admin'], true);
    if ($canManageUpdates && !request()->is('login') && !request()->is('setup')) {
        try {
            $updateActionAccess = app(\App\Services\Updater\UpdateActionAccessService::class);
            $canRunUpdateActions = $updateActionAccess->canRunFrom(request());
            $updateActionDeniedMessage = $updateActionAccess->denialMessage();
            $releaseFeedService = app(\App\Services\Updater\ReleaseFeedService::class);
            $developerUpdateStatus = $releaseFeedService->checkConfiguredCached((int) config('updater.app_shell_feed_cache_seconds', 0));
            if ($canManageUpdates && $canRunUpdateActions && !empty($developerUpdateStatus['update_available'])) {
                $developerLauncherHandoff = $releaseFeedService->launcherHandoff($developerUpdateStatus);
            }
        } catch (\Throwable) {
            $developerUpdateStatus = null;
            $developerLauncherHandoff = null;
            $canRunUpdateActions = false;
        }
    }

    $currentBranchModuleKey = null;
    $branchEditRecordToken = null;
    if (Auth::check() && !request()->is('login') && !request()->is('setup')) {
        try {
            $currentBranchModuleKey = app(\App\Services\Branches\BranchModuleRegistryService::class)
                ->moduleKeyForRoute(request()->route());

            $isEditRoute = request()->route()?->getActionMethod() === 'edit'
                || str_ends_with((string) request()->route()?->getName(), '.edit');
            if ($isEditRoute && $currentBranchModuleKey && app_can($currentBranchModuleKey, 'update')) {
                $routeRecord = collect(request()->route()?->parameters() ?? [])
                    ->first(fn ($parameter) => $parameter instanceof \Illuminate\Database\Eloquent\Model
                        && \Illuminate\Support\Facades\Schema::hasColumn($parameter->getTable(), 'branch_id'));

                if ($routeRecord) {
                    $branchEditRecordToken = \Illuminate\Support\Facades\Crypt::encryptString(json_encode([
                        'module_key' => $currentBranchModuleKey,
                        'model' => get_class($routeRecord),
                        'id' => $routeRecord->getKey(),
                        'branch_id' => $routeRecord->getAttribute('branch_id'),
                        'issued_at' => now()->timestamp,
                    ]));
                }
            }
        } catch (\Throwable) {
            $currentBranchModuleKey = null;
            $branchEditRecordToken = null;
        }
    }

    $developerModeModules = [];
    if (Auth::check() && !request()->is('login') && !request()->is('setup')) {
        try {
            $permissionService = app(\App\Services\Access\AppPermissionService::class);
            $branchModuleService = app(\App\Services\Branches\ModuleBranchService::class);
            $developerModeModules = collect(array_keys($branchModuleService->moduleRegistry()))
                ->filter(fn ($moduleKey) => $permissionService->can(Auth::user(), $moduleKey, 'override'))
                ->values()
                ->all();
        } catch (\Throwable) {
            $developerModeModules = [];
        }
    }

    $appConfig['currentModuleKey'] = $currentBranchModuleKey;
    $appConfig['developerModeModules'] = $developerModeModules;
    $assetVersion = config('app.version', config('updater.current_version', '1'));
    $suppressPageFlashMessages = request()->route()?->getActionMethod() === 'edit';
@endphp
<!DOCTYPE html>
<html lang="en" data-theme="{{ $preferredTheme }}">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="{{ $primaryColor }}">
    <meta name="description" content="{{ $appName }} - Garments Business Management Solution">
    <link rel="manifest" href="/manifest.json">
    <title>@yield('title', $client_company->name)</title>
    <style>
        @font-face {
            font-family: 'Calibri';
            src: url('/calibri.ttf') format('truetype'); /* For TTF */
            font-weight: normal;
            font-style: normal;
        }

        /* color theme */
        :root {
            --bg-color: #111827;
            /* Default dark theme background */
            --h-bg-color: #374151;
            --secondary-bg-color: #1f2937;
            --h-secondary-bg-color: hsl(215, 28%, 13%);
            /* Default dark theme secondary background */
            --text-color: #ffffff;
            /* Default dark theme text color */
            --secondary-text: #d1d5db;
            /* Default dark theme secondary text */
            --primary-color: {{ $primaryColor }};
            --h-primary-color: #1f56cd;
            --brand-secondary-color: {{ $secondaryColor }};
            --brand-accent-color: {{ $accentColor }};
            /* Default dark theme primary color */
            --bg-warning: hsl(45, 50%, 30%);
            --bg-success: hsl(130, 50%, 30%);
            --bg-error: hsl(360, 50%, 30%);
            --border-warning: hsl(45, 100%, 45%);
            --border-success: hsl(130, 100%, 45%);
            --border-error: hsl(360, 100%, 45%);
            --text-warning: hsl(45, 30%, 95%);
            --text-success: hsl(130, 30%, 95%);
            --text-error: hsl(360, 30%, 95%);

            --h-bg-warning: hsl(45, 50%, 20%);
            --h-bg-success: hsl(130, 50%, 20%);
            --h-bg-error: hsl(360, 50%, 20%);

            --danger-color: hsl(0, 65%, 51%);
            --h-danger-color: hsl(0, 65%, 41%);
            --success-color: hsl(142, 65%, 36%);
            --h-success-color: hsl(142, 65%, 26%);

            --overlay-color: rgba(0, 0, 0, 0.438);
            --glass-border-color: #ffffff;
        }

        [data-theme='light'] {
            --bg-color: #ffffff;
            --h-bg-color: #d1d3d7;
            --secondary-bg-color: #ebeff3;
            --h-secondary-bg-color: hsl(0, 0%, 96%);
            --text-color: #1f2937;
            --secondary-text: #4b5563;
            --bg-warning: hsl(45, 100%, 80%);
            --bg-success: hsl(130, 100%, 80%);
            --bg-error: hsl(360, 100%, 80%);
            --h-bg-warning: hsl(45, 100%, 75%);
            --h-bg-success: hsl(130, 100%, 75%);
            --h-bg-error: hsl(360, 100%, 75%);
            --border-warning: hsl(45, 100%, 40%);
            --border-success: hsl(130, 100%, 40%);
            --border-error: hsl(360, 100%, 40%);
            --text-warning: hsl(45, 75%, 35%);
            --text-success: hsl(130, 75%, 35%);
            --text-error: hsl(360, 75%, 35%);
            --glass-border-color: #000000;
        }

        [data-theme="dark"] input[type="date"]::-webkit-calendar-picker-indicator {
            filter: invert(1);
        }

        .bg-\[var\(--primary-color\)\] {
            color: #e2e8f0 !important;
        }

        .bg-\[var\(--primary-color\)\] svg {
            fill: #e2e8f0 !important;
        }

        .my-scrollbar-2 {
            scrollbar-gutter: auto;
            scrollbar-width: none;
            -ms-overflow-style: none;
        }

        /* Now target ONLY this element's own scrollbar */
        .my-scrollbar-2::-webkit-scrollbar,
        .my-scrollbar-2::-webkit-scrollbar-track,
        .my-scrollbar-2::-webkit-scrollbar-thumb,
        .my-scrollbar-2::-webkit-scrollbar-corner {
            all: unset;
        }

        .my-scrollbar-2::-webkit-scrollbar {
            width: 0;
            height: 0;
        }

        .my-scrollbar-2::-webkit-scrollbar-track {
            background: transparent;
            border-radius: 8px;
        }

        .my-scrollbar-2::-webkit-scrollbar-thumb {
            background: linear-gradient(
                180deg,
                var(--primary-color),
                var(--h-primary-color)
            );
            border-radius: 8px;
            border: 0;
            transition: background 0.3s ease;
        }

        .my-scrollbar-2::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(
                180deg,
                var(--h-primary-color),
                var(--primary-color)
            );
        }

        .gos-overlay-scrollbar-thumb {
            position: fixed;
            z-index: 10050;
            width: 8px;
            min-height: 32px;
            border-radius: 999px;
            background: linear-gradient(
                180deg,
                var(--primary-color),
                var(--h-primary-color)
            );
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.18s ease, background 0.18s ease;
        }

        .gos-overlay-scrollbar-thumb.is-visible {
            opacity: 1;
            pointer-events: auto;
        }

        .gos-overlay-scrollbar-thumb:hover,
        .gos-overlay-scrollbar-thumb.is-dragging {
            background: linear-gradient(
                180deg,
                var(--h-primary-color),
                var(--primary-color)
            );
        }

        .scrollbar-hidden::-webkit-scrollbar {
            display: none !important;
        }

        .fade-in {
            animation: fadeIn 0.35s ease-in-out;
        }

        .scale-in {
            animation: scaleIn 0.4s ease-in-out;
        }

        .scale-out {
            animation: scaleOut 0.4s ease-in-out;
        }

        /* Example animation */
        @keyframes fadeIn {
            0% {
                opacity: 0;
            }

            100% {
                opacity: 1;
            }
        }

        @keyframes fadeOut {
            0% {
                opacity: 1;
            }

            100% {
                opacity: 0;
            }
        }

        @keyframes scaleIn {
            0% {
                transform: scale(0.9);
            }
            60% {
                transform: scale(1.05);
            }
            80% {
                transform: scale(0.97);
            }
            100% {
                transform: scale(1);
            }
        }

        @keyframes scaleOut {
            0% {
                transform: scale(1);
            }
            30% {
                transform: scale(1.05);
            }
            60% {
                transform: scale(0.95);
            }
            100% {
                transform: scale(0);
            }
        }

        .fade-out {
            animation: fadeOut 0.35s forwards !important;
        }

        .opacity-zero {
            opacity: 0;
        }

        .opacity-transition {
            transition: opacity .2s linear;
        }

        #mobileMenu.is-open {
            transform: translateY(0) !important;
        }

        @media (max-width: 768px) {
            /* Allow horizontal scroll for A4 previews on small screens */
            #preview-container {
                overflow-x: auto !important;
                -webkit-overflow-scrolling: touch;
            }
        }

        .card {
            transition: all 0.3s ease-in-out;
            position: relative;
        }

        .card:not(.no-translate):hover {
            transform: translateY(-0.3rem);
        }

        .card:hover {
            background-color: var(--h-secondary-bg-color);
            box-shadow: 0 5px 0.8rem var(--bg-color);
        }

        .card button {
            transition: all 0.2s ease-in-out;
        }

        .card:hover button {
            scale: 1.1;
        }

        .active_inactive_dot {
            opacity: 100;
            transition: all 0.2s ease-in-out;
        }

        .active_inactive {
            opacity: 0;
            transition: all 0.2s ease-in-out;
        }

        .card:hover .active_inactive {
            opacity: 100;
        }

        .card:hover .active_inactive_dot {
            opacity: 0;
        }

        .nav-link.active {
            background-color: var(--h-bg-color) !important;
        }

        .nav-link.active i {
            color: var(--primary-color) !important;
        }

        .nav-link.active svg {
            fill: var(--primary-color) !important;
        }

        /* Normal input → focus input par */
        :where(input, select, textarea):focus-visible {
            outline: 1px solid var(--primary-color);
            outline-offset: 2px;
            box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary-color) 22%, transparent);
        }

        /* border-none wale input ki apni focus styling remove */
        :where(input, select, textarea).border-none:focus-visible {
            outline: none;
            box-shadow: none;
        }

        /* border-none input ke DIRECT parent par focus styling */
        *:has(> :where(input, select, textarea).border-none:focus-visible) {
            outline: 1px solid var(--primary-color);
            outline-offset: 2px;
            box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary-color) 22%, transparent);
        }

        .nav-link:focus-visible,
        .dropdownMenu :where(a, button):focus-visible {
            background-color: var(--h-bg-color) !important;
            color: var(--primary-color) !important;
        }

        .nav-link.active:hover i {
            color: var(--h-primary-color) !important;
        }

        .nav-link.active:hover svg {
            fill: var(--h-primary-color) !important;
        }

        input[type="number"]::-webkit-inner-spin-button,
        input[type="number"]::-webkit-outer-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

        input[type="number"] {
            -moz-appearance: textfield;
            /* For Firefox */
        }

        input[type="date"] {
            height: 2.437rem !important;
        }

        input[disabled] {
            cursor: not-allowed;
        }

        input[readonly] {
            background-color: transparent !important;
            pointer-events: none;
        }

        select[disabled] {
            cursor: not-allowed;
        }

        input::-webkit-calendar-picker-indicator {
            display: none !important;
            -webkit-appearance: none;
        }

        strong {
            font-weight: 600 !important;
        }

        span {
            color: var(--secondary-text) !important;
        }

        .negative-value,
        .negative-value * {
            color: var(--border-error) !important;
        }

        .open-dropdown:hover .open-dropdown-hover\:block {
            display: block;
        }

        input.row-checkbox:checked + input {
            opacity: 1 !important;
            pointer-events: all !important;
        }

        .app-toggle,
        .switchBtn {
            position: relative;
            display: inline-flex;
            align-items: center;
            flex-shrink: 0;
            width: 2.25rem;
            height: 1.25rem;
            padding: 0.125rem;
            border: 1px solid color-mix(in srgb, var(--secondary-text) 22%, transparent);
            border-radius: 9999px;
            background: color-mix(in srgb, var(--secondary-text) 12%, var(--h-bg-color));
            cursor: pointer;
            transition: background-color 180ms ease, border-color 180ms ease, box-shadow 180ms ease;
        }

        .app-toggle-track {
            position: absolute;
            inset: 0;
            border-radius: 9999px;
            background: color-mix(in srgb, var(--secondary-text) 22%, var(--h-bg-color));
            transition: background-color 160ms ease, box-shadow 160ms ease;
        }

        .app-toggle-thumb,
        .switchBtn .circle {
            position: relative;
            z-index: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 0.875rem;
            height: 0.875rem;
            border-radius: 9999px;
            background: color-mix(in srgb, white 78%, var(--secondary-text));
            box-shadow: 0 1px 3px rgb(15 23 42 / 0.22);
            transition: transform 180ms ease, background-color 180ms ease;
        }

        .app-toggle-thumb::before,
        .app-toggle-thumb::after,
        .switchBtn .circle::before,
        .switchBtn .circle::after {
            content: "";
            position: absolute;
            left: 50%;
            top: 50%;
            width: 0.42rem;
            height: 0.085rem;
            border-radius: 999px;
            background: color-mix(in srgb, var(--secondary-text) 68%, white);
            transform-origin: center;
        }

        .app-toggle-thumb::before,
        .switchBtn .circle::before {
            transform: translate(-50%, -50%) rotate(45deg);
        }

        .app-toggle-thumb::after,
        .switchBtn .circle::after {
            transform: translate(-50%, -50%) rotate(-45deg);
        }

        .app-toggle:not(.is-checked):not(:has(.app-toggle-input:checked)) {
            border-color: color-mix(in srgb, var(--secondary-text) 36%, transparent);
            background: color-mix(in srgb, var(--secondary-text) 20%, var(--h-bg-color));
        }

        .app-toggle.is-checked,
        .app-toggle:has(.app-toggle-input:checked),
        .app-toggle-input:checked ~ .app-toggle-track,
        .switchBtn.active {
            border-color: color-mix(in srgb, var(--primary-color) 60%, transparent);
            background: var(--primary-color);
        }

        .app-toggle:has(.app-toggle-input:disabled),
        .switchBtn.pointer-events-none {
            cursor: not-allowed;
            opacity: 0.62;
        }

        .app-toggle.is-checked .app-toggle-track,
        .app-toggle:has(.app-toggle-input:checked) .app-toggle-track {
            background: var(--primary-color);
        }

        .app-toggle-input:focus-visible ~ .app-toggle-track,
        .switchBtn:focus-visible {
            box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary-color) 25%, transparent);
        }

        .app-toggle.is-checked .app-toggle-thumb,
        .app-toggle:has(.app-toggle-input:checked) .app-toggle-thumb,
        .app-toggle-input:checked ~ .app-toggle-thumb,
        .switchBtn.active .circle {
            transform: translateX(1rem);
            background: white;
        }

        .app-toggle.is-checked .app-toggle-thumb::before,
        .app-toggle:has(.app-toggle-input:checked) .app-toggle-thumb::before,
        .app-toggle-input:checked ~ .app-toggle-thumb::before,
        .switchBtn.active .circle::before {
            left: 50%;
            top: 50%;
            width: 0.24rem;
            height: 0.38rem;
            border: solid var(--primary-color);
            border-width: 0 0.09rem 0.09rem 0;
            border-radius: 0;
            background: transparent;
            transform: translate(-50%, -58%) rotate(45deg);
        }

        .app-toggle.is-checked .app-toggle-thumb::after,
        .app-toggle:has(.app-toggle-input:checked) .app-toggle-thumb::after,
        .app-toggle-input:checked ~ .app-toggle-thumb::after,
        .switchBtn.active .circle::after {
            opacity: 0;
        }

        /* Error icon sirf tab dikhe jab field mein error ho */
        .form-group .field-control,
        .select-component .selectParent {
            position: relative;
        }

        .errorIconWrap {
            position: absolute;
            top: 50%;
            z-index: 20;
        }

        .errorIcon {
            display: flex;
            width: 1rem;
            height: 1rem;
            align-items: center;
            justify-content: center;
            padding: 0;
            border: 1px solid var(--border-error);
            border-radius: 9999px;
            background: var(--secondary-bg-color);
            color: var(--border-error) !important;
            font-size: 0.65rem;
            font-weight: 700;
            line-height: 1;
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
            box-shadow: none;
            transition: opacity 140ms ease, transform 140ms ease, background-color 140ms ease;
        }

        .form-group.has-field-error .errorIcon {
            opacity: 1;
            visibility: visible;
            pointer-events: auto;
        }

        .errorIcon:hover,
        .errorIcon:focus-visible {
            background: var(--border-error);
            color: white !important;
            outline: none;
            transform: scale(1.05);
        }

        .field-error-msg {
            position: absolute;
            right: 0;
            bottom: calc(100% + 0.55rem);
            z-index: 100;
            width: max-content;
            min-width: 7.5rem;
            max-width: 13rem;
            padding: 0.38rem 0.58rem;
            border: 1px solid color-mix(in srgb, var(--border-error) 42%, transparent);
            border-radius: 0.5rem;
            background: var(--secondary-bg-color);
            color: var(--border-error) !important;
            font-size: 0.7rem;
            font-weight: 500;
            line-height: 1.25;
            box-shadow: 0 6px 18px rgb(15 23 42 / 0.12);
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
            transform: translateY(3px);
            transition: opacity 140ms ease, visibility 140ms ease, transform 140ms ease;
        }

        .field-error-msg::after {
            content: '';
            position: absolute;
            top: 100%;
            right: 0.55rem;
            border: 4px solid transparent;
            border-top-color: color-mix(in srgb, var(--border-error) 42%, transparent);
        }

        .errorIconWrap:hover .field-error-msg,
        .errorIconWrap:focus-within .field-error-msg {
            opacity: 1;
            visibility: visible;
            pointer-events: auto;
            transform: translateY(0);
        }

        .field-error-msg.hidden {
            display: none !important;
        }

        .form-group.has-field-error > span label {
            color: var(--border-error) !important;
        }

        .form-group.has-field-error .field-control > input:not([type='hidden']),
        .select-component.has-field-error .selectParent > .form-group input:not([type='hidden']) {
            border-color: var(--border-error) !important;
            background-color: color-mix(in srgb, var(--border-error) 4%, var(--h-bg-color)) !important;
            box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--border-error) 18%, transparent);
        }

        .form-group.has-field-error .field-control > input:not([type='hidden']):focus-visible,
        .select-component.has-field-error .selectParent > .form-group input:not([type='hidden']):focus-visible {
            outline-color: var(--border-error) !important;
            box-shadow: 0 0 0 2px color-mix(in srgb, var(--border-error) 12%, transparent) !important;
        }

        .select-component .selectParent > .form-group input {
            padding-right: 3.5rem;
        }

        .form-group.has-field-error input:disabled {
            opacity: 0.78;
        }

        .td {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .rounded-sm {
            border-radius: 0.40rem !important;
        }

        .rounded-md {
            border-radius: 0.55rem !important;
        }

        .rounded-lg {
            border-radius: 0.75rem !important;
        }

        .rounded-xl {
            border-radius: 1.05rem !important;
        }

        .rounded-2xl {
            border-radius: 1.25rem !important;
        }

        .rounded-3xl {
            border-radius: 1.45rem !important;
        }
    </style>

    @include('components.document-preview-styles')

    @vite('resources/css/app.css')
    @stack('page-styles')

    <script defer src="{{ asset('jquery.js') }}"></script>
    <script defer src="{{ asset('js/validate-inputs.js') }}"></script>
    <script defer src="{{ asset('js/utils/format.js') }}"></script>
    <script defer src="{{ asset('js/utils/ui.js') }}"></script>
    <script defer src="{{ asset('js/utils/activity.js') }}"></script>
    <script defer src="{{ asset('js/utils/notifications.js') }}"></script>
    <script defer src="{{ asset('js/utils/loader.js') }}?v={{ $assetVersion }}.loader-3"></script>
    <script defer src="{{ asset('js/utils/table.js') }}"></script>
    <script defer src="{{ asset('js/utils/layout.js') }}?v={{ $assetVersion }}.skeleton-1"></script>
    <script defer src="{{ asset('js/utils/print-columns.js') }}"></script>
    <script defer src="{{ asset('js/utils/export-excel.js') }}"></script>
    <script defer src="{{ asset('js/utils/backup.js') }}"></script>
    <script defer src="{{ asset('js/utils/modal.js') }}"></script>
    <script defer src="{{ asset('js/components/sidebar.js') }}"></script>
    <script defer src="{{ asset('js/utils/menu-customization.js') }}"></script>
    <script defer src="{{ asset('js/utils/form-submit.js') }}"></script>
    <script defer src="{{ asset('js/utils/inputs.js') }}"></script>
    <script defer src="{{ asset('js/utils/navigation.js') }}"></script>
    <script defer src="{{ asset('js/utils/readonly.js') }}"></script>
    <script defer src="{{ asset('js/utils/amounts.js') }}"></script>
    <script defer src="{{ asset('js/utils/pusher-notifications.js') }}"></script>
    <script defer src="{{ asset('js/components/select.js') }}"></script>
    <script defer src="{{ asset('js/components/input.js') }}?v={{ $assetVersion }}"></script>
    <script defer src="{{ asset('js/components/overlay-scrollbar.js') }}?v={{ $assetVersion }}"></script>
    <script defer src="{{ asset('js/app-init.js') }}"></script>

    <script defer src="{{ asset('js/components/card.js') }}"></script>
    <script defer src="{{ asset('js/components/document-print.js') }}?v={{ $assetVersion }}"></script>
    <script defer src="{{ asset('js/components/document-preview.js') }}?v={{ $assetVersion }}"></script>
    <script defer src="{{ asset('js/components/modal.js') }}?v={{ $assetVersion }}.inputs-3"></script>
    <script defer src="{{ asset('js/components/context-menu.js') }}?v={{ $assetVersion }}"></script>
    <script defer src="{{ asset('js/global-filter-manager.js') }}?v={{ $assetVersion }}.skeleton-5"></script>
</head>

<body class="bg-[var(--secondary-bg-color)] text-[var(--text-color)] text-sm min-h-screen flex flex-col md:flex-row items-stretch justify-start fade-in" cz-shortcut-listen="true" data-app-config='@json($appConfig)'>
    {{-- side bar --}}
    @if (Auth::check())
        @component('components.sidebar')
        @endcomponent
    @endif

    <!-- Loader -->
    <div id="page-loader" class="fixed inset-0 z-[9999] flex items-center justify-center bg-[var(--overlay-color)]/60 px-4 hidden">
        <div class="flex w-full max-w-xs items-center gap-4 rounded-xl border border-[var(--glass-border-color)]/20 bg-[var(--secondary-bg-color)] px-5 py-4 text-[var(--text-color)] shadow-xl">
            <div class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-[var(--h-bg-color)]">
                <div class="size-6 rounded-full border-3 border-[var(--primary-color)] border-t-transparent animate-spin"></div>
            </div>
            <div class="min-w-0">
                <p class="font-semibold leading-none">Loading</p>
                <p class="mt-1 text-xs text-[var(--secondary-text)]">Please wait...</p>
            </div>
        </div>
    </div>
    <div class="wrapper flex-1 min-w-0 min-h-screen md:h-screen flex flex-col relative w-full overflow-hidden">
        {{-- main content --}}
        <main class="flex-1 min-h-0 px-4 py-6 md:p-8 overflow-y-hidden my-scrollbar-2 flex items-center justify-center bg-[var(--bg-color)] rounded-3xl mx-2.5 md:mr-2.5 {{ request()->is('login') ? 'mt-2.5 md:ml-2.5' : 'mt-0 md:ml-0' }} md:mt-2.5 relative">
            {{-- alert --}}
            <div id="messageBox" class="absolute top-5 mx-auto flex items-center flex-col space-y-3 z-[100] text-sm w-full select-none pointer-events-none">
                @unless ($suppressPageFlashMessages)
                    @if (session('info'))
                        <x-alert type="info" :messages="session('info')" />
                    @endif

                    @if (session('success'))
                        <x-alert type="success" :messages="session('success')" />
                    @endif

                    @if (session('warning'))
                        <x-alert type="warning" :messages="session('warning')" />
                    @endif

                    @if (session('error'))
                        <x-alert type="error" :messages="session('error')" />
                    @endif
                @endunless
                @if (!request()->is('login') && !request()->is('setup') && session('license_readonly'))
                    <x-alert type="warning" :messages="'Read-only mode is enabled. You can view data but cannot make changes.'"/>
                @endif
            </div>
            <!-- Notification Box -->
            <div id="notificationBox" class="absolute top-5 right-5 flex flex-col space-y-3 z-[100] text-sm mx-auto items-end select-none">
                {{-- <x-notification
                    title="Payment Method Expiring"
                    message="Your card ending in 1122 is expiring soon. Please update your billing info."
                    actionLabel="Update Card"
                    actionUrl="/billing"
                />
                <x-notification
                    title="Payment Method Expiring"
                    message="Your card ending in 1122 is expiring soon. Please update your billing info."
                /> --}}
            </div>
            <div class="left_actions absolute top-5 left-5 flex gap-2">
                <button id="go_back_button" type="button" aria-label="Go Back" class="border border-gray-600 group bg-[var(--bg-color)] h-full rounded-xl cursor-pointer flex items-center justify-end p-1 overflow-hidden hover:pr-3 transition-all duration-300 ease-in-out">
                    <div class="flex items-center justify-center bg-[var(--h-bg-color)] rounded-lg p-2">
                        <svg class="size-4 transition-all duration-300 ease-in-out group-hover:size-3.5 fill-[var(--secondary-text)]"
                        xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24">
                        <path d="M19 12H5m6-6l-6 6 6 6" stroke="currentColor" stroke-width="2.5" fill="none"/>
                        </svg>
                    </div>
                    <span class="inline-block max-w-0 opacity-0 overflow-hidden whitespace-nowrap transition-all duration-300 ease-in-out group-hover:opacity-100 group-hover:max-w-[200px] group-hover:ml-2">
                        Go Back
                    </span>
                </button>
                <button id="refresh_button" type="button" aria-label="Refresh" class="border border-gray-600 group bg-[var(--bg-color)] h-full rounded-xl cursor-pointer flex items-center justify-end p-1 overflow-hidden hover:pr-3 transition-all duration-300 ease-in-out">
                    <div class="flex items-center justify-center bg-[var(--h-bg-color)] rounded-lg p-2">
                        <svg class="size-4 transition-all duration-300 ease-in-out group-hover:size-3.5 fill-[var(--secondary-text)]" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24">
                            <g>
                              <path d="M12 4V1L8 5l4 4V6c3.31 0 6 2.69 6 6s-2.69 6-6 6-6-2.69-6-6H4c0 4.42 3.58 8 8 8s8-3.58 8-8-3.58-8-8-8z"/>
                              <path d="M12 4V1L8 5l4 4V6c3.31 0 6 2.69 6 6s-2.69 6-6 6-6-2.69-6-6H4c0 4.42 3.58 8 8 8s8-3.58 8-8-3.58-8-8-8z" transform="translate(0.3, 0.3)" />
                            </g>
                        </svg>
                    </div>
                    <span class="inline-block max-w-0 opacity-0 overflow-hidden whitespace-nowrap transition-all duration-300 ease-in-out group-hover:opacity-100 group-hover:max-w-[200px] group-hover:ml-2">
                        Refresh
                    </span>
                </button>
                @if ($currentBranchModuleKey)
                    <x-module-branch-selector :module-key="$currentBranchModuleKey" />
                @endif
                @stack('left-actions-after')
            </div>
            <div class="main-child w-full grow">
                @yield('content')
            </div>
        </main>

        {{-- footer --}}
        @component('components.footer')
        @endcomponent
    </div>

    @if (!empty($developerUpdateStatus['update_available']))
        @php
            $latestVersion = $developerUpdateStatus['latest_version'] ?? ($developerUpdateStatus['feed']['version'] ?? 'latest');
            $currentVersion = $developerUpdateStatus['current_version'] ?? config('app.version', 'Installed');
            $releaseNotes = $developerUpdateStatus['notes'] ?? data_get($developerUpdateStatus, 'feed.notes') ?? data_get($developerUpdateStatus, 'feed.body') ?? 'Laravel prepares the update handoff. The Windows launcher applies the update outside the running app.';
            $releaseNoteItems = format_release_notes($releaseNotes);
            $mandatoryUpdate = (bool) ($developerUpdateStatus['mandatory'] ?? data_get($developerUpdateStatus, 'feed.mandatory', false));
        @endphp
        <div id="developer-update-modal"
            class="fixed inset-0 z-[9998] flex items-center justify-center bg-[var(--overlay-color)] px-4"
            data-update-version="{{ $latestVersion }}"
            data-update-mandatory="{{ $mandatoryUpdate ? '1' : '0' }}"
            data-update-session="{{ substr(hash('sha256', session()->getId()), 0, 16) }}">
            <div class="flex max-h-[90vh] w-full max-w-xl flex-col overflow-hidden rounded-xl border border-[var(--glass-border-color)]/20 bg-[var(--secondary-bg-color)] text-[var(--text-color)] shadow-2xl">
                <div class="flex shrink-0 items-start justify-between gap-4 border-b border-[var(--glass-border-color)]/10 px-5 py-4">
                    <div>
                        <h2 class="text-lg font-semibold">Update available</h2>
                        <p class="mt-1 text-xs text-[var(--secondary-text)]">Current {{ $currentVersion }} · New {{ $latestVersion }}</p>
                    </div>
                    @if (!$mandatoryUpdate || !$canManageUpdates)
                        <button type="button" class="rounded-lg px-2 py-1 text-[var(--secondary-text)] hover:bg-[var(--h-bg-color)]" data-update-modal-close aria-label="Close update dialog">
                            <i class="fas fa-xmark"></i>
                        </button>
                    @endif
                </div>
                <div class="min-h-0 grow space-y-4 overflow-y-auto px-5 py-4 my-scrollbar-2">
                    <p class="text-sm text-[var(--secondary-text)]">
                        {{ $mandatoryUpdate ? 'This update is marked mandatory. Update before continuing if your deployment policy requires it.' : 'You can update now or continue working and update later.' }}
                    </p>
                    @unless ($canManageUpdates)
                        <div class="rounded-lg border border-[var(--border-warning)] bg-[var(--bg-warning)] p-3 text-sm text-[var(--text-warning)]">
                            Update is available. Please contact an admin or developer to apply it.
                        </div>
                    @endunless
                    @if ($canManageUpdates && !$canRunUpdateActions)
                        <div class="rounded-lg border border-[var(--border-warning)] bg-[var(--bg-warning)] p-3 text-sm text-[var(--text-warning)]">
                            {{ $updateActionDeniedMessage ?: 'Updates can only be started from the server PC.' }}
                        </div>
                    @endif
                    <details class="rounded-lg border border-[var(--glass-border-color)]/10 bg-[var(--h-bg-color)] p-3 text-sm" open>
                        <summary class="cursor-pointer font-semibold">What will change</summary>
                        <div class="mt-3 max-h-60 overflow-y-auto pr-2 my-scrollbar-2">
                            @if ($releaseNoteItems)
                                <ul class="space-y-2">
                                    @foreach ($releaseNoteItems as $note)
                                        <li class="flex gap-2 rounded-lg border border-[var(--glass-border-color)]/10 bg-[var(--secondary-bg-color)] px-3 py-2 text-[var(--secondary-text)]">
                                            <span class="mt-1 h-1.5 w-1.5 shrink-0 rounded-full bg-[var(--primary-color)]"></span>
                                            <span>{{ $note }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            @else
                                <div class="rounded-lg border border-[var(--glass-border-color)]/10 bg-[var(--secondary-bg-color)] px-3 py-2 text-[var(--secondary-text)]">
                                    Release details are not available for this update.
                                </div>
                            @endif
                        </div>
                    </details>
                </div>
                <div class="flex shrink-0 flex-wrap justify-end gap-2 border-t border-[var(--glass-border-color)]/10 px-5 py-4">
                    @if (!$mandatoryUpdate || !$canManageUpdates)
                        <button type="button" class="rounded-lg border border-gray-600 bg-[var(--h-bg-color)] px-4 py-2 text-sm font-semibold text-[var(--secondary-text)]" data-update-modal-close>
                            Later
                        </button>
                    @endif
                    @if ($canManageUpdates)
                        <a href="{{ route('developer.updater') }}" class="rounded-lg border border-gray-600 bg-[var(--h-bg-color)] px-4 py-2 text-sm font-semibold text-[var(--secondary-text)]">
                            Details
                        </a>
                    @endif
                    @if ($canManageUpdates && $canRunUpdateActions && !empty($developerLauncherHandoff['protocol_url']))
                        <a href="#" data-update-start-url="{{ route('developer.updater.launcher-handoff.start') }}" class="js-update-handoff rounded-lg bg-[var(--primary-color)] px-4 py-2 text-sm font-semibold text-white">
                            Update Now
                        </a>
                    @endif
                </div>
            </div>
        </div>
    @endif

    <div id="update-handoff-overlay" class="fixed inset-0 z-[10000] {{ $activeUpdateLock ? 'flex' : 'hidden' }} items-center justify-center bg-[var(--overlay-color)] px-4">
        <div class="w-full max-w-lg rounded-xl border border-[var(--border-warning)] bg-[var(--secondary-bg-color)] p-6 text-center shadow-xl">
            <h2 class="text-xl font-semibold text-[var(--text-color)]">GarmentsOS PRO is updating</h2>
            <p class="mt-3 text-sm text-[var(--secondary-text)]">
                {{ $activeUpdateLock['message'] ?? 'Please do not close or use the app until the update is complete.' }}
            </p>
            <p class="mt-3 text-xs text-[var(--secondary-text)]">
                If Windows asks to open GarmentsOS PRO Updater, choose Open.
            </p>
            @if (!empty($activeUpdateLock['expires_at']))
                <p class="mt-3 text-xs text-[var(--secondary-text)]">
                    Lock expires at {{ $activeUpdateLock['expires_at'] }}.
                </p>
            @endif
        </div>
    </div>

    <form id="moduleBranchPreferenceForm" method="POST" action="{{ route('module-branch-preferences.store') }}" class="hidden">
        @csrf
        <input type="hidden" name="module_key" value="">
        <input type="hidden" name="branch_id" value="">
        <input type="hidden" name="selection_mode" value="single">
        <input type="hidden" name="redirect_to" value="">
        <input type="hidden" name="edit_record_token" value="{{ $branchEditRecordToken }}">
    </form>

    <script>
        window.currentUser = @json(Auth::user());
        
        document.addEventListener('DOMContentLoaded', () => {
            const syncToggleVisual = (input) => {
                const toggle = input?.closest('.app-toggle');
                if (!toggle) return;
                toggle.classList.toggle('is-checked', Boolean(input.checked));
                toggle.setAttribute('aria-checked', input.checked ? 'true' : 'false');
            };

            document.querySelectorAll('.app-toggle-input').forEach((input) => {
                syncToggleVisual(input);
                input.addEventListener('change', () => syncToggleVisual(input));
            });

            document.addEventListener('click', (event) => {
                const toggle = event.target.closest?.('.app-toggle');
                if (!toggle || event.target.matches('input')) return;

                const input = toggle.querySelector('.app-toggle-input');
                if (!input || input.disabled) return;

                event.preventDefault();
                input.checked = !input.checked;
                input.dispatchEvent(new Event('change', { bubbles: true }));
                syncToggleVisual(input);
            });

            const overlay = document.getElementById('update-handoff-overlay');
            const moduleBranchPreferenceForm = document.getElementById('moduleBranchPreferenceForm');
            const updateLockStatusUrl = @json(Auth::check() ? route('developer.updater.update-lock-status') : null);
            const updatingUrl = @json(route('updating'));
            const launchGuardKey = 'garmentsos_update_launching';
            const updateModal = document.getElementById('developer-update-modal');
            let updateLockPoll = null;
            let closeFallbackStarted = false;

            const notify = (type, title, message) => {
                if (typeof showNotification === 'function') {
                    showNotification(title, message, type);
                    return;
                }

                if (typeof showMessageBox === 'function') {
                    showMessageBox(type, message || title);
                }
            };

            document.addEventListener('click', (event) => {
                const option = event.target.closest(
                    '[data-module-branch-option]'
                );

                if (!option) {
                    return;
                }

                event.preventDefault();
                event.stopPropagation();

                if (
                    option.disabled ||
                    option.dataset.submitting === '1' ||
                    !moduleBranchPreferenceForm
                ) {
                    return;
                }

                const moduleKey =
                    option.dataset.moduleKey || '';

                const branchId =
                    option.dataset.branchId || '';

                if (!moduleKey || !branchId) {
                    return;
                }

                const switcher =
                    option.closest('.branch-switcher');

                /*
                * Prevent repeated clicks/submits.
                */
                option.dataset.submitting = '1';

                /*
                * Play selected animation before submit.
                */
                option.classList.add('is-switching');

                switcher?.classList.add(
                    'is-submitting'
                );

                /*
                * Disable all single-branch options
                * while submit is pending.
                */
                switcher
                    ?.querySelectorAll(
                        '[data-module-branch-option]'
                    )
                    .forEach((otherOption) => {
                        otherOption.disabled = true;
                    });

                /*
                * Wait briefly so animation is visible.
                */
                window.setTimeout(() => {
                    moduleBranchPreferenceForm
                        .elements
                        .module_key
                        .value = moduleKey;

                    moduleBranchPreferenceForm
                        .elements
                        .branch_id
                        .value = branchId;

                    moduleBranchPreferenceForm
                        .elements
                        .selection_mode
                        .value = 'single';

                    moduleBranchPreferenceForm
                        .querySelectorAll(
                            '[name="branch_ids[]"]'
                        )
                        .forEach((input) => {
                            input.remove();
                        });

                    moduleBranchPreferenceForm
                        .elements
                        .redirect_to
                        .value = window.location.href;

                    moduleBranchPreferenceForm.submit();
                }, 220);
            }, true);

            document.addEventListener('change', (event) => {
                const allToggle = event.target.closest(
                    '[data-module-branch-all]'
                );

                /*
                * All Branches toggle hua:
                * sari individual branches ko same state do.
                */
                if (allToggle) {
                    const switcher = allToggle.closest(
                        '.branch-switcher'
                    );

                    switcher
                        ?.querySelectorAll(
                            '[data-module-branch-checkbox]'
                        )
                        .forEach((checkbox) => {
                            checkbox.checked = allToggle.checked;
                        });

                    return;
                }

                /*
                * Individual branch toggle hui:
                * check karo kya sari branches selected hain.
                */
                const branchCheckbox = event.target.closest(
                    '[data-module-branch-checkbox]'
                );

                if (!branchCheckbox) {
                    return;
                }

                const switcher = branchCheckbox.closest(
                    '.branch-switcher'
                );

                const allBranchesToggle = switcher?.querySelector(
                    '[data-module-branch-all]'
                );

                const branchCheckboxes = Array.from(
                    switcher?.querySelectorAll(
                        '[data-module-branch-checkbox]'
                    ) || []
                );

                const allSelected =
                    branchCheckboxes.length > 0 &&
                    branchCheckboxes.every(
                        (checkbox) => checkbox.checked
                    );

                /*
                * Sari manually selected hon to
                * All Branches auto selected.
                *
                * Kisi ek ko unselect karo to
                * All Branches auto unselected.
                */
                if (allBranchesToggle) {
                    allBranchesToggle.checked = allSelected;
                }
            });

            document.addEventListener('click', (event) => {
                const apply = event.target.closest(
                    '[data-module-branch-apply]'
                );

                if (!apply) {
                    return;
                }

                event.preventDefault();
                event.stopPropagation();

                if (
                    apply.dataset.submitting === '1' ||
                    !moduleBranchPreferenceForm
                ) {
                    return;
                }

                const moduleKey =
                    apply.dataset.moduleKey || '';

                if (!moduleKey) {
                    return;
                }

                const wrapper =
                    apply.closest('.branch-switcher');

                const selectedIds = Array.from(
                    wrapper?.querySelectorAll(
                        '[data-module-branch-checkbox]:checked'
                    ) || []
                )
                    .map((checkbox) =>
                        checkbox.dataset.branchId
                    )
                    .filter(Boolean);

                /*
                * Prevent duplicate apply submits.
                */
                apply.dataset.submitting = '1';
                apply.disabled = true;

                const originalText =
                    apply.textContent;

                apply.textContent =
                    'Applying...';

                wrapper?.classList.add(
                    'is-submitting'
                );

                /*
                * Remove previous branch_ids[] fields.
                */
                moduleBranchPreferenceForm
                    .querySelectorAll(
                        '[name="branch_ids[]"]'
                    )
                    .forEach((input) => {
                        input.remove();
                    });

                /*
                * Add currently selected branches.
                */
                selectedIds.forEach((branchId) => {
                    const input =
                        document.createElement('input');

                    input.type = 'hidden';
                    input.name = 'branch_ids[]';
                    input.value = branchId;

                    moduleBranchPreferenceForm
                        .appendChild(input);
                });

                moduleBranchPreferenceForm
                    .elements
                    .module_key
                    .value = moduleKey;

                moduleBranchPreferenceForm
                    .elements
                    .branch_id
                    .value = selectedIds[0] || '';

                moduleBranchPreferenceForm
                    .elements
                    .selection_mode
                    .value = 'multiple';

                moduleBranchPreferenceForm
                    .elements
                    .redirect_to
                    .value = window.location.href;

                /*
                * Small delay gives the button feedback
                * time to appear before navigation.
                */
                window.setTimeout(() => {
                    moduleBranchPreferenceForm.submit();
                }, 180);

                /*
                * Safety fallback if submit is interrupted.
                */
                window.setTimeout(() => {
                    if (document.body.contains(apply)) {
                        apply.dataset.submitting = '0';
                        apply.disabled = false;
                        apply.textContent = originalText;

                        wrapper?.classList.remove(
                            'is-submitting'
                        );
                    }
                }, 5000);
            }, true);

            const launchGuardActive = () => {
                try {
                    const payload = JSON.parse(sessionStorage.getItem(launchGuardKey) || 'null');
                    if (!payload || !payload.expiresAt) {
                        return false;
                    }

                    if (Date.now() > payload.expiresAt) {
                        sessionStorage.removeItem(launchGuardKey);
                        return false;
                    }

                    return true;
                } catch (error) {
                    sessionStorage.removeItem(launchGuardKey);
                    return false;
                }
            };

            const setLaunchGuard = () => {
                try {
                    sessionStorage.setItem(launchGuardKey, JSON.stringify({
                        startedAt: Date.now(),
                        expiresAt: Date.now() + 60000,
                    }));
                } catch (error) {
                }
            };

            const clearLaunchGuard = () => {
                try {
                    sessionStorage.removeItem(launchGuardKey);
                } catch (error) {
                }
            };

            const updateModalDismissKey = () => {
                if (!updateModal) {
                    return null;
                }

                const version = updateModal.dataset.updateVersion || 'latest';
                const sessionKey = updateModal.dataset.updateSession || 'session';
                return `garmentsos_update_modal_dismissed:${sessionKey}:${version}`;
            };

            const updateModalIsMandatory = () => updateModal?.dataset.updateMandatory === '1';

            const removeUpdateModal = (rememberDismissal = false) => {
                if (rememberDismissal && updateModal && !updateModalIsMandatory()) {
                    const dismissKey = updateModalDismissKey();
                    if (dismissKey) {
                        try {
                            sessionStorage.setItem(dismissKey, JSON.stringify({
                                dismissedAt: Date.now(),
                            }));
                        } catch (error) {
                        }
                    }
                }

                updateModal?.remove();
            };

            if (updateModal && !updateModalIsMandatory()) {
                const dismissKey = updateModalDismissKey();
                try {
                    if (dismissKey && sessionStorage.getItem(dismissKey)) {
                        removeUpdateModal(false);
                    }
                } catch (error) {
                }
            }

            document.querySelectorAll('[data-update-modal-close]').forEach((button) => {
                button.addEventListener('click', () => {
                    removeUpdateModal(true);
                });
            });

            const closeOrReplaceWithUpdating = (delay = 4000) => {
                if (closeFallbackStarted) {
                    return;
                }

                closeFallbackStarted = true;

                window.setTimeout(() => {
                    try {
                        window.close();
                    } catch (error) {
                    }

                    try {
                        window.open('', '_self');
                        window.close();
                    } catch (error) {
                    }

                    try {
                        window.location.replace(updatingUrl);
                    } catch (error) {
                        window.location.href = updatingUrl;
                    }
                }, delay);
            };

            const launchUpdaterProtocol = (protocolUrl) => {
                const iframe = document.createElement('iframe');
                iframe.style.display = 'none';
                iframe.setAttribute('aria-hidden', 'true');
                document.body.appendChild(iframe);

                try {
                    iframe.src = protocolUrl;
                } catch (error) {
                }

                window.setTimeout(() => {
                    iframe.remove();
                }, 5000);
            };

            const showUpdateOverlay = () => {
                if (overlay) {
                    overlay.classList.remove('hidden');
                    overlay.classList.add('flex');
                }
                document.querySelectorAll('button, input, select, textarea').forEach((element) => {
                    if (!element.closest('#update-handoff-overlay')) {
                        if (!element.hasAttribute('disabled')) {
                            element.dataset.updateLockDisabled = '1';
                        }
                        element.setAttribute('disabled', 'disabled');
                    }
                });
            };

            const hideUpdateOverlay = () => {
                if (overlay) {
                    overlay.classList.add('hidden');
                    overlay.classList.remove('flex');
                }
                document.querySelectorAll('[data-update-lock-disabled="1"]').forEach((element) => {
                    element.removeAttribute('disabled');
                    delete element.dataset.updateLockDisabled;
                });
            };

            const pollUpdateLock = () => {
                if (!updateLockStatusUrl || updateLockPoll) {
                    return;
                }

                updateLockPoll = window.setInterval(async () => {
                    try {
                        const response = await fetch(updateLockStatusUrl, {
                            headers: {
                                'Accept': 'application/json',
                            },
                        });
                        if (!response.ok) {
                            return;
                        }

                        const payload = await response.json();
                        if (payload && payload.updating === false) {
                            window.clearInterval(updateLockPoll);
                            updateLockPoll = null;
                            hideUpdateOverlay();
                            window.location.reload();
                        }
                    } catch (error) {
                        // Keep the overlay visible while the app/container is restarting.
                    }
                }, 5000);
            };

            @if ($activeUpdateLock)
                showUpdateOverlay();
                pollUpdateLock();
                @unless (request()->routeIs('developer.updater'))
                    closeOrReplaceWithUpdating();
                @endunless
            @endif

            document.querySelectorAll('.js-update-handoff').forEach((link) => {
                link.addEventListener('click', async (event) => {
                    event.preventDefault();
                    if (link.dataset.busy === '1' || launchGuardActive()) {
                        notify('warning', 'Launcher opening', 'Update launcher is already opening.');
                        return;
                    }

                    const startUrl = link.dataset.updateStartUrl;
                    if (!startUrl) {
                        return;
                    }

                    link.dataset.busy = '1';
                    link.setAttribute('aria-disabled', 'true');
                    const originalText = link.textContent;
                    link.textContent = 'Launcher opening...';
                    document.querySelectorAll('.js-update-handoff').forEach((otherLink) => {
                        otherLink.dataset.busy = '1';
                        otherLink.setAttribute('aria-disabled', 'true');
                    });
                    setLaunchGuard();
                    showUpdateOverlay();
                    pollUpdateLock();

                    try {
                        const response = await fetch(startUrl, {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                            },
                            body: JSON.stringify({}),
                        });

                        const payload = await response.json().catch(() => ({}));
                        if (!response.ok || !payload.protocol_url) {
                            throw new Error(payload.message || 'Update could not be started.');
                        }

                        try {
                            sessionStorage.setItem(launchGuardKey, JSON.stringify({
                                startedAt: Date.now(),
                                expiresAt: Date.now() + 60000,
                                requestId: payload.request_id || null,
                            }));
                        } catch (error) {
                        }

                        launchUpdaterProtocol(payload.protocol_url);
                        closeOrReplaceWithUpdating(4500);
                    } catch (error) {
                        clearLaunchGuard();
                        notify('error', 'Update could not be started', error.message || 'Update could not be started.');
                        link.dataset.busy = '0';
                        link.removeAttribute('aria-disabled');
                        link.textContent = originalText;
                        document.querySelectorAll('.js-update-handoff').forEach((otherLink) => {
                            otherLink.dataset.busy = '0';
                            otherLink.removeAttribute('aria-disabled');
                        });
                        hideUpdateOverlay();
                    }
                });
            });

            document.addEventListener('submit', (event) => {
                if (overlay && !overlay.classList.contains('hidden')) {
                    event.preventDefault();
                }
            }, true);

            if (window.fetch) {
                const originalFetch = window.fetch.bind(window);
                window.fetch = async (...args) => {
                    const response = await originalFetch(...args);
                    if (response.status === 423 || response.status === 503) {
                        const cloned = response.clone();
                        cloned.json().then((payload) => {
                            if (payload?.updating) {
                                showUpdateOverlay();
                                pollUpdateLock();
                                closeOrReplaceWithUpdating();
                            }
                        }).catch(() => {});
                    }

                    return response;
                };
            }
        });
    </script>

@stack('page-scripts')
</body>
</html>
