<?php

use App\Services\Settings\LabelSettingsService;
use App\Services\Settings\ModuleAvailabilityService;
use App\Services\Access\AppPermissionService;
use App\Services\Branches\ModuleBranchService;

if (!function_exists('label_text')) {
    function label_text(string $key, ?string $fallback = null): string
    {
        try {
            return app(LabelSettingsService::class)->text($key, $fallback);
        } catch (Throwable) {
            return $fallback ?? (string) config('labels.' . $key, $key);
        }
    }
}

if (!function_exists('module_enabled')) {
    function module_enabled(string $key): bool
    {
        try {
            $requiredModules = [
                'auth',
                'auth_login',
                'setup',
                'first_run_setup',
                'subscription_expired',
                'branch_logo_delivery',
                'home',
                'dashboard',
                'developer_settings',
                'developer_branches',
                'developer_backups',
                'developer_updater',
                'developer_license',
                'developer_audit_logs',
            ];

            if (in_array($key, $requiredModules, true)) {
                return true;
            }

            $branchModules = app(ModuleBranchService::class);
            $branchConfig = $branchModules->runtimeModuleConfig($key);
            $requiresBranchEnabled = (bool) (
                ($branchConfig['branchable'] ?? false)
                || ($branchConfig['supports_branch_selector'] ?? false)
                || ($branchConfig['supports_multi_branch_selector'] ?? false)
                || ($branchConfig['can_filter_records'] ?? false)
            );

            return app(ModuleAvailabilityService::class)->isEffectivelyVisibleInSidebar($key)
                && (!$requiresBranchEnabled || $branchModules->isEnabled($key))
                && app(AppPermissionService::class)->canCurrent($key, 'view');
        } catch (Throwable) {
            return true;
        }
    }
}

if (!function_exists('app_can')) {
    function app_can(string $moduleKey, string $action = 'view'): bool
    {
        try {
            return app(AppPermissionService::class)->canCurrent($moduleKey, $action);
        } catch (Throwable) {
            return in_array($action, ['override', 'developer'], true) ? false : true;
        }
    }
}

if (!function_exists('app_has_permission_rule')) {
    function app_has_permission_rule(string $moduleKey): bool
    {
        try {
            return app(AppPermissionService::class)->hasCurrentRule($moduleKey);
        } catch (Throwable) {
            return false;
        }
    }
}

if (!function_exists('app_menu_can')) {
    function app_menu_can(string $moduleKey, array $fallbackRoles = [], string $action = 'view'): bool
    {
        try {
            $user = auth()->user();
            if (!$user) {
                return false;
            }

            $branches = app(ModuleBranchService::class);
            $registry = $branches->moduleRegistry();
            $canonical = $branches->canonicalModuleKey($moduleKey);

            if (array_key_exists($canonical, app(\App\Services\Settings\ModuleSettingsService::class)->registry())) {
                if (!module_enabled($canonical)) {
                    return false;
                }
            } elseif (array_key_exists($canonical, $registry)) {
                $config = $branches->runtimeModuleConfig($canonical);
                $requiresBranchEnabled = (bool) (
                    ($config['branchable'] ?? false)
                    || ($config['supports_branch_selector'] ?? false)
                    || ($config['supports_multi_branch_selector'] ?? false)
                    || ($config['can_filter_records'] ?? false)
                );

                if ($requiresBranchEnabled && !$branches->isEnabled($canonical)) {
                    return false;
                }
            }

            if (app_has_permission_rule($canonical)) {
                return app_can($canonical, $action);
            }

            return in_array($user->role, $fallbackRoles, true);
        } catch (Throwable) {
            return in_array(auth()->user()?->role, $fallbackRoles, true);
        }
    }
}

if (!function_exists('format_release_notes')) {
    function format_release_notes(mixed $notes): array
    {
        if (is_array($notes)) {
            $items = $notes;
        } else {
            $text = trim((string) $notes);
            if ($text === '') {
                return [];
            }

            $decoded = json_decode($text, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $items = $decoded;
            } else {
                $normalized = preg_replace('/\r\n?/', "\n", $text);
                $normalized = preg_replace('/(?:^|\n)\s*[-*•]\s+/u', "\n", $normalized);
                $normalized = preg_replace('/\s*(?:;|\|)\s*/', "\n", $normalized);
                $normalized = preg_replace('/(?<=[.!?])\s+(?=[A-Z0-9])/', "\n", $normalized);
                $items = preg_split('/\n+/', $normalized) ?: [];
            }
        }

        return collect($items)
            ->flatMap(function ($item) {
                if (is_array($item)) {
                    return collect($item)->flatten()->all();
                }

                return [$item];
            })
            ->map(fn ($item) => trim(preg_replace('/^\s*[-*•]\s*/u', '', (string) $item)))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
