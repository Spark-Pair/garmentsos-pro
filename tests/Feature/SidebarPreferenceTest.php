<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SidebarPreferenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_sidebar_state_is_saved_only_for_the_authenticated_user(): void
    {
        $this->withoutMiddleware();

        $firstUser = $this->user('first_sidebar_user');
        $secondUser = $this->user('second_sidebar_user');

        $this->actingAs($firstUser)
            ->postJson(route('updateSidebarState'), ['expanded' => true])
            ->assertOk()
            ->assertJson([
                'status' => 'success',
                'saved' => true,
                'sidebar_expanded' => true,
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $firstUser->id,
            'sidebar_expanded' => true,
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $secondUser->id,
            'sidebar_expanded' => false,
        ]);
    }

    private function user(string $username): User
    {
        return User::create([
            'name' => str_replace('_', ' ', ucfirst($username)),
            'username' => $username,
            'password' => Hash::make('password'),
            'role' => 'owner',
            'status' => 'active',
        ]);
    }
}
