<?php

namespace App\Services\Access;

use App\Models\AppPermissionRule;
use App\Models\User;
use App\Services\Branches\BranchModuleRegistryService;
use Illuminate\Support\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class AppPermissionService
{
    protected ?bool $permissionTableReady = null;

    protected array $applicableRuleCache = [];

    public function can(?User $user, ?string $moduleKey, string $action = 'view'): bool
    {
        if (!$user || !$moduleKey) {
            return true;
        }

        if ($user->role === 'developer') {
            return true;
        }

        if (!$this->tableReady()) {
            return true;
        }

        $moduleKey = app(BranchModuleRegistryService::class)->canonicalKey($moduleKey);
        $field = $this->fieldForAction($action);
        $rule = $this->bestRuleFor($user, $moduleKey);

        if (!$rule) {
            return $field === 'can_override' ? false : true;
        }

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
        if (!$user || !$moduleKey || !$this->tableReady()) {
            return false;
        }

        $moduleKey = app(BranchModuleRegistryService::class)->canonicalKey($moduleKey);

        return $this->bestRuleFor($user, $moduleKey) !== null;
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

    protected function bestRuleFor(User $user, string $moduleKey): ?AppPermissionRule
    {
        return $this->applicableRules($user)
            ->filter(fn (AppPermissionRule $rule) => $rule->module_key === null || $rule->module_key === $moduleKey)
            ->sortBy(fn (AppPermissionRule $rule) => match (true) {
                (int) $rule->user_id === (int) $user->id && $rule->module_key !== null => 0,
                (int) $rule->user_id === (int) $user->id && $rule->module_key === null => 1,
                $rule->user_id === null && $rule->module_key !== null => 2,
                default => 3,
            })
            ->first();
    }

    protected function applicableRules(User $user): Collection
    {
        $cacheKey = $user->id . ':' . $user->role;

        if (array_key_exists($cacheKey, $this->applicableRuleCache)) {
            return $this->applicableRuleCache[$cacheKey];
        }

        return $this->applicableRuleCache[$cacheKey] = AppPermissionRule::query()
            ->where(function ($query) use ($user) {
                $query->where('user_id', $user->id)
                    ->orWhere(function ($roleQuery) use ($user) {
                        $roleQuery->whereNull('user_id')->where('role', $user->role);
                    });
            })
            ->get();
    }

    protected function tableReady(): bool
    {
        if ($this->permissionTableReady !== null) {
            return $this->permissionTableReady;
        }

        try {
            return $this->permissionTableReady = Schema::hasTable('app_permission_rules');
        } catch (\Throwable) {
            return $this->permissionTableReady = false;
        }
    }
}
