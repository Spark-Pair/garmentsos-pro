<?php

namespace App\Http\Controllers;

use App\Services\Branches\ModuleBranchService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class ModuleBranchPreferenceController extends Controller
{
    public function store(Request $request, ModuleBranchService $branches): RedirectResponse
    {
        if ($request->filled('module_key')) {
            $request->merge(['module_key' => $branches->canonicalModuleKey((string) $request->input('module_key'))]);
        }

        $validated = $request->validate([
            'module_key' => ['required', 'string', 'max:80', Rule::in(array_keys($branches->moduleRegistry()))],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'branch_ids' => ['nullable', 'array'],
            'branch_ids.*' => ['integer', 'exists:branches,id'],
            'selection_mode' => ['nullable', 'string', Rule::in(['single', 'multiple'])],
            'redirect_to' => ['nullable', 'string', 'max:2048'],
            'edit_record_token' => ['nullable', 'string', 'max:4096'],
        ]);

        if (($validated['selection_mode'] ?? 'single') === 'multiple') {
            $branches->setMultiPreference($validated['module_key'], $validated['branch_ids'] ?? [], $request->user());
        } else {
            $request->validate(['branch_id' => ['required', 'integer', 'exists:branches,id']]);
            $recordMoved = $this->moveEditRecordIfRequested(
                $validated['edit_record_token'] ?? null,
                $validated['module_key'],
                (int) $validated['branch_id'],
                $request,
                $branches
            );
            $branches->setPreference($validated['module_key'], (int) $validated['branch_id'], $request->user());
        }

        $redirectTo = $validated['redirect_to'] ?? null;
        if (
            is_string($redirectTo)
            && (str_starts_with($redirectTo, url('/')) || str_starts_with($redirectTo, $request->getSchemeAndHttpHost()))
        ) {
            return redirect()->to($redirectTo)->with('success', !empty($recordMoved)
                ? 'Record moved to the selected branch successfully.'
                : 'Branch selection updated for this module.');
        }

        return redirect()->back()->with('success', 'Branch selection updated for this module.');
    }

    private function moveEditRecordIfRequested(?string $token, string $moduleKey, int $branchId, Request $request, ModuleBranchService $branches): bool
    {
        if (!$token) {
            return false;
        }

        try {
            $payload = json_decode(Crypt::decryptString($token), true, flags: JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            abort(422, 'The edit-page branch transfer is no longer valid. Please refresh the page.');
        }

        if (($payload['module_key'] ?? null) !== $moduleKey || (int) ($payload['issued_at'] ?? 0) < now()->subHours(4)->timestamp) {
            abort(422, 'The edit-page branch transfer is no longer valid. Please refresh the page.');
        }

        if (!app_can($moduleKey, 'update')) {
            abort(403, 'You are not allowed to move this record to another branch.');
        }

        $availableBranchIds = $branches->availableBranchesForModule($moduleKey, $request->user())
            ->pluck('id')->map(fn ($id) => (int) $id);
        if (!$availableBranchIds->contains($branchId)) {
            abort(403, 'The selected branch is not available for this module.');
        }

        $modelClass = $payload['model'] ?? null;
        if (!is_string($modelClass) || !is_a($modelClass, Model::class, true)) {
            abort(422, 'The edit-page record type is invalid.');
        }

        /** @var Model $record */
        $record = $modelClass::query()->findOrFail($payload['id'] ?? null);
        if (!Schema::hasColumn($record->getTable(), 'branch_id')) {
            abort(422, 'This record does not support branch transfer.');
        }

        $originalBranchId = $payload['branch_id'] ?? null;
        if ((int) ($record->getAttribute('branch_id') ?? 0) !== (int) ($originalBranchId ?? 0)) {
            abort(409, 'This record branch changed after the page was opened. Please refresh and try again.');
        }

        if ((int) ($record->getAttribute('branch_id') ?? 0) === $branchId) {
            return false;
        }

        $record->setAttribute('branch_id', $branchId);
        $record->save();

        return true;
    }
}
