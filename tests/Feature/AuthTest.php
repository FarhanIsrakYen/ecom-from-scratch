<?php

namespace Tests\Feature;

use App\Enums\RoleEnum;
use App\Enums\UserStatusEnum;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_register_and_receive_token(): void
    {
        $this->createRole(RoleEnum::Customer);

        $this->postJson('/api/auth/register', [
            'name' => 'Jane Customer',
            'email' => 'jane@example.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
            'device_name' => 'phpunit',
        ])
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'user' => ['id', 'name', 'email', 'status', 'email_verified_at', 'roles', 'created_at'],
                    'token',
                    'token_type',
                ],
            ]);
    }

    public function test_customer_can_login_and_read_profile(): void
    {
        $user = $this->createUserWithRole(RoleEnum::Customer, [
            'email' => 'login@example.com',
            'password' => 'Password123',
        ]);

        $token = $this->postJson('/api/auth/login', [
            'email' => 'login@example.com',
            'password' => 'Password123',
            'device_name' => 'phpunit',
            'role' => RoleEnum::Customer->value,
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->json('data.token');

        $this->withToken($token)
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.user.email', 'login@example.com');
    }

    public function test_admin_can_login_with_admin_role(): void
    {
        $this->createUserWithRole(RoleEnum::Admin, [
            'email' => 'admin-login@example.com',
            'password' => 'Password123',
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'admin-login@example.com',
            'password' => 'Password123',
            'role' => RoleEnum::Admin->value,
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.roles.0', RoleEnum::Admin->value);
    }

    public function test_user_can_logout(): void
    {
        $user = $this->createUserWithRole(RoleEnum::Customer);
        $token = $user->createToken('phpunit')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/auth/logout')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->app['auth']->forgetGuards();

        $this->withToken($token)
            ->getJson('/api/auth/me')
            ->assertUnauthorized();
    }

    public function test_invalid_login_returns_validation_error(): void
    {
        $this->createUserWithRole(RoleEnum::Customer, [
            'email' => 'invalid-login@example.com',
            'password' => 'Password123',
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'invalid-login@example.com',
            'password' => 'WrongPassword123',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('success', false);
    }

    public function test_banned_user_cannot_login(): void
    {
        $this->createUserWithRole(RoleEnum::Customer, [
            'email' => 'banned@example.com',
            'password' => 'Password123',
            'status' => UserStatusEnum::Banned->value,
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'banned@example.com',
            'password' => 'Password123',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('success', false);
    }

    public function test_inactive_user_cannot_login(): void
    {
        $this->createUserWithRole(RoleEnum::Customer, [
            'email' => 'inactive@example.com',
            'password' => 'Password123',
            'status' => UserStatusEnum::Inactive->value,
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'inactive@example.com',
            'password' => 'Password123',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('success', false);
    }

    public function test_role_protected_route_access_is_enforced(): void
    {
        $customer = $this->createUserWithRole(RoleEnum::Customer);
        $admin = $this->createUserWithRole(RoleEnum::Admin);
        $superAdmin = $this->createUserWithRole(RoleEnum::SuperAdmin);

        $this->withToken($customer->createToken('phpunit')->plainTextToken)
            ->getJson('/api/admin/health')
            ->assertForbidden();

        $this->app['auth']->forgetGuards();

        $this->withToken($admin->createToken('phpunit')->plainTextToken)
            ->getJson('/api/admin/health')
            ->assertOk();

        $this->app['auth']->forgetGuards();

        $this->withToken($superAdmin->createToken('phpunit')->plainTextToken)
            ->getJson('/api/admin/health')
            ->assertOk();
    }

    public function test_forgot_password_endpoint_sends_reset_link(): void
    {
        $this->createUserWithRole(RoleEnum::Customer, [
            'email' => 'forgot@example.com',
        ]);

        $this->postJson('/api/auth/forgot-password', [
            'email' => 'forgot@example.com',
        ])
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_reset_password_endpoint_updates_password_and_revokes_tokens(): void
    {
        $user = $this->createUserWithRole(RoleEnum::Customer, [
            'email' => 'reset@example.com',
            'password' => 'Password123',
        ]);
        $user->createToken('phpunit');

        $token = Password::broker()->createToken($user);

        $this->postJson('/api/auth/reset-password', [
            'email' => 'reset@example.com',
            'token' => $token,
            'password' => 'NewPassword123',
            'password_confirmation' => 'NewPassword123',
        ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $user->refresh();

        $this->assertTrue(Hash::check('NewPassword123', $user->password));
        $this->assertSame(0, $user->tokens()->count());
    }

    private function createUserWithRole(RoleEnum $role, array $attributes = []): User
    {
        $roleModel = $this->createRole($role);
        $user = User::factory()->create($attributes);
        $user->roles()->attach($roleModel);

        return $user;
    }

    private function createRole(RoleEnum $role): Role
    {
        return Role::query()->firstOrCreate(
            ['name' => $role->value],
            ['label' => $role->label()],
        );
    }
}
