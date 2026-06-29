<?php

namespace App\Http\Requests\Auth;

use App\DTOs\Auth\ResetPasswordData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class ResetPasswordRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'token' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()],
        ];
    }

    public function toDto(): ResetPasswordData
    {
        return new ResetPasswordData(
            email: $this->string('email')->toString(),
            token: $this->string('token')->toString(),
            password: $this->string('password')->toString(),
        );
    }
}
