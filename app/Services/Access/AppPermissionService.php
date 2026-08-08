<?php

namespace App\Services\Access;

use App\Models\AppPermissionRule;
use App\Models\User;
use App\Services\Branches\BranchModuleRegistryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class AppPermissionService
{
    public function can(?User $user, ?string $moduleKey, string $action = 'view'): bool
    {
        if (!$user || !$moduleKey) {
            return true;
        }

        if ($user->role === 'developer') {
            return true;
        }

        if (!Schema::hasTable('app_permission_rules')) {
            return true;
        }

        $moduleKey = app(BranchModuleRegistryService::class)->canonicalKey($moduleKey);
        $field = $this->fieldForAction($action);
        $base = AppPermissionRule::query()
            ->where(function ($query) use ($moduleKey) {
                $query->whereNull('module_key')->orWhere('module_key', $moduleKey);
            });

        $query = (clone $base)->where(function ($query) use ($user) {
            $query->where('user_id', $user->id)
                ->orWhere(function ($roleQuery) use ($user) {
                    $roleQuery->whereNull('user_id')->where('role', $user->role);
                });
        });

        if (!$query->exists()) {
            return $field === 'can_override' ? false : true;
        }

        $rule = (clone $query)
            ->orderByRaw('case when user_id = ? and module_key is not null then 0 when user_id = ? and module_key is null then 1 when user_id is null and module_key is not null then 2 else 3 end', [$user->id, $user->id])
            ->first();

        if (
            $field !== 'can_override'
            && in_array($action, ['update', 'edit', 'delete', 'destroy'], true)
            && (bool) ($rule?->can_override ?? false)
        ) {
            return true;
        }

        return (bool) ($rule?->{$field} ?? false);
    }

    public function canCurrent(?string $moduleKey, string $action = 'view'): bool
    {
        return $this->can(Auth::user(), $moduleKey, $action);
    }

    public function hasRule(?User $user, ?string $moduleKey): bool
    {
        if (!$user || !$moduleKey || !Schema::hasTable('app_permission_rules')) {
            return false;
        }

        $moduleKey = app(BranchModuleRegistryService::class)->canonicalKey($moduleKey);

        return AppPermissionRule::query()
            ->where(function ($query) use ($moduleKey) {
                $query->whereNull('module_key')->orWhere('module_key', $moduleKey);
            })
            ->where(function ($query) use ($user) {
                $query->where('user_id', $user->id)
                    ->orWhere(function ($roleQuery) use ($user) {
                        $roleQuery->whereNull('user_id')->where('role', $user->role);
                    });
            })
            ->exists();
    }

    public function hasCurrentRule(?string $moduleKey): bool
    {
        return $this->hasRule(Auth::user(), $moduleKey);
    }

    public function moduleForRequest(Request $request): ?string
    {
        return app(BranchModuleRegistryService::class)->moduleKeyForRoute($request->route());
    }

    public function actionForRequest(Request $request): string
    {
        $routeName = (string) $request->route()?->getName();
        $last = str($routeName)->afterLast('.')->toString();

        if ($request->isMethod('delete') || $last === 'destroy') {
            return 'delete';
        }

        if ($request->isMethod('put') || $request->isMethod('patch') || in_array($last, ['edit', 'update', 'status'], true)) {
            return 'update';
        }

        if ($request->isMethod('post') || in_array($last, ['create', 'store'], true)) {
            return 'create';
        }

        return 'view';
    }

    public function fieldForAction(string $action): string
    {
        return match ($action) {
            'create' => 'can_create',
            'update', 'edit' => 'can_update',
            'delete', 'destroy' => 'can_delete',
            'override', 'developer' => 'can_override',
            default => 'can_view',
        };
    }
}
