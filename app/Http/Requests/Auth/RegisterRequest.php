<?php

namespace App\Http\Requests\Auth;

use App\DTOs\Auth\RegisterUserData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()],
            'device_name' => ['sometimes', 'string', 'max:100'],
        ];
    }

    public function toDto(): RegisterUserData
    {
        return new RegisterUserData(
            name: $this->string('name')->toString(),
            email: $this->string('email')->toString(),
            password: $this->string('password')->toString(),
            deviceName: $this->string('device_name', 'api')->toString(),
        );
    }
}
