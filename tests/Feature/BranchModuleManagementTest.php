<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckActiveSession;
use App\Http\Middleware\SubscriptionExpiry;
use App\Http\Middleware\VerifyCsrfToken;
use App\Models\BranchModuleSetting;
use App\Models\User;
use App\Services\Branches\ModuleBranchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class BranchModuleManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([CheckActiveSession::class, SubscriptionExpiry::class, VerifyCsrfToken::class]);
    }

    public function test_module_management_shows_searchable_overview_and_detail_panel(): void
    {
        $this->actingAs($this->developer())
            ->get(route('developer.branches.modules.index', ['module' => 'articles']))
            ->assertOk()
            ->assertSee('All modules')
            ->assertSee('Select without reloading the page.')
            ->assertSee('data-module-search', false)
            ->assertSee('data-module-filter="branching"', false)
            ->assertSee('data-branch-module-link', false)
            ->assertSee('data-no-loader="true"', false)
            ->assertSee('data-branch-module-detail', false)
            ->assertSee('name="module_key" value="articles"', false)
            ->assertSee('Active 1/1');
    }

    public function test_module_overview_exposes_filtering_and_client_disabled_states(): void
    {
        $mainBranch = app(ModuleBranchService::class)->ensureMainBranch();

        $branchSetting = BranchModuleSetting::query()
            ->where('branch_id', $mainBranch->id)
            ->where('module_key', 'articles')
            ->firstOrFail();
        $branchSetting->update([
            'metadata' => array_merge($branchSetting->metadata ?? [], ['record_filtering_enabled' => true]),
        ]);

        $globalSetting = BranchModuleSetting::query()
            ->whereNull('branch_id')
            ->where('module_key', 'articles')
            ->firstOrFail();
        $globalSetting->update([
            'branch_enabled' => false,
            'status' => 'inactive',
            'metadata' => array_merge($globalSetting->metadata ?? [], ['client_disabled' => true]),
        ]);

        $this->actingAs($this->developer())
            ->get(route('developer.branches.modules.index', ['module' => 'articles']))
            ->assertOk()
            ->assertSee('Filter 1')
            ->assertSee('Client off')
            ->assertSee('Disabled for client');
    }

    protected function developer(): User
    {
        return User::create([
            'name' => 'Developer User',
            'username' => 'developer_' . Str::random(8),
            'password' => Hash::make('password'),
            'role' => 'developer',
            'status' => 'active',
        ]);
    }
}
