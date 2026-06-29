<?php

namespace App\Http\Requests\Auth;

use App\DTOs\Auth\ForgotPasswordData;
use Illuminate\Foundation\Http\FormRequest;

class ForgotPasswordRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
        ];
    }

    public function toDto(): ForgotPasswordData
    {
        return new ForgotPasswordData(
            email: $this->string('email')->toString(),
        );
    }
}
