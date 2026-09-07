<?php

namespace App\Http\Middleware;

use App\Services\Branches\BranchModuleRegistryService;
use App\Services\Branches\ModuleBranchService;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class SelectRecordBranchForEdit
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!Auth::check() || strtoupper($request->method()) !== 'GET') {
            return $next($request);
        }

        $route = $request->route();
        $action = strtolower((string) ($route?->getActionMethod() ?? ''));
        $routeName = (string) ($route?->getName() ?? '');

        if (!in_array($action, ['edit', 'show'], true) && !str_ends_with($routeName, '.edit') && !str_ends_with($routeName, '.show')) {
            return $next($request);
        }

        try {
            $moduleKey = app(BranchModuleRegistryService::class)->moduleKeyForRoute($route);
            if (!$moduleKey) {
                return $next($request);
            }

            $record = collect($route?->parameters() ?? [])
                ->first(fn ($parameter) => $parameter instanceof Model
                    && Schema::hasColumn($parameter->getTable(), 'branch_id'));

            if ($record) {
                app(ModuleBranchService::class)->selectRecordBranchForReadRoute($record, $moduleKey);
            }
        } catch (\Throwable) {
            return $next($request);
        }

        return $next($request);
    }
}
