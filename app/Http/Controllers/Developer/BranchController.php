<?php

namespace App\Http\Controllers\Developer;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\BranchModuleSetting;
use App\Models\BranchUserAccess;
use App\Models\AppPermissionRule;
use App\Models\User;
use App\Services\Branches\ModuleBranchService;
use App\Services\Settings\ModuleSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class BranchController extends Controller
{
    public function index(Request $request, ModuleBranchService $branches)
    {
        if ($resp = $this->denyIfNoRole(['developer'])) {
            return $resp;
        }

        $branches->ensureMainBranch();
        $branches->backfillManagerAccess();

        $branchRows = Branch::query()
            ->orderByDesc('is_main')
            ->orderBy('name')
            ->get();

        if ($request->ajax()) {
            return response()->json([
                'data' => $this->formattedBranchRows($branchRows, $request),
                'authLayout' => 'table',
            ]);
        }

        return view('developer.branches.index', [
            'branches' => $branchRows,
            'branchRows' => $this->formattedBranchRows($branchRows, $request),
            'moduleLabels' => $branches->moduleLabels(),
        ]);
    }

    protected function formattedBranchRows($branches, Request $request)
    {
        $name = trim((string) $request->query('name', ''));
        $code = trim((string) $request->query('code', ''));
        $status = trim((string) $request->query('status', ''));
        $limit = (int) $request->query('limit', 0);

        return $branches
            ->filter(function (Branch $branch) use ($name, $code, $status) {
                if ($name !== '' && stripos($branch->displayName(), $name) === false) {
                    return false;
                }

                if ($code !== '' && stripos((string) $branch->code, $code) === false) {
                    return false;
                }

                if ($status !== '' && strcasecmp((string) $branch->status, $status) !== 0) {
                    return false;
                }

                return true;
            })
            ->when($limit > 0, fn ($rows) => $rows->take($limit))
            ->map(fn (Branch $branch) => $this->formattedBranchRow($branch))
            ->values();
    }

    protected function formattedBranchRow(Branch $branch): array
    {
        $logoUrl = $branch->logo_path
            ? route('branch-logos.show', $branch)
            : null;

        return [
            'id' => $branch->id,
            'name' => $branch->displayName(),
            'code' => $branch->code,
            'status' => $branch->status,
            'image' => $logoUrl,
            'manage_url' => route('developer.branches.show', $branch),
            'edit_url' => route('developer.branches.edit', $branch),
            'details' => [
                'Code' => $branch->code,
                'Prefix' => $branch->prefix ?: '-',
                'Main Branch' => $branch->is_main ? 'Yes' : 'No',
                'Business Name' => $branch->displayName(),
                'Phone' => $branch->phone ?: '-',
                'City' => $branch->city ?: '-',
                'Address' => $branch->address ?: '-',
                'Status' => ucfirst($branch->status),
            ],
        ];
    }

    public function create()
    {
        if ($resp = $this->denyIfNoRole(['developer'])) {
            return $resp;
        }

        return view('developer.branches.create');
    }

    public function modules(Request $request, ModuleBranchService $branches, ModuleSettingsService $moduleSettings)
    {
        if ($resp = $this->denyIfNoRole(['developer'])) {
            return $resp;
        }

        $mainBranch = $branches->ensureMainBranch();
        $branches->backfillManagerAccess();

        Branch::query()
            ->where(function ($query) {
                $query->where('status', 'active')->orWhere('is_main', true);
            })
            ->get()
            ->each(fn (Branch $branch) => $branch->is_main
                ? $branches->ensureRegistryModuleSettings($branch)
                : $branches->ensureBranchModuleRows($branch));

        $registry = $branches->moduleRegistry();
        $moduleKeys = array_keys($registry);
        $selectedModule = $branches->canonicalModuleKey((string) $request->query('module', $moduleKeys[0] ?? ''));
        if (!array_key_exists($selectedModule, $registry)) {
            $selectedModule = $moduleKeys[0] ?? null;
        }

        $branchRows = Branch::query()->orderByDesc('is_main')->orderBy('name')->get();
        $settings = BranchModuleSetting::query()
            ->where('module_key', $selectedModule)
            ->get()
            ->keyBy(fn (BranchModuleSetting $setting) => $setting->branch_id ?? 'global');

        return view('developer.branches.modules', [
            'branches' => $branchRows,
            'moduleRegistry' => $registry,
            'moduleLabels' => $branches->moduleLabels(),
            'selectedModuleKey' => $selectedModule,
            'selectedModule' => $selectedModule ? $branches->runtimeModuleConfig($selectedModule, $mainBranch?->id) : null,
            'settings' => $settings,
            'clientModuleState' => $moduleSettings->effectiveState($selectedModule),
            'clientModuleKnown' => array_key_exists($selectedModule, $moduleSettings->registry()),
            'clientModuleEnabled' => ! (bool) (($settings->get('global')?->metadata ?? [])['client_disabled'] ?? false),
        ]);
    }

    public function access(Request $request, ModuleBranchService $branches)
    {
        if ($resp = $this->denyIfNoRole(['developer'])) {
            return $resp;
        }

        $branches->ensureMainBranch();
        $branches->backfillManagerAccess();

        $moduleLabels = $branches->moduleLabels();

        $accessRows = AppPermissionRule::query()
            ->with('user')
            ->orderBy('role')
            ->orderBy('user_id')
            ->orderBy('module_key')
            ->get();

        if ($request->ajax()) {
            return response()->json([
                'data' => $this->formattedAccessRows($accessRows, $moduleLabels, $request),
                'authLayout' => 'table',
            ]);
        }

        return view('developer.branches.access', [
            'moduleLabels' => $moduleLabels,
            'moduleOptions' => collect($moduleLabels)
                ->mapWithKeys(fn ($label, $moduleKey) => [$moduleKey => ['text' => $label]])
                ->all(),
            'roleLabels' => ['developer', 'owner', 'admin', 'manager', 'accountant', 'store_keeper', 'guest', 'supplier'],
            'users' => User::query()->select('id', 'name', 'username', 'role')->orderBy('name')->get(),
            'accessRows' => $accessRows,
            'formattedAccessRows' => $this->formattedAccessRows($accessRows, $moduleLabels, $request),
        ]);
    }

    protected function formattedAccessRows($accessRows, array $moduleLabels, Request $request)
    {
        $limit = (int) $request->query('limit', 0);

        return $accessRows
            ->when($limit > 0, fn ($rows) => $rows->take($limit))
            ->map(fn (AppPermissionRule $row) => $this->formattedAccessRow($row, $moduleLabels))
            ->values();
    }

    protected function formattedAccessRow(AppPermissionRule $row, array $moduleLabels): array
    {
        $permissions = collect([
            $row->can_view ? 'view' : null,
            $row->can_create ? 'create' : null,
            $row->can_update ? 'edit' : null,
            $row->can_delete ? 'delete' : null,
            $row->can_override ? 'developer mode' : null,
        ])->filter()->values()->all();

        return [
            'id' => $row->id,
            'role_user' => $row->user
                ? (($row->user->name ?? $row->user->username) . ' (user)')
                : ($row->role ?: '-'),
            'subtitle' => $row->user?->username ?: 'Role rule',
            'module' => $row->module_key ? ($moduleLabels[$row->module_key] ?? $row->module_key) : 'All modules',
            'permissions' => $permissions,
        ];
    }

    public function store(Request $request, ModuleBranchService $branches): RedirectResponse
    {
        if ($resp = $this->denyIfNoRole(['developer'])) {
            return $resp;
        }

        $validated = $this->validateBranch($request);
        if ($request->hasFile('logo')) {
            $validated['logo_path'] = $request->file('logo')->store('branch-logos', 'public');
        }

        $branch = $branches->createBranch($validated);

        return redirect()->route('developer.branches.show', $branch)->with('success', 'Branch created.');
    }

    public function show(Branch $branch, ModuleBranchService $branches)
    {
        if ($resp = $this->denyIfNoRole(['developer'])) {
            return $resp;
        }

        $branch->is_main
            ? $branches->ensureRegistryModuleSettings($branch)
            : $branches->ensureBranchModuleRows($branch);

        return view('developer.branches.show', $this->branchViewData($branch));
    }

    public function edit(Branch $branch, ModuleBranchService $branches)
    {
        if ($resp = $this->denyIfNoRole(['developer'])) {
            return $resp;
        }

        $branch->is_main
            ? $branches->ensureRegistryModuleSettings($branch)
            : $branches->ensureBranchModuleRows($branch);

        return view('developer.branches.edit', $this->branchViewData($branch));
    }

    public function update(Request $request, Branch $branch): RedirectResponse
    {
        if ($resp = $this->denyIfNoRole(['developer'])) {
            return $resp;
        }

        $validated = $this->validateBranch($request, $branch);

        if ($branch->is_main) {
            $validated['status'] = 'active';
            $validated['code'] = 'MAIN';
        } elseif (blank($validated['code'] ?? null)) {
            $validated['code'] = $branch->code;
        }

        if ($request->hasFile('logo')) {
            if ($branch->logo_path) {
                Storage::disk('public')->delete($branch->logo_path);
            }
            $validated['logo_path'] = $request->file('logo')->store('branch-logos', 'public');
        }

        $branch->update($validated);

        return redirect()->route('developer.branches.show', $branch)->with('success', 'Branch saved.');
    }

    public function updateStatus(Request $request, Branch $branch): RedirectResponse
    {
        if ($resp = $this->denyIfNoRole(['developer'])) {
            return $resp;
        }

        $validated = $request->validate([
            'status' => ['required', 'in:active,inactive'],
        ]);

        if ($branch->is_main && $validated['status'] !== 'active') {
            return redirect()->route('developer.branches.show', $branch)
                ->with('error', 'Main Branch cannot be deactivated.');
        }

        $branch->update(['status' => $validated['status']]);

        return redirect()->route('developer.branches.show', $branch)->with('success', 'Branch status updated.');
    }

    protected function validateBranch(Request $request, ?Branch $branch = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'code' => ['nullable', 'string', 'max:40', Rule::unique('branches', 'code')->ignore($branch?->id)],
            'prefix' => ['required', 'string', 'max:20', Rule::unique('branches', 'prefix')->ignore($branch?->id)],
            'display_name' => ['nullable', 'string', 'max:160'],
            'owner_name' => ['nullable', 'string', 'max:160'],
            'phone' => ['nullable', 'string', 'max:80'],
            'email' => ['nullable', 'email', 'max:160'],
            'address' => ['nullable', 'string', 'max:500'],
            'city' => ['nullable', 'string', 'max:120'],
            'province' => ['nullable', 'string', 'max:120'],
            'ntn_cnic' => ['nullable', 'string', 'max:80'],
            'strn_sntn' => ['nullable', 'string', 'max:80'],
            'header_text' => ['nullable', 'string', 'max:160'],
            'footer_text' => ['nullable', 'string', 'max:160'],
            'terms_text' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', 'in:active,inactive'],
            'logo' => ['nullable', 'image', 'max:2048'],
        ]);

        $validated['prefix'] = strtoupper(preg_replace('/[^A-Z0-9]+/', '', $validated['prefix'] ?? '')) ?: 'BR';

        return $validated;
    }

    public function updateModules(Request $request, ModuleBranchService $branches): RedirectResponse
    {
        if ($resp = $this->denyIfNoRole(['developer'])) {
            return $resp;
        }

        if ($request->filled('modules_payload')) {
            $decodedModules = json_decode((string) $request->input('modules_payload'), true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decodedModules)) {
                $request->merge(['modules' => $decodedModules]);
            }
        }

        $validated = $request->validate([
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'modules_payload' => ['nullable', 'string'],
            'modules' => ['array'],
            'modules.*._present' => ['nullable', 'boolean'],
            'modules.*._advanced_present' => ['nullable', 'boolean'],
            'modules.*.branch_enabled' => ['nullable', 'boolean'],
            'modules.*.allow_user_switching' => ['nullable', 'boolean'],
            'modules.*.supports_multi_branch_selector' => ['nullable', 'boolean'],
            'modules.*.record_filtering_enabled' => ['nullable', 'boolean'],
            'modules.*.has_branch_id_support' => ['nullable', 'boolean'],
            'modules.*.supports_branch_branding' => ['nullable', 'boolean'],
            'modules.*.supports_branch_serial_prefix' => ['nullable', 'boolean'],
            'modules.*.supports_doc_identity_prefix' => ['nullable', 'boolean'],
            'modules.*.default_order_discount_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
            'modules.*.discount_disabled' => ['nullable', 'boolean'],
            'modules.*.document_note' => ['nullable', 'string', 'max:300'],
            'modules.*.doc_identity_prefix' => ['nullable', 'string', 'max:20'],
            'modules.*.label_override' => ['nullable', 'string', 'max:80'],
            'modules.*.default_branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'modules.*.status' => ['nullable', 'in:active,inactive'],
        ]);

        $branchId = $validated['branch_id'] ?? null;
        $branch = $branchId ? Branch::query()->find($branchId) : null;
        foreach (($validated['modules'] ?? []) as $moduleKey => $data) {
            $moduleKey = $branches->canonicalModuleKey((string) $moduleKey);
            if (!array_key_exists($moduleKey, $branches->moduleRegistry())) {
                continue;
            }
            $module = $branches->moduleRegistry()[$moduleKey];
            $multiBranchEnabled = (bool) ($data['supports_multi_branch_selector'] ?? false);
            $switchingEnabled = (bool) ($data['allow_user_switching'] ?? false);
            $branchEnabled = (bool) ($data['branch_enabled'] ?? false);
            $recordFilteringEnabled = (bool) ($data['record_filtering_enabled'] ?? false);
            $advancedPresent = array_key_exists('_advanced_present', $data);
            $setting = BranchModuleSetting::query()
                ->where('branch_id', $branchId)
                ->where('module_key', $moduleKey)
                ->first();
            $metadata = is_array($setting?->metadata) ? $setting->metadata : [];
            $metadata['record_filtering_enabled'] = $recordFilteringEnabled;

            $metadata['branchable'] = $branchEnabled || $switchingEnabled || $multiBranchEnabled || $recordFilteringEnabled;
            $metadata['supports_branch_selector'] = $switchingEnabled;
            $metadata['supports_record_filtering'] = $recordFilteringEnabled;
            $metadata['can_filter_records'] = $recordFilteringEnabled;
            $metadata['supports_multi_branch_selector'] = $multiBranchEnabled;

            foreach ([
                'has_branch_id_support',
                'supports_branch_branding',
                'supports_branch_serial_prefix',
                'supports_doc_identity_prefix',
            ] as $key) {
                if ($advancedPresent || array_key_exists($key, $data)) {
                    $metadata[$key] = (bool) ($data[$key] ?? false);
                }
            }

            if ($advancedPresent || array_key_exists('supports_branch_branding', $data)) {
                $metadata['can_use_branch_branding'] = (bool) ($data['supports_branch_branding'] ?? false);
            }

            if ($advancedPresent || array_key_exists('supports_branch_serial_prefix', $data)) {
                $metadata['supports_serial_prefix'] = (bool) ($data['supports_branch_serial_prefix'] ?? false);
            }

            $metadata['is_system_module'] = ! ($branchEnabled || $switchingEnabled || $multiBranchEnabled || $recordFilteringEnabled);
            if (array_key_exists('doc_identity_prefix', $data)) {
                $metadata['doc_identity_prefix'] = strtoupper(preg_replace('/[^A-Z0-9]+/', '', (string) $data['doc_identity_prefix']));
            }
            if (array_key_exists('label_override', $data)) {
                $labelOverride = trim((string) $data['label_override']);
                if ($labelOverride === '') {
                    unset($metadata['label_override']);
                } else {
                    $metadata['label_override'] = $labelOverride;
                }
            }

            if ($moduleKey === 'orders' && array_key_exists('default_order_discount_percent', $data)) {
                $metadata['default_order_discount_percent'] = max(
                    0,
                    min(100, (int) $data['default_order_discount_percent'])
                );
            }
            if (in_array($moduleKey, ['orders', 'invoices'], true)) {
                if ($advancedPresent || array_key_exists('discount_disabled', $data)) {
                    $metadata['discount_disabled'] = (bool) ($data['discount_disabled'] ?? false);
                }
                if (array_key_exists('document_note', $data)) {
                    $metadata['document_note'] = trim((string) $data['document_note']);
                }
            } else {
                unset($metadata['discount_disabled'], $metadata['document_note']);
            }

            $values = [
                'branch_enabled' => $branchEnabled,
                'allow_user_switching' => $switchingEnabled,
                'default_branch_id' => $data['default_branch_id'] ?? null,
                'status' => $data['status'] ?? $setting?->status ?? 'active',
                'metadata' => $metadata,
            ];

            foreach (array_unique([$branchId, $branch?->is_main ? null : $branchId], SORT_REGULAR) as $targetBranchId) {
                BranchModuleSetting::query()->updateOrCreate(
                    ['branch_id' => $targetBranchId, 'module_key' => $moduleKey],
                    $values,
                );
            }
        }

        return redirect()->back()->with('success', 'Module branch settings saved.');
    }

    public function updateModuleAcrossBranches(Request $request, ModuleBranchService $branches, ModuleSettingsService $moduleSettings): RedirectResponse
    {
        if ($resp = $this->denyIfNoRole(['developer'])) {
            return $resp;
        }

        $validated = $request->validate([
            'module_key' => ['required', 'string', Rule::in(array_keys($branches->moduleRegistry()))],
            'module_enabled' => ['nullable', 'boolean'],
            'visible_in_sidebar' => ['nullable', 'boolean'],
            'label_override' => ['nullable', 'string', 'max:80'],
            'impact_note' => ['nullable', 'string', 'max:500'],
            'branches' => ['array'],
            'branches.*.branch_enabled' => ['nullable', 'boolean'],
            'branches.*.allow_user_switching' => ['nullable', 'boolean'],
            'branches.*.supports_multi_branch_selector' => ['nullable', 'boolean'],
            'branches.*.record_filtering_enabled' => ['nullable', 'boolean'],
            'branches.*.has_branch_id_support' => ['nullable', 'boolean'],
            'branches.*.supports_branch_branding' => ['nullable', 'boolean'],
            'branches.*.supports_branch_serial_prefix' => ['nullable', 'boolean'],
            'branches.*.supports_doc_identity_prefix' => ['nullable', 'boolean'],
            'branches.*.doc_identity_prefix' => ['nullable', 'string', 'max:20'],
            'branches.*.default_order_discount_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
            'branches.*.discount_disabled' => ['nullable', 'boolean'],
            'branches.*.document_note' => ['nullable', 'string', 'max:300'],
            'branches.*.default_branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'branches.*.status' => ['nullable', 'in:active,inactive'],
        ]);

        $moduleKey = $branches->canonicalModuleKey($validated['module_key']);
        $registry = $branches->moduleRegistry();
        if (!array_key_exists($moduleKey, $registry)) {
            return redirect()->back()->with('error', 'Unknown module selected.');
        }

        if (array_key_exists($moduleKey, $moduleSettings->registry())) {
            $moduleSettings->save(
                $moduleKey,
                $request->boolean('module_enabled'),
                $request->boolean('visible_in_sidebar', true),
            );
        }

        $global = BranchModuleSetting::query()
            ->whereNull('branch_id')
            ->where('module_key', $moduleKey)
            ->first();
        $globalMetadata = is_array($global?->metadata) ? $global->metadata : [];
        $isRequiredSystemModule = in_array($moduleKey, ['home', 'dashboard'], true);
        $labelOverride = trim((string) ($validated['label_override'] ?? ''));
        if ($labelOverride === '') {
            unset($globalMetadata['label_override']);
        } else {
            $globalMetadata['label_override'] = $labelOverride;
        }
        $impactNote = trim((string) ($validated['impact_note'] ?? ''));
        if ($impactNote === '') {
            unset($globalMetadata['impact_note']);
        } else {
            $globalMetadata['impact_note'] = $impactNote;
        }
        $moduleEnabledForClient = $isRequiredSystemModule || $request->boolean('module_enabled');
        if ($moduleEnabledForClient) {
            unset($globalMetadata['client_disabled']);
        } else {
            $globalMetadata['client_disabled'] = true;
        }

        BranchModuleSetting::query()->updateOrCreate(
            ['branch_id' => null, 'module_key' => $moduleKey],
            [
                'branch_enabled' => $moduleEnabledForClient,
                'allow_user_switching' => (bool) ($global?->allow_user_switching ?? false),
                'default_branch_id' => $global?->default_branch_id,
                'status' => $moduleEnabledForClient ? 'active' : 'inactive',
                'metadata' => $globalMetadata,
            ],
        );

        foreach (($validated['branches'] ?? []) as $branchId => $data) {
            if (!is_numeric($branchId) || !Branch::query()->whereKey((int) $branchId)->exists()) {
                continue;
            }

            $setting = BranchModuleSetting::query()
                ->where('branch_id', (int) $branchId)
                ->where('module_key', $moduleKey)
                ->first();
            $metadata = is_array($setting?->metadata) ? $setting->metadata : [];
            $branchEnabled = (bool) ($data['branch_enabled'] ?? false);
            $switchingEnabled = (bool) ($data['allow_user_switching'] ?? false);
            $multiBranchEnabled = (bool) ($data['supports_multi_branch_selector'] ?? false);
            $recordFilteringEnabled = (bool) ($data['record_filtering_enabled'] ?? false);

            $metadata['record_filtering_enabled'] = $recordFilteringEnabled;
            $metadata['branchable'] = $branchEnabled || $switchingEnabled || $multiBranchEnabled || $recordFilteringEnabled;
            $metadata['supports_branch_selector'] = $switchingEnabled;
            $metadata['supports_record_filtering'] = $recordFilteringEnabled;
            $metadata['can_filter_records'] = $recordFilteringEnabled;
            $metadata['supports_multi_branch_selector'] = $multiBranchEnabled;
            $metadata['has_branch_id_support'] = (bool) ($data['has_branch_id_support'] ?? false);
            $metadata['supports_branch_branding'] = (bool) ($data['supports_branch_branding'] ?? false);
            $metadata['can_use_branch_branding'] = (bool) ($data['supports_branch_branding'] ?? false);
            $metadata['supports_branch_serial_prefix'] = (bool) ($data['supports_branch_serial_prefix'] ?? false);
            $metadata['supports_serial_prefix'] = (bool) ($data['supports_branch_serial_prefix'] ?? false);
            $metadata['supports_doc_identity_prefix'] = (bool) ($data['supports_doc_identity_prefix'] ?? false);
            $metadata['doc_identity_prefix'] = strtoupper(preg_replace('/[^A-Z0-9]+/', '', (string) ($data['doc_identity_prefix'] ?? '')));
            if ($moduleKey === 'orders') {
                $metadata['default_order_discount_percent'] = max(0, min(100, (int) ($data['default_order_discount_percent'] ?? 0)));
            }
            if (in_array($moduleKey, ['orders', 'invoices'], true)) {
                $metadata['discount_disabled'] = (bool) ($data['discount_disabled'] ?? false);
                $metadata['document_note'] = trim((string) ($data['document_note'] ?? ''));
            } else {
                unset($metadata['discount_disabled'], $metadata['document_note']);
            }
            $metadata['is_system_module'] = ! ($branchEnabled || $switchingEnabled || $multiBranchEnabled || $recordFilteringEnabled);

            BranchModuleSetting::query()->updateOrCreate(
                ['branch_id' => (int) $branchId, 'module_key' => $moduleKey],
                [
                    'branch_enabled' => $branchEnabled,
                    'allow_user_switching' => $switchingEnabled,
                    'default_branch_id' => $data['default_branch_id'] ?? $setting?->default_branch_id,
                    'status' => $data['status'] ?? $setting?->status ?? 'active',
                    'metadata' => $metadata,
                ],
            );
        }

        return redirect()
            ->route('developer.branches.modules.index', ['module' => $moduleKey])
            ->with('success', 'Module settings saved for all branches.');
    }

    public function updateAccess(Request $request, ModuleBranchService $branches): RedirectResponse
    {
        if ($resp = $this->denyIfNoRole(['developer'])) {
            return $resp;
        }

        if (filled($request->input('module_key'))) {
            $request->merge(['module_key' => $branches->canonicalModuleKey((string) $request->input('module_key'))]);
        }

        $validated = $request->validate([
            'role' => ['nullable', 'required_without:user_id', 'string', Rule::in(['developer', 'owner', 'admin', 'manager', 'accountant', 'store_keeper', 'guest', 'supplier'])],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'module_key' => ['nullable', 'string', Rule::in(array_keys($branches->moduleRegistry()))],
            'can_view' => ['nullable', 'boolean'],
            'can_create' => ['nullable', 'boolean'],
            'can_update' => ['nullable', 'boolean'],
            'can_delete' => ['nullable', 'boolean'],
            'can_override' => ['nullable', 'boolean'],
            'can_switch' => ['nullable', 'boolean'],
            'can_manage' => ['nullable', 'boolean'],
        ]);
        $validated['module_key'] = filled($validated['module_key'] ?? null)
            ? $branches->canonicalModuleKey((string) $validated['module_key'])
            : null;
        $userId = $validated['user_id'] ?? null;
        $role = $userId ? null : ($validated['role'] ?? null);

        AppPermissionRule::query()->updateOrCreate(
            [
                'role' => $role,
                'user_id' => $userId,
                'module_key' => $validated['module_key'] ?? null,
            ],
            [
                'can_view' => $request->boolean('can_view'),
                'can_create' => $request->boolean('can_create'),
                'can_update' => $request->boolean('can_update'),
                'can_delete' => $request->boolean('can_delete'),
                'can_override' => $request->boolean('can_override'),
                'can_switch' => false,
                'can_manage' => false,
            ],
        );

        return redirect()->back()->with('success', 'Permissions saved.');
    }

    protected function branchViewData(Branch $branch): array
    {
        $branches = app(ModuleBranchService::class);
        $registry = $branches->moduleRegistry();

        return [
            'branch' => $branch->load(['moduleSettings', 'accessRows']),
            'branches' => Branch::query()->orderByDesc('is_main')->orderBy('name')->get(),
            'moduleLabels' => $branches->moduleLabels(),
            'moduleRegistry' => $registry,
            'moduleSettings' => BranchModuleSetting::query()
                ->where('branch_id', $branch->id)
                ->get()
                ->sortBy(fn (BranchModuleSetting $setting) => ($registry[$branches->canonicalModuleKey($setting->module_key)]['group'] ?? 'ZZZ') . '|' . ($registry[$branches->canonicalModuleKey($setting->module_key)]['label'] ?? $setting->module_key))
                ->values()
                ->keyBy(fn (BranchModuleSetting $setting) => $branches->canonicalModuleKey($setting->module_key)),
            'accessRows' => BranchUserAccess::query()
                ->with(['branch', 'user'])
                ->where('branch_id', $branch->id)
                ->orderBy('role')
                ->orderBy('user_id')
                ->orderBy('module_key')
                ->get(),
            'roleLabels' => ['developer', 'owner', 'admin', 'manager', 'accountant', 'store_keeper', 'guest', 'supplier'],
            'users' => User::query()->select('id', 'name', 'username', 'role')->orderBy('name')->get(),
        ];
    }
}
