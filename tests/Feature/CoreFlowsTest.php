<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckActiveSession;
use App\Http\Middleware\SubscriptionExpiry;
use App\Http\Middleware\VerifyCsrfToken;
use App\Models\BankAccount;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\PaymentProgram;
use App\Models\Setup;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CoreFlowsTest extends TestCase
{
    use RefreshDatabase;

    private function actingDeveloper(): User
    {
        $user = User::create([
            'name' => 'Dev User',
            'username' => 'dev_user',
            'password' => Hash::make('password'),
            'role' => 'developer',
            'status' => 'active',
        ]);

        $this->actingAs($user);
        $this->withoutMiddleware([CheckActiveSession::class, SubscriptionExpiry::class, VerifyCsrfToken::class]);

        return $user;
    }

    public function test_orders_create_with_date_returns_success(): void
    {
        $this->actingDeveloper();

        $response = $this->get('/orders/create?date=2026-02-26');

        $response->assertStatus(200);
    }

    public function test_shipments_create_with_date_returns_success(): void
    {
        $this->actingDeveloper();

        $response = $this->get('/shipments/create?date=2026-02-26');

        $response->assertStatus(200);
    }

    public function test_setups_can_store_multiple_null_short_titles(): void
    {
        $this->actingDeveloper();

        $firstResponse = $this->post(route('setups.store'), [
            'type' => 'fabric',
            'title' => 'Cotton',
            'short_title' => '',
        ]);

        $secondResponse = $this->post(route('setups.store'), [
            'type' => 'fabric',
            'title' => 'Linen',
            'short_title' => '   ',
        ]);

        $firstResponse->assertSessionHasNoErrors();
        $secondResponse->assertSessionHasNoErrors();
        $this->assertDatabaseHas('setups', [
            'type' => 'fabric',
            'title' => 'Cotton',
            'short_title' => null,
        ]);
        $this->assertDatabaseHas('setups', [
            'type' => 'fabric',
            'title' => 'Linen',
            'short_title' => null,
        ]);
    }

    public function test_payment_program_mark_paid_updates_status(): void
    {
        $user = $this->actingDeveloper();

        $city = Setup::create([
            'title' => 'Karachi',
            'short_title' => 'KHI',
            'type' => 'city',
        ]);

        $customerUser = User::create([
            'name' => 'Customer User',
            'username' => 'customer_user',
            'password' => Hash::make('password'),
            'role' => 'guest',
            'status' => 'active',
        ]);

        $customer = Customer::create([
            'user_id' => $customerUser->id,
            'customer_name' => 'Test Customer',
            'person_name' => 'Test Person',
            'phone_number' => '03001234567',
            'date' => '2026-02-26',
            'category' => 'cash',
            'city_id' => $city->id,
            'address' => 'Test Address',
            'creator_id' => $user->id,
        ]);

        $program = PaymentProgram::create([
            'program_no' => 999001,
            'date' => '2026-02-26',
            'customer_id' => $customer->id,
            'category' => 'waiting',
            'amount' => 1000,
            'status' => 'Unpaid',
        ]);

        \App\Models\CustomerPayment::create([
            'customer_id' => $customer->id,
            'date' => '2026-02-26',
            'type' => 'program',
            'method' => 'program',
            'amount' => 1000,
            'program_id' => $program->id,
        ]);

        $response = $this->post(route('payment-programs.mark-paid', $program->id));

        $response->assertRedirect(route('payment-programs.index'));
        $this->assertSame('Paid', $program->fresh()->status);
    }

    public function test_program_payment_can_exceed_remaining_balance_and_mark_program_overpaid(): void
    {
        $user = $this->actingDeveloper();

        $city = Setup::create([
            'title' => 'Lahore',
            'short_title' => 'LHR',
            'type' => 'city',
        ]);

        $customerUser = User::create([
            'name' => 'Customer 2',
            'username' => 'customer_user_2',
            'password' => Hash::make('password'),
            'role' => 'guest',
            'status' => 'active',
        ]);

        $customer = Customer::create([
            'user_id' => $customerUser->id,
            'customer_name' => 'Balance Check Customer',
            'person_name' => 'Person',
            'phone_number' => '03009998888',
            'date' => '2026-02-26',
            'category' => 'cash',
            'city_id' => $city->id,
            'address' => 'Addr',
            'creator_id' => $user->id,
        ]);

        $supplierUser = User::create([
            'name' => 'Program Supplier',
            'username' => 'program_supplier_user',
            'password' => Hash::make('password'),
            'role' => 'guest',
            'status' => 'active',
        ]);
        $supplier = Supplier::create([
            'user_id' => $supplierUser->id,
            'supplier_name' => 'Program Supplier',
            'person_name' => 'Supplier Person',
            'phone_number' => '03001112222',
            'date' => '2026-02-26',
            'categories_array' => '[]',
            'creator_id' => $user->id,
        ]);

        $program = PaymentProgram::create([
            'program_no' => 999002,
            'date' => '2026-02-26',
            'customer_id' => $customer->id,
            'category' => 'supplier',
            'sub_category_id' => $supplier->id,
            'sub_category_type' => Supplier::class,
            'amount' => 1000,
            'status' => 'Unpaid',
        ]);

        CustomerPayment::create([
            'customer_id' => $customer->id,
            'date' => '2026-02-26',
            'type' => 'program',
            'method' => 'program',
            'amount' => 900,
            'program_id' => $program->id,
        ]);

        $response = $this->post(route('customer-payments.store'), [
            'customer_id' => $customer->id,
            'date' => '2026-02-26',
            'type' => 'program',
            'method' => 'program',
            'amount' => 200,
            'program_id' => $program->id,
        ]);

        $response->assertSessionHas('success', 'Payment Added successfully.');
        $this->assertDatabaseHas('customer_payments', [
            'customer_id' => $customer->id,
            'program_id' => $program->id,
            'amount' => 200,
        ]);
        $createdPayment = CustomerPayment::where('program_id', $program->id)->latest('id')->firstOrFail();
        $this->assertDatabaseHas('supplier_payments', [
            'customer_payment_id' => $createdPayment->id,
            'supplier_id' => $supplier->id,
            'amount' => 200,
        ]);

        $updateResponse = $this->put(route('customer-payments.update', $createdPayment), [
            'customer_id' => $customer->id,
            'date' => '2026-02-27',
            'type' => 'payment_program',
            'method' => 'program',
            'amount' => 250,
            'program_id' => $program->id,
        ]);
        $updateResponse->assertSessionHas('success');
        $this->assertSame(1, SupplierPayment::where('customer_payment_id', $createdPayment->id)->count());
        $this->assertDatabaseHas('supplier_payments', [
            'customer_payment_id' => $createdPayment->id,
            'amount' => 250,
            'date' => '2026-02-27 00:00:00',
        ]);

        $markPaidResponse = $this->post(route('payment-programs.mark-paid', $program->id));

        $markPaidResponse->assertRedirect(route('payment-programs.index'));
        $this->assertSame('Overpaid', $program->fresh()->status);
    }

    public function test_clear_amount_cannot_exceed_outstanding_amount(): void
    {
        $user = $this->actingDeveloper();

        $city = Setup::create([
            'title' => 'Faisalabad',
            'short_title' => 'FSD',
            'type' => 'city',
        ]);

        $bank = Setup::create([
            'title' => 'Meezan Bank',
            'short_title' => 'MZN',
            'type' => 'bank_name',
        ]);

        $customerUser = User::create([
            'name' => 'Customer 3',
            'username' => 'customer_user_3',
            'password' => Hash::make('password'),
            'role' => 'guest',
            'status' => 'active',
        ]);

        $customer = Customer::create([
            'user_id' => $customerUser->id,
            'customer_name' => 'Clear Test Customer',
            'person_name' => 'Person',
            'phone_number' => '03007776666',
            'date' => '2026-02-26',
            'category' => 'cash',
            'city_id' => $city->id,
            'address' => 'Addr',
            'creator_id' => $user->id,
        ]);

        $payment = CustomerPayment::create([
            'customer_id' => $customer->id,
            'date' => '2026-02-26',
            'type' => 'cheque',
            'method' => 'cheque',
            'amount' => 1000,
            'cheque_no' => 'CHQ-001',
            'cheque_date' => '2026-02-26',
        ]);

        $bankAccount = BankAccount::create([
            'category' => 'self',
            'bank_id' => (string) $bank->id,
            'account_title' => 'Test Account',
            'date' => '2026-02-26',
            'account_no' => 'AC-0001',
        ]);

        $response = $this->post(route('customer-payments.clear', $payment->id), [
            'clear_date' => '2026-02-26',
            'method_select' => 'cheque',
            'bank_account_id' => $bankAccount->id,
            'amount' => 1200,
            'reff_no' => 'CLR-001',
        ]);

        $response->assertSessionHas('error', 'Clear amount cannot be greater than the remaining outstanding amount.');
    }

    public function test_self_bank_account_statement_excludes_returns_and_unvouchered_withdrawals_and_reconciles_with_balance(): void
    {
        $this->actingDeveloper();

        $bank = Setup::create([
            'title' => 'Statement Test Bank',
            'short_title' => 'STB',
            'type' => 'bank_name',
        ]);

        $account = BankAccount::create([
            'category' => 'self',
            'bank_id' => $bank->id,
            'account_title' => 'Self Statement Account',
            'date' => '2026-01-01',
            'account_no' => 'SELF-001',
        ]);

        $deposit = CustomerPayment::create([
            'date' => '2026-01-10',
            'type' => 'self_account_deposit',
            'method' => 'Cash',
            'amount' => 1000,
            'remarks' => 'Counter deposit',
            'bank_account_id' => $account->id,
        ]);

        $returnedDeposit = CustomerPayment::create([
            'date' => '2026-01-11',
            'type' => 'self_account_deposit',
            'method' => 'Cheque',
            'amount' => 400,
            'remarks' => 'Returned deposit',
            'bank_account_id' => $account->id,
            'is_return' => true,
        ]);

        $withdrawal = SupplierPayment::create([
            'date' => '2026-01-12',
            'method' => 'ATM',
            'amount' => 250,
            'remarks' => 'Valid withdrawal',
            'bank_account_id' => $account->id,
        ]);

        $returnedWithdrawal = SupplierPayment::create([
            'date' => '2026-01-13',
            'method' => 'Self Cheque',
            'amount' => 100,
            'remarks' => 'Returned withdrawal',
            'bank_account_id' => $account->id,
            'is_return' => true,
        ]);

        $account->statementAdjustments()->create([
            'date' => '2026-01-14',
            'entry_type' => 'adjustment',
            'direction' => 'plus',
            'amount' => 50,
            'remarks' => 'Bank correction',
        ]);

        $statement = $account->getStatement('2026-01-01', '2026-01-31', 'general');

        $this->assertSame(1050.0, (float) $account->calculateBalance());
        $this->assertSame(1050.0, (float) $statement['totals']['bill']);
        $this->assertSame(0.0, (float) $statement['totals']['payment']);
        $this->assertSame(1050.0, (float) $statement['closing_balance']);
        $this->assertSame(250.0, (float) $statement['totals']['pending_payment']);
        $this->assertCount(2, $statement['statements']);
        $depositRow = $statement['statements']->first(fn ($row) =>
            data_get($row, 'source.type') === 'customer_payment'
            && data_get($row, 'source.id') === $deposit->id
        );
        $this->assertSame('Counter deposit', $depositRow['description']);
        $this->assertFalse($statement['statements']->contains(fn ($row) =>
            data_get($row, 'source.type') === 'customer_payment'
            && data_get($row, 'source.id') === $returnedDeposit->id
        ));
        $this->assertFalse($statement['statements']->contains(fn ($row) =>
            data_get($row, 'source.type') === 'supplier_payment'
            && data_get($row, 'source.id') === $withdrawal->id
        ));
        $this->assertFalse($statement['statements']->contains(fn ($row) =>
            data_get($row, 'source.type') === 'supplier_payment'
            && data_get($row, 'source.id') === $returnedWithdrawal->id
        ));

        $summarized = $account->getStatement('2026-01-01', '2026-01-31', 'summarized');
        $this->assertSame(1050.0, (float) $summarized['totals']['bill']);
        $this->assertSame(0.0, (float) $summarized['totals']['payment']);
        $this->assertSame(1050.0, (float) $summarized['closing_balance']);
    }

    public function test_self_cheque_voucher_keeps_both_accounts_linked_through_create_update_and_delete(): void
    {
        $this->actingDeveloper();

        $bank = Setup::create([
            'title' => 'Transfer Test Bank',
            'short_title' => 'TTB',
            'type' => 'bank_name',
        ]);

        $source = BankAccount::create([
            'category' => 'self',
            'bank_id' => $bank->id,
            'account_title' => 'Source Account | SRC',
            'date' => '2026-01-01',
            'account_no' => 'SOURCE-001',
            'chqbk_serial_start' => 1001,
            'chqbk_serial_end' => 1010,
        ]);
        $destination = BankAccount::create([
            'category' => 'self',
            'bank_id' => $bank->id,
            'account_title' => 'Destination Account | DST',
            'date' => '2026-01-01',
            'account_no' => 'DEST-001',
        ]);

        $createResponse = $this->post(route('vouchers.store'), [
            'date' => '2026-01-15',
            'supplier_id' => null,
            'payment_details_array' => json_encode([[
                'method' => 'Self Cheque',
                'amount' => 600,
                'bank_account_id' => $source->id,
                'self_account_id' => $destination->id,
                'cheque_no' => 1001,
                'cheque_date' => '2026-01-15',
                'remarks' => 'Internal transfer',
            ]]),
        ]);

        $createResponse->assertSessionHas('success');
        $voucher = \App\Models\Voucher::latest('id')->firstOrFail();
        $payment = SupplierPayment::where('voucher_id', $voucher->id)->firstOrFail();
        $deposit = CustomerPayment::findOrFail($payment->cheque_id);

        $this->assertNull($voucher->supplier_id);
        $this->assertSame($source->id, $payment->bank_account_id);
        $this->assertSame($destination->id, $payment->self_account_id);
        $this->assertSame(1001, (int) $payment->cheque_no);
        $this->assertSame($destination->id, $deposit->bank_account_id);
        $this->assertSame(-600.0, (float) $source->fresh()->calculateBalance());
        $this->assertSame(600.0, (float) $destination->fresh()->calculateBalance());
        $this->assertNotContains(1001, $source->fresh()->available_cheques);

        $sourceStatement = $source->fresh()->getStatement('2026-01-01', '2026-01-31', 'general');
        $destinationStatement = $destination->fresh()->getStatement('2026-01-01', '2026-01-31', 'general');
        $sourceRow = $sourceStatement['statements']->first();
        $destinationRow = $destinationStatement['statements']->first();

        $this->assertStringContainsString('SRC (-)', $sourceRow['description']);
        $this->assertStringContainsString('DST (+)', $sourceRow['description']);
        $this->assertStringNotContainsString('Source Account', $sourceRow['description']);
        $this->assertStringNotContainsString('TTB', $sourceRow['description']);
        $this->assertSame(1001, (int) $sourceRow['reff_no']);
        $this->assertStringContainsString('Voucher: ' . $voucher->voucher_no, $sourceRow['description']);
        $this->assertStringContainsString('Internal transfer', $sourceRow['description']);
        $this->assertSame($sourceRow['description'], $destinationRow['description']);
        $this->assertSame(['type' => 'voucher', 'id' => $voucher->id], $destinationRow['source']);

        $voucherPayload = $voucher->fresh()->load([
            'payments.cheque',
            'payments.bankAccount.bank',
            'payments.selfAccount.bank',
        ])->toFormattedArray();
        $voucherPayment = $voucherPayload['data']['payments']->first();
        $this->assertSame('SRC', $voucherPayment['bank_account']['display_label']);
        $this->assertSame('DST', $voucherPayment['self_account']['display_label']);
        $this->assertSame($voucher->voucher_no, $voucherPayment['voucher_no']);
        $this->assertSame('Internal transfer', $voucherPayment['remarks']);

        $updateResponse = $this->put(route('vouchers.update', $voucher), [
            'payment_details_array' => json_encode([[
                'method' => 'Self Cheque',
                'amount' => 700,
                'bank_account_id' => $source->id,
                'self_account_id' => $destination->id,
                'cheque_no' => 1002,
                'cheque_date' => '2026-01-16',
                'remarks' => 'Updated internal transfer',
            ]]),
        ]);

        $updateResponse->assertSessionHas('success');
        $updatedPayment = SupplierPayment::where('voucher_id', $voucher->id)->firstOrFail();
        $this->assertSame(1002, (int) $updatedPayment->cheque_no);
        $this->assertNotNull($updatedPayment->cheque_id);
        $this->assertSame(-700.0, (float) $source->fresh()->calculateBalance());
        $this->assertSame(700.0, (float) $destination->fresh()->calculateBalance());
        $this->assertContains(1001, $source->fresh()->available_cheques);
        $this->assertNotContains(1002, $source->fresh()->available_cheques);

        // Legacy edited rows can have a null supplier-side cheque_no; the linked
        // deposit must still keep that cheque reserved.
        $updatedPayment->update(['cheque_no' => null]);
        $this->assertNotContains(1002, $source->fresh()->available_cheques);

        $duplicateResponse = $this->post(route('vouchers.store'), [
            'date' => '2026-01-16',
            'supplier_id' => null,
            'payment_details_array' => json_encode([[
                'method' => 'Self Cheque',
                'amount' => 100,
                'bank_account_id' => $source->id,
                'self_account_id' => $destination->id,
                'cheque_no' => 1002,
                'cheque_date' => '2026-01-16',
                'remarks' => 'Duplicate must fail',
            ]]),
        ]);
        $duplicateResponse->assertSessionHasErrors('payment_details_array');
        $this->assertSame(1, \App\Models\Voucher::count());

        $deleteResponse = $this->delete(route('vouchers.destroy', $voucher));
        $deleteResponse->assertSessionHas('success');
        $this->assertDatabaseMissing('vouchers', ['id' => $voucher->id]);
        $this->assertDatabaseMissing('supplier_payments', ['voucher_id' => $voucher->id]);
        $this->assertDatabaseMissing('customer_payments', ['id' => $updatedPayment->cheque_id]);
        $this->assertSame(0.0, (float) $source->fresh()->calculateBalance());
        $this->assertSame(0.0, (float) $destination->fresh()->calculateBalance());
    }

    public function test_incomplete_self_cheque_does_not_create_a_voucher(): void
    {
        $this->actingDeveloper();

        $response = $this->post(route('vouchers.store'), [
            'date' => '2026-01-15',
            'supplier_id' => null,
            'payment_details_array' => json_encode([[
                'method' => 'Self Cheque',
                'amount' => 600,
                'cheque_no' => 1001,
                'cheque_date' => '2026-01-15',
            ]]),
        ]);

        $response->assertSessionHasErrors('payment_details_array');
        $this->assertDatabaseCount('vouchers', 0);
        $this->assertDatabaseCount('supplier_payments', 0);
        $this->assertDatabaseCount('customer_payments', 0);
    }

    public function test_legacy_unpaired_self_cheque_still_credits_destination_once(): void
    {
        $this->actingDeveloper();

        $bank = Setup::create([
            'title' => 'Legacy Transfer Bank',
            'short_title' => 'LTB',
            'type' => 'bank_name',
        ]);
        $source = BankAccount::create([
            'category' => 'self',
            'bank_id' => $bank->id,
            'account_title' => 'Legacy Source | LS',
            'date' => '2026-01-01',
        ]);
        $destination = BankAccount::create([
            'category' => 'self',
            'bank_id' => $bank->id,
            'account_title' => 'Legacy Destination | LD',
            'date' => '2026-01-01',
        ]);
        $voucher = \App\Models\Voucher::create([
            'voucher_no' => 'LEGACY-SELF-1',
            'date' => '2026-01-20',
        ]);
        SupplierPayment::create([
            'date' => '2026-01-20',
            'method' => 'Self Cheque',
            'amount' => 300,
            'cheque_no' => 5001,
            'bank_account_id' => $source->id,
            'self_account_id' => $destination->id,
            'voucher_id' => $voucher->id,
            'remarks' => 'Legacy missing deposit link',
        ]);

        $this->assertSame(-300.0, (float) $source->fresh()->calculateBalance());
        $this->assertSame(300.0, (float) $destination->fresh()->calculateBalance());

        $statement = $destination->fresh()->getStatement('2026-01-01', '2026-01-31', 'general');
        $this->assertCount(1, $statement['statements']);
        $this->assertSame(300.0, (float) $statement['totals']['bill']);
        $this->assertSame(0.0, (float) $statement['totals']['payment']);
        $this->assertStringContainsString('LS (-)', $statement['statements']->first()['description']);
        $this->assertStringContainsString('LD (+)', $statement['statements']->first()['description']);
        $this->assertStringNotContainsString('Legacy Source', $statement['statements']->first()['description']);
        $this->assertStringNotContainsString('LTB', $statement['statements']->first()['description']);
        $this->assertSame(5001, (int) $statement['statements']->first()['reff_no']);
        $this->assertStringContainsString('Voucher: LEGACY-SELF-1', $statement['statements']->first()['description']);
        $this->assertSame(['type' => 'voucher', 'id' => $voucher->id], $statement['statements']->first()['source']);
    }
}
