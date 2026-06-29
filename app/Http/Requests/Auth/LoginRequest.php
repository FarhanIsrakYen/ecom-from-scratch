<?php

namespace App\Http\Requests\Auth;

use App\DTOs\Auth\LoginData;
use App\Enums\RoleEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LoginRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['sometimes', 'string', 'max:100'],
            'role' => ['sometimes', 'string', Rule::enum(RoleEnum::class)],
        ];
    }

    public function toDto(): LoginData
    {
        return new LoginData(
            email: $this->string('email')->toString(),
            password: $this->string('password')->toString(),
            deviceName: $this->string('device_name', 'api')->toString(),
            role: $this->input('role'),
        );
    }
}
