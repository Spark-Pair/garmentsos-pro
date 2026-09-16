<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckActiveSession;
use App\Http\Middleware\SubscriptionExpiry;
use App\Http\Middleware\VerifyCsrfToken;
use App\Models\DailyLedgerDeposit;
use App\Models\Setup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DailyLedgerEditingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            CheckActiveSession::class,
            SubscriptionExpiry::class,
            VerifyCsrfToken::class,
        ]);
    }

    public function test_accountant_can_open_and_update_a_daily_ledger_entry(): void
    {
        $this->actingAs($this->user('accountant'));

        Setup::create([
            'type' => 'daily_ledger_method',
            'title' => 'Cash',
            'short_title' => 'DLM-CASH',
        ]);

        $deposit = DailyLedgerDeposit::create([
            'date' => '2026-09-15',
            'method' => 'Cash',
            'amount' => 1000,
            'reff_no' => 'OLD-REF',
        ]);

        $this->get(route('daily-ledger.edit', [
            'daily_ledger' => $deposit->id,
            'type' => 'deposit',
        ]))->assertOk()->assertSee('Edit Daily Ledger Deposit');

        $this->put(route('daily-ledger.update', [
            'daily_ledger' => $deposit->id,
            'type' => 'deposit',
        ]), [
            'ledger_type' => 'deposit',
            'date' => '2026-09-16',
            'method' => 'Cash',
            'amount' => 1250,
            'reff_no' => 'NEW-REF',
        ])->assertRedirect(route('daily-ledger.index'))
            ->assertSessionHas('success', 'Daily ledger deposit updated successfully.');

        $this->assertDatabaseHas('daily_ledger_deposits', [
            'id' => $deposit->id,
            'date' => '2026-09-16 00:00:00',
            'method' => 'Cash',
            'amount' => 1250,
            'reff_no' => 'NEW-REF',
        ]);
    }

    public function test_guest_cannot_open_daily_ledger_edit_form(): void
    {
        $this->actingAs($this->user('guest'));

        $deposit = DailyLedgerDeposit::create([
            'date' => '2026-09-15',
            'method' => 'Cash',
            'amount' => 1000,
        ]);

        $this->get(route('daily-ledger.edit', [
            'daily_ledger' => $deposit->id,
            'type' => 'deposit',
        ]))->assertRedirect(route('home'))
            ->assertSessionHas('error', 'You do not have permission to edit daily ledger records.');
    }

    private function user(string $role): User
    {
        return User::create([
            'name' => ucfirst($role) . ' User',
            'username' => $role . '_daily_ledger_user',
            'password' => Hash::make('password'),
            'role' => $role,
            'status' => 'active',
        ]);
    }
}
