<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckActiveSession;
use App\Http\Middleware\SubscriptionExpiry;
use App\Http\Middleware\VerifyCsrfToken;
use App\Models\Article;
use App\Models\BranchModuleSetting;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\Invoice;
use App\Models\SalesReturn;
use App\Models\Setup;
use App\Models\User;
use App\Services\Branches\ModuleBranchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class OwnerDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([CheckActiveSession::class, SubscriptionExpiry::class, VerifyCsrfToken::class]);
    }

    public function test_developer_sees_owner_dashboard_using_existing_app_shell(): void
    {
        $this->actingAs($this->user('developer'))
            ->get(route('home'))
            ->assertOk()
            ->assertSee('Business Dashboard')
            ->assertSee('Customer Receivable')
            ->assertSee('data-dashboard-apply', false)
            ->assertSee('data-dashboard-detail="branches"', false);
    }

    public function test_non_developer_keeps_the_existing_welcome_home(): void
    {
        $this->actingAs($this->user('owner'))
            ->get(route('home'))
            ->assertOk()
            ->assertSee('Welcome to')
            ->assertDontSee('OWNER OVERVIEW');
    }

    public function test_dashboard_data_is_developer_only_and_returns_reusable_summary_html(): void
    {
        $query = ['from' => today()->startOfMonth()->toDateString(), 'to' => today()->toDateString()];

        $this->actingAs($this->user('developer'))
            ->getJson(route('home.dashboard-data', $query))
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonStructure([
                'html',
                'summary' => [
                    'meta', 'modules', 'financial', 'activity', 'receivables', 'payables',
                    'orders', 'stock', 'inventory', 'operations', 'alerts', 'branches', 'recent',
                ],
            ]);

        $this->actingAs($this->user('owner'))
            ->getJson(route('home.dashboard-data', $query))
            ->assertForbidden();
    }

    public function test_sales_return_payment_is_not_double_counted_in_receivable(): void
    {
        $developer = $this->user('developer');
        $this->actingAs($developer);
        $customerUser = $this->user('customer');
        $city = Setup::create(['type' => 'city', 'title' => 'Dashboard City', 'short_title' => 'DASH-CITY']);
        $customer = Customer::create([
            'user_id' => $customerUser->id,
            'customer_name' => 'Dashboard Buyer',
            'person_name' => 'Dashboard Person',
            'phone_number' => '03000000000',
            'date' => today(),
            'category' => 'retail',
            'city_id' => $city->id,
            'address' => 'Dashboard Test Address',
        ]);
        $article = Article::create([
            'article_no' => 'DASH-001',
            'date' => today(),
            'size' => 'M',
            'season' => 'All Season',
            'quantity' => 100,
            'pcs_per_packet' => 10,
            'sales_rate' => 10,
            'rates_array' => [],
        ]);
        $invoice = Invoice::create([
            'invoice_no' => 'DASH-INV-001',
            'customer_id' => $customer->id,
            'date' => today(),
            'netAmount' => 100,
        ]);
        SalesReturn::create([
            'return_no' => 'DASH-RET-001',
            'article_id' => $article->id,
            'invoice_id' => $invoice->id,
            'type' => 'return',
            'date' => today(),
            'quantity' => 2,
            'amount' => 20,
        ]);
        CustomerPayment::create([
            'customer_id' => $customer->id,
            'date' => today(),
            'type' => 'sales_return',
            'method' => 'adjustment',
            'amount' => 20,
            'is_return' => false,
        ]);

        $this->getJson(route('home.dashboard-data', [
            'from' => today()->toDateString(),
            'to' => today()->toDateString(),
            'refresh' => 1,
        ]))
            ->assertOk()
            ->assertJsonPath('summary.financial.receivable', 80)
            ->assertJsonPath('summary.activity.net_sales', 80);
    }

    public function test_client_disabled_module_section_is_hidden(): void
    {
        app(ModuleBranchService::class)->ensureMainBranch();
        $setting = BranchModuleSetting::query()->whereNull('branch_id')->where('module_key', 'inventory')->firstOrFail();
        $setting->update([
            'status' => 'inactive',
            'metadata' => array_merge($setting->metadata ?? [], ['client_disabled' => true]),
        ]);

        $this->actingAs($this->user('developer'))
            ->getJson(route('home.dashboard-data', [
                'from' => today()->startOfMonth()->toDateString(),
                'to' => today()->toDateString(),
                'refresh' => 1,
            ]))
            ->assertOk()
            ->assertJsonPath('summary.modules.inventory', false)
            ->assertDontSee('Material inventory');
    }

    private function user(string $role): User
    {
        return User::create([
            'name' => Str::headline($role).' User',
            'username' => $role.'_'.Str::random(8),
            'password' => Hash::make('password'),
            'role' => $role,
            'status' => 'active',
        ]);
    }
}
