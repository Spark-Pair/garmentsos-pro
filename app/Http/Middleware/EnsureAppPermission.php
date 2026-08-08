<?php

namespace App\Http\Middleware;

use App\Services\Access\AppPermissionService;
use App\Services\Branches\ModuleBranchService;
use App\Services\Settings\ModuleAvailabilityService;
use App\Services\Settings\ModuleSettingsService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAppPermission
{
    private const BYPASS_MODULES = [
        'auth',
        'auth_login',
        'setup',
        'first_run_setup',
        'subscription_expired',
        'branch_logo_delivery',
        'developer_settings',
        'developer_branches',
        'developer_backups',
        'developer_updater',
        'developer_license',
        'developer_audit_logs',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $permissions = app(AppPermissionService::class);
        $moduleKey = $permissions->moduleForRequest($request);

        if ($moduleKey && in_array($moduleKey, self::BYPASS_MODULES, true)) {
            return $next($request);
        }

        $branchModules = app(ModuleBranchService::class);
        $branchConfig = $moduleKey ? $branchModules->runtimeModuleConfig($moduleKey) : [];
        $requiresBranchEnabled = (bool) (
            ($branchConfig['branchable'] ?? false)
            || ($branchConfig['supports_branch_selector'] ?? false)
            || ($branchConfig['supports_multi_branch_selector'] ?? false)
            || ($branchConfig['can_filter_records'] ?? false)
        );

        if (
            $moduleKey
            && !$request->routeIs('home')
            && (
                (array_key_exists($moduleKey, app(ModuleSettingsService::class)->registry())
                    && !app(ModuleAvailabilityService::class)->isEffectivelyEnabled($moduleKey))
                || ($requiresBranchEnabled && !$branchModules->isEnabled($moduleKey))
            )
        ) {
            $message = 'This module is currently disabled.';

            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 403);
            }

            return redirect()->route('home')->with('error', $message);
        }

        $action = $permissions->actionForRequest($request);
        $allowed = $moduleKey
            ? $permissions->can($request->user(), $moduleKey, $action)
            : true;

        if (
            !$allowed
            && $moduleKey === 'articles'
            && $request->isMethod('get')
            && str_ends_with((string) $request->route()?->getName(), '.edit')
        ) {
            $allowed = $permissions->can($request->user(), $moduleKey, 'override');
        }

        if (!$moduleKey || $allowed) {
            return $next($request);
        }

        $message = 'You do not have permission to access this action.';

        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 403);
        }

        return redirect()->route('home')->with('error', $message);
    }
}
