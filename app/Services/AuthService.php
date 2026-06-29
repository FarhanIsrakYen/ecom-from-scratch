<?php

namespace App\Services;

use App\DTOs\Auth\LoginData;
use App\DTOs\Auth\ForgotPasswordData;
use App\DTOs\Auth\RegisterUserData;
use App\DTOs\Auth\ResetPasswordData;
use App\Enums\RoleEnum;
use App\Enums\UserStatusEnum;
use App\Events\UserRegistered;
use App\Models\Role;
use App\Models\User;
use App\Repositories\UserRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthService
{
    public function __construct(private readonly UserRepository $users)
    {
    }

    /**
     * @return array{user: User, token: string}
     */
    public function register(RegisterUserData $data): array
    {
        return DB::transaction(function () use ($data): array {
            $user = User::query()->create([
                'name' => $data->name,
                'email' => $data->email,
                'password' => $data->password,
                'status' => UserStatusEnum::Active->value,
            ]);

            $customerRole = Role::query()->where('name', RoleEnum::Customer->value)->firstOrFail();
            $user->roles()->syncWithoutDetaching([$customerRole->id]);
            UserRegistered::dispatch($user);

            if (! $user->hasVerifiedEmail()) {
                $user->sendEmailVerificationNotification();
            }

            return [
                'user' => $user->load('roles'),
                'token' => $user->createToken($data->deviceName)->plainTextToken,
            ];
        });
    }

    /**
     * @return array{user: User, token: string}
     */
    public function login(LoginData $data): array
    {
        $user = $this->users->findByEmail($data->email);

        if (! $user || ! Hash::check($data->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $this->ensureCanLogin($user);

        if ($data->role !== null && ! $user->hasRole($data->role)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect for this role.'],
            ]);
        }

        return [
            'user' => $user->load('roles'),
            'token' => $user->createToken($data->deviceName)->plainTextToken,
        ];
    }

    public function forgotPassword(ForgotPasswordData $data): string
    {
        return Password::sendResetLink([
            'email' => $data->email,
        ]);
    }

    public function resetPassword(ResetPasswordData $data): string
    {
        return DB::transaction(fn (): string => Password::reset([
            'email' => $data->email,
            'token' => $data->token,
            'password' => $data->password,
            'password_confirmation' => $data->password,
        ], function (User $user, string $password): void {
            $user->forceFill([
                'password' => $password,
                'remember_token' => Str::random(60),
            ])->save();

            $user->tokens()->delete();
        }));
    }

    public function logout(User $user): void
    {
        $user->currentAccessToken()?->delete();
    }

    private function ensureCanLogin(User $user): void
    {
        if ($user->status === UserStatusEnum::Active) {
            return;
        }

        throw ValidationException::withMessages([
            'email' => ['This account is not allowed to log in.'],
        ]);
    }
}
