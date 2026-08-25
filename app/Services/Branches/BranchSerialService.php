<?php

namespace App\Services\Branches;

use App\Models\Branch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BranchSerialService
{
    private const DOCUMENT_IDENTITIES = [
        'orders' => 'O',
        'invoices' => 'I',
        'vouchers' => 'V',
        'productions' => 'P',
        'customer_payments' => 'CP',
        'supplier_payments' => 'SP',
        'payment_programs' => 'PP',
        'bilties' => 'B',
        'cargos' => 'C',
        'cargo' => 'C',
        'shipments' => 'S',
        'cr' => 'CR',
        'dr' => 'DR',
        'bank_accounts' => 'BA',
        'daily_ledger' => 'DL',
        'utility_bills' => 'UB',
    ];

    public function __construct(private readonly ModuleBranchService $branches)
    {
    }

    public function next(string $moduleKey, string $modelClass, string $column, ?string $middle = null, int $pad = 6): string
    {
        $moduleKey = $this->branches->canonicalModuleKey($moduleKey);
        $branchId = $this->branches->shouldFilterRecords($moduleKey)
            ? $this->branches->selectedBranchIdForModule($moduleKey)
            : null;

        $this->lockSerialNamespace($moduleKey, $branchId);

        $baseNumber = $this->nextBaseNumber($moduleKey, $modelClass, $column, $pad, $branchId);

        return $this->formatBranchDocumentNumber($baseNumber, $moduleKey, $branchId ? Branch::query()->find($branchId) : null);
    }

    public function nextProductionTicket(string $workPrefix): string
    {
        $moduleKey = 'productions';
        $branchId = $this->branches->shouldFilterRecords($moduleKey)
            ? $this->branches->selectedBranchIdForModule($moduleKey)
            : null;
        $cleanWorkPrefix = $this->cleanPrefix($workPrefix);
        $query = \App\Models\Production::query()
            ->where('ticket', 'like', '%' . $cleanWorkPrefix . '%');

        $model = new \App\Models\Production();
        if ($branchId && Schema::hasColumn($model->getTable(), 'branch_id')) {
            $query->where($model->getTable() . '.branch_id', $branchId);
        }

        $last = $query->orderByDesc('id')->value('ticket');
        $baseNumber = $cleanWorkPrefix . str_pad((string) ($this->numericTail($last) + 1), 3, '0', STR_PAD_LEFT);

        return $this->formatBranchDocumentNumber($baseNumber, $moduleKey, $branchId ? Branch::query()->find($branchId) : null);
    }

    public function formatBranchDocumentNumber(string $baseNumber, string $moduleKey, ?Branch $branch = null): string
    {
        $moduleKey = $this->branches->canonicalModuleKey($moduleKey);
        $config = $this->branches->runtimeModuleConfig($moduleKey, $branch?->id) ?? [];

        if (
            !$branch
            || !$this->branches->shouldFilterRecords($moduleKey)
            || !($config['supports_branch_serial_prefix'] ?? $config['supports_serial_prefix'] ?? false)
        ) {
            return $baseNumber;
        }

        $branchPrefix = $this->cleanPrefix($branch->prefix ?: $branch->code ?: '');
        $docIdentity = $this->cleanPrefix(
            (string) ($config['doc_identity_prefix'] ?? self::DOCUMENT_IDENTITIES[$moduleKey] ?? '')
        );

        $baseNumber = $this->stripKnownDocumentPrefix($baseNumber, $branchPrefix, $docIdentity);

        if (!$branchPrefix) {
            return $baseNumber;
        }

        // Agar document identity available hai to include karo
        if ($docIdentity !== '') {
            return "{$branchPrefix}-{$docIdentity}-{$baseNumber}";
        }

        // Warna sirf branch prefix lagao
        return "{$branchPrefix}-{$baseNumber}";
    }

    private function nextBaseNumber(string $moduleKey, string $modelClass, string $column, int $pad, ?int $branchId): string
    {
        $query = $modelClass::query();

        /** @var Model $model */
        $model = new $modelClass();
        if ($branchId && Schema::hasColumn($model->getTable(), 'branch_id')) {
            $query->where($model->getTable() . '.branch_id', $branchId);
        }

        $last = $query->orderByDesc('id')->value($column);
        $lastBase = $this->stripKnownDocumentPrefix((string) $last, null, null);
        $serial = $this->numericTail($lastBase) + 1;

        if (in_array($moduleKey, ['orders', 'invoices'], true)) {
            return date('y') . '-' . str_pad((string) $serial, 4, '0', STR_PAD_LEFT);
        }

        if ($moduleKey === 'vouchers' && $lastBase === '') {
            return '00/150';
        }

        if ($moduleKey === 'vouchers' && $nextVoucherBookNumber = $this->nextVoucherBookNumber($lastBase)) {
            return $nextVoucherBookNumber;
        }

        if ($lastBase && preg_match('/^(.*?)(\d+)$/', $lastBase, $matches) && $matches[1] !== '') {
            return $matches[1] . str_pad((string) $serial, strlen($matches[2]), '0', STR_PAD_LEFT);
        }

        return str_pad((string) $serial, $pad, '0', STR_PAD_LEFT);
    }

    private function lockSerialNamespace(string $moduleKey, ?int $branchId): void
    {
        // Store actions already run in a transaction. Lock one stable coordinator
        // row so concurrent users cannot calculate the same next document number.
        if (DB::connection()->transactionLevel() < 1) {
            return;
        }

        if ($branchId && Schema::hasTable('branches')) {
            DB::table('branches')->where('id', $branchId)->lockForUpdate()->value('id');
            return;
        }

        if (Schema::hasTable('branch_module_settings')) {
            $locked = DB::table('branch_module_settings')
                ->where('module_key', $moduleKey)
                ->lockForUpdate()
                ->value('id');

            if ($locked) {
                return;
            }
        }

        if (Schema::hasTable('branches')) {
            DB::table('branches')->orderByDesc('is_main')->orderBy('id')->lockForUpdate()->value('id');
        }
    }

    private function nextVoucherBookNumber(string $lastBase): ?string
    {
        if (!preg_match('/^(\d+)\/(\d+)$/', trim($lastBase), $matches)) {
            return null;
        }

        $pageNo = (int) $matches[1];
        $bookNo = (int) $matches[2];

        if ($pageNo >= 100) {
            $pageNo = 1;
            $bookNo++;
        } else {
            $pageNo++;
        }

        return str_pad((string) $pageNo, 2, '0', STR_PAD_LEFT)
            . '/'
            . str_pad((string) $bookNo, max(3, strlen($matches[2])), '0', STR_PAD_LEFT);
    }

    private function numericTail(?string $value): int
    {
        if (!$value || !preg_match('/(\d+)$/', $value, $matches)) {
            return 0;
        }

        return (int) $matches[1];
    }

    private function cleanPrefix(string $prefix): string
    {
        return strtoupper(preg_replace('/[^A-Z0-9]+/', '', $prefix)) ?: '';
    }

    private function stripKnownDocumentPrefix(?string $value, ?string $branchPrefix, ?string $docIdentity): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        if ($branchPrefix && $docIdentity) {
            $prefix = preg_quote($branchPrefix . '-' . $docIdentity . '-', '/');
            return preg_replace('/^' . $prefix . '/i', '', $value) ?? $value;
        }

        if (preg_match('/^[A-Z0-9]+-[A-Z0-9]+-(.+)$/i', $value, $matches)) {
            return $matches[1];
        }

        return $value;
    }
}
