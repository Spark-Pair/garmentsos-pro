<?php

namespace App\Services\Branches;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class BranchModuleRegistryService
{
    private ?array $registryCache = null;
    private ?array $labelsCache = null;
    private ?array $aliasMapCache = null;
    private ?Collection $discoveredRouteModulesCache = null;
    private array $configCache = [];
    private array $aliasesForCache = [];
    private array $tableNamesCache = [];
    private array $schemaTableCache = [];
    private array $schemaColumnCache = [];
    private ?array $branchModulesConfigCache = null;

    public function registry(): array
    {
        if ($this->registryCache !== null) {
            return $this->registryCache;
        }

        $configured = collect($this->configuredModules())
            ->mapWithKeys(fn (array $module, string $key) => [
                $this->normalizeKey($key) => $this->normalizeModule($this->normalizeKey($key), $module, true, false),
            ]);

        $discovered = $this->discoverRouteModules();

        return $this->registryCache = $configured
            ->keys()
            ->merge($discovered->keys())
            ->unique()
            ->mapWithKeys(function (string $key) use ($configured, $discovered) {
                $configuredModule = $configured->get($key, []);
                $discoveredModule = $discovered->get($key, []);

                $module = array_replace_recursive($discoveredModule, $configuredModule);
                $module['route_prefixes'] = array_values(array_unique(array_merge(
                    $discoveredModule['route_prefixes'] ?? [],
                    $configuredModule['route_prefixes'] ?? [],
                )));

                return [$key => $this->normalizeModule($key, $module, $configured->has($key), $discovered->has($key))];
            })
            ->sortBy(fn (array $module) => ($module['group'] ?? 'ZZZ') . '|' . ($module['label'] ?? ''))
            ->all();
    }

    public function labels(): array
    {
        return $this->labelsCache ??= collect($this->registry())
            ->mapWithKeys(fn (array $module, string $key) => [$key => $module['label']])
            ->all();
    }

    public function canonicalKey(string $moduleKey): string
    {
        $key = $this->normalizeKey($moduleKey);

        return $this->aliasMap()[$key] ?? $key;
    }

    public function configFor(string $moduleKey): ?array
    {
        $key = $this->canonicalKey($moduleKey);

        return $this->configCache[$key] ??= ($this->registry()[$key] ?? null);
    }

    public function aliasesFor(string $moduleKey): array
    {
        $canonical = $this->canonicalKey($moduleKey);

        return $this->aliasesForCache[$canonical] ??= collect($this->aliasMap())
            ->filter(fn (string $target) => $target === $canonical)
            ->keys()
            ->push($canonical)
            ->unique()
            ->values()
            ->all();
    }

    public function moduleKeyForRoute(?object $route): ?string
    {
        if (!$route || !method_exists($route, 'uri')) {
            return null;
        }

        $uri = trim((string) $route->uri(), '/');
        $name = method_exists($route, 'getName') ? $route->getName() : null;

        $namedModule = $this->moduleKeyForRouteName((string) $name);
        if ($namedModule && $this->configFor($namedModule)) {
            return $this->canonicalKey($namedModule);
        }

        if ($this->isIgnoredRoute($uri, $name)) {
            return null;
        }

        $moduleKey = $this->routeModuleKey($uri, $name);

        return $moduleKey && $this->configFor($moduleKey) ? $moduleKey : null;
    }

    private function discoverRouteModules(): Collection
    {
        if ($this->discoveredRouteModulesCache !== null) {
            return $this->discoveredRouteModulesCache;
        }

        return $this->discoveredRouteModulesCache = collect(Route::getRoutes())
            ->map(fn ($route) => [
                'uri' => trim($route->uri(), '/'),
                'name' => $route->getName(),
                'action' => $route->getActionName(),
            ])
            ->map(function (array $route) {
                $key = $this->routeModuleKey($route['uri'], $route['name']);

                return $key ? [$key, $route] : null;
            })
            ->filter()
            ->reject(fn (array $entry) => $this->isIgnoredRoute($entry[1]['uri'], $entry[1]['name']))
            ->groupBy(fn (array $entry) => $entry[0])
            ->map(function (Collection $entries, string $key) {
                $routes = $entries->pluck(1);

                return $this->normalizeModule($key, [
                    'label' => Str::headline($key),
                    'group' => 'Detected / Needs Configuration',
                    'route_prefixes' => $routes->pluck('name')->filter()->unique()->values()->all(),
                    'page_reference' => $routes->pluck('uri')->filter()->unique()->take(3)->implode(', '),
                    'notes' => 'Detected from Laravel routes. Review and configure branch behavior if this page should be branch-aware.',
                    'configured' => false,
                    'discovered' => true,
                ], false, true);
            });
    }

    private function routeModuleKey(string $uri, ?string $name): ?string
    {
        $uri = trim($uri, '/');
        $name = (string) $name;

        $namedModule = $this->moduleKeyForRouteName($name);
        if ($namedModule) {
            return $this->canonicalKey($namedModule);
        }

        $first = $uri === '' ? 'home' : explode('/', $uri)[0];

        if ($uri === '' || $first === 'home') {
            return 'home';
        }

        if ($first === 'setup') {
            return 'first_run_setup';
        }

        if ($first === 'login' || $first === 'logout') {
            return 'auth_login';
        }

        if ($first === 'subscription-expired') {
            return 'subscription_expired';
        }

        if ($first === 'developer') {
            $second = explode('/', $uri)[1] ?? '';

            return match ($second) {
                'settings' => 'developer_settings',
                'branches' => 'developer_branches',
                'backups' => 'developer_backups',
                'license' => 'developer_license',
                'updater' => 'developer_updater',
                'audit-logs' => 'developer_audit_logs',
                default => $second ? 'developer_' . $this->normalizeKey($second) : null,
            };
        }

        if ($first === 'reports') {
            $second = explode('/', $uri)[1] ?? '';

            return match ($second) {
                'article' => 'reports_article',
                'fabric' => 'reports_fabric',
                'statement' => 'reports_statement',
                'pending-payments' => 'reports_pending_payments',
                'physical-quantity' => 'reports_physical_quantity',
                default => 'reports',
            };
        }

        return $this->canonicalKey($first);
    }

    private function moduleKeyForRouteName(string $name): ?string
    {
        if ($name === '') {
            return null;
        }

        $map = [
            'add-rate' => 'articles',
            'update-image' => 'articles',
            'update-supplier-category' => 'suppliers',
            'update-bank-account-status' => 'bank_accounts',
            'update-employee-status' => 'employees',
            'users.reset-password' => 'users',
            'get-order-details' => 'orders',
            'get-program-details' => 'payment_programs',
            'payment-programs.update-program' => 'payment_programs',
            'get-shipment-details' => 'shipments',
            'get-voucher-details' => 'vouchers',
            'set-voucher-type' => 'vouchers',
            'set-invoice-type' => 'invoices',
            'print-invoices' => 'invoices',
            'set-production-type' => 'productions',
            'get-payments-by-method' => 'vouchers',
            'get-employees-by-category' => 'employees',
            'set-daily-ledger-type' => 'daily_ledger',
            'get-utility-accounts' => 'utility_accounts',
            'set-cr-type' => 'cr',
            'set-statement-type' => 'reports_statement',
            'set-physical-quantity-report-type' => 'reports_physical_quantity',
            'set-fabric-report-type' => 'reports_fabric',
            'reports.statement.get-names' => 'reports_statement',
        ];

        return $map[$name] ?? null;
    }

    private function isIgnoredRoute(string $uri, ?string $name): bool
    {
        $ignored = collect($this->ignoredRoutePrefixes())
            ->map(fn (string $prefix) => trim($prefix, '/'))
            ->all();
        $firstSegment = explode('/', trim($uri, '/') ?: '/')[0];

        return in_array($firstSegment, $ignored, true)
            || str_starts_with((string) $name, 'ignition.')
            || str_starts_with($uri, '_ignition');
    }

    private function normalizeModule(string $key, array $module, bool $configured, bool $discovered): array
    {
        $configurable = (bool) ($module['configurable_by_developer'] ?? $module['branchable'] ?? false);
        $supportsSelector = (bool) ($module['supports_branch_selector'] ?? $module['branchable'] ?? false);
        $branchable = (bool) ($module['branchable'] ?? $supportsSelector);
        $hasBranchIdSupport = $this->hasBranchIdSupport($key, $module);
        $supportsFiltering = (bool) ($module['supports_record_filtering'] ?? $module['can_filter_records'] ?? false);
        $canFilter = (bool) ($module['can_filter_records'] ?? $supportsFiltering);
        $canBrand = (bool) ($module['can_use_branch_branding'] ?? $module['supports_branch_branding'] ?? false);
        $canSerial = (bool) ($module['supports_serial_prefix'] ?? $module['supports_branch_serial_prefix'] ?? false);

        return array_replace([
            'module_key' => $key,
            'label' => Str::headline($key),
            'group' => 'Detected / Needs Configuration',
            'route_prefixes' => [],
            'page_reference' => $key,
            'table_names' => $this->tableNamesFor($key, $module),
            'configurable_by_developer' => false,
            'branchable' => $branchable,
            'supports_branch_selector' => $supportsSelector,
            'supports_multi_branch_selector' => false,
            'supports_record_filtering' => $supportsFiltering,
            'can_filter_records' => $canFilter,
            'has_branch_id_support' => $hasBranchIdSupport,
            'supports_branch_branding' => $canBrand,
            'can_use_branch_branding' => $canBrand,
            'supports_serial_prefix' => $canSerial,
            'supports_branch_branding' => $canBrand,
            'supports_branch_serial_prefix' => $canSerial,
            'supports_doc_identity_prefix' => false,
            'safe_default_enabled' => false,
            'is_system_module' => !$supportsSelector,
            'configured' => $configured,
            'discovered' => $discovered,
            'dependencies' => null,
            'notes' => 'Detected from application routes. Needs branch behavior review.',
        ], $module, [
            'module_key' => $key,
            'route_prefixes' => array_values(array_unique($module['route_prefixes'] ?? [])),
            'table_names' => $this->tableNamesFor($key, $module),
            'configurable_by_developer' => $configurable,
            'branchable' => $branchable,
            'supports_branch_selector' => $supportsSelector,
            'supports_record_filtering' => $supportsFiltering,
            'can_filter_records' => $canFilter,
            'has_branch_id_support' => $hasBranchIdSupport,
            'supports_branch_branding' => $canBrand,
            'can_use_branch_branding' => $canBrand,
            'supports_serial_prefix' => $canSerial,
            'supports_branch_serial_prefix' => $canSerial,
            'is_system_module' => (bool) ($module['is_system_module'] ?? !$supportsSelector),
            'configured' => $configured,
            'discovered' => $discovered,
        ]);
    }

    private function hasBranchIdSupport(string $key, array $module): bool
    {
        $tables = $this->tableNamesFor($key, $module);
        if ($tables === []) {
            return (bool) ($module['has_branch_id_support'] ?? false);
        }

        return collect($tables)->every(fn (string $table) => $this->schemaHasColumn($table, 'branch_id'));
    }

    private function tableNamesFor(string $key, array $module): array
    {
        $cacheKey = $key . ':' . md5(json_encode([
            $module['table_names'] ?? null,
            $module['table_name'] ?? null,
        ]));

        if (array_key_exists($cacheKey, $this->tableNamesCache)) {
            return $this->tableNamesCache[$cacheKey];
        }

        if (!empty($module['table_names']) && is_array($module['table_names'])) {
            return $this->tableNamesCache[$cacheKey] = array_values($module['table_names']);
        }

        if (!empty($module['table_name'])) {
            return $this->tableNamesCache[$cacheKey] = [(string) $module['table_name']];
        }

        $tables = [
            'daily_ledger' => ['daily_ledger_deposits', 'daily_ledger_uses'],
            'cr' => ['c_r_s'],
            'dr' => ['d_r_s'],
            'fabrics' => ['fabrics', 'issued_fabrics', 'return_fabrics'],
            'attendances' => ['attendances', 'salaries'],
            'reports_statement' => [],
            'reports_pending_payments' => [],
            'reports_physical_quantity' => [],
            'reports_article' => [],
            'reports_fabric' => [],
            'reports' => [],
        ][$key] ?? [$key];

        return $this->tableNamesCache[$cacheKey] = collect($tables)
            ->filter(fn (string $table) => $table !== '' && $this->schemaHasTable($table))
            ->values()
            ->all();
    }

    private function normalizeKey(string $key): string
    {
        return Str::of($key)->replace(['.', '-'], '_')->snake()->toString();
    }

    private function branchModulesConfig(): array
    {
        if ($this->branchModulesConfigCache !== null) {
            return $this->branchModulesConfigCache;
        }

        $config = config('branch_modules');
        if (is_array($config)) {
            return $this->branchModulesConfigCache = $config;
        }

        $path = config_path('branch_modules.php');

        return $this->branchModulesConfigCache = is_file($path) ? require $path : [];
    }

    private function configuredModules(): array
    {
        return $this->branchModulesConfig()['modules'] ?? [];
    }

    private function configuredAliases(): array
    {
        return $this->branchModulesConfig()['aliases'] ?? [];
    }

    private function ignoredRoutePrefixes(): array
    {
        return $this->branchModulesConfig()['ignored_route_prefixes'] ?? [];
    }

    private function aliasMap(): array
    {
        return $this->aliasMapCache ??= collect($this->configuredAliases())
            ->mapWithKeys(fn (string $target, string $alias) => [$this->normalizeKey($alias) => $this->normalizeKey($target)])
            ->all();
    }

    private function schemaHasTable(string $table): bool
    {
        return $this->schemaTableCache[$table] ??= Schema::hasTable($table);
    }

    private function schemaHasColumn(string $table, string $column): bool
    {
        $key = $table . '.' . $column;

        return $this->schemaColumnCache[$key] ??= ($this->schemaHasTable($table) && Schema::hasColumn($table, $column));
    }
}
