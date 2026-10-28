<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\User;
use App\Services\Navigation\MenuCategoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MenuCategoryConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_developer_can_save_global_menu_category_assignments(): void
    {
        $this->withoutMiddleware();
        $developer = $this->user('menu_developer', 'developer');

        $response = $this->actingAs($developer)->postJson(route('updateMenuCategories'), [
            'assignments' => [
                'customers' => 'purchase',
                'orders' => 'sales',
            ],
        ]);

        $response->assertOk()
            ->assertJson([
                'status' => 'success',
                'saved' => true,
                'assignments' => [
                    'customers' => 'purchase',
                    'orders' => 'sales',
                ],
            ]);

        $setting = AppSetting::query()->where('key', MenuCategoryService::SETTING_KEY)->firstOrFail();
        $this->assertSame('json', $setting->type);
        $this->assertSame('purchase', json_decode($setting->value, true)['customers']);
        $this->assertSame($developer->id, $setting->updated_by);
    }

    public function test_non_developer_cannot_save_menu_categories(): void
    {
        $this->withoutMiddleware();
        $owner = $this->user('menu_owner', 'owner');

        $this->actingAs($owner)->postJson(route('updateMenuCategories'), [
            'assignments' => ['customers' => 'purchase'],
        ])->assertForbidden();

        $this->assertDatabaseMissing('app_settings', [
            'key' => MenuCategoryService::SETTING_KEY,
        ]);
    }

    public function test_unknown_menu_modules_are_rejected(): void
    {
        $this->withoutMiddleware();
        $developer = $this->user('menu_validation_developer', 'developer');

        $this->actingAs($developer)->postJson(route('updateMenuCategories'), [
            'assignments' => ['unknown-module' => 'sales'],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('assignments');
    }

    private function user(string $username, string $role): User
    {
        return User::create([
            'name' => str_replace('_', ' ', ucfirst($username)),
            'username' => $username,
            'password' => Hash::make('password'),
            'role' => $role,
            'status' => 'active',
        ]);
    }
}
