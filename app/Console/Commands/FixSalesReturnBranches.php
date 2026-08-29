<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\Models\CustomerPayment;
use App\Models\PhysicalQuantity;
use App\Models\SalesReturn;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FixSalesReturnBranches extends Command
{
    protected $signature = 'sales-returns:fix-branches
        {--dry-run : Sirf preview karo, kuch save mat karo}
        {--split-multi-branch-payments : Multi-branch payments ko split karo}';

    protected $description = 'Sales returns, physical quantities aur customer payments ka branch_id article ki asal branch ke mutabiq theek karo';

    /**
     * 🔑 RECOVERY MAP — ek purani backup (corruption se pehle) se nikala
     * gaya hai. Us backup me customer_payments.reff_no abhi tak sahi tha
     * (jaise 'SR-19'), jabke live production me wo '-' ho chuka hai.
     * Ye mapping: CustomerPayment.id => original sales_return_id (jo ab
     * sales_returns table se delete ho chuka hai, lekin physical_quantities
     * me abhi bhi reference maujood hai).
     *
     * Payment #6907 is list me shamil NAHI — wo already live matching se
     * (multi-branch split logic se) resolve ho jata hai, isay yahan
     * dobara handle karne ki zaroorat nahi.
     */
    private const RECOVERED_PAYMENT_TO_SALES_RETURN = [
        5982 => 19,  5983 => 20,  5984 => 21,  6182 => 22,  6213 => 23,
        6233 => 25,  6234 => 26,  6237 => 27,  6243 => 30,  6244 => 33,
        6252 => 41,  6269 => 43,  6270 => 50,  6271 => 54,  6273 => 69,
        6274 => 70,  6275 => 71,  6276 => 74,  6277 => 75,  6278 => 76,
        6279 => 88,  6280 => 99,  6289 => 101, 6308 => 110, 6309 => 113,
        6339 => 114, 6344 => 115, 6345 => 116, 6346 => 125, 6347 => 139,
        6348 => 158, 6439 => 177, 6444 => 183, 6446 => 199, 6610 => 231,
        6612 => 235, 6613 => 238, 6614 => 258, 6615 => 280, 6616 => 305,
        6617 => 319, 6618 => 322, 6620 => 334, 6660 => 344, 6661 => 357,
        6662 => 369, 6663 => 383, 6664 => 398, 6672 => 416, 6687 => 443,
        6688 => 470, 6689 => 479, 6701 => 482, 6714 => 490,
    ];

    public function handle(): int
    {
        
        $systemUserId = User::query()->value('id');

        if ($systemUserId === null) {
            $this->error('Users table mein koi user nahi mila. creator_id set nahi kiya ja sakta.');
            return self::FAILURE;
        }
        
        $dryRun = (bool) $this->option('dry-run');
        $splitMultiBranch = (bool) $this->option('split-multi-branch-payments');

        if (!$dryRun) {
            if (!$this->confirm('⚠️  Aapne DB ka backup le liya hai? Ye command live data update karegi. Continue karein?')) {
                $this->warn('Rok diya. Pehle backup le kar dobara chalayein.');
                return self::FAILURE;
            }
        }

        if (!Schema::hasColumn('sales_returns', 'branch_id')) {
            $this->error('sales_returns.branch_id column nahi mila, ruk raha hu.');
            return self::FAILURE;
        }

        $hasPhysicalQuantityBranch = Schema::hasColumn('physical_quantities', 'branch_id');
        $hasCustomerPaymentBranch = Schema::hasColumn('customer_payments', 'branch_id');

        $stats = [
            'sales_returns_fixed' => 0,
            'physical_quantities_fixed' => 0,
            'payments_fixed_via_reffno' => 0,
            'payments_fixed_via_1to1_match' => 0,
            'payments_fixed_via_group_sum' => 0,
            'payments_fixed_via_recovery_map' => 0,
            'payments_split_multi_branch' => 0,
            'payments_already_correct' => 0,
            'payments_manual_review' => 0,
            'sales_return_payments_repaired' => 0,
            'sales_return_payments_created' => 0,
            'sales_return_reffnos_repaired' => 0,
        ];

        $manualReview = [];

        DB::transaction(function () use (
            $dryRun,
            $splitMultiBranch,
            $hasPhysicalQuantityBranch,
            $hasCustomerPaymentBranch,
            &$stats,
            &$manualReview,
            $systemUserId
        ) {
            // ---------------------------------------------------------
            // STEP 1: SalesReturn -> article ki branch
            // ---------------------------------------------------------
            SalesReturn::with('article')->chunkById(200, function ($returns) use ($dryRun, &$stats) {
                foreach ($returns as $return) {
                    $articleBranchId = $return->article?->branch_id;
                    if ($articleBranchId === null || (int) $return->branch_id === (int) $articleBranchId) {
                        continue;
                    }
                    $this->line("SalesReturn #{$return->id}: branch_id {$return->branch_id} -> {$articleBranchId}");
                    $stats['sales_returns_fixed']++;
                    if (!$dryRun) {
                        $return->branch_id = $articleBranchId;
                        $return->save();
                    }
                }
            });

            // ---------------------------------------------------------
            // STEP 2: PhysicalQuantity -> article ki branch
            // ---------------------------------------------------------
            if ($hasPhysicalQuantityBranch) {
                PhysicalQuantity::whereIn('category', ['sales_return', 'adjustment'])
                    ->with('article')
                    ->chunkById(200, function ($rows) use ($dryRun, &$stats) {
                        foreach ($rows as $row) {
                            $articleBranchId = $row->article?->branch_id;
                            if ($articleBranchId === null || (int) $row->branch_id === (int) $articleBranchId) {
                                continue;
                            }
                            $this->line("PhysicalQuantity #{$row->id}: branch_id {$row->branch_id} -> {$articleBranchId}");
                            $stats['physical_quantities_fixed']++;
                            if (!$dryRun) {
                                $row->branch_id = $articleBranchId;
                                $row->save();
                            }
                        }
                    });
            }

            if (!$hasCustomerPaymentBranch) {
                return;
            }

            $unmatchedPayments = collect();

            // ---------------------------------------------------------
            // STEP 3: SalesReturn -> CustomerPayment synchronization
            // ---------------------------------------------------------
            // SalesReturn is the source of truth. Maintain one return
            // payment per customer/date/type/branch group and repair its
            // reference to SR-{first_sales_return_id} / ADJ-{id}.
            if ($hasCustomerPaymentBranch) {
                $salesReturns = SalesReturn::with(['article', 'invoice'])
                    ->whereHas('invoice')
                    ->orderBy('id')
                    ->get();

                $groups = $salesReturns->groupBy(function ($sr) {
                    $customerId = $sr->invoice?->customer_id;
                    $branchId = $sr->article?->branch_id ?? $sr->branch_id;
                    $rawDate = $sr->getRawOriginal('date');

                    return $customerId . '|' . $rawDate . '|' . $sr->type . '|' . ($branchId ?? 'NULL');
                });

                foreach ($groups as $group) {
                    $firstReturn = $group->first();
                    $customerId = $firstReturn->invoice?->customer_id;
                    $branchId = $firstReturn->article?->branch_id ?? $firstReturn->branch_id;

                    if ($customerId === null || $branchId === null) {
                        continue;
                    }

                    $totalAmount = (float) $group->sum('amount');
                    if ($totalAmount <= 0) {
                        continue;
                    }

                    $prefix = $firstReturn->type === 'adjustment' ? 'ADJ-' : 'SR-';
                    $canonicalReff = $prefix . $firstReturn->id;
                    $returnIds = $group->pluck('id')->map(fn ($id) => (int) $id)->values();

                    $payments = CustomerPayment::where('customer_id', $customerId)
                        ->where('type', 'sales_return')
                        ->whereDate('date', $firstReturn->getRawOriginal('date'))
                        ->where(function ($q) use ($branchId) {
                            $q->where('branch_id', $branchId)
                              ->orWhereNull('branch_id');
                        })
                        ->orderBy('id')
                        ->get();

                    $matchingPayment = $payments->firstWhere('reff_no', $canonicalReff);

                    if (!$matchingPayment) {
                        $matchingPayment = $payments->first(function ($payment) use ($returnIds) {
                            $mapped = self::RECOVERED_PAYMENT_TO_SALES_RETURN[$payment->id] ?? null;
                            return $mapped !== null && $returnIds->contains((int) $mapped);
                        });
                    }

                    if (!$matchingPayment) {
                        $matchingPayment = $payments->first(function ($payment) use ($totalAmount) {
                            return abs((float) $payment->amount - $totalAmount) < 0.00001;
                        });
                    }

                    if ($matchingPayment) {
                        $changed = false;

                        if ((string) $matchingPayment->reff_no !== $canonicalReff) {
                            $this->line("CustomerPayment #{$matchingPayment->id}: reff_no {$matchingPayment->reff_no} -> {$canonicalReff}");
                            $stats['sales_return_reffnos_repaired']++;
                            $matchingPayment->reff_no = $canonicalReff;
                            $changed = true;
                        }

                        if ((int) $matchingPayment->branch_id !== (int) $branchId) {
                            $this->line("CustomerPayment #{$matchingPayment->id}: branch_id {$matchingPayment->branch_id} -> {$branchId}");
                            $matchingPayment->branch_id = $branchId;
                            $changed = true;
                        }

                        if ($changed && !$dryRun) {
                            $matchingPayment->save();
                        }

                        $stats['sales_return_payments_repaired']++;
                    } else {
                        $this->line("Missing CustomerPayment: customer={$customerId}, branch={$branchId}, amount={$totalAmount}, reff_no={$canonicalReff}");
                        $stats['sales_return_payments_created']++;

                        if (!$dryRun) {
                            CustomerPayment::create([
                                'customer_id' => $customerId,
                                'date' => $firstReturn->getRawOriginal('date'),
                                'type' => 'sales_return',
                                'method' => 'return',
                                'amount' => $totalAmount,
                                'reff_no' => $canonicalReff,
                                'remarks' => $firstReturn->type === 'adjustment'
                                    ? 'Invoice adjustment'
                                    : 'Sales return',
                                'branch_id' => $branchId,
                                'creator_id' => $systemUserId,
                            ]);
                        }
                    }
                }
            }

            // ---------------------------------------------------------
            // STEP 4: CustomerPayment via reff_no
            // ---------------------------------------------------------
            CustomerPayment::where('type', 'sales_return')
                ->orderBy('id')
                ->get()
                ->each(function ($payment) use ($dryRun, &$stats, &$unmatchedPayments) {
                    if (!preg_match('/^(?:SR|ADJ)-(\d+)$/', (string) $payment->reff_no, $m)) {
                        $unmatchedPayments->push($payment);
                        return;
                    }

                    $branchId = $this->resolveBranchForSalesReturnId((int) $m[1]);

                    if ($branchId === null) {
                        $unmatchedPayments->push($payment);
                        return;
                    }

                    if ((int) $payment->branch_id === (int) $branchId) {
                        $stats['payments_already_correct']++;
                        return;
                    }

                    $this->line("CustomerPayment #{$payment->id}: branch_id {$payment->branch_id} -> {$branchId} (via reff_no)");
                    $stats['payments_fixed_via_reffno']++;
                    if (!$dryRun) {
                        $payment->branch_id = $branchId;
                        $payment->save();
                    }
                });

            // ---------------------------------------------------------
            // STEP 5: 🔑 Recovery map se fix (corrupted reff_no wale)
            // ---------------------------------------------------------
            $stillUnmatched = collect();

            foreach ($unmatchedPayments as $payment) {
                $srId = self::RECOVERED_PAYMENT_TO_SALES_RETURN[$payment->id] ?? null;

                if ($srId === null) {
                    $stillUnmatched->push($payment);
                    continue;
                }

                $branchId = $this->resolveBranchForSalesReturnId($srId);

                if ($branchId === null) {
                    $manualReview[] = [
                        'payment_id' => $payment->id,
                        'reason' => "recovery map points to sales_return_id={$srId} but its article/branch could not be resolved even via physical_quantities — is data ko manually check karna hoga",
                    ];
                    $stats['payments_manual_review']++;
                    continue;
                }

                if ((int) $payment->branch_id === (int) $branchId) {
                    $stats['payments_already_correct']++;
                    continue;
                }

                $this->line("CustomerPayment #{$payment->id}: branch_id {$payment->branch_id} -> {$branchId} (via recovery map, original sales_return_id={$srId})");
                $stats['payments_fixed_via_recovery_map']++;
                if (!$dryRun) {
                    $payment->branch_id = $branchId;
                    $payment->save();
                }
            }

            if ($stillUnmatched->isEmpty()) {
                return;
            }

            // ---------------------------------------------------------
            // STEP 6: Baqi bache hue ke liye group/1:1/split matching
            // (jaise #6907 — jinke SalesReturn rows abhi bhi live DB me
            // maujood hain, sirf reff_no corrupt hai)
            // ---------------------------------------------------------
            $key = fn ($customerId, $rawDate, $type) => $customerId . '|' . $rawDate . '|' . $type;

            $allSalesReturns = SalesReturn::with(['article', 'invoice'])->get();
            $candidatesByGroup = [];
            foreach ($allSalesReturns as $sr) {
                $customerId = $sr->invoice?->customer_id;
                if ($customerId === null) continue;
                $groupKey = $key($customerId, $sr->getRawOriginal('date'), $sr->type);
                $candidatesByGroup[$groupKey][] = $sr;
            }

            $paymentsByGroup = [];
            foreach ($stillUnmatched as $payment) {
                $type = in_array($payment->remarks, ['Sales adjustment', 'Invoice adjustment'], true)
                    ? 'adjustment'
                    : 'return';
                $groupKey = $key($payment->customer_id, $payment->getRawOriginal('date'), $type);
                $paymentsByGroup[$groupKey][] = $payment;
            }

            foreach ($paymentsByGroup as $groupKey => $payments) {
                $candidates = collect($candidatesByGroup[$groupKey] ?? []);

                if ($candidates->isEmpty()) {
                    foreach ($payments as $payment) {
                        $manualReview[] = [
                            'payment_id' => $payment->id,
                            'reason' => 'reff_no corrupt, recovery map me nahi mila, aur live SalesReturn rows bhi nahi mile',
                            'customer_id' => $payment->customer_id,
                            'amount' => $payment->amount,
                        ];
                        $stats['payments_manual_review']++;
                    }
                    continue;
                }

                $usedCandidateIds = [];

                if (count($payments) > 1) {
                    foreach ($payments as $payment) {
                        $match = $candidates->first(fn ($sr) =>
                            !in_array($sr->id, $usedCandidateIds, true)
                            && (float) $sr->amount === (float) $payment->amount
                        );
                        if (!$match) continue;

                        $usedCandidateIds[] = $match->id;
                        $branchId = $match->article?->branch_id;
                        if ($branchId === null) continue;

                        if ((int) $payment->branch_id === (int) $branchId) {
                            $stats['payments_already_correct']++;
                        } else {
                            $this->line("CustomerPayment #{$payment->id}: branch_id {$payment->branch_id} -> {$branchId} (via 1:1 match with SalesReturn #{$match->id})");
                            $stats['payments_fixed_via_1to1_match']++;
                            if (!$dryRun) {
                                $payment->branch_id = $branchId;
                                $payment->save();
                            }
                        }
                    }
                }

                $remainingPayments = collect($payments)->reject(fn ($payment) =>
                    $candidates->contains(fn ($sr) => in_array($sr->id, $usedCandidateIds, true) && (float) $sr->amount === (float) $payment->amount)
                )->values();

                $remainingCandidates = $candidates->reject(fn ($sr) => in_array($sr->id, $usedCandidateIds, true))->values();

                if ($remainingPayments->count() === 1 && $remainingCandidates->isNotEmpty()) {
                    $payment = $remainingPayments->first();
                    $sum = $remainingCandidates->sum('amount');

                    if ((float) $sum !== (float) $payment->amount) {
                        $manualReview[] = [
                            'payment_id' => $payment->id,
                            'reason' => "amount mismatch: remaining candidates sum={$sum} vs payment amount={$payment->amount}",
                            'candidate_sales_return_ids' => $remainingCandidates->pluck('id')->implode(','),
                        ];
                        $stats['payments_manual_review']++;
                        continue;
                    }

                    $branches = $remainingCandidates->pluck('article.branch_id')->filter()->unique()->values();

                    if ($branches->count() === 1) {
                        $branchId = $branches->first();
                        if ((int) $payment->branch_id === (int) $branchId) {
                            $stats['payments_already_correct']++;
                        } else {
                            $this->line("CustomerPayment #{$payment->id}: branch_id {$payment->branch_id} -> {$branchId} (via group sum, SalesReturn IDs: {$remainingCandidates->pluck('id')->implode(',')})");
                            $stats['payments_fixed_via_group_sum']++;
                            if (!$dryRun) {
                                $payment->branch_id = $branchId;
                                $payment->save();
                            }
                        }
                        continue;
                    }

                    if (!$splitMultiBranch) {
                        $manualReview[] = [
                            'payment_id' => $payment->id,
                            'reason' => 'amount matched but spans multiple branches — re-run with --split-multi-branch-payments',
                            'candidate_sales_return_ids' => $remainingCandidates->pluck('id')->implode(','),
                            'candidate_branches' => $branches->implode(','),
                        ];
                        $stats['payments_manual_review']++;
                        continue;
                    }

                    $totalsByBranch = $remainingCandidates->groupBy('article.branch_id')->map(fn ($g) => $g->sum('amount'));
                    $this->line("CustomerPayment #{$payment->id}: splitting into " . $totalsByBranch->count() . " branch-specific payments: " . $totalsByBranch->toJson());
                    $stats['payments_split_multi_branch']++;

                    if (!$dryRun) {
                        $originalReffNo = $payment->reff_no;
                        $originalRemarks = $payment->remarks;
                        $originalDate = $payment->getRawOriginal('date');
                        $originalCustomerId = $payment->customer_id;
                        $originalType = $payment->type;
                        $originalMethod = $payment->method;
                        $payment->delete();

                        foreach ($totalsByBranch as $branchId => $branchAmount) {
                            CustomerPayment::create([
                                'customer_id' => $originalCustomerId,
                                'date' => $originalDate,
                                'type' => $originalType,
                                'method' => $originalMethod,
                                'amount' => $branchAmount,
                                'reff_no' => $originalReffNo,
                                'remarks' => $originalRemarks,
                                'branch_id' => $branchId,
                                'creator_id' => $systemUserId,
                            ]);
                        }
                    }
                    continue;
                }

                foreach ($remainingPayments as $payment) {
                    $manualReview[] = [
                        'payment_id' => $payment->id,
                        'reason' => 'could not confidently match',
                        'customer_id' => $payment->customer_id,
                        'amount' => $payment->amount,
                    ];
                    $stats['payments_manual_review']++;
                }
            }
        });

        $this->info('');
        $this->info(($dryRun ? '[DRY RUN] ' : '[APPLIED] ') . 'Summary:');
        foreach ($stats as $k => $v) {
            $this->line("  {$k}: {$v}");
        }

        if (!empty($manualReview)) {
            $this->warn('');
            $this->warn('⚠️  Ye records auto-fix nahi ho sake, manual review chahiye:');
            foreach ($manualReview as $item) {
                $this->line('  - ' . json_encode($item));
            }
        }

        return self::SUCCESS;
    }

    /**
     * sales_return_id se branch resolve karo — pehle live sales_returns
     * table se try karo, agar row delete ho chuki hai to physical_quantities
     * ke sales_return_id link se article dhoondo.
     */
    private function resolveBranchForSalesReturnId(int $srId): ?int
    {
        $salesReturn = SalesReturn::with('article')->find($srId);
        if ($salesReturn?->article?->branch_id !== null) {
            return (int) $salesReturn->article->branch_id;
        }

        $pq = PhysicalQuantity::with('article')->where('sales_return_id', $srId)->first();
        if ($pq?->article?->branch_id !== null) {
            return (int) $pq->article->branch_id;
        }

        return null;
    }
}
