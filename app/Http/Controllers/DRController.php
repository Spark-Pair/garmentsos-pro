<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\DR;
use App\Models\Setup;
use App\Services\Branches\BranchSerialService;
use App\Services\Branches\ModuleBranchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class DRController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // $drs = DR::with('customer.city')->orderBy('id', 'desc')->get();
        $authLayout = $this->getAuthLayout($request->route()->getName(), 'table');

        if ($request->ajax()) {
            $drs = app(ModuleBranchService::class)
                ->applyScope(DR::orderByDesc('id'), 'dr')
                ->applyFilters($request);

            return response()->json(['data' => $drs, 'authLayout' => $authLayout]);
        }

        return view('dr.index', compact('authLayout'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'manager', 'admin', 'accountant', 'guest'])) {
            return $resp;
        }

        $branches = app(ModuleBranchService::class);
        $customer_options = $branches->applyRelatedScope(Customer::select('id', 'customer_name', 'city_id'), 'customers', 'dr')
            ->distinct()
            ->whereHas('user', function ($query) {
                $query->where('status', 'active');
            })
            ->orderBy('customer_name')
            ->get()
            ->mapWithKeys(function ($customer) {
                return [
                    (int)$customer->id => [
                        'text' => ucfirst($customer->customer_name) . ' | ' . strtoupper($customer->city->short_title),
                    ]
                ];
            });

        $bank_options = $branches->applyRelatedScope(Setup::where('type', 'bank_name'), 'setups', 'dr')
            ->distinct()
            ->orderBy('title')
            ->get()
            ->mapWithKeys(function ($bank) {
                return [
                    (int)$bank->id => [
                        'text' => ucfirst($bank->title),
                    ]
                ];
            });

        return view('dr.generate', compact('customer_options', 'bank_options'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'manager', 'admin', 'accountant', 'guest'])) {
            return $resp;
        }

        $validator = Validator::make($request->all(), [
            'customer_id' => 'nullable|integer|exists:customers,id',
            'date' => 'required|date',
            'returnPayments' => 'required|string',
            'newPayments' => 'required|string',
        ]);

        // Check for validation errors
        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $data = [
            'customer_id' => $request->customer_id,
            'date' => $request->date,
            'return_payments' => json_decode($request->returnPayments ?? '[]'),
            'new_payments_data' => json_decode($request->newPayments ?? '[]'),
        ];

        $returnEmpty = empty($data['return_payments']);
        $newEmpty = empty($data['new_payments_data']);

        if ($returnEmpty && $newEmpty) {
            return redirect()->back()->withInput()->with('error', 'Please select returned payments and add replacement payments.');
        }

        if ($returnEmpty) {
            return redirect()->back()->withInput()->with('error', 'Please select at least one returned payment.');
        }

        if ($newEmpty) {
            return redirect()->back()->withInput()->with('error', 'Please add at least one replacement payment.');
        }

        $data['new_payments'] = [];

        $branches = app(ModuleBranchService::class);
        $customer = $branches->applyRelatedScope(Customer::query(), 'customers', 'dr')->find($data['customer_id']);
        if (!$customer) {
            return redirect()->back()->withErrors(['customer_id' => 'Selected customer is not available for this branch.'])->withInput();
        }

        $dr = new DR($branches->assignBranchOnCreate($data, 'dr'));
        $dr->save(); // 👈 pehle save karenge taake $dr->id mil jaye
        $dr->d_r_no = app(BranchSerialService::class)->formatBranchDocumentNumber(
            $dr->id,
            'dr',
            $branches->selectedBranchForModule('dr')
        );
        $dr->save(); // 👈 pehle save karenge taake $dr->id mil jaye

        foreach($data['return_payments'] as $paymentId) {
            $branches->applyRelatedScope(CustomerPayment::query(), 'customer_payments', 'dr')
                ->findOrFail($paymentId)
                ->update(['clear_date' => $data['date'], 'd_r_id' => $dr->id]);
        }

        foreach ($data['new_payments_data'] as $payment) {
            $newPayment = CustomerPayment::create($branches->assignBranchOnCreate([
                'customer_id'     => $data['customer_id'],
                'date'            => $payment->date ?? $data['date'],
                'type'            => 'DR',
                'method'          => strtolower($payment->method),
                'amount'          => $payment->amount,
                'cheque_no'          => $payment->cheque_no ?? null,
                'slip_no'          => $payment->slip_no ?? null,
                'transaction_id'          => $payment->transaction_id ?? null,
                'cheque_date'          => $payment->cheque_date ?? null,
                'slip_date'          => $payment->slip_date ?? null,
                'bank_id'          => $payment->bank_id ?? null,
                'remarks'          => $payment->remarks ?? null,
            ], 'customer_payments'));

            $data['new_payments'][] = $newPayment->id;
        }

        $dr->new_payments = $data['new_payments'];
        $dr->save(); // 👈 dubara save karenge taake new_payments update ho jaye

        return redirect()->route('dr.create')->with('success', 'DR Generated successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(DR $dR)
    {
        app(ModuleBranchService::class)->assertRecordInAllowedBranch($dR, 'dr');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(DR $dR)
    {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'manager', 'admin', 'accountant', 'guest'])) {
            return $resp;
        }

        app(ModuleBranchService::class)->assertRecordInAllowedBranch($dR, 'dr');

        $branches = app(ModuleBranchService::class);
        $customer_options = $branches->applyRelatedScope(Customer::select('id', 'customer_name', 'city_id'), 'customers', 'dr')
            ->distinct()->whereHas('user', fn ($query) => $query->where('status', 'active'))
            ->orderBy('customer_name')->get()->mapWithKeys(fn ($customer) => [
                (int) $customer->id => ['text' => ucfirst($customer->customer_name) . ' | ' . strtoupper($customer->city->short_title)],
            ]);
        // Keep the record's current customer selectable during edit even if
        // current branch/user filters would otherwise omit this legacy record.
        if (!$customer_options->has((int) $dR->customer_id)) {
            $editCustomer = $dR->customer()->with('city')->first();
            if ($editCustomer) {
                $customer_options->put((int) $editCustomer->id, [
                    'text' => ucfirst($editCustomer->customer_name) . ' | ' . strtoupper($editCustomer->city?->short_title ?? ''),
                ]);
            }
        }
        $bank_options = $branches->applyRelatedScope(Setup::where('type', 'bank_name'), 'setups', 'dr')
            ->distinct()->orderBy('title')->get()->mapWithKeys(fn ($bank) => [
                (int) $bank->id => ['text' => ucfirst($bank->title)],
            ]);
        $new_payment_ids = collect($dR->new_payments ?? [])
            ->map(fn ($id) => is_array($id) ? ($id['id'] ?? $id['payment_id'] ?? null) : $id)
            ->filter()->values();
        $new_payment_details = CustomerPayment::whereIn('id', $new_payment_ids)->get()->map(fn ($payment) => [
            'id' => $payment->id,
            'method' => $payment->method,
            'amount' => $payment->amount,
            'cheque_no' => $payment->cheque_no,
            'slip_no' => $payment->slip_no,
            'transaction_id' => $payment->transaction_id,
            'date' => $payment->date?->format('Y-m-d'),
        ])->values();
        $return_payment_ids = collect($dR->return_payments ?? [])
            ->map(fn ($id) => is_array($id) ? ($id['id'] ?? $id['payment_id'] ?? null) : $id)
            ->filter()->values();
        $return_payment_details = CustomerPayment::whereIn('id', $return_payment_ids)->get()->map(fn ($payment) => [
            'id' => $payment->id,
            'date' => $payment->date?->format('Y-m-d'),
            'method' => $payment->method,
            'cheque_no' => $payment->cheque_no,
            'slip_no' => $payment->slip_no,
            'amount' => $payment->amount,
            'is_return' => (bool) $payment->is_return,
        ])->values();

        return view('dr.generate', compact('dR', 'customer_options', 'bank_options', 'new_payment_details', 'return_payment_details'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, DR $dR)
    {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'manager', 'admin', 'accountant', 'guest'])) {
            return $resp;
        }

        app(ModuleBranchService::class)->assertRecordInAllowedBranch($dR, 'dr');

        $validated = $request->validate([
            'customer_id' => 'required|integer|exists:customers,id',
            'date' => 'required|date',
            'd_r_no' => 'required|string|max:255',
            'returnPayments' => 'nullable|string',
            'newPayments' => 'nullable|string',
        ]);

        $returnPayments = collect(json_decode((string) $request->input('returnPayments', ''), true) ?: ($dR->return_payments ?? []))
            ->map(fn ($id) => (int) (is_array($id) ? ($id['id'] ?? $id['payment_id'] ?? 0) : $id))->filter();
        $newPayments = collect(json_decode((string) $request->input('newPayments', ''), true) ?: ($dR->new_payments ?? []));
        if ($returnPayments->isEmpty() || $newPayments->isEmpty()) {
            return redirect()->back()->withInput()->with('error', 'Please select returned payments and add replacement payments.');
        }

        $branches = app(ModuleBranchService::class);
        $customerId = $validated['customer_id'] ?: $dR->customer_id;
        $customer = $branches->applyRelatedScope(Customer::query(), 'customers', 'dr')->find($customerId);
        if (!$customer) {
            return redirect()->back()->withInput()->withErrors(['customer_id' => 'Selected customer is not available for this branch.']);
        }

        DB::transaction(function () use ($dR, $validated, $customerId, $returnPayments, $newPayments, $branches) {
            $paymentQuery = fn () => $branches->applyRelatedScope(CustomerPayment::query(), 'customer_payments', 'dr');
            $oldReturnIds = collect($dR->return_payments ?? [])->map(fn ($id) => (int) (is_array($id) ? ($id['id'] ?? $id['payment_id'] ?? 0) : $id))->filter();
            $oldReturnIds->diff($returnPayments)->each(fn ($paymentId) => $paymentQuery()->whereKey($paymentId)->where('d_r_id', $dR->id)->update(['clear_date' => null, 'd_r_id' => null]));
            $returnPayments->each(fn ($paymentId) => $paymentQuery()->whereKey($paymentId)->update(['clear_date' => $validated['date'], 'd_r_id' => $dR->id]));

            $oldNewIds = collect($dR->new_payments ?? [])->map(fn ($id) => (int) (is_array($id) ? ($id['id'] ?? $id['payment_id'] ?? 0) : $id))->filter();
            $incomingIds = $newPayments->map(fn ($payment) => (int) data_get($payment, 'id'))->filter();
            $oldNewIds->diff($incomingIds)->each(fn ($paymentId) => $paymentQuery()->whereKey($paymentId)->where('type', 'DR')->delete());
            $savedNewIds = [];

            foreach ($newPayments as $payment) {
                $existingId = (int) data_get($payment, 'id');
                if ($existingId && $oldNewIds->contains($existingId)) {
                    $savedNewIds[] = $existingId;
                    continue;
                }

                $created = CustomerPayment::create($branches->assignBranchOnCreate([
                    'customer_id' => $customerId,
                    'date' => data_get($payment, 'date') ?: $validated['date'],
                    'type' => 'DR',
                    'method' => strtolower((string) data_get($payment, 'method')),
                    'amount' => data_get($payment, 'amount', 0),
                    'cheque_no' => data_get($payment, 'cheque_no'),
                    'slip_no' => data_get($payment, 'slip_no'),
                    'transaction_id' => data_get($payment, 'transaction_id'),
                    'cheque_date' => data_get($payment, 'cheque_date'),
                    'slip_date' => data_get($payment, 'slip_date'),
                    'bank_id' => data_get($payment, 'bank_id'),
                    'remarks' => data_get($payment, 'remarks'),
                ], 'customer_payments'));
                $savedNewIds[] = $created->id;
            }

            $dR->update([
                'customer_id' => $customerId,
                'date' => $validated['date'],
                'd_r_no' => $validated['d_r_no'],
                'return_payments' => $returnPayments->values()->all(),
                'new_payments' => $savedNewIds,
            ]);
        });

        return redirect()->route('dr.index')->with('success', 'DR updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(DR $dR)
    {
        if (Auth::user()?->role !== 'developer' && !app_can('dr', 'override')) {
            abort(403, 'Only a developer or developer-mode user can delete DR records.');
        }

        $branches = app(ModuleBranchService::class);
        $branches->assertRecordInAllowedBranch($dR, 'dr');

        DB::transaction(function () use ($dR, $branches) {
            $branches->applyRelatedScope(CustomerPayment::query(), 'customer_payments', 'dr')
                ->where('d_r_id', $dR->id)
                ->update(['clear_date' => null, 'd_r_id' => null]);

            $newPaymentIds = collect($dR->new_payments ?? [])
                ->map(fn ($id) => is_array($id) ? ($id['id'] ?? $id['payment_id'] ?? null) : $id)
                ->filter()
                ->values();

            if ($newPaymentIds->isNotEmpty()) {
                $branches->applyRelatedScope(CustomerPayment::query(), 'customer_payments', 'dr')
                    ->whereIn('id', $newPaymentIds)
                    ->where('type', 'DR')
                    ->delete();
            }

            $dR->delete();
        });

        return redirect()->route('dr.index')->with('success', 'DR deleted successfully.');
    }

    public function getPayments(Request $request)
    {
        $payments = app(ModuleBranchService::class)
            ->applyRelatedScope(CustomerPayment::where('customer_id', $request->customer_id), 'customer_payments', 'dr')
            ->whereIn('method', ['cheque', 'slip'])
            ->where(function ($query) use ($request) {
                $query->whereNull('d_r_id');
                if ($request->filled('dr_id')) {
                    $query->orWhere('d_r_id', $request->dr_id);
                }
            })
            ->where('is_return', true)
            ->get();

        return response()->json(['status' => 'success', 'data' => $payments]);
    }
}
