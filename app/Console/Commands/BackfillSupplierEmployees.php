<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Models\Setup;
use App\Models\Supplier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillSupplierEmployees extends Command
{
    protected $signature = 'suppliers:backfill-employees
                            {--dry-run : Show what would be created without making changes}';

    protected $description = 'Create missing supplier workers based on supplier categories and worker_id';

    private const RELEVANT_CATEGORIES = [
        'CMT',
        'Cut to Pack',
        'Stitching',
        'Print',
        'Embroidery',
    ];

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        $this->newLine();

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No database changes will be made.');
        } else {
            $this->info('Starting supplier worker backfill...');
        }

        $this->newLine();

        /*
         * Category-wise report.
         */
        $createdByCategory = [];

        foreach (self::RELEVANT_CATEGORIES as $categoryTitle) {
            $createdByCategory[$categoryTitle] = [];
        }

        /*
         * Cache supplier categories so we don't repeatedly query
         * the same Setup records.
         */
        $supplierCategories = Setup::query()
            ->where('type', 'supplier_category')
            ->whereIn('title', self::RELEVANT_CATEGORIES)
            ->get()
            ->keyBy('id');

        /*
         * Cache worker types.
         */
        $workerTypes = Setup::query()
            ->where('type', 'worker_type')
            ->whereIn(
                'title',
                collect(self::RELEVANT_CATEGORIES)
                    ->map(fn ($title) => $title . ' | E')
                    ->all()
            )
            ->get()
            ->keyBy('title');

        /*
         * Show mapping first.
         */
        $this->info('Category / Worker Type Mapping');

        $this->line(str_repeat('-', 70));

        foreach (self::RELEVANT_CATEGORIES as $categoryTitle) {

            $category = $supplierCategories
                ->firstWhere('title', $categoryTitle);

            $workerType = $workerTypes
                ->get($categoryTitle . ' | E');

            $this->line(
                sprintf(
                    '%-18s Category ID: %-6s | Worker Type ID: %-6s | %s',
                    $categoryTitle,
                    $category?->id ?? 'NOT FOUND',
                    $workerType?->id ?? 'NOT FOUND',
                    $workerType?->title ?? 'NOT FOUND'
                )
            );
        }

        $this->line(str_repeat('-', 70));
        $this->newLine();

        /*
         * Only suppliers with missing worker_id.
         *
         * This is the important part.
         */
        $suppliers = Supplier::query()
            ->where(function ($query) {
                $query
                    ->whereNull('worker_id')
                    ->orWhere('worker_id', 0);
            })
            ->orderBy('id')
            ->get();

        $checked = 0;
        $skippedNoRelevantCategory = 0;
        $createdTotal = 0;

        foreach ($suppliers as $supplier) {

            $checked++;

            /*
             * Decode categories_array.
             */
            $categoryIds = $this->decodeCategoryIds(
                $supplier->categories_array
            );

            if (empty($categoryIds)) {
                $skippedNoRelevantCategory++;
                continue;
            }

            /*
             * Normalize category IDs.
             *
             * Handles:
             *
             * [1, 2, 3]
             * ["1", "2", "3"]
             * [1, "2", "3"]
             */
            $categoryIds = collect($categoryIds)
                ->filter(fn ($id) => is_numeric($id))
                ->map(fn ($id) => (int) $id)
                ->filter(fn ($id) => $id > 0)
                ->unique()
                ->values()
                ->all();

            if (empty($categoryIds)) {
                $skippedNoRelevantCategory++;
                continue;
            }

            /*
             * Match supplier categories against Setup.
             */
            $matchedCategories = $supplierCategories
                ->only($categoryIds);

            /*
             * Keep only relevant categories.
             */
            $matchedCategories = $matchedCategories->filter(
                fn ($category) =>
                    in_array(
                        $category->title,
                        self::RELEVANT_CATEGORIES,
                        true
                    )
            );

            if ($matchedCategories->isEmpty()) {
                $skippedNoRelevantCategory++;
                continue;
            }

            /*
             * IMPORTANT:
             *
             * Supplier has missing worker_id and has at least one
             * relevant category.
             *
             * We use the first relevant category for worker_id,
             * exactly like the existing store() behavior where
             * firstWorkerId is assigned to supplier->worker_id.
             */
            $category = $matchedCategories->first();

            $categoryTitle = $category->title;

            /*
             * Find corresponding worker type.
             */
            $workerType = $workerTypes->get(
                $categoryTitle . ' | E'
            );

            if (!$workerType) {

                $this->error(
                    "Worker type NOT FOUND: "
                    . "{$categoryTitle} | E "
                    . "(Category ID: {$category->id}, "
                    . "Supplier #{$supplier->id})"
                );

                continue;
            }

            /*
             * DRY RUN
             */
            if ($dryRun) {

                $createdByCategory[$categoryTitle][] = [
                    'supplier_id' => $supplier->id,
                    'supplier_name' => $supplier->supplier_name,
                    'employee_id' => null,
                    'category_id' => $category->id,
                    'worker_type_id' => $workerType->id,
                ];

                continue;
            }

            /*
             * Create worker + update supplier.worker_id
             * in one transaction.
             */
            $employee = DB::transaction(function () use (
                $supplier,
                $workerType
            ) {

                $employee = Employee::create([
                    'category' => 'worker',
                    'branch_id' => $supplier->branch_id,
                    'type_id' => $workerType->id,
                    'employee_name' => $supplier->supplier_name,
                    'urdu_title' => $supplier->urdu_title,
                    'phone_number' => $supplier->phone_number,
                    'joining_date' => $supplier->date,
                ]);

                /*
                 * IMPORTANT:
                 * Save created worker ID into supplier.
                 */
                $supplier->update([
                    'worker_id' => $employee->id,
                ]);

                return $employee;
            });

            $createdByCategory[$categoryTitle][] = [
                'supplier_id' => $supplier->id,
                'supplier_name' => $supplier->supplier_name,
                'employee_id' => $employee->id,
                'category_id' => $category->id,
                'worker_type_id' => $workerType->id,
            ];
        }

        /*
         * ============================================================
         * FINAL REPORT
         * ============================================================
         */

        $this->newLine();

        $overallTotal = 0;

        foreach (self::RELEVANT_CATEGORIES as $categoryTitle) {

            $items = $createdByCategory[$categoryTitle];

            /*
             * Don't show empty categories if nothing was created.
             */
            if (empty($items)) {
                continue;
            }

            $category = $supplierCategories
                ->firstWhere('title', $categoryTitle);

            $workerType = $workerTypes
                ->get($categoryTitle . ' | E');

            $this->line(str_repeat('=', 75));

            $this->info($categoryTitle);

            $this->line(str_repeat('-', 75));

            $this->line(
                'Category ID       : ' . ($category?->id ?? 'N/A')
            );

            $this->line(
                'Category Title    : ' . $categoryTitle
            );

            $this->line(
                'Worker Type ID    : ' . ($workerType?->id ?? 'N/A')
            );

            $this->line(
                'Worker Type Title : ' . ($workerType?->title ?? 'N/A')
            );

            $this->line(str_repeat('-', 75));

            foreach ($items as $item) {

                if ($dryRun) {

                    $this->line(
                        "[DRY RUN] Would create: "
                        . "Supplier #{$item['supplier_id']} "
                        . "{$item['supplier_name']} "
                        . "-> Category #{$item['category_id']} "
                        . "-> Worker Type #{$item['worker_type_id']}"
                    );

                } else {

                    $this->line(
                        "Created: "
                        . "Supplier #{$item['supplier_id']} "
                        . "{$item['supplier_name']} "
                        . "-> Employee #{$item['employee_id']} "
                        . "-> worker_id saved"
                    );
                }
            }

            $categoryTotal = count($items);

            $this->newLine();

            $this->info(
                "{$categoryTitle} Total: {$categoryTotal}"
            );

            $overallTotal += $categoryTotal;

            $this->newLine();
        }

        /*
         * ============================================================
         * SUMMARY
         * ============================================================
         */

        $this->line(str_repeat('=', 75));

        $this->info("Suppliers checked: {$checked}");

        $this->info(
            "Suppliers without relevant category: {$skippedNoRelevantCategory}"
        );

        if ($dryRun) {

            $this->info(
                "TOTAL WORKERS THAT WOULD BE CREATED: {$overallTotal}"
            );

        } else {

            $this->info(
                "TOTAL WORKERS CREATED: {$overallTotal}"
            );
        }

        $this->line(str_repeat('=', 75));

        $this->newLine();

        if ($dryRun) {
            $this->warn(
                'Dry run completed. No database changes were made.'
            );
        } else {
            $this->info(
                'Supplier worker backfill completed successfully.'
            );
        }

        return self::SUCCESS;
    }

    /**
     * Decode categories_array safely.
     */
    private function decodeCategoryIds($categoriesArray): array
    {
        if (is_array($categoriesArray)) {
            return $categoriesArray;
        }

        if (!is_string($categoriesArray)) {
            return [];
        }

        $categoriesArray = trim($categoriesArray);

        if ($categoriesArray === '') {
            return [];
        }

        $decoded = json_decode($categoriesArray, true);

        if (!is_array($decoded)) {
            return [];
        }

        return $decoded;
    }
}